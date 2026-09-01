<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CommissionSettlementRepository;
use App\Repositories\CustomerAdvanceRepository;
use App\Repositories\SalesInvoiceRepository;
use App\Repositories\SalesPaymentRepository;
use App\Repositories\SalesmanRepository;
use App\Services\SalesPaymentService;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;

class SalesPaymentController
{
    public function __construct(
        private SalesPaymentRepository         $payments    = new SalesPaymentRepository(),
        private SalesInvoiceRepository         $invoices    = new SalesInvoiceRepository(),
        private SalesPaymentService            $service     = new SalesPaymentService(),
        private BusinessProfileRepository      $business    = new BusinessProfileRepository(),
        private BankAccountRepository          $banks       = new BankAccountRepository(),
        private CashierDrawerRepository        $drawers     = new CashierDrawerRepository(),
        private SalesmanRepository             $salesmen    = new SalesmanRepository(),
        private CommissionSettlementRepository $commissions = new CommissionSettlementRepository(),
        private CustomerAdvanceRepository      $advances    = new CustomerAdvanceRepository(),
    ) {}

    public function index(): string
    {
        $filter = (string) ($_GET['filter'] ?? 'due');
        $statusClause = match ($filter) {
            'due'     => "si.status = 'finalized' AND si.payment_status IN ('unpaid','partial')",
            'paid'    => "si.status = 'finalized' AND si.payment_status = 'paid'",
            'all'     => "si.status = 'finalized'",
            default   => "si.status = 'finalized' AND si.payment_status IN ('unpaid','partial')",
        };

        $p      = \App\Support\Paginator::fromQuery();
        $params = [];
        $where  = $statusClause;
        if ($p->search !== '') {
            $where .= ' AND (si.invoice_number LIKE ? OR c.name LIKE ?)';
            $params[] = '%' . $p->search . '%'; $params[] = '%' . $p->search . '%';
        }

        $countStmt = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM sales_invoices si JOIN customers c ON c.id = si.customer_id WHERE {$where}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset  = \max(0, ($p->page - 1) * $p->perPage);
        $perPage = \max(1, $p->perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT si.id, si.invoice_number, si.invoice_date, si.grand_total,
                    si.paid_amount, si.due_amount, si.payment_status, si.bill_type,
                    c.name AS customer_name
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id
             WHERE {$where}
             ORDER BY si.invoice_date DESC, si.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $counts = Db::pdo()->query(
            "SELECT
               SUM(CASE WHEN payment_status IN ('unpaid','partial') THEN 1 ELSE 0 END) AS due_count,
               SUM(CASE WHEN payment_status = 'paid'                THEN 1 ELSE 0 END) AS paid_count,
               COUNT(*)                                                                AS all_count
             FROM sales_invoices WHERE status = 'finalized'"
        )->fetch(\PDO::FETCH_ASSOC) ?: ['due_count' => 0, 'paid_count' => 0, 'all_count' => 0];

        return view('sales-payments/index', [
            'title'    => 'Sales Payments',
            'invoices' => $rows,
            'filter'   => $filter,
            'counts'   => $counts,
            'pg'       => $p->buildContext($total, url('/sales-payments'), ['filter' => $filter, 'q' => $p->search]),
        ]);
    }

    public function show(array $params): string
    {
        $invoiceId = (int) $params['id'];
        $invoice = $this->invoices->find($invoiceId);
        if ($invoice === null) {
            Flash::error('Invoice not found.');
            return redirect('/sales-payments');
        }
        if ($invoice['status'] !== 'finalized') {
            Flash::warning('Only finalized invoices can receive payments.');
            return redirect("/sales-invoices/{$invoiceId}");
        }
        // If a salesman has linked_customer_id pointing to this customer, show their
        // outstanding-commission balance as a hint — staff can settle commission and
        // immediately use it to pay this bill (one-click flow on the settlement page).
        $linkedSalesman = $this->salesmen->findByLinkedCustomer((int) $invoice['customer_id']);
        $linkedCommission = $linkedSalesman !== null
            ? $this->commissions->outstandingForSalesman((int) $linkedSalesman['id'])
            : 0.0;

        // Customer advances available to apply against this invoice (active + balance > 0).
        $availableAdvances = $this->advances->activeForCustomer((int) $invoice['customer_id']);
        $availableAdvanceTotal = 0.0;
        foreach ($availableAdvances as $a) { $availableAdvanceTotal += (float) $a['balance']; }

        return view('sales-payments/show', [
            'title'                => "Payments for {$invoice['invoice_number']}",
            'invoice'              => $invoice,
            'payments'             => $this->payments->byInvoice($invoiceId),
            'banks'                => $this->banks->listActive(),
            'drawers'              => $this->drawers->listActive(),
            'linkedSalesman'       => $linkedSalesman,
            'linkedCommission'     => $linkedCommission,
            'availableAdvances'    => $availableAdvances,
            'availableAdvanceTotal'=> $availableAdvanceTotal,
        ]);
    }

    public function store(array $params): string
    {
        $invoiceId = (int) $params['id'];

        $accountRaw = (string) ($_POST['received_into'] ?? '');   // "bank_account:5" or "cashier_drawer:2"
        [$accountKind, $accountId] = $this->parseAccountChoice($accountRaw);

        $fromAdvanceAmount = (float) ($_POST['from_advance_amount'] ?? 0);
        $fromAdvanceId     = ((int) ($_POST['from_advance_id'] ?? 0)) ?: null;

        $data = [
            'payment_date'        => \trim((string) ($_POST['payment_date']      ?? \date('Y-m-d'))),
            'cash_amount'         => (float) ($_POST['cash_amount']       ?? 0),
            'online_amount'       => (float) ($_POST['online_amount']     ?? 0),
            'online_reference'    => \trim((string) ($_POST['online_reference'] ?? '')) ?: null,
            'cheque_amount'       => (float) ($_POST['cheque_amount']     ?? 0),
            'cheque_number'       => \trim((string) ($_POST['cheque_number']     ?? '')) ?: null,
            'cheque_date'         => \trim((string) ($_POST['cheque_date']       ?? '')) ?: null,
            'cheque_bank_name'    => \trim((string) ($_POST['cheque_bank_name']  ?? '')) ?: null,
            'received_into_kind'  => $accountKind,
            'received_into_id'    => $accountId,
            'from_advance_amount' => $fromAdvanceAmount,
            'from_advance_id'     => $fromAdvanceId,
            'remarks'             => \trim((string) ($_POST['remarks']           ?? '')) ?: null,
        ];

        $error = $this->validatePayment($data);
        if ($error !== null) {
            Flash::error($error);
            return redirect("/sales-payments/{$invoiceId}");
        }

        // total_amount counts toward the invoice paid_amount — includes cash/online/cheque
        // AND any from_advance_amount applied (the advance is real customer credit being absorbed).
        $data['total_amount'] = \round(
            $data['cash_amount']
                + $data['online_amount']
                + $data['cheque_amount']
                + $data['from_advance_amount'],
            2
        );
        $data['cheque_status'] = 'pending';

        try {
            $this->service->record($invoiceId, $data, (int) Auth::id());
            Flash::success(sprintf('Payment of ₹%0.2f received.', $data['total_amount']));
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        return redirect("/sales-payments/{$invoiceId}");
    }

    public function destroy(array $params): string
    {
        $paymentId = (int) $params['paymentId'];
        $payment = $this->payments->find($paymentId);
        if ($payment === null) {
            Flash::error('Payment not found.');
            return redirect('/sales-payments');
        }
        $invoiceId = (int) $payment['sales_invoice_id'];
        try {
            $this->service->delete($paymentId, (int) Auth::id());
            Flash::success('Payment deleted. Invoice due updated.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        return redirect("/sales-payments/{$invoiceId}");
    }

    public function printReceipt(array $params): string
    {
        $paymentId = (int) $params['paymentId'];
        $payment   = $this->payments->find($paymentId);
        if ($payment === null) {
            Flash::error('Payment not found.');
            return redirect('/sales-payments');
        }
        $invoice = $this->invoices->find((int) $payment['sales_invoice_id']);
        if ($invoice === null) {
            Flash::error('Invoice not found.');
            return redirect('/sales-payments');
        }
        return view('sales-payments/print/receipt', [
            'title'    => "Receipt #{$paymentId}",
            'payment'  => $payment,
            'invoice'  => $invoice,
            'business' => $this->business->get(),
        ], 'print');
    }

    public function updateChequeStatus(array $params): string
    {
        $paymentId = (int) $params['paymentId'];
        $status    = (string) ($_POST['cheque_status'] ?? '');

        $payment = $this->payments->find($paymentId);
        if ($payment === null) {
            Flash::error('Payment not found.');
            return redirect('/sales-payments');
        }
        $invoiceId = (int) $payment['sales_invoice_id'];

        try {
            $this->service->updateChequeStatus($paymentId, $status, (int) Auth::id());
            Flash::success("Cheque marked as {$status}. Invoice totals updated.");
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        return redirect("/sales-payments/{$invoiceId}");
    }

    private function validatePayment(array $d): ?string
    {
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['payment_date'])) {
            return 'Payment date must be YYYY-MM-DD.';
        }
        foreach (['cash_amount', 'online_amount', 'cheque_amount', 'from_advance_amount'] as $k) {
            if (($d[$k] ?? 0) < 0) return 'Amounts cannot be negative.';
        }
        $cashTotal = $d['cash_amount'] + $d['online_amount'] + $d['cheque_amount'];
        $advanceTotal = $d['from_advance_amount'] ?? 0;
        if ($cashTotal + $advanceTotal <= 0.0) {
            return 'Enter a positive amount in at least one of cash, online, cheque, or apply-from-advance.';
        }
        if ($d['cheque_amount'] > 0) {
            if (!$d['cheque_number'])    return 'Cheque number is required when cheque amount > 0.';
            if (!$d['cheque_date'])      return 'Cheque date is required when cheque amount > 0.';
            if (!$d['cheque_bank_name']) return "Drawer's bank name is required when cheque amount > 0.";
            if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['cheque_date'])) {
                return 'Cheque date must be YYYY-MM-DD.';
            }
        }
        // Receiving account is required ONLY when there's a cash component (advance-only payments
        // don't move cash through any drawer/bank).
        if ($cashTotal > 0 && ($d['received_into_kind'] === null || (int) $d['received_into_id'] <= 0)) {
            return 'Pick the bank account or cashier drawer that receives the cash/online/cheque portion.';
        }
        if ($advanceTotal > 0 && (int) ($d['from_advance_id'] ?? 0) <= 0) {
            return 'Pick which customer advance to draw from.';
        }
        return null;
    }

    /** Parse "bank_account:5" or "cashier_drawer:2" into [kind, id] or [null, 0]. */
    private function parseAccountChoice(string $raw): array
    {
        if ($raw === '' || !\str_contains($raw, ':')) {
            return [null, 0];
        }
        [$kind, $id] = \explode(':', $raw, 2);
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true)) {
            return [null, 0];
        }
        return [$kind, (int) $id];
    }
}
