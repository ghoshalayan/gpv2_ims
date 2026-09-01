<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\SupplierAdvanceRepository;
use App\Repositories\SupplierRepository;
use App\Services\SupplierAdvanceService;
use App\Support\Auth;
use App\Support\Flash;

class SupplierAdvanceController
{
    public function __construct(
        private SupplierAdvanceRepository $repo      = new SupplierAdvanceRepository(),
        private SupplierRepository        $suppliers = new SupplierRepository(),
        private BankAccountRepository     $banks     = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers   = new CashierDrawerRepository(),
        private SupplierAdvanceService    $service   = new SupplierAdvanceService(),
    ) {}

    public function index(): string
    {
        $p            = \App\Support\Paginator::fromQuery();
        $supplierId   = (int) ($_GET['supplier_id'] ?? 0) ?: null;
        $statusFilter = (string) ($_GET['status'] ?? '');
        $filters = ['supplier_id' => $supplierId, 'status' => $statusFilter];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('supplier-advances/index', [
            'title'        => 'Supplier Advances',
            'advances'     => $result['rows'],
            'supplierId'   => $supplierId,
            'statusFilter' => $statusFilter,
            'pg'           => $p->buildContext($result['total'], url('/supplier-advances'),
                                ['supplier_id' => $supplierId, 'status' => $statusFilter, 'q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        $suppliers = $this->suppliers->listActive();
        if ($suppliers === []) {
            Flash::warning('Create at least one active supplier first.');
            return redirect('/suppliers/create');
        }
        $banks   = $this->banks->listActive();
        $drawers = $this->drawers->listActive();
        if ($banks === [] && $drawers === []) {
            Flash::warning('Create a bank account or cashier drawer first.');
            return redirect('/cashier-drawers/create');
        }
        return view('supplier-advances/create', [
            'title'      => 'Pay Supplier Advance',
            'suppliers'  => $suppliers,
            'banks'      => $banks,
            'drawers'    => $drawers,
            'preSupplier'=> (int) ($_GET['supplier_id'] ?? 0),
        ]);
    }

    public function store(): string
    {
        [$kind, $id] = $this->parseAccountChoice((string) ($_POST['paid_from'] ?? ''));
        $_POST['paid_from_kind'] = $kind;
        $_POST['paid_from_id']   = $id;
        try {
            $newId = $this->service->pay($_POST, (int) Auth::id());
            Flash::success('Advance paid to supplier. Paying account debited.');
            return redirect("/supplier-advances/{$newId}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not record: ' . $e->getMessage());
            return redirect('/supplier-advances/create');
        }
    }

    public function show(array $params): string
    {
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            Flash::error('Advance not found.');
            return redirect('/supplier-advances');
        }
        return view('supplier-advances/show', [
            'title' => $row['advance_number'],
            'row'   => $row,
        ]);
    }

    /** Admin-only at route layer. Refunds the remaining balance — supplier returns money to us. */
    public function refund(array $params): string
    {
        $id     = (int) $params['id'];
        $reason = \trim((string) ($_POST['refund_reason'] ?? '')) ?: 'No reason given';
        try {
            $this->service->refund($id, $reason, (int) Auth::id());
            Flash::success('Advance refunded. Paying account credited back by remaining balance.');
        } catch (\Throwable $e) {
            Flash::error('Refund failed: ' . $e->getMessage());
        }
        return redirect("/supplier-advances/{$id}");
    }

    private function parseAccountChoice(string $raw): array
    {
        if ($raw === '' || !\str_contains($raw, ':')) { return [null, 0]; }
        [$kind, $id] = \explode(':', $raw, 2);
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true)) { return [null, 0]; }
        return [$kind, (int) $id];
    }
}
