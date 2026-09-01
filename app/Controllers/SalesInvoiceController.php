<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BusinessProfileRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductCompositionRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SalesInvoiceExtraRepository;
use App\Repositories\SalesInvoiceLineComponentRepository;
use App\Repositories\SalesInvoiceLineRepository;
use App\Repositories\SalesInvoiceRepository;
use App\Repositories\SalesmanRepository;
use App\Repositories\ProductUnitRepository;
use App\Services\PricingService;
use App\Services\SalesInvoiceService;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;
use App\Support\UnitConversion;
use App\Support\Validator;

class SalesInvoiceController
{
    public function __construct(
        private SalesInvoiceRepository               $repo                = new SalesInvoiceRepository(),
        private SalesInvoiceLineRepository           $lineRepo            = new SalesInvoiceLineRepository(),
        private SalesInvoiceLineComponentRepository  $lineComponentRepo   = new SalesInvoiceLineComponentRepository(),
        private SalesInvoiceExtraRepository          $extraRepo           = new SalesInvoiceExtraRepository(),
        private CustomerRepository                   $customers           = new CustomerRepository(),
        private SalesmanRepository                   $salesmen            = new SalesmanRepository(),
        private ProductRepository                    $products            = new ProductRepository(),
        private ProductCompositionRepository         $productCompositions = new ProductCompositionRepository(),
        private BusinessProfileRepository            $business            = new BusinessProfileRepository(),
        private SalesInvoiceService                  $service             = new SalesInvoiceService(),
        private PricingService                       $pricing             = new PricingService(),
        private ProductUnitRepository                $productUnits        = new ProductUnitRepository(),
        private UnitConversion                       $conversion          = new UnitConversion(),
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

        return view('sales-invoices/index', [
            'title'    => 'Sales Invoices',
            'invoices' => $result['rows'],
            'pg'       => $p->buildContext($result['total'], url('/sales-invoices'), $filters + ['q' => $p->search]),
            'filters'  => $filters,
        ]);
    }

