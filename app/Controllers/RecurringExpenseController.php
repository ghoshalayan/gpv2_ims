<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankAccountRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\OperatingExpenseCategoryRepository;
use App\Repositories\RecurringExpenseRepository;
use App\Services\RecurringExpenseService;
use App\Support\Auth;
use App\Support\Flash;

class RecurringExpenseController
{
    public function __construct(
        private RecurringExpenseRepository           $repo       = new RecurringExpenseRepository(),
        private OperatingExpenseCategoryRepository   $categories = new OperatingExpenseCategoryRepository(),
        private BankAccountRepository                $banks      = new BankAccountRepository(),
        private CashierDrawerRepository              $drawers    = new CashierDrawerRepository(),
        private RecurringExpenseService              $service    = new RecurringExpenseService(),
    ) {}

    public function index(): string
    {
        return view('recurring-expenses/index', [
            'title'      => 'Recurring Expenses',
            'recurrings' => $this->repo->all(),
            'today'      => \date('Y-m-d'),
        ]);
    }

    public function create(): string
    {
        return view('recurring-expenses/create', [
            'title'      => 'New Recurring Expense',
            'categories' => $this->categories->listActive(),
            'banks'      => $this->banks->listActive(),
            'drawers'    => $this->drawers->listActive(),
        ]);
    }

    public function store(): string
    {
        try {
            $id = $this->service->create($_POST, (int) Auth::id());
            Flash::success('Recurring expense template created.');
            return redirect("/recurring-expenses/{$id}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Could not create: ' . $e->getMessage());
            return redirect('/recurring-expenses/create');
        }
    }

    public function show(array $params): string
    {
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            Flash::error('Recurring template not found.');
            return redirect('/recurring-expenses');
        }
        return view('recurring-expenses/show', [
            'title'    => $row['name'],
            'row'      => $row,
            'spawned'  => $this->repo->spawnedExpenses((int) $row['id']),
        ]);
    }

    public function edit(array $params): string
    {
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            Flash::error('Recurring template not found.');
            return redirect('/recurring-expenses');
        }
        return view('recurring-expenses/edit', [
            'title'      => 'Edit Recurring',
            'row'        => $row,
            'categories' => $this->categories->all(),
            'banks'      => $this->banks->listActive(),
            'drawers'    => $this->drawers->listActive(),
        ]);
    }

    public function update(array $params): string
    {
        $id = (int) $params['id'];
        try {
            $this->service->update($id, $_POST, (int) Auth::id());
            Flash::success('Recurring template updated.');
            return redirect("/recurring-expenses/{$id}");
        } catch (\Throwable $e) {
            flash_old($_POST);
            Flash::error('Update failed: ' . $e->getMessage());
            return redirect("/recurring-expenses/{$id}/edit");
        }
    }

    public function pauseResume(array $params): string
    {
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Recurring template not found.');
            return redirect('/recurring-expenses');
        }
        $newStatus = $row['status'] === 'active' ? 'paused' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Template '{$row['name']}' is now {$newStatus}.");
        return redirect("/recurring-expenses/{$id}");
    }

    public function delete(array $params): string
    {
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Recurring template not found.');
            return redirect('/recurring-expenses');
        }
        $this->repo->delete($id);
        Flash::success("Template '{$row['name']}' deleted. Past spawned expenses are preserved.");
        return redirect('/recurring-expenses');
    }

    /** Fire all due templates as of today. */
    public function runDue(): string
    {
        $summary = $this->service->fireDue(\date('Y-m-d'), (int) Auth::id());
        if ($summary['fired'] === 0 && $summary['paused'] === 0 && $summary['errors'] === []) {
            Flash::warning('Nothing due today.');
        } else {
            $msg = "Fired {$summary['fired']} expense(s)";
            if ($summary['paused'] > 0)         { $msg .= ", auto-paused {$summary['paused']} (end-date reached)"; }
            if ($summary['errors'] !== [])      { $msg .= '. Errors: ' . \implode(' | ', $summary['errors']); }
            $summary['errors'] === [] ? Flash::success($msg . '.') : Flash::error($msg . '.');
        }
        return redirect('/recurring-expenses');
    }

    /** Fire just one template (for the "Run Now" button on the show page). */
    public function runOne(array $params): string
    {
        $id = (int) $params['id'];
        try {
            $this->service->fireOne($id, \date('Y-m-d'), (int) Auth::id());
            Flash::success('Template fired. New expense posted.');
        } catch (\Throwable $e) {
            Flash::error('Run failed: ' . $e->getMessage());
        }
        return redirect("/recurring-expenses/{$id}");
    }
}
