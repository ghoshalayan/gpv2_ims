<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\PurchaseInvoiceLineRepository;
use App\Repositories\PurchaseInvoiceRepository;
use App\Repositories\PurchaseReturnLineRepository;
use App\Repositories\PurchaseReturnRepository;
use App\Services\PurchaseReturnService;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class PurchaseReturnController
{
    public function __construct(
        private PurchaseReturnRepository      $repo                = new PurchaseReturnRepository(),
        private PurchaseReturnLineRepository  $lineRepo            = new PurchaseReturnLineRepository(),
        private PurchaseInvoiceRepository     $invoiceRepo         = new PurchaseInvoiceRepository(),
        private PurchaseInvoiceLineRepository $invoiceLineRepo     = new PurchaseInvoiceLineRepository(),
        private BusinessProfileRepository     $business            = new BusinessProfileRepository(),
        private PurchaseReturnService         $service             = new PurchaseReturnService(),
        private BankAccountRepository         $banks               = new BankAccountRepository(),
        private CashierDrawerRepository       $drawers             = new CashierDrawerRepository(),
    ) {}

    /**
     * Parse a "kind:id" account selection from the form into [kind, id].
     * Returns [null, null] when nothing valid was picked.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function parseAccount(string $raw): array
    {
        if (!\str_contains($raw, ':')) { return [null, null]; }
        [$kind, $id] = \explode(':', $raw, 2);
        $id = (int) $id;
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true) || $id <= 0) {
            return [null, null];
        }
        return [$kind, $id];
    }

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();
        $filters = [
            'status'    => (string) ($_GET['status'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to'   => (string) ($_GET['date_to'] ?? ''),
        ];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('purchase-returns/index', [
            'title'   => 'Purchase Returns',
            'returns' => $result['rows'],
            'filters' => $filters,
            'pg'      => $p->buildContext($result['total'], url('/purchase-returns'), $filters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        return view('purchase-returns/create', [
            'title'    => 'New Purchase Return',
            'invoices' => $this->repo->returnableInvoices(),
            'banks'    => $this->banks->listActive(),
            'drawers'  => $this->drawers->listActive(),
        ]);
    }

    public function store(): string
    {
        $invoiceId = (int) ($_POST['original_purchase_invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            Flash::error('Pick a purchase invoice to return against.');
            return redirect('/purchase-returns/create');
        }
        $invoice = $this->invoiceRepo->find($invoiceId);
        if ($invoice === null || $invoice['status'] !== 'finalized') {
            Flash::error('Original invoice not found or not finalized.');
            return redirect('/purchase-returns/create');
        }

        $refundMethod = (string) ($_POST['refund_method'] ?? 'credit_note');
        [$refundKind, $refundAccId] = $this->parseAccount((string) ($_POST['refund_account'] ?? ''));

        $data = [
            'original_purchase_invoice_id' => $invoiceId,
            'supplier_id'                  => (int) $invoice['supplier_id'],
            'return_date'                  => \trim((string) ($_POST['return_date'] ?? \date('Y-m-d'))),
            'reason'                       => \trim((string) ($_POST['reason']         ?? '')) ?: null,
            'refund_method'                => $refundMethod,
            'refund_account_kind'          => $refundMethod === 'cash' ? $refundKind  : null,
            'refund_account_id'            => $refundMethod === 'cash' ? $refundAccId : null,
            'bill_format'                  => (string) $invoice['bill_format'],
            'tax_treatment'                => (string) $invoice['tax_treatment'],
            'is_intra_state'               => (int)    $invoice['is_intra_state'],
            'invoice_level_tax_rate'       => (float)  $invoice['invoice_level_tax_rate'],
            'gst_claimable'                => (int)    $invoice['gst_claimable'],
            'remarks'                      => null,
        ];

        $v = Validator::make([
                'return_date'   => $data['return_date'],
                'refund_method' => $data['refund_method'],
            ])
            ->required('return_date')
            ->in('refund_method', ['credit_note', 'cash'], 'Invalid refund method.');
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['return_date'])) {
            Flash::error('Return date must be YYYY-MM-DD.');
            return redirect('/purchase-returns/create');
        }
        if ($v->fails()) {
            Flash::error($v->firstError() ?? 'Validation failed.');
            return redirect('/purchase-returns/create');
        }
        if ($data['refund_method'] === 'cash' && $refundKind === null) {
            Flash::error('Pick the bank/drawer that will receive the cash refund.');
            return redirect('/purchase-returns/create');
        }

        $id = $this->repo->insertDraft($data, (int) Auth::id());
        Flash::success('Draft return created. Tick the lines + qty to return, then finalize.');
        return redirect("/purchase-returns/{$id}/edit");
    }

    public function edit(array $params): string
    {
        $return = $this->requireReturn((int) $params['id']);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            return redirect("/purchase-returns/{$return['id']}");
        }

        $returnLines = $this->lineRepo->byReturn((int) $return['id']);
        $existingByOrigLine = [];
        foreach ($returnLines as $rl) {
            $existingByOrigLine[(int) $rl['original_purchase_line_id']] = $rl;
        }

        $origLines = $this->invoiceLineRepo->byInvoice((int) $return['original_purchase_invoice_id']);
        $eligibleLines = [];
        foreach ($origLines as $ol) {
            $alreadyReturned = $this->lineRepo->returnedQtyForOriginalLine(
                (int) $ol['id'],
                (int) $return['id']
            );
            $remaining = (float) $ol['quantity'] - $alreadyReturned;
            $eligibleLines[] = [
                'orig'             => $ol,
                'already_returned' => $alreadyReturned,
                'remaining'        => $remaining,
                'on_draft'         => $existingByOrigLine[(int) $ol['id']] ?? null,
            ];
        }

        return view('purchase-returns/edit', [
            'title'         => 'Edit Purchase Return',
            'return'        => $return,
            'returnLines'   => $returnLines,
            'eligibleLines' => $eligibleLines,
            'banks'         => $this->banks->listActive(),
            'drawers'       => $this->drawers->listActive(),
        ]);
    }

    public function updateHeader(array $params): string
    {
        $id     = (int) $params['id'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Cannot edit a finalized or cancelled return.');
            return redirect("/purchase-returns/{$id}");
        }

        $refundMethod = (string) ($_POST['refund_method'] ?? 'credit_note');
        [$refundKind, $refundAccId] = $this->parseAccount((string) ($_POST['refund_account'] ?? ''));

        $chargeMode = (string) ($_POST['return_charge_mode'] ?? 'none');
        if (!\in_array($chargeMode, ['none', 'flat', 'percent'], true)) { $chargeMode = 'none'; }
        $chargeTaxable = $chargeMode !== 'none' && isset($_POST['return_charge_taxable']) ? 1 : 0;

        $data = [
            'return_date'         => \trim((string) ($_POST['return_date'] ?? \date('Y-m-d'))),
            'reason'              => \trim((string) ($_POST['reason']  ?? '')) ?: null,
            'refund_method'       => $refundMethod,
            'refund_account_kind' => $refundMethod === 'cash' ? $refundKind  : null,
            'refund_account_id'   => $refundMethod === 'cash' ? $refundAccId : null,
            'include_original_extras' => isset($_POST['include_original_extras']) ? 1 : 0,
            'return_charge_mode'      => $chargeMode,
            'return_charge_value'     => $chargeMode === 'none' ? 0 : \max(0, (float) ($_POST['return_charge_value'] ?? 0)),
            'return_charge_taxable'   => $chargeTaxable,
            'return_charge_tax_rate'  => $chargeTaxable ? \max(0, (float) ($_POST['return_charge_tax_rate'] ?? 0)) : 0,
            'remarks'             => \trim((string) ($_POST['remarks'] ?? '')) ?: null,
        ];
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['return_date'])) {
            Flash::error('Return date must be YYYY-MM-DD.');
            return redirect("/purchase-returns/{$id}/edit");
        }
        if (!\in_array($refundMethod, ['credit_note', 'cash'], true)) {
            Flash::error('Invalid refund method.');
            return redirect("/purchase-returns/{$id}/edit");
        }
        if ($refundMethod === 'cash' && $refundKind === null) {
            Flash::error('Pick the bank/drawer that will receive the cash refund.');
            return redirect("/purchase-returns/{$id}/edit");
        }
        $this->repo->updateDraftHeader($id, $data, (int) Auth::id());
        Flash::success('Header updated.');
        return redirect("/purchase-returns/{$id}/edit");
    }

    public function addLine(array $params): string
    {
        $id     = (int) $params['id'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Cannot add lines to a finalized return.');
            return redirect("/purchase-returns/{$id}");
        }

        $origLineId = (int) ($_POST['original_purchase_line_id'] ?? 0);
        $qty        = (float) ($_POST['quantity_returned'] ?? 0);
        if ($origLineId <= 0 || $qty <= 0) {
            Flash::error('Pick a line and enter a positive return qty.');
            return redirect("/purchase-returns/{$id}/edit");
        }

        $origLine = $this->invoiceLineRepo->findLine($origLineId, (int) $return['original_purchase_invoice_id']);
        if ($origLine === null) {
            Flash::error('Original line not found on this invoice.');
            return redirect("/purchase-returns/{$id}/edit");
        }
        $alreadyReturned = $this->lineRepo->returnedQtyForOriginalLine($origLineId, $id);
        $remaining = (float) $origLine['quantity'] - $alreadyReturned;
        if ($qty > $remaining + 0.0001) {
            Flash::error("Return qty {$qty} exceeds remaining {$remaining} for '{$origLine['product_name']}'.");
            return redirect("/purchase-returns/{$id}/edit");
        }

        $this->lineRepo->insert($id, [
            'original_purchase_line_id' => $origLineId,
            'line_no'                   => $this->lineRepo->nextLineNo($id),
            'product_id'                => (int) $origLine['product_id'],
            'quantity_returned'         => $qty,
            'unit_price'                => (float) $origLine['unit_price'],
            'addsub_percent'            => (float) $origLine['addsub_percent'],
            'tax_rate'                  => (float) $origLine['tax_rate'],
        ]);
        Flash::success("Added: {$origLine['product_name']} × " . \rtrim(\rtrim((string) $qty, '0'), '.'));
        return redirect("/purchase-returns/{$id}/edit");
    }

    public function deleteLine(array $params): string
    {
        $id     = (int) $params['id'];
        $lineId = (int) $params['lineId'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Cannot delete lines on a finalized return.');
            return redirect("/purchase-returns/{$id}");
        }
        $line = $this->lineRepo->find($lineId);
        if ($line === null || (int) $line['purchase_return_id'] !== $id) {
            Flash::error('Line not found on this return.');
            return redirect("/purchase-returns/{$id}/edit");
        }
        $this->lineRepo->delete($lineId);
        Flash::success('Line removed.');
        return redirect("/purchase-returns/{$id}/edit");
    }

    public function finalize(array $params): string
    {
        $id     = (int) $params['id'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Only drafts can be finalized.');
            return redirect("/purchase-returns/{$id}");
        }
        try {
            $this->service->finalize($id, (int) Auth::id());
            Flash::success('Return finalized. Stock out at current moving avg. Supplier payable reduced.');
        } catch (\Throwable $e) {
            Flash::error('Finalize failed: ' . $e->getMessage());
            return redirect("/purchase-returns/{$id}/edit");
        }
        return redirect("/purchase-returns/{$id}");
    }

    public function show(array $params): string
    {
        $return = $this->requireReturn((int) $params['id']);
        if (\is_string($return)) { return $return; }
        return view('purchase-returns/show', [
            'title'  => $return['return_number'] ?: 'Purchase return draft',
            'return' => $return,
            'lines'  => $this->lineRepo->byReturn((int) $return['id']),
        ]);
    }

    public function printA4(array $params): string
    {
        $return = $this->requireReturn((int) $params['id']);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'finalized') {
            Flash::warning('Only finalized returns can be printed.');
            return redirect("/purchase-returns/{$return['id']}");
        }
        return view('purchase-returns/print/a4', [
            'title'    => 'Debit Note ' . $return['return_number'],
            'return'   => $return,
            'lines'    => $this->lineRepo->byReturn((int) $return['id']),
            'business' => $this->business->get(),
        ], 'print');
    }

    public function cancel(array $params): string
    {
        $id     = (int) $params['id'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Only drafts can be cancelled.');
            return redirect("/purchase-returns/{$id}");
        }
        $this->repo->cancelDraft($id, (int) Auth::id());
        Flash::success('Draft cancelled.');
        return redirect('/purchase-returns');
    }

    /** Hard-delete a draft. Admin only — gated at the route layer. */
    public function destroy(array $params): string
    {
        $id     = (int) $params['id'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Only drafts can be deleted.');
            return redirect("/purchase-returns/{$id}");
        }
        $this->repo->deleteDraft($id);
        Flash::success('Draft deleted.');
        return redirect('/purchase-returns');
    }

    private function requireReturn(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Purchase return not found.');
            return redirect('/purchase-returns');
        }
        return $row;
    }
}