    public function create(): string
    {
        $customers = $this->customers->listActive();
        if ($customers === []) {
            Flash::warning('Create at least one active Customer before adding a Sale.');
            return redirect('/customers/create');
        }
        return view('sales-invoices/create', [
            'title'     => 'New Sale',
            'customers' => $customers,
            'salesmen'  => $this->salesmen->listActive(),
            'customerDefaults' => $this->customerDefaults($customers),
        ]);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateHeader($_POST, '/sales-invoices/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }
        $id = $this->repo->insertDraft($data, (int) Auth::id());
        Flash::success('Draft sale created. Add lines and finalize when ready.');
        return redirect("/sales-invoices/{$id}/edit");
    }

    public function edit(array $params): string
    {
        $invoice = $this->requireInvoice((int) $params['id']);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            return redirect("/sales-invoices/{$invoice['id']}");
        }

        $lines = $this->lineRepo->byInvoice((int) $invoice['id']);
        // Load components for composite lines to show inline in the edit table.
        $lineComponents = [];
        foreach ($lines as $l) {
            if ((int) $l['is_composite_line'] === 1) {
                $lineComponents[(int) $l['id']] = $this->lineComponentRepo->byLine((int) $l['id']);
            }
        }
        $extras = $this->extraRepo->byInvoice((int) $invoice['id']);
        $products = $this->productsForSale();
        $unitMap  = $this->productUnits->unitMapForProducts(
            \array_map(static fn ($p) => (int) $p['id'], $products),
            'sale'
        );
        return view('sales-invoices/edit', [
            'title'    => 'Edit Sale',
            'invoice'  => $invoice,
            'lines'    => $lines,
            'lineComponents' => $lineComponents,
            'extras'   => $extras,
            'products' => $products,
            'unitMap'  => $unitMap,
            'salesmen' => $this->salesmen->listActive(),
            'preview'  => $this->computePreview($invoice, $lines, $extras),
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
            return redirect("/sales-invoices/{$id}");
        }

        $hasLines = $this->lineRepo->byInvoice($id) !== [];
        $input = $_POST;
        if ($hasLines) {
            // Lock customer + bill_format once lines exist (tax behavior shouldn't shift mid-invoice).
            $input['customer_id'] = $invoice['customer_id'];
            $input['bill_format'] = $invoice['bill_format'];
        }

        [$data, $errorRedirect] = $this->validateHeader($input, "/sales-invoices/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }
        $this->repo->updateDraftHeader($id, $data, (int) Auth::id());
        // Mode B has no invoice-level tax rate or final discount — zero them on flip.
        if ($data['bill_format'] === 'per_line_tax') {
            $this->repo->zeroFooterFields($id);
        }
        Flash::success('Header updated.');
        return redirect("/sales-invoices/{$id}/edit");
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
            return redirect("/sales-invoices/{$id}");
        }

        $productId     = (int)   ($_POST['product_id']     ?? 0);
        $displayName   = \trim((string) ($_POST['display_name']   ?? ''));
        $salesmanId    = ((int) ($_POST['salesman_id']     ?? 0)) ?: null;
        $enteredQty    = (float) ($_POST['quantity']       ?? 0);   // in the chosen entry unit
        $entryUnitId   = (int)   ($_POST['entry_unit_id']  ?? 0);
        $unitPrice     = (float) ($_POST['unit_price']     ?? 0);   // per BASE/stock unit
        $addsubPercent = (float) ($_POST['addsub_percent'] ?? 0);
        $taxRate       = (float) ($_POST['tax_rate']       ?? 0);
        // CR-6: blank means "use the salesman's own rate" — distinct from an explicit 0%.
        $commOverride  = $this->commissionOverrideInput();

        $errors = [];
        if ($productId <= 0)  { $errors[] = 'Product is required.'; }
        if ($enteredQty <= 0) { $errors[] = 'Quantity must be > 0.'; }
        if ($unitPrice <  0)  { $errors[] = 'Unit price cannot be negative.'; }
        if ($addsubPercent < -100 || $addsubPercent > 100) { $errors[] = 'AddSub % must be between -100 and 100.'; }
        if ($taxRate < 0 || $taxRate > 100) { $errors[] = 'Tax rate must be between 0 and 100.'; }
        if ($commOverride !== null && ($commOverride < 0 || $commOverride > 100)) {
            $errors[] = 'Commission % must be between 0 and 100.';
        }
        if ($commOverride !== null && $salesmanId === null) {
            $errors[] = 'Assign a salesman before setting a commission rate for the line.';
        }

        $product = $this->products->find($productId);
        if ($product === null) {
            $errors[] = 'Product not found.';
        } elseif ($product['status'] !== 'active') {
            $errors[] = "Product '{$product['name']}' is inactive.";
        }

        // Convert entered quantity → base stock unit BEFORE any stock/composite
        // math (which scales by quantity). Price is per base unit, so all money
        // and COGS stay exact. entry_* columns are display-only.
        $entryUnitIdStore = null;
        $entryQtyStore    = null;
        $quantity         = $enteredQty;
        if ($product !== null && $errors === []) {
            try {
                $conv = $this->conversion->resolve($product, $entryUnitId, $enteredQty);
                $quantity         = $conv['base_qty'];
                $entryUnitIdStore = $conv['entry_unit_id'];
                $entryQtyStore    = $conv['entry_quantity'];
                if ($quantity <= 0) { $errors[] = 'Converted quantity must be greater than zero.'; }
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Determine whether this is a Reversible composite (expand recipe) or simple/Irreversible (stock-out self).
        $isReversibleComposite = $product !== null
            && (int) $product['is_composite'] === 1
            && (int) $product['is_irreversible'] === 0;

        $expandedComponents = [];
        if ($isReversibleComposite) {
            $composition = $this->productCompositions->findByProduct($productId);
            if ($composition === null) {
                $errors[] = "Composite '{$product['name']}' has no recipe yet — add components first.";
            } else {
                $recipeItems = $this->productCompositions->itemsByComposition((int) $composition['id']);
                if ($recipeItems === []) {
                    $errors[] = "Composite '{$product['name']}' has an empty recipe.";
                }
                foreach ($recipeItems as $ri) {
                    $consumed = (float) $ri['quantity'] * $quantity;
                    if ($ri['component_status'] !== 'active') {
                        $errors[] = "Component '{$ri['component_name']}' in recipe is inactive.";
                        break;
                    }
                    if ($consumed > (float) $ri['component_stock'] + 0.0001) {
                        $errors[] = sprintf(
                            "Not enough '%s' to produce %s × '%s' (need %s %s, have %s).",
                            $ri['component_name'],
                            \rtrim(\rtrim((string) $quantity, '0'), '.'),
                            $product['name'],
                            \rtrim(\rtrim((string) $consumed, '0'), '.'),
                            $ri['component_unit'],
                            \rtrim(\rtrim((string) $ri['component_stock'], '0'), '.')
                        );
                        break;
                    }
                    $expandedComponents[] = [
                        'component_product_id' => (int) $ri['component_product_id'],
                        'component_name'       => (string) $ri['component_name'],
                        'component_unit'       => (string) $ri['component_unit'],
                        'recipe_quantity'      => (float) $ri['quantity'],
                        'quantity_consumed'    => $consumed,
                    ];
                }
            }
        } else {
            // Simple product or saved Irreversible: oversell pre-check against own stock.
            if ($product !== null && $quantity > (float) $product['quantity_on_hand']) {
                $errors[] = sprintf(
                    "Only %s %s of '%s' in stock — cannot add %s.",
                    \rtrim(\rtrim((string) $product['quantity_on_hand'], '0'), '.'),
                    $product['unit'],
                    $product['name'],
                    \rtrim(\rtrim((string) $quantity, '0'), '.')
                );
            }
        }

        if ($salesmanId !== null) {
            $sm = $this->salesmen->find($salesmanId);
            if ($sm === null || $sm['status'] !== 'active') {
                $errors[] = 'Selected salesman is invalid or inactive.';
            }
        }

        if ($errors !== []) {
            Flash::error($errors[0]);
            return redirect("/sales-invoices/{$id}/edit");
        }

        if ($displayName === '') {
            $displayName = (string) $product['name'];
        }

        $computed = $this->pricing->computeLine(
            $quantity, $unitPrice, $addsubPercent, $taxRate,
            $invoice['bill_format'], $invoice['tax_treatment'], (int) $invoice['is_intra_state'] === 1
        );

        $lineId = Db::transaction(function () use ($id, $productId, $isReversibleComposite, $displayName, $salesmanId, $quantity, $entryUnitIdStore, $entryQtyStore, $unitPrice, $addsubPercent, $taxRate, $computed, $expandedComponents, $commOverride) {
            $lineId = $this->lineRepo->insert([
            'sales_invoice_id' => $id,
            'line_no'          => $this->lineRepo->nextLineNo($id),
            'product_id'       => $productId,
            'is_composite_line'=> $isReversibleComposite ? 1 : 0,
            'display_name'     => $displayName,
            'salesman_id'      => $salesmanId,
            'commission_rate_override' => $commOverride,
            'quantity'         => $quantity,
            'entry_unit_id'    => $entryUnitIdStore,
            'entry_quantity'   => $entryQtyStore,
            'unit_price'       => $unitPrice,
            'addsub_percent'   => $addsubPercent,
            'subtotal'         => $computed['subtotal'],
            'tax_rate'         => $taxRate,
            'tax_amount'       => $computed['tax_amount'],
            'cgst_amount'      => $computed['cgst'],
            'sgst_amount'      => $computed['sgst'],
            'igst_amount'      => $computed['igst'],
            'line_total'       => $computed['line_total'],
            ]);
            foreach ($expandedComponents as $comp) {
                $this->lineComponentRepo->insert($lineId, $comp);
            }
            return $lineId;
        });

        $suffix = $isReversibleComposite ? ' (composite — components will be deducted on finalize)' : '';
        Flash::success("Line added: {$displayName} × {$quantity}{$suffix}");
        return redirect("/sales-invoices/{$id}/edit");
    }

    public function addAdHocLine(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot add lines to a finalized invoice.');
            return redirect("/sales-invoices/{$id}");
        }

        $displayName   = \trim((string) ($_POST['display_name']   ?? ''));
        $salesmanId    = ((int) ($_POST['salesman_id']     ?? 0)) ?: null;
        $quantity      = (float) ($_POST['quantity']       ?? 1);
        $unitPrice     = (float) ($_POST['unit_price']     ?? 0);
        $addsubPercent = (float) ($_POST['addsub_percent'] ?? 0);
        $taxRate       = (float) ($_POST['tax_rate']       ?? 0);
        $compInput     = $_POST['components'] ?? [];
        $errors = [];

        if ($displayName === '') { $errors[] = 'Display name is required for ad-hoc composites.'; }
        if ($quantity  <= 0)     { $errors[] = 'Quantity must be > 0.'; }
        if ($unitPrice <  0)     { $errors[] = 'Unit price cannot be negative.'; }
        if ($addsubPercent < -100 || $addsubPercent > 100) { $errors[] = 'AddSub % must be between -100 and 100.'; }
        if ($taxRate < 0 || $taxRate > 100) { $errors[] = 'Tax rate must be between 0 and 100.'; }
        if (!\is_array($compInput) || $compInput === []) {
            $errors[] = 'Add at least one component to the ad-hoc composite.';
        }

        // Validate each component and compute consumed qty.
        $expandedComponents = [];
        $seenComponentIds   = [];
        if ($errors === []) {
            foreach ($compInput as $c) {
                $cid = (int)   ($c['product_id'] ?? 0);
                $qty = (float) ($c['quantity']   ?? 0);
                if ($cid <= 0 || $qty <= 0) {
                    $errors[] = 'Every component needs a product and a positive quantity.';
                    break;
                }
                if (isset($seenComponentIds[$cid])) {
                    $errors[] = 'Duplicate component in ad-hoc recipe — combine into a single row.';
                    break;
                }
                $seenComponentIds[$cid] = true;

                $cp = $this->products->find($cid);
                if ($cp === null) { $errors[] = "Component #{$cid} not found."; break; }
                if ($cp['status'] !== 'active') { $errors[] = "Component '{$cp['name']}' is inactive."; break; }
                $consumed = $qty * $quantity;
                if ($consumed > (float) $cp['quantity_on_hand'] + 0.0001) {
                    $errors[] = sprintf(
                        "Not enough '%s' (need %s %s, have %s).",
                        $cp['name'],
                        \rtrim(\rtrim((string) $consumed, '0'), '.'),
                        $cp['unit'],
                        \rtrim(\rtrim((string) $cp['quantity_on_hand'], '0'), '.')
                    );
                    break;
                }
                $expandedComponents[] = [
                    'component_product_id' => $cid,
                    'component_name'       => (string) $cp['name'],
                    'component_unit'       => (string) $cp['unit'],
                    'recipe_quantity'      => $qty,
                    'quantity_consumed'    => $consumed,
                ];
            }
        }

        if ($salesmanId !== null) {
            $sm = $this->salesmen->find($salesmanId);
            if ($sm === null || $sm['status'] !== 'active') {
                $errors[] = 'Selected salesman is invalid or inactive.';
            }
        }

        if ($errors !== []) {
            Flash::error($errors[0]);
            return redirect("/sales-invoices/{$id}/edit");
        }

        $computed = $this->pricing->computeLine(
            $quantity, $unitPrice, $addsubPercent, $taxRate,
            $invoice['bill_format'], $invoice['tax_treatment'], (int) $invoice['is_intra_state'] === 1
        );

        Db::transaction(function () use ($id, $displayName, $salesmanId, $quantity, $unitPrice, $addsubPercent, $taxRate, $computed, $expandedComponents) {
            $lineId = $this->lineRepo->insert([
                'sales_invoice_id'  => $id,
                'line_no'           => $this->lineRepo->nextLineNo($id),
                'product_id'        => null,
                'is_composite_line' => 1,
                'display_name'      => $displayName,
                'salesman_id'       => $salesmanId,
                'quantity'          => $quantity,
                'unit_price'        => $unitPrice,
                'addsub_percent'    => $addsubPercent,
                'subtotal'          => $computed['subtotal'],
                'tax_rate'          => $taxRate,
                'tax_amount'        => $computed['tax_amount'],
                'cgst_amount'       => $computed['cgst'],
                'sgst_amount'       => $computed['sgst'],
                'igst_amount'       => $computed['igst'],
                'line_total'        => $computed['line_total'],
            ]);
            foreach ($expandedComponents as $comp) {
                $this->lineComponentRepo->insert($lineId, $comp);
            }
        });

        Flash::success("Ad-hoc composite line added: {$displayName} (" . \count($expandedComponents) . ' components)');
        return redirect("/sales-invoices/{$id}/edit");
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
            return redirect("/sales-invoices/{$id}");
        }
        $this->lineRepo->deleteLine($lineId, $id);
        Flash::success('Line removed.');
        return redirect("/sales-invoices/{$id}/edit");
    }

    public function updateLine(array $params): string
    {
        $id      = (int) $params['id'];
        $lineId  = (int) $params['lineId'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot edit lines on a finalized invoice.');
            return redirect("/sales-invoices/{$id}");
        }

        $line = $this->lineRepo->findLine($lineId, $id);
        if ($line === null) {
            Flash::error('Line not found.');
            return redirect("/sales-invoices/{$id}/edit");
        }

        $displayName   = \trim((string) ($_POST['display_name']   ?? '')) ?: (string) $line['display_name'];
        $quantity      = (float) ($_POST['quantity']       ?? $line['quantity']);
        $unitPrice     = (float) ($_POST['unit_price']     ?? $line['unit_price']);
        $addsubPercent = (float) ($_POST['addsub_percent'] ?? $line['addsub_percent']);
        $taxRate       = (float) ($_POST['tax_rate']       ?? $line['tax_rate']);
        $salesmanId    = ((int) ($_POST['salesman_id']     ?? 0)) ?: null;
        $commOverride  = $this->commissionOverrideInput();

        $errors = [];
        if ($quantity  <= 0) { $errors[] = 'Quantity must be > 0.'; }
        if ($unitPrice <  0) { $errors[] = 'Unit price cannot be negative.'; }
        if ($addsubPercent < -100 || $addsubPercent > 100) { $errors[] = 'AddSub % must be between -100 and 100.'; }
        if ($taxRate < 0 || $taxRate > 100) { $errors[] = 'Tax rate must be between 0 and 100.'; }
        if ($commOverride !== null && ($commOverride < 0 || $commOverride > 100)) {
            $errors[] = 'Commission % must be between 0 and 100.';
        }
        if ($commOverride !== null && $salesmanId === null) {
            $errors[] = 'Assign a salesman before setting a commission rate for the line.';
        }

        // For composite lines, quantity change would require re-snapshotting components — block for v1.
        if ((int) $line['is_composite_line'] === 1 && (float) $line['quantity'] != $quantity) {
            $errors[] = 'Quantity cannot be changed on a composite line — delete and re-add instead.';
        }

        // Stock pre-check (simple lines only; composite lines validated at finalize).
        if ((int) $line['is_composite_line'] !== 1 && $line['product_id']) {
            $product = $this->products->find((int) $line['product_id']);
            if ($product !== null && $quantity > (float) $product['quantity_on_hand']) {
                $errors[] = sprintf(
                    "Only %s %s of '%s' in stock — cannot set qty %s.",
                    \rtrim(\rtrim((string) $product['quantity_on_hand'], '0'), '.'),
                    $product['unit'], $product['name'],
                    \rtrim(\rtrim((string) $quantity, '0'), '.')
                );
            }
        }

        if ($errors !== []) {
            Flash::error($errors[0]);
            return redirect("/sales-invoices/{$id}/edit");
        }

        $computed = $this->pricing->computeLine(
            $quantity, $unitPrice, $addsubPercent, $taxRate,
            $invoice['bill_format'], $invoice['tax_treatment'], (int) $invoice['is_intra_state'] === 1
        );

        $this->lineRepo->updateLineEdit($lineId, [
            'display_name'   => $displayName,
            'quantity'       => $quantity,
            'unit_price'     => $unitPrice,
            'addsub_percent' => $addsubPercent,
            'tax_rate'       => $taxRate,
            'salesman_id'    => $salesmanId,
            'commission_rate_override' => $commOverride,
            'subtotal'       => $computed['subtotal'],
            'tax_amount'     => $computed['tax_amount'],
            'cgst_amount'    => $computed['cgst'],
            'sgst_amount'    => $computed['sgst'],
            'igst_amount'    => $computed['igst'],
            'line_total'     => $computed['line_total'],
        ]);

        Flash::success("Line updated: {$displayName}");
        return redirect("/sales-invoices/{$id}/edit");
    }

    /**
     * CR-6 — read the per-line commission rate override.
     *
     * A blank field means "use the salesman's own rate" and is stored as NULL. That is
     * deliberately different from an explicit 0, which means "no commission on this
     * sale" and must be honoured rather than falling back to the master rate.
     */
    private function commissionOverrideInput(): ?float
    {
        $raw = \trim((string) ($_POST['commission_rate_override'] ?? ''));
        return $raw === '' ? null : (float) $raw;
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
            return redirect("/sales-invoices/{$id}");
        }

        try {
            $this->service->finalize($id, (int) Auth::id());
            Flash::success('Sale finalized. Stock updated, COGS and commission recorded.');
        } catch (\Throwable $e) {
            Flash::error('Finalize failed: ' . $e->getMessage());
            return redirect("/sales-invoices/{$id}/edit");
        }
        return redirect("/sales-invoices/{$id}");
    }

