<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\SalesInvoiceLineComponentRepository;
use App\Repositories\SalesInvoiceLineRepository;
use App\Repositories\SalesInvoiceRepository;
use App\Repositories\SalesReturnLineComponentRepository;
use App\Repositories\SalesReturnLineRepository;
use App\Repositories\SalesReturnRepository;
use App\Services\SalesReturnService;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class SalesReturnController
{
    public function __construct(
        private SalesReturnRepository                $repo                  = new SalesReturnRepository(),
        private SalesReturnLineRepository            $lineRepo              = new SalesReturnLineRepository(),
        private SalesReturnLineComponentRepository   $lineComponentRepo     = new SalesReturnLineComponentRepository(),
        private SalesInvoiceRepository               $invoiceRepo           = new SalesInvoiceRepository(),
        private SalesInvoiceLineRepository           $invoiceLineRepo       = new SalesInvoiceLineRepository(),
        private SalesInvoiceLineComponentRepository  $invoiceLineCompRepo   = new SalesInvoiceLineComponentRepository(),
        private BusinessProfileRepository            $business              = new BusinessProfileRepository(),
        private SalesReturnService                   $service               = new SalesReturnService(),
        private BankAccountRepository                $banks                 = new BankAccountRepository(),
        private CashierDrawerRepository              $drawers               = new CashierDrawerRepository(),
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
        return view('sales-returns/index', [
            'title'   => 'Sales Returns',
            'returns' => $result['rows'],
            'pg'      => $p->buildContext($result['total'], url('/sales-returns'), $filters + ['q' => $p->search]),
            'filters' => $filters,
        ]);
    }

    public function create(): string
    {
        return view('sales-returns/create', [
            'title'    => 'New Sales Return',
            'invoices' => $this->repo->returnableInvoices(),
            'banks'    => $this->banks->listActive(),
            'drawers'  => $this->drawers->listActive(),
        ]);
    }

    public function store(): string
    {
        $invoiceId = (int) ($_POST['original_sales_invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            Flash::error('Pick a sales invoice to return against.');
            return redirect('/sales-returns/create');
        }
        $invoice = $this->invoiceRepo->find($invoiceId);
        if ($invoice === null || $invoice['status'] !== 'finalized') {
            Flash::error('Original invoice not found or not finalized.');
            return redirect('/sales-returns/create');
        }

        $refundMethod = (string) ($_POST['refund_method'] ?? 'credit_note');
        [$refundKind, $refundAccId] = $this->parseAccount((string) ($_POST['refund_account'] ?? ''));

        $data = [
            'original_sales_invoice_id' => $invoiceId,
            'customer_id'               => (int) $invoice['customer_id'],
            'return_date'               => \trim((string) ($_POST['return_date'] ?? \date('Y-m-d'))),
            'reason'                    => \trim((string) ($_POST['reason']         ?? '')) ?: null,
            'refund_method'             => $refundMethod,
            'refund_account_kind'       => $refundMethod === 'cash' ? $refundKind  : null,
            'refund_account_id'         => $refundMethod === 'cash' ? $refundAccId : null,
            'bill_format'               => (string) $invoice['bill_format'],
            'tax_treatment'             => (string) $invoice['tax_treatment'],
            'is_intra_state'            => (int)    $invoice['is_intra_state'],
            'invoice_level_tax_rate'    => (float)  $invoice['invoice_level_tax_rate'],
            'remarks'                   => null,
        ];

        $v = Validator::make([
                'return_date'   => $data['return_date'],
                'refund_method' => $data['refund_method'],
            ])
            ->required('return_date')
            ->in('refund_method', ['credit_note', 'cash'], 'Invalid refund method.');
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['return_date'])) {
            Flash::error('Return date must be YYYY-MM-DD.');
            return redirect('/sales-returns/create');
        }
        if ($v->fails()) {
            Flash::error($v->firstError() ?? 'Validation failed.');
            return redirect('/sales-returns/create');
        }
        if ($data['refund_method'] === 'cash' && $refundKind === null) {
            Flash::error('Pick the bank/drawer that will pay the cash refund.');
            return redirect('/sales-returns/create');
        }

        $id = $this->repo->insertDraft($data, (int) Auth::id());
        Flash::success('Draft return created. Tick the lines + qty to return, then finalize.');
        return redirect("/sales-returns/{$id}/edit");
    }

    public function edit(array $params): string
    {
        $return = $this->requireReturn((int) $params['id']);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            return redirect("/sales-returns/{$return['id']}");
        }

        $returnLines = $this->lineRepo->byReturn((int) $return['id']);
        // Build a quick lookup of which original lines are already on this draft.
        $existingByOrigLine = [];
        foreach ($returnLines as $rl) {
            $existingByOrigLine[(int) $rl['original_sales_line_id']] = $rl;
        }

        // Load original invoice's lines + remaining qty per line.
        $origLines = $this->invoiceLineRepo->byInvoice((int) $return['original_sales_invoice_id']);
        $eligibleLines = [];
        foreach ($origLines as $ol) {
            $alreadyReturned = $this->lineRepo->returnedQtyForOriginalLine(
                (int) $ol['id'],
                (int) $return['id']
            );
            $remaining = (float) $ol['quantity'] - $alreadyReturned;
            $existingDraft = $existingByOrigLine[(int) $ol['id']] ?? null;
            $eligibleLines[] = [
                'orig'           => $ol,
                'already_returned' => $alreadyReturned,
                'remaining'      => $remaining,
                'on_draft'       => $existingDraft,
            ];
        }

        return view('sales-returns/edit', [
            'title'         => 'Edit Sales Return',
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
            return redirect("/sales-returns/{$id}");
        }

        $refundMethod = (string) ($_POST['refund_method'] ?? 'credit_note');
        [$refundKind, $refundAccId] = $this->parseAccount((string) ($_POST['refund_account'] ?? ''));

        $chargeMode = (string) ($_POST['return_charge_mode'] ?? 'none');
        if (!\in_array($chargeMode, ['none', 'flat', 'percent'], true)) { $chargeMode = 'none'; }
        $chargeTaxable = $chargeMode !== 'none' && isset($_POST['return_charge_taxable']) ? 1 : 0;

        $data = [
            'return_date'         => \trim((string) ($_POST['return_date'] ?? \date('Y-m-d'))),
            'reason'              => \trim((string) ($_POST['reason'] ?? '')) ?: null,
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
            return redirect("/sales-returns/{$id}/edit");
        }
        if (!\in_array($refundMethod, ['credit_note', 'cash'], true)) {
            Flash::error('Invalid refund method.');
            return redirect("/sales-returns/{$id}/edit");
        }
        if ($refundMethod === 'cash' && $refundKind === null) {
            Flash::error('Pick the bank/drawer that will pay the cash refund.');
            return redirect("/sales-returns/{$id}/edit");
        }
        $this->repo->updateDraftHeader($id, $data, (int) Auth::id());
        Flash::success('Header updated.');
        return redirect("/sales-returns/{$id}/edit");
    }

    public function addLine(array $params): string
    {
        $id     = (int) $params['id'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Cannot add lines to a finalized return.');
            return redirect("/sales-returns/{$id}");
        }

        $origLineId = (int) ($_POST['original_sales_line_id'] ?? 0);
        $qty        = (float) ($_POST['quantity_returned'] ?? 0);
        if ($origLineId <= 0 || $qty <= 0) {
            Flash::error('Pick a line and enter a positive return qty.');
            return redirect("/sales-returns/{$id}/edit");
        }

        $origLine = $this->invoiceLineRepo->findLine($origLineId, (int) $return['original_sales_invoice_id']);
        if ($origLine === null) {
            Flash::error('Original line not found on this invoice.');
            return redirect("/sales-returns/{$id}/edit");
        }
        $alreadyReturned = $this->lineRepo->returnedQtyForOriginalLine($origLineId, $id);
        $remaining = (float) $origLine['quantity'] - $alreadyReturned;
        if ($qty > $remaining + 0.0001) {
            Flash::error("Return qty {$qty} exceeds remaining {$remaining} for '{$origLine['display_name']}'.");
            return redirect("/sales-returns/{$id}/edit");
        }

        $this->lineRepo->insert($id, [
            'original_sales_line_id' => $origLineId,
            'line_no'                => $this->lineRepo->nextLineNo($id),
            'product_id'             => $origLine['product_id'] ? (int) $origLine['product_id'] : null,
            'is_composite_line'      => (int) $origLine['is_composite_line'],
            'display_name'           => (string) $origLine['display_name'],
            'salesman_id'            => $origLine['salesman_id'] ? (int) $origLine['salesman_id'] : null,
            'quantity_returned'      => $qty,
            'unit_price'             => (float) $origLine['unit_price'],
            'addsub_percent'         => (float) $origLine['addsub_percent'],
            'tax_rate'               => (float) $origLine['tax_rate'],
            'cogs_per_unit'          => (float) $origLine['cogs_per_unit'],
            'commission_rate'        => (float) $origLine['commission_rate'],
        ]);
        Flash::success("Added: {$origLine['display_name']} × " . \rtrim(\rtrim((string) $qty, '0'), '.'));
        return redirect("/sales-returns/{$id}/edit");
    }

    public function deleteLine(array $params): string
    {
        $id     = (int) $params['id'];
        $lineId = (int) $params['lineId'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Cannot delete lines on a finalized return.');
            return redirect("/sales-returns/{$id}");
        }
        $line = $this->lineRepo->find($lineId);
        if ($line === null || (int) $line['sales_return_id'] !== $id) {
            Flash::error('Line not found on this return.');
            return redirect("/sales-returns/{$id}/edit");
        }
        $this->lineRepo->delete($lineId);
        Flash::success('Line removed.');
        return redirect("/sales-returns/{$id}/edit");
    }

    public function finalize(array $params): string
    {
        $id     = (int) $params['id'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Only drafts can be finalized.');
            return redirect("/sales-returns/{$id}");
        }
        try {
            $this->service->finalize($id, (int) Auth::id());
            Flash::success('Return finalized. Stock restocked at snapshot cost. Customer due reduced.');
        } catch (\Throwable $e) {
            Flash::error('Finalize failed: ' . $e->getMessage());
            return redirect("/sales-returns/{$id}/edit");
        }
        return redirect("/sales-returns/{$id}");
    }

    public function show(array $params): string
    {
        $return = $this->requireReturn((int) $params['id']);
        if (\is_string($return)) { return $return; }
        $lines = $this->lineRepo->byReturn((int) $return['id']);
        $lineComponents = [];
        foreach ($lines as $rl) {
            if ((int) $rl['is_composite_line'] === 1) {
                $lineComponents[(int) $rl['id']] = $this->lineComponentRepo->byLine((int) $rl['id']);
            }
        }
        return view('sales-returns/show', [
            'title'          => $return['return_number'] ?: 'Sales return draft',
            'return'         => $return,
            'lines'          => $lines,
            'lineComponents' => $lineComponents,
        ]);
    }

    public function printA4(array $params): string
    {
        $return = $this->requireReturn((int) $params['id']);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'finalized') {
            Flash::warning('Only finalized returns can be printed.');
            return redirect("/sales-returns/{$return['id']}");
        }
        $lines = $this->lineRepo->byReturn((int) $return['id']);
        $lineComponents = [];
        foreach ($lines as $rl) {
            if ((int) $rl['is_composite_line'] === 1) {
                $lineComponents[(int) $rl['id']] = $this->lineComponentRepo->byLine((int) $rl['id']);
            }
        }
        return view('sales-returns/print/a4', [
            'title'          => 'Credit Note ' . $return['return_number'],
            'return'         => $return,
            'lines'          => $lines,
            'lineComponents' => $lineComponents,
            'business'       => $this->business->get(),
        ], 'print');
    }

    public function cancel(array $params): string
    {
        $id     = (int) $params['id'];
        $return = $this->requireReturn($id);
        if (\is_string($return)) { return $return; }
        if ($return['status'] !== 'draft') {
            Flash::error('Only drafts can be cancelled.');
            return redirect("/sales-returns/{$id}");
        }
        $this->repo->cancelDraft($id, (int) Auth::id());
        Flash::success('Draft cancelled.');
        return redirect('/sales-returns');
    }

    private function requireReturn(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Sales return not found.');
            return redirect('/sales-returns');
        }
        return $row;
    }
}
