<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OperatingExpenseCategoryRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class OperatingExpenseCategoryController
{
    public function __construct(
        private OperatingExpenseCategoryRepository $repo = new OperatingExpenseCategoryRepository(),
    ) {}

    public function index(): string
    {
        return view('operating-expenses/categories/index', [
            'title'      => 'Expense Categories',
            'categories' => $this->repo->all(),
        ]);
    }

    public function create(): string
    {
        return view('operating-expenses/categories/create', ['title' => 'New Category']);
    }

    public function store(): string
    {
        [$name, $description, $errorRedirect] = $this->validateInput($_POST, '/operating-expense-categories/create');
        if ($errorRedirect !== null) { return $errorRedirect; }
        if ($this->repo->existsByName($name)) {
            flash_old($_POST);
            Flash::error("A category named '{$name}' already exists.");
            return redirect('/operating-expense-categories/create');
        }
        $this->repo->insert($name, $description, (int) Auth::id());
        Flash::success("Category '{$name}' created.");
        return redirect('/operating-expense-categories');
    }

    public function edit(array $params): string
    {
        $cat = $this->require((int) $params['id']);
        if (\is_string($cat)) { return $cat; }
        return view('operating-expenses/categories/edit', [
            'title'    => 'Edit Category',
            'category' => $cat,
        ]);
    }

    public function update(array $params): string
    {
        $id  = (int) $params['id'];
        $cat = $this->require($id);
        if (\is_string($cat)) { return $cat; }
        [$name, $description, $errorRedirect] = $this->validateInput($_POST, "/operating-expense-categories/{$id}/edit");
        if ($errorRedirect !== null) { return $errorRedirect; }
        if ($this->repo->existsByName($name, $id)) {
            flash_old($_POST);
            Flash::error("Another category named '{$name}' already exists.");
            return redirect("/operating-expense-categories/{$id}/edit");
        }
        $this->repo->update($id, $name, $description, (int) Auth::id());
        Flash::success("Category '{$name}' updated.");
        return redirect('/operating-expense-categories');
    }

    public function toggleStatus(array $params): string
    {
        $id  = (int) $params['id'];
        $cat = $this->require($id);
        if (\is_string($cat)) { return $cat; }
        $newStatus = $cat['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Category '{$cat['name']}' is now {$newStatus}.");
        return redirect('/operating-expense-categories');
    }

    /** @return array{0:string, 1:?string, 2:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $name = \trim((string) ($input['name'] ?? ''));
        $description = \trim((string) ($input['description'] ?? ''));
        $description = $description === '' ? null : $description;
        $v = Validator::make(['name' => $name, 'description' => $description ?? ''])
            ->required('name')
            ->max('name', 100)
            ->max('description', 255);
        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$name, $description, redirect($redirectTo)];
        }
        return [$name, $description, null];
    }

    private function require(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Category not found.');
            return redirect('/operating-expense-categories');
        }
        return $row;
    }
}
