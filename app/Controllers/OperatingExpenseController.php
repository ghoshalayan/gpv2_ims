<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\OperatingExpenseCategoryRepository;
use App\Repositories\OperatingExpenseRepository;
use App\Services\OperatingExpenseService;
use App\Support\Auth;
use App\Support\Flash;

class OperatingExpenseController
{
    public function __construct(
        private OperatingExpenseRepository           $repo       = new OperatingExpenseRepository(),
        private OperatingExpenseCategoryRepository   $categories = new OperatingExpenseCategoryRepository(),
        private BankAccountRepository                $banks      = new BankAccountRepository(),
        private CashierDrawerRepository              $drawers    = new CashierDrawerRepository(),
        private BusinessProfileRepository            $business   = new BusinessProfileRepository(),
        private OperatingExpenseService              $service    = new OperatingExpenseService(),
    ) {}

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();
        $filters = [
            'category_id'    => (int) ($_GET['category_id'] ?? 0) ?: null,
            'status'         => (string) ($_GET['status'] ?? ''),
            'paid_from_kind' => (string) ($_GET['paid_from_kind'] ?? ''),
            'paid_from_id'   => (int) ($_GET['paid_from_id'] ?? 0) ?: null,
            'date_from'      => (string) ($_GET['date_from'] ?? ''),
            'date_to'        => (string) ($_GET['date_to'] ?? ''),
        ];
        $result  = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        $summary = $this->repo->summaryByCategory();
        return view('operating-expenses/index', [
            'title'    => 'Operating Expenses',
            'expenses' => $result['rows'],
            'summary'  => $summary,
            'pg'       => $p->buildContext($result['total'], url('/operating-expenses'), $filters + ['q' => $p->search]),
            'filters'  => $filters,
        ]);
    }

    public function create(): string
    {
        $categories = $this->categories->listActive();
        if ($categories === []) {
            Flash::warning('Create at least one expense category before posting an expense.');
            return redirect('/operating-expense-categories/create');
        }
        $banks   = $this->banks->listActive();
        $drawers = $this->drawers->listActive();
        if ($banks === [] && $drawers === []) {
            Flash::warning('Create a bank account or cashier drawer before posting an expense.');
            return redirect('/cashier-drawers/create');
        }
        return view('operating-expenses/create', [
            'title'      => 'New Expense',
            'categories' => $categories,
            'banks'      => $banks,
            'drawers'    => $drawers,
        ]);
    }

    public function store(): string
    {
        try {
            $id = $this->service->post($_POST, (int) Auth::id());
            Flash::success('Expense posted. Paying account debited.');
            return redirect("/operating-expenses/{$id}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not post expense: ' . $e->getMessage());
            return redirect('/operating-expenses/create');
        }
    }

    public function show(array $params): string
    {
        $expense = $this->repo->find((int) $params['id']);
        if ($expense === null) {
            Flash::error('Expense not found.');
            return redirect('/operating-expenses');
        }
        return view('operating-expenses/show', [
            'title'   => $expense['expense_number'],
            'expense' => $expense,
        ]);
    }

    public function edit(array $params): string
    {
        $expense = $this->repo->find((int) $params['id']);
        if ($expense === null) {
            Flash::error('Expense not found.');
            return redirect('/operating-expenses');
        }
        if ($expense['status'] !== 'posted') {
            Flash::error("Expense is {$expense['status']} — cannot edit.");
            return redirect('/operating-expenses/' . $expense['id']);
        }
        return view('operating-expenses/edit', [
            'title'      => 'Edit ' . $expense['expense_number'],
            'expense'    => $expense,
            'categories' => $this->categories->all(),
            'banks'      => $this->banks->listActive(),
            'drawers'    => $this->drawers->listActive(),
        ]);
    }

    public function update(array $params): string
    {
        $id = (int) $params['id'];
        try {
            $this->service->editPost($id, $_POST, (int) Auth::id());
            Flash::success('Expense updated. Account balances rebalanced atomically.');
            return redirect("/operating-expenses/{$id}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Update failed: ' . $e->getMessage());
            return redirect("/operating-expenses/{$id}/edit");
        }
    }

    public function printReceipt(array $params): string
    {
        $expense = $this->repo->find((int) $params['id']);
        if ($expense === null) {
            Flash::error('Expense not found.');
            return redirect('/operating-expenses');
        }
        return view('operating-expenses/print/receipt', [
            'title'    => "Voucher {$expense['expense_number']}",
            'expense'  => $expense,
            'business' => $this->business->get(),
        ], 'print');
    }

    /** Admin only — gated at the route layer. Posts a reverse balance delta + marks voided. */
    public function void(array $params): string
    {
        $id     = (int) $params['id'];
        $reason = \trim((string) ($_POST['void_reason'] ?? '')) ?: 'No reason given';
        try {
            $this->service->void($id, $reason, (int) Auth::id());
            Flash::success('Expense voided. Paying account credited back.');
        } catch (\Throwable $e) {
            Flash::error('Void failed: ' . $e->getMessage());
        }
        return redirect("/operating-expenses/{$id}");
    }
}
