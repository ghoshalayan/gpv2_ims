<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class CategoryController
{
    public function __construct(private CategoryRepository $repo = new CategoryRepository()) {}

    public function index(): string
    {
        return view('categories/index', [
            'title'      => 'Categories',
            'categories' => $this->repo->all(),
        ]);
    }

    public function create(): string
    {
        return view('categories/create', ['title' => 'New Category']);
    }

    public function store(): string
    {
        [$name, $description, $errorRedirect] = $this->validateInput($_POST, '/categories/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($name)) {
            flash_old($_POST);
            Flash::error("A category named '{$name}' already exists.");
            return redirect('/categories/create');
        }

        $this->repo->insert($name, $description, (int) Auth::id());
        Flash::success("Category '{$name}' created.");
        return redirect('/categories');
    }

    public function edit(array $params): string
    {
        $category = $this->requireCategory((int) $params['id']);
        if (\is_string($category)) {
            return $category;
        }
        return view('categories/edit', [
            'title'    => 'Edit Category',
            'category' => $category,
        ]);
    }

    public function update(array $params): string
    {
        $id = (int) $params['id'];
        $category = $this->requireCategory($id);
        if (\is_string($category)) {
            return $category;
        }

        [$name, $description, $errorRedirect] = $this->validateInput($_POST, "/categories/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($name, $id)) {
            flash_old($_POST);
            Flash::error("Another category named '{$name}' already exists.");
            return redirect("/categories/{$id}/edit");
        }

        $this->repo->update($id, $name, $description, (int) Auth::id());
        Flash::success("Category '{$name}' updated.");
        return redirect('/categories');
    }

    public function toggleStatus(array $params): string
    {
        $id = (int) $params['id'];
        $category = $this->requireCategory($id);
        if (\is_string($category)) {
            return $category;
        }
        $newStatus = $category['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Category '{$category['name']}' is now {$newStatus}.");
        return redirect('/categories');
    }

    /** @return array{0:string, 1:?string, 2:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $name = \trim((string) ($input['name'] ?? ''));
        $description = \trim((string) ($input['description'] ?? ''));
        $description = $description === '' ? null : $description;

        $v = Validator::make(['name' => $name, 'description' => $description ?? ''])
            ->required('name')
            ->max('name', 150)
            ->max('description', 255);

        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$name, $description, redirect($redirectTo)];
        }
        return [$name, $description, null];
    }

    /** Returns the row, or a redirect-string if not found. */
    private function requireCategory(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Category not found.');
            return redirect('/categories');
        }
        return $row;
    }
}