    public function show(array $params): string
    {
        $invoice = $this->requireInvoice((int) $params['id']);
        if (\is_string($invoice)) {
            return $invoice;
        }
        $lines = $this->lineRepo->byInvoice((int) $invoice['id']);
        $lineComponents = [];
        foreach ($lines as $l) {
            if ((int) $l['is_composite_line'] === 1) {
                $lineComponents[(int) $l['id']] = $this->lineComponentRepo->byLine((int) $l['id']);
            }
        }
        return view('sales-invoices/show', [
            'title'          => $invoice['invoice_number'] ?: 'Sales Invoice draft',
            'invoice'        => $invoice,
            'lines'          => $lines,
            'lineComponents' => $lineComponents,
            'extras'         => $this->extraRepo->byInvoice((int) $invoice['id']),
        ]);
    }

    public function printA4(array $params): string
    {
        return $this->renderPrint($params, 'sales-invoices/print/a4');
    }

    public function printThermal(array $params): string
    {
        return $this->renderPrint($params, 'sales-invoices/print/thermal');
    }

    private function renderPrint(array $params, string $viewName): string
    {
        $invoice = $this->requireInvoice((int) $params['id']);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'finalized') {
            Flash::warning('Only finalized invoices can be printed.');
            return redirect("/sales-invoices/{$invoice['id']}");
        }
        $lines = $this->lineRepo->byInvoice((int) $invoice['id']);
        $lineComponents = [];
        foreach ($lines as $l) {
            if ((int) $l['is_composite_line'] === 1) {
                $lineComponents[(int) $l['id']] = $this->lineComponentRepo->byLine((int) $l['id']);
            }
        }
        $previousDue = $this->repo->previousDueForCustomer(
            (int) $invoice['customer_id'],
            (int) $invoice['id']
        );
        return view($viewName, [
            'title'          => 'Invoice ' . $invoice['invoice_number'],
            'invoice'        => $invoice,
            'lines'          => $lines,
            'lineComponents' => $lineComponents,
            'extras'         => $this->extraRepo->byInvoice((int) $invoice['id']),
            'business'       => $this->business->get(),
            'previousDue'    => $previousDue,
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
            return redirect("/sales-invoices/{$id}");
        }
        $this->repo->cancelDraft($id, (int) Auth::id());
        \App\Support\ActivityLog::log('cancel', 'sales_invoice', $id, [
            'label' => $invoice['invoice_number'] ?? null,
        ]);
        Flash::success('Draft cancelled.');
        return redirect('/sales-invoices');
    }

