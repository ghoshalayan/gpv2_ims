<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CommissionSettlementRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SalesInvoiceRepository;
use App\Repositories\SalesmanRepository;
use App\Services\CommissionSettlementService;
use App\Services\SalesPaymentService;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;

class CommissionSettlementController
{
    public function __construct(
        private CommissionSettlementRepository $repo           = new CommissionSettlementRepository(),
        private SalesmanRepository             $salesmen       = new SalesmanRepository(),
        private ProductRepository              $products       = new ProductRepository(),
        private BankAccountRepository          $banks          = new BankAccountRepository(),
        private CashierDrawerRepository        $drawers        = new CashierDrawerRepository(),
        private CustomerRepository             $customers      = new CustomerRepository(),
        private SalesInvoiceRepository         $invoices       = new SalesInvoiceRepository(),
        private CommissionSettlementService    $service        = new CommissionSettlementService(),
        private SalesPaymentService            $paymentService = new SalesPaymentService(),
    ) {}

    public function index(): string
    {
        $p              = \App\Support\Paginator::fromQuery();
        $salesmanFilter = (int) ($_GET['salesman_id'] ?? 0) ?: null;
        $payoutMode     = (string) ($_GET['payout_mode'] ?? '');
        $status         = (string) ($_GET['status'] ?? '');
        $dateFrom       = (string) ($_GET['date_from'] ?? '');
        $dateTo         = (string) ($_GET['date_to'] ?? '');
        $filters = [
            'salesman_id' => $salesmanFilter,
            'payout_mode' => $payoutMode,
            'status'      => $status,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
        ];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('commission-settlements/index', [
            'title'          => 'Commission Settlements',
            'settlements'    => $result['rows'],
            'outstanding'    => $this->repo->outstandingPerSalesman(),
            'salesmanFilter' => $salesmanFilter,
            'pg'             => $p->buildContext($result['total'], url('/commission-settlements'), $filters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        $salesmen = $this->salesmen->listActive();
        if ($salesmen === []) {
            Flash::warning('Create at least one active salesman first.');
            return redirect('/salesmen/create');
        }
        $banks   = $this->banks->listActive();
        $drawers = $this->drawers->listActive();
        if ($banks === [] && $drawers === []) {
            Flash::warning('Create a bank account or cashier drawer first.');
            return redirect('/cashier-drawers/create');
        }

        // Build a per-salesman map so the create form can show the linked-customer's
        // unpaid invoices and offer one-click "apply this payout to their bill" for cash mode.
        $linkedMap = [];
        foreach ($salesmen as $sm) {
            $full = $this->salesmen->find((int) $sm['id']);
            $linkedCustomerId = $full['linked_customer_id'] ?? null;
            if (!$linkedCustomerId) { continue; }
            $cust = $this->customers->find((int) $linkedCustomerId);
            if ($cust === null) { continue; }
            $linkedMap[(int) $sm['id']] = [
                'customer_id'    => (int) $cust['id'],
                'customer_name'  => (string) $cust['name'],
                'unpaid_invoices'=> $this->invoices->unpaidForCustomer((int) $cust['id']),
            ];
        }

        return view('commission-settlements/create', [
            'title'        => 'Settle Commission',
            'salesmen'     => $salesmen,
            'banks'        => $banks,
            'drawers'      => $drawers,
            'products'     => $this->productsForGoods(),
            'outstanding'  => $this->repo->outstandingPerSalesman(),
            'preSalesman'  => (int) ($_GET['salesman_id'] ?? 0),
            'linkedMap'    => $linkedMap,
        ]);
    }

    public function store(): string
    {
        // Convert "bank_account:5" / "cashier_drawer:2" combobox value to kind+id.
        $payoutMode = (string) ($_POST['payout_mode'] ?? '');
        if ($payoutMode === 'cash') {
            [$kind, $id] = $this->parseAccountChoice((string) ($_POST['paid_from'] ?? ''));
            $_POST['paid_from_kind'] = $kind;
            $_POST['paid_from_id']   = $id;
        }

        // Decide whether to chain a sales-payment posting after the settlement.
        $applyToBill = $payoutMode === 'cash'
            && (string) ($_POST['apply_to_bill'] ?? '') === '1'
            && (int) ($_POST['apply_to_invoice_id'] ?? 0) > 0;

        try {
            if ($applyToBill) {
                // Both must succeed atomically — settlement debits the drawer, payment credits
                // it back. Net cash effect = 0. Wrap in a single DB transaction.
                $newId = Db::transaction(function (): int {
                    $userId = (int) Auth::id();
                    $invoiceId = (int) $_POST['apply_to_invoice_id'];
                    $cashAmount = (float) ($_POST['cash_amount'] ?? 0);

                    // 1) Post the commission settlement (debits the drawer/bank).
                    $settlementId = $this->service->post($_POST, $userId);

                    // 2) Post a matching customer payment (credits the same drawer/bank).
                    $payment = [
                        'payment_date'        => (string) ($_POST['settlement_date'] ?? \date('Y-m-d')),
                        'cash_amount'         => $cashAmount,
                        'online_amount'       => 0.0,
                        'online_reference'    => null,
                        'cheque_amount'       => 0.0,
                        'cheque_number'       => null,
                        'cheque_date'         => null,
                        'cheque_bank_name'    => null,
                        'cheque_status'       => 'pending',
                        'received_into_kind'  => $_POST['paid_from_kind'] ?? null,
                        'received_into_id'    => (int) ($_POST['paid_from_id'] ?? 0),
                        'total_amount'        => \round($cashAmount, 2),
                        'remarks'             => 'Auto-applied from commission settlement #' . $settlementId,
                    ];
                    $this->paymentService->record($invoiceId, $payment, $userId);

                    return $settlementId;
                });

                Flash::success('Commission settled and applied to the customer bill in one go. Net cash movement is zero.');
                return redirect("/commission-settlements/{$newId}");
            }

            $newId = $this->service->post($_POST, (int) Auth::id());
            Flash::success('Commission settled. Salesman balance reduced.');
            return redirect("/commission-settlements/{$newId}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not settle: ' . $e->getMessage());
            return redirect('/commission-settlements/create');
        }
    }

    public function show(array $params): string
    {
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            Flash::error('Settlement not found.');
            return redirect('/commission-settlements');
        }
        return view('commission-settlements/show', [
            'title' => $row['settlement_number'],
            'row'   => $row,
        ]);
    }

    /** Admin only — gated at route layer. */
    public function void(array $params): string
    {
        $id     = (int) $params['id'];
        $reason = \trim((string) ($_POST['void_reason'] ?? '')) ?: 'No reason given';
        try {
            $this->service->void($id, $reason, (int) Auth::id());
            Flash::success('Settlement voided. Effects reversed.');
        } catch (\Throwable $e) {
            Flash::error('Void failed: ' . $e->getMessage());
        }
        return redirect("/commission-settlements/{$id}");
    }

    /** Active products that have stock (otherwise goods-mode would always fail). */
    private function productsForGoods(): array
    {
        $stmt = \App\Support\Db::pdo()->query(
            "SELECT p.id, p.name, p.unit, p.quantity_on_hand, p.moving_avg_cost,
                    b.name AS brand_name
             FROM products p
             JOIN brands b ON b.id = p.brand_id
             WHERE p.status = 'active' AND p.quantity_on_hand > 0
             ORDER BY p.name"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function parseAccountChoice(string $raw): array
    {
        if ($raw === '' || !\str_contains($raw, ':')) { return [null, 0]; }
        [$kind, $id] = \explode(':', $raw, 2);
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true)) { return [null, 0]; }
        return [$kind, (int) $id];
    }
}
