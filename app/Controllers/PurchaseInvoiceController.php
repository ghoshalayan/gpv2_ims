<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BrandRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PurchaseInvoiceExpenseRepository;
use App\Repositories\PurchaseInvoiceLineRepository;
use App\Repositories\PurchaseInvoiceRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\ProductUnitRepository;
use App\Services\PricingService;
use App\Services\PurchaseInvoiceService;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;
use App\Support\UnitConversion;
use App\Support\Validator;

class PurchaseInvoiceController
{
    public function __construct(
        private PurchaseInvoiceRepository         $repo         = new PurchaseInvoiceRepository(),
        private PurchaseInvoiceLineRepository     $lineRepo     = new PurchaseInvoiceLineRepository(),
        private PurchaseInvoiceExpenseRepository  $expenseRepo  = new PurchaseInvoiceExpenseRepository(),
        private SupplierRepository                $suppliers    = new SupplierRepository(),
        private BrandRepository                   $brands       = new BrandRepository(),
        private ProductRepository                 $products     = new ProductRepository(),
        private BusinessProfileRepository         $business     = new BusinessProfileRepository(),
        private PurchaseInvoiceService            $service      = new PurchaseInvoiceService(),
        private PricingService                    $pricing      = new PricingService(),
        private ProductUnitRepository             $productUnits = new ProductUnitRepository(),
        private UnitConversion                    $conversion   = new UnitConversion(),
    ) {}

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();
        $filters = [
            'status'         => (string) ($_GET['status'] ?? ''),
            'payment_status' => (string) ($_GET['payment_status'] ?? ''),
            'date_from'      => (string) ($_GET['date_from'] ?? ''),
            'date_to'        => (string) ($_GET['date_to'] ?? ''),
        ];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('purchase-invoices/index', [
            'title'    => 'Purchase Invoices',
            'invoices' => $result['rows'],
            'pg'       => $p->buildContext($result['total'], url('/purchase-invoices'), $filters + ['q' => $p->search]),
            'filters'  => $filters,
        ]);
    }

    public function create(): string
    {
        $suppliers = $this->suppliers->listActive();
        $brands    = $this->brands->listActive();
        if ($suppliers === [] || $brands === []) {
            Flash::warning('Create at least one active Supplier and one active Brand before adding a Purchase.');
            return redirect($suppliers === [] ? '/suppliers/create' : '/brands/create');
        }
        return view('purchase-invoices/create', [
            'title'     => 'New Purchase Invoice',
            'suppliers' => $suppliers,
            'brands'    => $brands,
        ]);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateHeader($_POST, '/purchase-invoices/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }
        if ($this->repo->existsBySupplierAndNumber($data['supplier_id'], $data['invoice_number'])) {
            flash_old($_POST);
            Flash::error("Invoice number '{$data['invoice_number']}' already exists for this supplier.");
            return redirect('/purchase-invoices/create');
        }

        $id = $this->repo->insertDraft($data, (int) Auth::id());
        Flash::success('Draft invoice created. Add lines and finalize when ready.');
        return redirect("/purchase-invoices/{$id}/edit");
    }

    public function edit(array $params): string
    {
        $invoice = $this->requireInvoice((int) $params['id']);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            return redirect("/purchase-invoices/{$invoice['id']}");
        }

        $lines    = $this->lineRepo->byInvoice((int) $invoice['id']);
        $expenses = $this->expenseRepo->byInvoice((int) $invoice['id']);
        $brandProducts = $this->productsForBrand((int) $invoice['brand_id']);
        $unitMap = $this->productUnits->unitMapForProducts(
            \array_map(static fn ($p) => (int) $p['id'], $brandProducts),
            'purchase'
        );

        return view('purchase-invoices/edit', [
            'title'         => 'Edit Purchase Invoice',
            'invoice'       => $invoice,
            'lines'         => $lines,
            'expenses'      => $expenses,
            'brandProducts' => $brandProducts,
            'unitMap'       => $unitMap,
            'preview'       => $this->computePreview($invoice, $lines, $expenses),
        ]);
    }

    public function updateHeader(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot edit a finalized or cancelled invoice.');
            return redirect("/purchase-invoices/{$id}");
        }

        // Once lines exist, immutable header fields are locked (supplier, brand, bill_format, gst_claimable).
        $hasLines = $this->lineRepo->byInvoice($id) !== [];
        $input = $_POST;
        if ($hasLines) {
            $input['supplier_id']  = $invoice['supplier_id'];
            $input['brand_id']     = $invoice['brand_id'];
            $input['bill_format']  = $invoice['bill_format'];
            $input['gst_claimable']= $invoice['gst_claimable'];
        }

        [$data, $errorRedirect] = $this->validateHeader($input, "/purchase-invoices/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }
        if ($this->repo->existsBySupplierAndNumber($data['supplier_id'], $data['invoice_number'], $id)) {
            flash_old($input);
            Flash::error("Invoice number '{$data['invoice_number']}' already exists for this supplier.");
            return redirect("/purchase-invoices/{$id}/edit");
        }

        $this->repo->updateDraftHeader($id, $data, (int) Auth::id());
        // Mode B has no invoice-level tax rate / final discount — zero them on flip.
        if ($data['bill_format'] === 'per_line_tax') {
            $this->repo->zeroFooterFields($id);
        }
        Flash::success('Header updated.');
        return redirect("/purchase-invoices/{$id}/edit");
    }

    /** Footer-editable fields (invoice-level tax rate + final discount). Draft + Mode A only. */
    public function updateFooter(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) { return $invoice; }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot edit a finalized or cancelled invoice.');
            return redirect("/purchase-invoices/{$id}");
        }
        $billFormat = (string) $invoice['bill_format'];
        $discount   = $billFormat === 'discount_then_tax' ? \max(0.0, (float) ($_POST['final_discount_amount'] ?? 0)) : 0.0;
        $rate       = $billFormat === 'discount_then_tax' ? (float) ($_POST['invoice_level_tax_rate'] ?? 0) : 0.0;
        if ($rate < 0 || $rate > 100) {
            Flash::error('Invoice-level tax rate must be 0-100.');
            return redirect("/purchase-invoices/{$id}/edit");
        }
        $this->repo->updateFooter($id, $rate, $discount, (int) Auth::id());
        Flash::success('Totals updated.');
        return redirect("/purchase-invoices/{$id}/edit");
    }

    public function addLine(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot add lines to a finalized invoice.');
            return redirect("/purchase-invoices/{$id}");
        }

        $productId     = (int)   ($_POST['product_id']     ?? 0);
        $enteredQty    = (float) ($_POST['quantity']       ?? 0);   // in the chosen entry unit
        $entryUnitId   = (int)   ($_POST['entry_unit_id']  ?? 0);
        $unitPrice     = (float) ($_POST['unit_price']     ?? 0);   // per BASE/stock unit
        $addsubPercent = (float) ($_POST['addsub_percent'] ?? 0);
        $taxRate       = (float) ($_POST['tax_rate']       ?? 0);

        $errors = [];
        if ($productId <= 0)  { $errors[] = 'Product is required.'; }
        if ($enteredQty <= 0) { $errors[] = 'Quantity must be > 0.'; }
        if ($unitPrice < 0)   { $errors[] = 'Unit price cannot be negative.'; }
        if ($addsubPercent < -100 || $addsubPercent > 100) { $errors[] = 'AddSub % must be between -100 and 100.'; }
        if ($taxRate < 0 || $taxRate > 100) { $errors[] = 'Tax rate must be between 0 and 100.'; }

        // Cross-brand check (D14)
        $product = $this->products->find($productId);
        if ($product === null) {
            $errors[] = 'Product not found.';
        } elseif ((int) $product['brand_id'] !== (int) $invoice['brand_id']) {
            $errors[] = "Product '{$product['name']}' does not belong to brand '{$invoice['brand_name']}'. This invoice is single-brand.";
        }

        if ($errors !== []) {
            Flash::error($errors[0]);
            return redirect("/purchase-invoices/{$id}/edit");
        }

        // Convert the entered quantity into the product's base stock unit. Price
        // is per base unit, so subtotal/COGS math is exact and unchanged.
        try {
            $conv = $this->conversion->resolve($product, $entryUnitId, $enteredQty);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            return redirect("/purchase-invoices/{$id}/edit");
        }
        $baseQty = $conv['base_qty'];
        if ($baseQty <= 0) {
            Flash::error('Converted quantity must be greater than zero.');
            return redirect("/purchase-invoices/{$id}/edit");
        }

        $computed = $this->pricing->computeLine(
            $baseQty, $unitPrice, $addsubPercent, $taxRate,
            $invoice['bill_format'], $invoice['tax_treatment'], (int) $invoice['is_intra_state'] === 1
        );

        $this->lineRepo->insert([
            'purchase_invoice_id' => $id,
            'line_no'             => $this->lineRepo->nextLineNo($id),
            'product_id'          => $productId,
            'quantity'            => $baseQty,
            'entry_unit_id'       => $conv['entry_unit_id'],
            'entry_quantity'      => $conv['entry_quantity'],
            'unit_price'          => $unitPrice,
            'addsub_percent'      => $addsubPercent,
            'subtotal'            => $computed['subtotal'],
            'tax_rate'            => $taxRate,
            'tax_amount'          => $computed['tax_amount'],
            'cgst_amount'         => $computed['cgst'],
            'sgst_amount'         => $computed['sgst'],
            'igst_amount'         => $computed['igst'],
            'line_total'          => $computed['line_total'],
        ]);

        Flash::success("Line added: {$product['name']} × {$baseQty}");
        return redirect("/purchase-invoices/{$id}/edit");
    }

    public function deleteLine(array $params): string
    {
        $id      = (int) $params['id'];
        $lineId  = (int) $params['lineId'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot modify a finalized invoice.');
            return redirect("/purchase-invoices/{$id}");
        }
        $this->lineRepo->deleteLine($lineId, $id);
        Flash::success('Line removed.');
        return redirect("/purchase-invoices/{$id}/edit");
    }

    /**
     * Edit an existing draft line in place. Product is immutable — to change it,
     * delete and re-add. Quantity/price/addsub/tax are editable; totals get
     * recomputed via PricingService. No stock effect (draft → finalize is what
     * commits stock).
     */
    public function updateLine(array $params): string
    {
        $id      = (int) $params['id'];
        $lineId  = (int) $params['lineId'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot modify a finalized invoice.');
            return redirect("/purchase-invoices/{$id}");
        }
        $line = $this->lineRepo->findLine($lineId, $id);
        if ($line === null) {
            Flash::error('Line not found.');
            return redirect("/purchase-invoices/{$id}/edit");
        }

        $enteredQty    = (float) ($_POST['quantity']       ?? 0);
        $entryUnitId   = (int)   ($_POST['entry_unit_id']  ?? 0);
        $unitPrice     = (float) ($_POST['unit_price']     ?? 0);
        $addsubPercent = (float) ($_POST['addsub_percent'] ?? 0);
        $taxRate       = (float) ($_POST['tax_rate']       ?? 0);

        $errors = [];
        if ($enteredQty <= 0) { $errors[] = 'Quantity must be > 0.'; }
        if ($unitPrice < 0)   { $errors[] = 'Unit price cannot be negative.'; }
        if ($addsubPercent < -100 || $addsubPercent > 100) { $errors[] = 'AddSub % must be between -100 and 100.'; }
        if ($taxRate < 0 || $taxRate > 100) { $errors[] = 'Tax rate must be between 0 and 100.'; }
        if ($errors !== []) {
            Flash::error($errors[0]);
            return redirect("/purchase-invoices/{$id}/edit");
        }

        $product = $this->products->find((int) $line['product_id']);
        if ($product === null) {
            Flash::error('Product for this line no longer exists.');
            return redirect("/purchase-invoices/{$id}/edit");
        }
        try {
            $conv = $this->conversion->resolve($product, $entryUnitId, $enteredQty);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            return redirect("/purchase-invoices/{$id}/edit");
        }
        $baseQty = $conv['base_qty'];
        if ($baseQty <= 0) {
            Flash::error('Converted quantity must be greater than zero.');
            return redirect("/purchase-invoices/{$id}/edit");
        }

        $computed = $this->pricing->computeLine(
            $baseQty, $unitPrice, $addsubPercent, $taxRate,
            $invoice['bill_format'], $invoice['tax_treatment'], (int) $invoice['is_intra_state'] === 1
        );

        $this->lineRepo->updateLine($lineId, $id, [
            'quantity'       => $baseQty,
            'entry_unit_id'  => $conv['entry_unit_id'],
            'entry_quantity' => $conv['entry_quantity'],
            'unit_price'     => $unitPrice,
            'addsub_percent' => $addsubPercent,
            'subtotal'       => $computed['subtotal'],
            'tax_rate'       => $taxRate,
            'tax_amount'     => $computed['tax_amount'],
            'cgst_amount'    => $computed['cgst'],
            'sgst_amount'    => $computed['sgst'],
            'igst_amount'    => $computed['igst'],
            'line_total'     => $computed['line_total'],
        ]);

        Flash::success('Line updated.');
        return redirect("/purchase-invoices/{$id}/edit");
    }

    public function addExpense(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot modify a finalized invoice.');
            return redirect("/purchase-invoices/{$id}");
        }

        $expenseType = \trim((string) ($_POST['expense_type'] ?? ''));
        $amount      = (float) ($_POST['amount']   ?? 0);
        $taxRate     = (float) ($_POST['tax_rate'] ?? 0);
        $method      = (string) ($_POST['allocation_method'] ?? 'by_value');
        $remarks     = \trim((string) ($_POST['remarks'] ?? '')) ?: null;

        if ($expenseType === '') { Flash::error('Expense type is required.'); return redirect("/purchase-invoices/{$id}/edit"); }
        if ($amount < 0) { Flash::error('Expense amount cannot be negative.'); return redirect("/purchase-invoices/{$id}/edit"); }
        if ($taxRate < 0 || $taxRate > 100) { Flash::error('Tax rate must be between 0 and 100.'); return redirect("/purchase-invoices/{$id}/edit"); }
        if (!\in_array($method, ['by_value', 'by_quantity', 'equal_split'], true)) {
            Flash::error('Invalid allocation method.');
            return redirect("/purchase-invoices/{$id}/edit");
        }

        $computed = $this->pricing->computeExpense($amount, $taxRate);
        $this->expenseRepo->insert([
            'purchase_invoice_id' => $id,
            'expense_type'        => $expenseType,
            'amount'              => $amount,
            'tax_rate'            => $taxRate,
            'tax_amount'          => $computed['tax_amount'],
            'total_with_tax'      => $computed['total_with_tax'],
            'allocation_method'   => $method,
            'remarks'             => $remarks,
        ]);

        Flash::success("Expense '{$expenseType}' added.");
        return redirect("/purchase-invoices/{$id}/edit");
    }

    public function deleteExpense(array $params): string
    {
        $id        = (int) $params['id'];
        $expenseId = (int) $params['expenseId'];
        $invoice   = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot modify a finalized invoice.');
            return redirect("/purchase-invoices/{$id}");
        }
        $this->expenseRepo->deleteExpense($expenseId, $id);
        Flash::success('Expense removed.');
        return redirect("/purchase-invoices/{$id}/edit");
    }

    public function finalize(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Only drafts can be finalized.');
            return redirect("/purchase-invoices/{$id}");
        }

        try {
            $this->service->finalize($id, (int) Auth::id());
            Flash::success("Invoice '{$invoice['invoice_number']}' finalized. Stock and costs updated.");
        } catch (\Throwable $e) {
            Flash::error('Finalize failed: ' . $e->getMessage());
            return redirect("/purchase-invoices/{$id}/edit");
        }
        return redirect("/purchase-invoices/{$id}");
    }

    public function show(array $params): string
    {
        $invoice = $this->requireInvoice((int) $params['id']);
        if (\is_string($invoice)) {
            return $invoice;
        }
        return view('purchase-invoices/show', [
            'title'    => "Invoice {$invoice['invoice_number']}",
            'invoice'  => $invoice,
            'lines'    => $this->lineRepo->byInvoice((int) $invoice['id']),
            'expenses' => $this->expenseRepo->byInvoice((int) $invoice['id']),
        ]);
    }

    public function printA4(array $params): string
    {
        return $this->renderPrint($params, 'purchase-invoices/print/a4');
    }

    public function printThermal(array $params): string
    {
        return $this->renderPrint($params, 'purchase-invoices/print/thermal');
    }

    private function renderPrint(array $params, string $viewName): string
    {
        $invoice = $this->requireInvoice((int) $params['id']);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'finalized') {
            Flash::warning('Only finalized invoices can be printed.');
            return redirect("/purchase-invoices/{$invoice['id']}");
        }
        return view($viewName, [
            'title'    => 'Purchase Invoice ' . $invoice['invoice_number'],
            'invoice'  => $invoice,
            'lines'    => $this->lineRepo->byInvoice((int) $invoice['id']),
            'expenses' => $this->expenseRepo->byInvoice((int) $invoice['id']),
            'business' => $this->business->get(),
        ], 'print');
    }

    public function cancel(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Only drafts can be cancelled in v1.');
            return redirect("/purchase-invoices/{$id}");
        }
        $this->repo->cancelDraft($id, (int) Auth::id());
        \App\Support\ActivityLog::log('cancel', 'purchase_invoice', $id, [
            'label' => $invoice['invoice_number'] ?? null,
        ]);
        Flash::success('Draft cancelled.');
        return redirect('/purchase-invoices');
    }

    /** Hard-delete a draft. Lines + expenses cascade via FK. */
    public function destroy(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Only drafts can be deleted. Cancel a finalized invoice instead.');
            return redirect("/purchase-invoices/{$id}");
        }
        $this->repo->deleteDraft($id);
        \App\Support\ActivityLog::log('delete', 'purchase_invoice', $id, [
            'label' => $invoice['invoice_number'] ?? '(draft)',
            'notes' => 'Hard-delete of draft',
        ]);
        Flash::success('Draft deleted.');
        return redirect('/purchase-invoices');
    }

    /** @return array{0:array, 1:?string} */
    private function validateHeader(array $input, string $redirectTo): array
    {
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        $brandId    = (int) ($input['brand_id']    ?? 0);
        $billFormat = (string) ($input['bill_format'] ?? 'per_line_tax');

        $data = [
            'invoice_number'         => \trim((string) ($input['invoice_number'] ?? '')),
            'supplier_id'            => $supplierId,
            'brand_id'               => $brandId,
            'invoice_date'           => \trim((string) ($input['invoice_date'] ?? \date('Y-m-d'))),
            'bill_format'            => $billFormat,
            'tax_treatment'          => (string) ($input['tax_treatment'] ?? 'exclusive'),
            'gst_claimable'          => !empty($input['gst_claimable']) ? 1 : 0,
            'is_intra_state'         => $this->resolveIntraState($supplierId),
            'remarks'                => \trim((string) ($input['remarks'] ?? '')) ?: null,
        ];

        $v = Validator::make([
                'invoice_number' => $data['invoice_number'],
                'supplier_id'    => (string) $data['supplier_id'],
                'brand_id'       => (string) $data['brand_id'],
                'invoice_date'   => $data['invoice_date'],
                'bill_format'    => $data['bill_format'],
                'tax_treatment'  => $data['tax_treatment'],
            ])
            ->required('invoice_number')->max('invoice_number', 100)
            ->required('supplier_id', 'Supplier is required.')
            ->required('brand_id', 'Brand is required.')
            ->required('invoice_date')
            ->in('bill_format', ['per_line_tax', 'discount_then_tax'], 'Invalid bill format.')
            ->in('tax_treatment', ['exclusive', 'inclusive', 'no_tax'], 'Invalid tax treatment.');

        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['invoice_date'])) {
            flash_old($input);
            Flash::error('Invoice date must be YYYY-MM-DD.');
            return [$data, redirect($redirectTo)];
        }
        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, redirect($redirectTo)];
        }
        return [$data, null];
    }

    /** Determine intra-state based on business profile vs supplier state. Default intra-state if either missing. */
    private function resolveIntraState(int $supplierId): int
    {
        $bp = $this->business->get();
        $supplier = $this->suppliers->find($supplierId);
        $businessState = (string) ($bp['state'] ?? '');
        $supplierState = (string) ($supplier['state'] ?? '');
        if ($businessState === '' || $supplierState === '') {
            return 1; // default intra-state when either is unknown
        }
        return \strcasecmp($businessState, $supplierState) === 0 ? 1 : 0;
    }

    /** Products belonging to the given brand that are active. */
    private function productsForBrand(int $brandId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT id, name, unit, unit_id, tax_rate, default_selling_price
             FROM products
             WHERE brand_id = ? AND status = 'active'
             ORDER BY name"
        );
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Quick preview of draft totals (not persisted until finalize).
     * Mode A (discount_then_tax): final discount reduces the lines' taxable base BEFORE
     *   invoice-level tax. Expenses are independent (own tax rate per expense).
     * Mode B (per_line_tax): no final discount.
     */
    private function computePreview(array $invoice, array $lines, array $expenses): array
    {
        $treatment  = (string) ($invoice['tax_treatment'] ?? 'exclusive');
        $billFormat = (string) ($invoice['bill_format'] ?? 'per_line_tax');
        $subtotal   = (float) \array_sum(\array_column($lines, 'subtotal'));
        $lineTax    = (float) \array_sum(\array_column($lines, 'tax_amount'));

        $expensesTotal = (float) \array_sum(\array_column($expenses, 'amount'));
        $expenseTax    = (float) \array_sum(\array_column($expenses, 'tax_amount'));

        if ($billFormat === 'per_line_tax') {
            $tax   = $treatment === 'no_tax' ? 0.0 : $this->pricing->money($lineTax + $expenseTax);
            $grand = $this->pricing->money($subtotal + $lineTax + $expensesTotal + $expenseTax);
            return [
                'mode'             => 'per_line_tax',
                'subtotal'         => $this->pricing->money($subtotal),
                'line_tax'         => $this->pricing->money($lineTax),
                'final_discount'   => 0.0,
                'taxable_subtotal' => $this->pricing->money($subtotal),
                'lines_tax'        => $this->pricing->money($lineTax),
                'expenses'         => $this->pricing->money($expensesTotal),
                'expense_tax'      => $this->pricing->money($expenseTax),
                'tax'              => $tax,
                'grand_total'      => $grand,
            ];
        }

        // Mode A
        $finalDiscount = \max(0.0, (float) ($invoice['final_discount_amount'] ?? 0));
        if ($finalDiscount > $subtotal) { $finalDiscount = $subtotal; }
        $taxableLines  = $this->pricing->money($subtotal - $finalDiscount);
        $rate          = $treatment === 'no_tax' ? 0.0 : (float) $invoice['invoice_level_tax_rate'];

        if ($treatment === 'inclusive' && $rate > 0) {
            $net = $taxableLines / (1 + $rate / 100);
            $linesTax = $this->pricing->money($taxableLines - $net);
        } else {
            $linesTax = $this->pricing->money($taxableLines * $rate / 100);
        }

        $grand = $this->pricing->money($taxableLines + $linesTax + $expensesTotal + $expenseTax);
        return [
            'mode'             => 'discount_then_tax',
            'subtotal'         => $this->pricing->money($subtotal),
            'line_tax'         => 0.0,
            'final_discount'   => $this->pricing->money($finalDiscount),
            'taxable_subtotal' => $taxableLines,
            'lines_tax'        => $linesTax,
            'expenses'         => $this->pricing->money($expensesTotal),
            'expense_tax'      => $this->pricing->money($expenseTax),
            'tax'              => $this->pricing->money($linesTax + $expenseTax),
            'grand_total'      => $grand,
        ];
    }

    private function requireInvoice(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Purchase invoice not found.');
            return redirect('/purchase-invoices');
        }
        return $row;
    }
}