    /** Hard-delete a draft. Lines / extras / components cascade via FK. */
    public function destroy(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) {
            return $invoice;
        }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Only drafts can be deleted. Cancel a finalized invoice instead.');
            return redirect("/sales-invoices/{$id}");
        }
        $this->repo->deleteDraft($id);
        \App\Support\ActivityLog::log('delete', 'sales_invoice', $id, [
            'label' => $invoice['invoice_number'] ?? '(draft)',
            'notes' => 'Hard-delete of draft',
        ]);
        Flash::success('Draft deleted.');
        return redirect('/sales-invoices');
    }

    // ---------- Footer editor endpoints ----------

    /** Update final discount + invoice-level tax rate from the footer. Draft only. */
    public function updateFooter(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) { return $invoice; }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot edit a finalized or cancelled invoice.');
            return redirect("/sales-invoices/{$id}");
        }
        $billFormat = (string) $invoice['bill_format'];
        $discount   = $billFormat === 'discount_then_tax' ? \max(0.0, (float) ($_POST['final_discount_amount'] ?? 0)) : 0.0;
        $rate       = $billFormat === 'discount_then_tax' ? (float) ($_POST['invoice_level_tax_rate'] ?? 0) : 0.0;
        if ($rate < 0 || $rate > 100) {
            Flash::error('Invoice-level tax rate must be 0-100.');
            return redirect("/sales-invoices/{$id}/edit");
        }
        $this->repo->updateFooter($id, $rate, $discount, (int) Auth::id());
        Flash::success('Totals updated.');
        return redirect("/sales-invoices/{$id}/edit");
    }

    public function addExtra(array $params): string
    {
        $id      = (int) $params['id'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) { return $invoice; }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot edit a finalized or cancelled invoice.');
            return redirect("/sales-invoices/{$id}");
        }

        $label   = \trim((string) ($_POST['label'] ?? ''));
        $amount  = \max(0.0, (float) ($_POST['amount'] ?? 0));
        $taxRate = \max(0.0, (float) ($_POST['tax_rate'] ?? 0));

        $errors = [];
        if ($label === '')             { $errors[] = 'Label is required.'; }
        if ($amount <= 0)              { $errors[] = 'Amount must be > 0.'; }
        if ($taxRate < 0 || $taxRate > 100) { $errors[] = 'Tax rate must be 0-100.'; }
        if ($errors !== []) {
            Flash::error(\implode(' ', $errors));
            return redirect("/sales-invoices/{$id}/edit");
        }

        $this->extraRepo->insert($id, [
            'label'      => $label,
            'amount'     => $amount,
            'tax_rate'   => $taxRate,
            'sort_order' => $this->extraRepo->nextSortOrder($id),
        ]);
        Flash::success('Extra added.');
        return redirect("/sales-invoices/{$id}/edit");
    }

    public function updateExtra(array $params): string
    {
        $id      = (int) $params['id'];
        $extraId = (int) $params['extraId'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) { return $invoice; }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot edit a finalized or cancelled invoice.');
            return redirect("/sales-invoices/{$id}");
        }
        $extra = $this->extraRepo->find($extraId);
        if ($extra === null || (int) $extra['sales_invoice_id'] !== $id) {
            Flash::error('Extra not found.');
            return redirect("/sales-invoices/{$id}/edit");
        }

        $label   = \trim((string) ($_POST['label'] ?? ''));
        $amount  = \max(0.0, (float) ($_POST['amount'] ?? 0));
        $taxRate = \max(0.0, (float) ($_POST['tax_rate'] ?? 0));

        $errors = [];
        if ($label === '')             { $errors[] = 'Label is required.'; }
        if ($amount <= 0)              { $errors[] = 'Amount must be > 0.'; }
        if ($taxRate < 0 || $taxRate > 100) { $errors[] = 'Tax rate must be 0-100.'; }
        if ($errors !== []) {
            Flash::error(\implode(' ', $errors));
            return redirect("/sales-invoices/{$id}/edit");
        }

        $this->extraRepo->update($extraId, [
            'label'    => $label,
            'amount'   => $amount,
            'tax_rate' => $taxRate,
        ]);
        Flash::success('Extra updated.');
        return redirect("/sales-invoices/{$id}/edit");
    }

    public function deleteExtra(array $params): string
    {
        $id      = (int) $params['id'];
        $extraId = (int) $params['extraId'];
        $invoice = $this->requireInvoice($id);
        if (\is_string($invoice)) { return $invoice; }
        if ($invoice['status'] !== 'draft') {
            Flash::error('Cannot edit a finalized or cancelled invoice.');
            return redirect("/sales-invoices/{$id}");
        }
        $extra = $this->extraRepo->find($extraId);
        if ($extra === null || (int) $extra['sales_invoice_id'] !== $id) {
            Flash::error('Extra not found.');
            return redirect("/sales-invoices/{$id}/edit");
        }
        $this->extraRepo->delete($extraId);
        Flash::success('Extra removed.');
        return redirect("/sales-invoices/{$id}/edit");
    }

    /** @return array{0:array, 1:?string} */
    private function validateHeader(array $input, string $redirectTo): array
    {
        $customerId = (int) ($input['customer_id'] ?? 0);
        $billFormat = (string) ($input['bill_format'] ?? 'per_line_tax');

        $data = [
            'customer_id'            => $customerId,
            'invoice_date'           => \trim((string) ($input['invoice_date'] ?? \date('Y-m-d'))),
            'bill_format'            => $billFormat,
            'tax_treatment'          => (string) ($input['tax_treatment'] ?? 'exclusive'),
            'is_intra_state'         => $this->resolveIntraState($customerId),
            'header_salesman_id'     => ((int) ($input['header_salesman_id'] ?? 0)) ?: null,
            'remarks'                => \trim((string) ($input['remarks'] ?? '')) ?: null,
        ];

        $v = Validator::make([
                'customer_id'    => (string) $data['customer_id'],
                'invoice_date'   => $data['invoice_date'],
                'bill_format'    => $data['bill_format'],
                'tax_treatment'  => $data['tax_treatment'],
            ])
            ->required('customer_id', 'Customer is required.')
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

    private function resolveIntraState(int $customerId): int
    {
        $bp = $this->business->get();
        $customer = $this->customers->find($customerId);
        $businessState = (string) ($bp['state'] ?? '');
        $customerState = (string) ($customer['state'] ?? '');
        if ($businessState === '' || $customerState === '') {
            return 1;
        }
        return \strcasecmp($businessState, $customerState) === 0 ? 1 : 0;
    }

    /** @return array<int, array{id:int, default_salesman_id:?int}> Map of customer id → default salesman id. */
    private function customerDefaults(array $customers): array
    {
        if ($customers === []) {
            return [];
        }
        $stmt = Db::pdo()->query(
            'SELECT id, default_salesman_id FROM customers WHERE status = "active"'
        );
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id']] = $r['default_salesman_id'] ? (int) $r['default_salesman_id'] : null;
        }
        return $map;
    }

    /** All sellable products (D14: multi-brand allowed for sales). Includes moving-avg + latest cost + derived-market for composites. */
    private function productsForSale(): array
    {
        return Db::pdo()->query(
            "SELECT p.id, p.name, p.unit, p.unit_id, p.brand_id, b.name AS brand_name,
                    p.tax_rate, p.default_selling_price, p.quantity_on_hand,
                    p.moving_avg_cost, p.latest_purchase_landed_cost,
                    p.is_composite,
                    CASE WHEN p.is_composite = 1 THEN COALESCE((
                        SELECT SUM(pci.quantity * cp.latest_purchase_landed_cost)
                        FROM product_composition_items pci
                        JOIN product_compositions pc ON pc.id = pci.composition_id
                        JOIN products cp ON cp.id = pci.component_product_id
                        WHERE pc.product_id = p.id
                    ), 0) ELSE 0 END AS derived_market_price,
                    CASE WHEN p.is_composite = 1 THEN COALESCE((
                        SELECT SUM(pci.quantity * cp.moving_avg_cost)
                        FROM product_composition_items pci
                        JOIN product_compositions pc ON pc.id = pci.composition_id
                        JOIN products cp ON cp.id = pci.component_product_id
                        WHERE pc.product_id = p.id
                    ), 0) ELSE 0 END AS derived_avg_cost
             FROM products p
             JOIN brands b         ON b.id  = p.brand_id
             JOIN subcategories sc ON sc.id = p.subcategory_id
             JOIN categories c     ON c.id  = sc.category_id
             WHERE p.status = 'active' AND b.status = 'active'
               AND sc.status = 'active' AND c.status = 'active'
             ORDER BY p.name"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Preview totals — unified extras model (each extra has its own tax_rate; both modes).
     * Mode A (discount_then_tax): final discount reduces the LINE base only; extras are
     *   independent and taxed at their own rates.
     * Mode B (per_line_tax): no final discount. Lines and extras each tax at their own rates.
     */
    private function computePreview(array $invoice, array $lines, array $extras = []): array
    {
        $treatment  = (string) ($invoice['tax_treatment'] ?? 'exclusive');
        $billFormat = (string) ($invoice['bill_format'] ?? 'per_line_tax');
        $subtotal   = (float) \array_sum(\array_column($lines, 'subtotal'));
        $lineTax    = (float) \array_sum(\array_column($lines, 'tax_amount'));

        $totalExtras    = 0.0;
        $totalExtrasTax = 0.0;
        foreach ($extras as $ex) {
            $amt  = (float) $ex['amount'];
            $rate = $treatment === 'no_tax' ? 0.0 : (float) $ex['tax_rate'];
            $totalExtras    += $amt;
            $totalExtrasTax += $this->pricing->money($amt * $rate / 100);
        }

        if ($billFormat === 'per_line_tax') {
            $tax   = $treatment === 'no_tax' ? 0.0 : $this->pricing->money($lineTax + $totalExtrasTax);
            $grand = $this->pricing->money($subtotal + $lineTax + $totalExtras + $totalExtrasTax);
            return [
                'mode'             => 'per_line_tax',
                'subtotal'         => $this->pricing->money($subtotal),
                'line_tax'         => $this->pricing->money($lineTax),
                'final_discount'   => 0.0,
                'taxable_subtotal' => $this->pricing->money($subtotal),
                'lines_tax'        => $this->pricing->money($lineTax),
                'total_extras'     => $this->pricing->money($totalExtras),
                'total_extras_tax' => $this->pricing->money($totalExtrasTax),
                'tax'              => $tax,
                'grand_total'      => $grand,
            ];
        }

        // Mode A: discount on lines only; invoice-level tax on (lineSubtotal − discount).
        $finalDiscount = \max(0.0, (float) ($invoice['final_discount_amount'] ?? 0));
        if ($finalDiscount > $subtotal) { $finalDiscount = $subtotal; }
        $taxableLines = $this->pricing->money($subtotal - $finalDiscount);
        $rate         = $treatment === 'no_tax' ? 0.0 : (float) $invoice['invoice_level_tax_rate'];
        $linesTax     = $this->pricing->money($taxableLines * $rate / 100);

        $grand = $this->pricing->money($taxableLines + $linesTax + $totalExtras + $totalExtrasTax);
        return [
            'mode'             => 'discount_then_tax',
            'subtotal'         => $this->pricing->money($subtotal),
            'line_tax'         => 0.0,
            'final_discount'   => $this->pricing->money($finalDiscount),
            'taxable_subtotal' => $taxableLines,
            'lines_tax'        => $linesTax,
            'total_extras'     => $this->pricing->money($totalExtras),
            'total_extras_tax' => $this->pricing->money($totalExtrasTax),
            'tax'              => $this->pricing->money($linesTax + $totalExtrasTax),
            'grand_total'      => $grand,
        ];
    }

    private function requireInvoice(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Sales invoice not found.');
            return redirect('/sales-invoices');
        }
        return $row;
    }
}
