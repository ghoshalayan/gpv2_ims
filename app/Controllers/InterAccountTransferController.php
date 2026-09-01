<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\InterAccountTransferRepository;
use App\Services\InterAccountTransferService;
use App\Support\Auth;
use App\Support\Flash;

class InterAccountTransferController
{
    public function __construct(
        private InterAccountTransferRepository $repo    = new InterAccountTransferRepository(),
        private BankAccountRepository          $banks   = new BankAccountRepository(),
        private CashierDrawerRepository        $drawers = new CashierDrawerRepository(),
        private InterAccountTransferService    $service = new InterAccountTransferService(),
    ) {}

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();
        $filters = [
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to'   => (string) ($_GET['date_to'] ?? ''),
        ];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('inter-account-transfers/index', [
            'title'     => 'Inter-account Transfers',
            'transfers' => $result['rows'],
            'pg'        => $p->buildContext($result['total'], url('/inter-account-transfers'), $filters + ['q' => $p->search]),
            'filters'   => $filters,
        ]);
    }

    public function create(): string
    {
        return view('inter-account-transfers/create', [
            'title'   => 'New Transfer',
            'banks'   => $this->banks->listActive(),
            'drawers' => $this->drawers->listActive(),
            'preFromKind' => (string) ($_GET['from_kind'] ?? ''),
            'preFromId'   => (int)    ($_GET['from_id']   ?? 0),
        ]);
    }

    public function store(): string
    {
        try {
            $id = $this->service->post($_POST, (int) Auth::id());
            Flash::success('Transfer posted. Balances updated.');
            return redirect("/inter-account-transfers/{$id}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Transfer failed: ' . $e->getMessage());
            return redirect('/inter-account-transfers/create');
        }
    }

    public function show(array $params): string
    {
        $transfer = $this->repo->find((int) $params['id']);
        if ($transfer === null) {
            Flash::error('Transfer not found.');
            return redirect('/inter-account-transfers');
        }
        return view('inter-account-transfers/show', [
            'title'    => $transfer['transfer_number'],
            'transfer' => $transfer,
        ]);
    }
}
