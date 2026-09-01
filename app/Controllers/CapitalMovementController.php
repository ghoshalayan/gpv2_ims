<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CapitalMovementRepository;
use App\Repositories\CashierDrawerRepository;
use App\Services\CapitalMovementService;
use App\Support\Auth;
use App\Support\Flash;

class CapitalMovementController
{
    public function __construct(
        private CapitalMovementRepository $repo    = new CapitalMovementRepository(),
        private BankAccountRepository     $banks   = new BankAccountRepository(),
        private CashierDrawerRepository   $drawers = new CashierDrawerRepository(),
        private CapitalMovementService    $service = new CapitalMovementService(),
    ) {}

    public function index(): string
    {
        $p      = \App\Support\Paginator::fromQuery();
        $filter = (string) ($_GET['type'] ?? $_GET['txn_type'] ?? '');
        $allowed = ['', 'capital_in', 'drawing', 'loan_in', 'loan_repay', 'other'];
        if (!\in_array($filter, $allowed, true)) { $filter = ''; }
        $direction = (string) ($_GET['direction'] ?? '');
        $status    = (string) ($_GET['status'] ?? '');
        $dateFrom  = (string) ($_GET['date_from'] ?? '');
        $dateTo    = (string) ($_GET['date_to'] ?? '');
        $filters = [
            'txn_type'  => $filter,
            'direction' => $direction,
            'status'    => $status,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('capital-movements/index', [
            'title'         => 'Capital & Drawings',
            'movements'     => $result['rows'],
            'filter'        => $filter,
            'loans'         => $this->repo->outstandingLoans(),
            'ownerSummary'  => $this->repo->ownerCapitalSummary(),
            'pg'            => $p->buildContext($result['total'], url('/capital-movements'),
                                ['type' => $filter, 'direction' => $direction, 'status' => $status,
                                 'date_from' => $dateFrom, 'date_to' => $dateTo, 'q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        $banks   = $this->banks->listActive();
        $drawers = $this->drawers->listActive();
        if ($banks === [] && $drawers === []) {
            Flash::warning('Create a bank account or cashier drawer first.');
            return redirect('/cashier-drawers/create');
        }
        return view('capital-movements/create', [
            'title'   => 'New Capital Movement',
            'banks'   => $banks,
            'drawers' => $drawers,
            'preType' => (string) ($_GET['type'] ?? ''),
        ]);
    }

    public function store(): string
    {
        $accountRaw = (string) ($_POST['account'] ?? '');
        [$accountKind, $accountId] = $this->parseAccountChoice($accountRaw);
        $_POST['account_kind'] = $accountKind;
        $_POST['account_id']   = $accountId;
        try {
            $id = $this->service->post($_POST, (int) Auth::id());
            Flash::success('Capital movement posted. Account balance updated.');
            return redirect("/capital-movements/{$id}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not post: ' . $e->getMessage());
            return redirect('/capital-movements/create');
        }
    }

    public function show(array $params): string
    {
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            Flash::error('Movement not found.');
            return redirect('/capital-movements');
        }
        return view('capital-movements/show', [
            'title' => $row['movement_number'],
            'row'   => $row,
        ]);
    }

    /** Admin only — gated at the route layer. */
    public function void(array $params): string
    {
        $id     = (int) $params['id'];
        $reason = \trim((string) ($_POST['void_reason'] ?? '')) ?: 'No reason given';
        try {
            $this->service->void($id, $reason, (int) Auth::id());
            Flash::success('Movement voided. Account balance restored.');
        } catch (\Throwable $e) {
            Flash::error('Void failed: ' . $e->getMessage());
        }
        return redirect("/capital-movements/{$id}");
    }

    private function parseAccountChoice(string $raw): array
    {
        if ($raw === '' || !\str_contains($raw, ':')) { return [null, 0]; }
        [$kind, $id] = \explode(':', $raw, 2);
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true)) { return [null, 0]; }
        return [$kind, (int) $id];
    }
}
