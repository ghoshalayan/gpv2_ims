<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CustomerAdvanceRepository;
use App\Repositories\CustomerRepository;
use App\Services\CustomerAdvanceService;
use App\Support\Auth;
use App\Support\Flash;

class CustomerAdvanceController
{
    public function __construct(
        private CustomerAdvanceRepository $repo      = new CustomerAdvanceRepository(),
        private CustomerRepository        $customers = new CustomerRepository(),
        private BankAccountRepository     $banks     = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers   = new CashierDrawerRepository(),
        private CustomerAdvanceService    $service   = new CustomerAdvanceService(),
    ) {}

    public function index(): string
    {
        $p            = \App\Support\Paginator::fromQuery();
        $customerId   = (int) ($_GET['customer_id'] ?? 0) ?: null;
        $statusFilter = (string) ($_GET['status'] ?? '');
        $filters = ['customer_id' => $customerId, 'status' => $statusFilter];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('customer-advances/index', [
            'title'        => 'Customer Advances',
            'advances'     => $result['rows'],
            'customerId'   => $customerId,
            'statusFilter' => $statusFilter,
            'pg'           => $p->buildContext($result['total'], url('/customer-advances'),
                                ['customer_id' => $customerId, 'status' => $statusFilter, 'q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        $customers = $this->customers->listActive();
        if ($customers === []) {
            Flash::warning('Create at least one active customer first.');
            return redirect('/customers/create');
        }
        $banks   = $this->banks->listActive();
        $drawers = $this->drawers->listActive();
        if ($banks === [] && $drawers === []) {
            Flash::warning('Create a bank account or cashier drawer first.');
            return redirect('/cashier-drawers/create');
        }
        return view('customer-advances/create', [
            'title'      => 'Receive Customer Advance',
            'customers'  => $customers,
            'banks'      => $banks,
            'drawers'    => $drawers,
            'preCustomer'=> (int) ($_GET['customer_id'] ?? 0),
        ]);
    }

    public function store(): string
    {
        // Convert "bank_account:5" / "cashier_drawer:2" to kind+id.
        [$kind, $id] = $this->parseAccountChoice((string) ($_POST['received_into'] ?? ''));
        $_POST['received_into_kind'] = $kind;
        $_POST['received_into_id']   = $id;

        try {
            $newId = $this->service->receive($_POST, (int) Auth::id());
            Flash::success('Customer advance recorded. Receiving account credited.');
            return redirect("/customer-advances/{$newId}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not record: ' . $e->getMessage());
            return redirect('/customer-advances/create');
        }
    }

    public function show(array $params): string
    {
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            Flash::error('Advance not found.');
            return redirect('/customer-advances');
        }
        return view('customer-advances/show', [
            'title' => $row['advance_number'],
            'row'   => $row,
        ]);
    }

    /** Admin-only at route layer. Refunds the remaining balance. */
    public function refund(array $params): string
    {
        $id     = (int) $params['id'];
        $reason = \trim((string) ($_POST['refund_reason'] ?? '')) ?: 'No reason given';
        try {
            $this->service->refund($id, $reason, (int) Auth::id());
            Flash::success('Advance refunded. Receiving account debited by remaining balance.');
        } catch (\Throwable $e) {
            Flash::error('Refund failed: ' . $e->getMessage());
        }
        return redirect("/customer-advances/{$id}");
    }

    private function parseAccountChoice(string $raw): array
    {
        if ($raw === '' || !\str_contains($raw, ':')) { return [null, 0]; }
        [$kind, $id] = \explode(':', $raw, 2);
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true)) { return [null, 0]; }
        return [$kind, (int) $id];
    }
}
