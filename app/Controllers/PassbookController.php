<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PassbookRepository;
use App\Repositories\SalesmanRepository;
use App\Repositories\SupplierRepository;
use App\Support\Flash;
use App\Support\Whatsapp;

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
        private BusinessProfileRepository $business = new BusinessProfileRepository(),
    ) {}

    public function show(array $params): string
    {
        $data = $this->build($params);
        if (\is_string($data)) {
            return $data;
        }

        // CR-5: offer a WhatsApp share only where the entity has a usable number.
        $data['whatsappUrl'] = $this->whatsappUrl($data);

        return view('passbook/show', $data);
    }

    /**
     * CR-5 — printable statement. Rendered through layouts/print.php so the browser's
     * "Save as PDF" produces the file; no PDF library is involved.
     */
    public function printA4(array $params): string
    {
        $data = $this->build($params);
        if (\is_string($data)) {
            return $data;
        }

        $data['business']  = $this->business->get();
        $data['printedAt'] = \date('d M Y, g:i a');

        return view('passbook/print/a4', $data, 'print');
    }

    /**
     * Shared data build for the screen and the print view, so a printed statement can
     * never show different figures from the screen it was printed from.
     *
     * @return array<string, mixed>|string  view data, or a redirect string on failure
     */
    private function build(array $params): array|string
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

        return [
            'title'         => $context['title'],
            'kind'          => $kind,
            'context'       => $context,
            'rows'          => $rows,
            'totals'        => $this->totals($rows),
            'from'          => $from,
            'to'            => $to,
            'computedOpening' => $opening,
            'closingBalance'  => $this->closingBalance($rows, $opening),
            'totalLifetimeRows' => \count($allRows),
        ];
    }

    /**
     * Pre-filled WhatsApp message summarising the statement. Returns null when the
     * entity has no usable phone (bank accounts and drawers never do), so the view
     * simply omits the button rather than producing a broken link.
     */
    private function whatsappUrl(array $data): ?string
    {
        $context = $data['context'];
        $phone   = $context['phone'] ?? null;
        if ($phone === null || $phone === '') {
            return null;
        }

        // Show the balance the same way the screen does (payables are inverted).
        $display = !empty($context['invert_balance'])
            ? -$data['closingBalance']
            : $data['closingBalance'];

        $lines = [
            $context['entity_name'] ?? '',
            '',
            'Statement ' . $data['from'] . ' to ' . $data['to'],
            'Opening: ' . \number_format($data['computedOpening'], 2),
            'Debit: '   . \number_format((float) $data['totals']['debit'], 2),
            'Credit: '  . \number_format((float) $data['totals']['credit'], 2),
            ($context['balance_label'] ?? 'Balance') . ': ' . \number_format($display, 2),
        ];
        if (!empty($context['positive_means']) && \abs($display) > 0.004) {
            $lines[] = $display > 0
                ? '(' . $context['positive_means'] . ')'
                : '(advance / credit in their favour)';
        }
        $lines[] = '';
        $lines[] = \trim((string) ($this->business->get()['display_name'] ?? env('APP_NAME')));

        return Whatsapp::chatUrl((string) $phone, \implode("\n", $lines));
    }

    private function closingBalance(array $rows, float $opening): float
    {
        $balance = $opening;
        foreach ($rows as $r) {
            $balance += (float) $r['credit'] - (float) $r['debit'];
        }
        return $balance;
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
                    'entity_name'     => $row['name'],
                    'phone'           => $row['phone'] ?? null,     // CR-5: WhatsApp share
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
                    'entity_name'     => $row['name'],
                    'phone'           => $row['phone'] ?? null,
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
                    'entity_name'     => $row['name'],
                    'phone'           => $row['phone'] ?? null,
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
