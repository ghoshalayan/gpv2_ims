<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PassbookRepository;
use App\Repositories\SalesmanRepository;
use App\Repositories\SupplierRepository;
use App\Support\Flash;

/**
 * Universal Passbook (#24, D40 / D50) — single endpoint that renders any entity's
 * chronological ledger with running balance.
 *
 *   /passbook/{kind}/{id}
 *
 * kind ∈ {customer, supplier, bank-account, cashier-drawer, salesman}
 *
 * v1 covers transactional entities. Owner / lender summaries already live on the
 * Capital Movements index (grouped by counterparty_name).
 */
class PassbookController
{
    public function __construct(
        private PassbookRepository       $repo      = new PassbookRepository(),
        private CustomerRepository       $customers = new CustomerRepository(),
        private SupplierRepository       $suppliers = new SupplierRepository(),
        private SalesmanRepository       $salesmen  = new SalesmanRepository(),
        private BankAccountRepository    $banks     = new BankAccountRepository(),
        private CashierDrawerRepository  $drawers   = new CashierDrawerRepository(),
    ) {}

    public function show(array $params): string
    {
        $kind = (string) $params['kind'];
        $id   = (int) $params['id'];

        $context = $this->resolveContext($kind, $id);
        if ($context === null) {
            Flash::error('Passbook entity not found.');
            return redirect('/dashboard');
        }
        $context['id'] = $id;

        // Date range bounds the running balance — defaults to current FY so a
        // long-lived entity's passbook stays fast even with thousands of events.
        $today = \date('Y-m-d');
        $defaultFrom = \date('Y-m-d', \strtotime('first day of January')) ?: '2000-01-01';
        $from = (string) ($_GET['from'] ?? $defaultFrom);
        $to   = (string) ($_GET['to']   ?? $today);
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = $defaultFrom; }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = $today; }

        $allRows = $this->loadRows($kind, $id);

        // Opening balance = baseline + everything BEFORE $from (so the running balance
        // stays meaningful even though we're showing only the period rows).
        $opening = (float) $context['opening_balance'];
        $periodRows = [];
        foreach ($allRows as $r) {
            $d = (string) $r['txn_date'];
            if ($d < $from) {
                $opening += (float) $r['credit'] - (float) $r['debit'];
            } elseif ($d <= $to) {
                $periodRows[] = $r;
            }
        }
        $rows = $this->withRunningBalance($periodRows, $opening);

        return view('passbook/show', [
            'title'         => $context['title'],
            'kind'          => $kind,
            'context'       => $context,
            'rows'          => $rows,
            'totals'        => $this->totals($rows),
            'from'          => $from,
            'to'            => $to,
            'computedOpening' => $opening,
            'totalLifetimeRows' => \count($allRows),
        ]);
    }

    /**
     * Build a small descriptor per entity kind: title, subtitle, opening_balance,
     * back-link to the entity's own page, sign convention labels.
     */
    private function resolveContext(string $kind, int $id): ?array
    {
        switch ($kind) {
            case 'customer':
                $row = $this->customers->find($id);
                if ($row === null) return null;
                return [
                    'title'           => 'Customer Passbook · ' . $row['name'],
                    'subtitle'        => 'Outstanding = (sales) − (payments + returns)',
                    'opening_balance' => 0.0,                       // legacy old_record balance not yet wired
                    'back_url'        => '/customers/' . $id . '/edit',
                    'back_label'      => 'Customer details',
                    'debit_label'     => 'Sale (owes us)',
                    'credit_label'    => 'Payment / Credit',
                    'balance_label'   => 'Net receivable',
                    'positive_means'  => 'customer owes us',
                ];
            case 'supplier':
                $row = $this->suppliers->find($id);
                if ($row === null) return null;
                return [
                    'title'           => 'Supplier Passbook · ' . $row['name'],
                    'subtitle'        => 'Outstanding = (purchases) − (payments + returns)',
                    'opening_balance' => 0.0,
                    'back_url'        => '/suppliers/' . $id . '/edit',
                    'back_label'      => 'Supplier details',
                    'debit_label'     => 'Payment / Debit',
                    'credit_label'    => 'Purchase (we owe)',
                    'balance_label'   => 'Net payable',
                    'positive_means'  => 'we owe supplier',
                    'invert_balance'  => true,                       // payable: credits − debits
                ];
            case 'bank-account':
                $row = $this->banks->find($id);
                if ($row === null) return null;
                return [
                    'title'           => 'Bank Passbook · ' . $row['name'],
                    'subtitle'        => 'Running balance = opening + (credits − debits)',
                    'opening_balance' => (float) $row['opening_balance'],
                    'current_balance' => (float) $row['current_balance'],
                    'back_url'        => '/bank-accounts/' . $id,
                    'back_label'      => 'Bank account',
                    'debit_label'     => 'Out (−)',
                    'credit_label'    => 'In (+)',
                    'balance_label'   => 'Balance',
                ];
            case 'cashier-drawer':
                $row = $this->drawers->find($id);
                if ($row === null) return null;
                return [
                    'title'           => 'Drawer Passbook · ' . $row['name'],
                    'subtitle'        => 'Running balance = opening + (credits − debits)',
                    'opening_balance' => (float) $row['opening_balance'],
                    'current_balance' => (float) $row['current_balance'],
                    'back_url'        => '/cashier-drawers/' . $id,
                    'back_label'      => 'Drawer',
                    'debit_label'     => 'Out (−)',
                    'credit_label'    => 'In (+)',
                    'balance_label'   => 'Balance',
                ];
            case 'salesman':
                $row = $this->salesmen->find($id);
                if ($row === null) return null;
                return [
                    'title'           => 'Salesman Passbook · ' . $row['name'],
                    'subtitle'        => 'Commission earned − reversals (settlements come later in #22)',
                    'opening_balance' => 0.0,
                    'back_url'        => '/salesmen/' . $id . '/edit',
                    'back_label'      => 'Salesman details',
                    'debit_label'     => 'Reversed',
                    'credit_label'    => 'Earned',
                    'balance_label'   => 'Net commission outstanding',
                ];
        }
        return null;
    }

    private function loadRows(string $kind, int $id): array
    {
        return match ($kind) {
            'customer'        => $this->repo->customerLedger($id),
            'supplier'        => $this->repo->supplierLedger($id),
            'bank-account'    => $this->repo->accountLedger('bank_account', $id),
            'cashier-drawer'  => $this->repo->accountLedger('cashier_drawer', $id),
            'salesman'        => $this->repo->salesmanLedger($id),
            default           => [],
        };
    }

    /** Annotate each row with a running balance based on the opening. */
    private function withRunningBalance(array $rows, float $opening): array
    {
        $balance = $opening;
        $out = [];
        foreach ($rows as $r) {
            $balance += (float) $r['credit'] - (float) $r['debit'];
            $r['running_balance'] = $balance;
            $out[] = $r;
        }
        return $out;
    }

    private function totals(array $rows): array
    {
        $debit = 0.0; $credit = 0.0;
        foreach ($rows as $r) {
            $debit  += (float) $r['debit'];
            $credit += (float) $r['credit'];
        }
        return ['debit' => $debit, 'credit' => $credit, 'net' => $credit - $debit];
    }
}
