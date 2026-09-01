<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Repositories\SubcategoryRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class SubcategoryController
{
    public function __construct(
        private SubcategoryRepository $repo = new SubcategoryRepository(),
        private CategoryRepository    $categories = new CategoryRepository(),
    ) {}

    public function index(): string
    {
        return view('subcategories/index', [
            'title'         => 'Sub-Categories',
            'subcategories' => $this->repo->all(),
        ]);
    }

    public function create(): string
    {
        $categories = $this->categories->listActive();
        if ($categories === []) {
            Flash::warning('Create at least one active Category before adding Sub-Categories.');
            return redirect('/categories/create');
        }
        return view('subcategories/create', [
            'title'      => 'New Sub-Category',
            'categories' => $categories,
        ]);
    }

    public function store(): string
    {
        [$categoryId, $name, $description, $errorRedirect] =
            $this->validateInput($_POST, '/subcategories/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($categoryId, $name)) {
            flash_old($_POST);
            Flash::error("A sub-category named '{$name}' already exists in this category.");
            return redirect('/subcategories/create');
        }

        $this->repo->insert($categoryId, $name, $description, (int) Auth::id());
        Flash::success("Sub-Category '{$name}' created.");
        return redirect('/subcategories');
    }

    public function edit(array $params): string
    {
        $sub = $this->requireSubcategory((int) $params['id']);
        if (\is_string($sub)) {
            return $sub;
        }
        return view('subcategories/edit', [
            'title'        => 'Edit Sub-Category',
            'subcategory'  => $sub,
            'categories'   => $this->categories->listActiveOrIncluding((int) $sub['category_id']),
        ]);
    }

    public function update(array $params): string
    {
        $id  = (int) $params['id'];
        $sub = $this->requireSubcategory($id);
        if (\is_string($sub)) {
            return $sub;
        }

        [$categoryId, $name, $description, $errorRedirect] =
            $this->validateInput($_POST, "/subcategories/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($categoryId, $name, $id)) {
            flash_old($_POST);
            Flash::error("Another sub-category named '{$name}' already exists in this category.");
            return redirect("/subcategories/{$id}/edit");
        }

        $this->repo->update($id, $categoryId, $name, $description, (int) Auth::id());
        Flash::success("Sub-Category '{$name}' updated.");
        return redirect('/subcategories');
    }

    public function toggleStatus(array $params): string
    {
        $id  = (int) $params['id'];
        $sub = $this->requireSubcategory($id);
        if (\is_string($sub)) {
            return $sub;
        }
        $newStatus = $sub['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Sub-Category '{$sub['name']}' is now {$newStatus}.");
        return redirect('/subcategories');
    }

    /** @return array{0:int, 1:string, 2:?string, 3:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $categoryId  = (int) ($input['category_id'] ?? 0);
        $name        = \trim((string) ($input['name'] ?? ''));
        $description = \trim((string) ($input['description'] ?? ''));
        $description = $description === '' ? null : $description;

        $v = Validator::make([
                'category_id' => (string) $categoryId,
                'name'        => $name,
                'description' => $description ?? '',
            ])
            ->required('category_id', 'Category is required.')
            ->required('name')
            ->max('name', 150)
            ->max('description', 255);

        if ($v->passes() && $categoryId > 0) {
            $allowed = \array_column(
                $this->categories->listActiveOrIncluding(null),
                'id'
            );
            if (!\in_array($categoryId, $allowed, true)) {
                $v = Validator::make([])->required('category_id', 'Selected category is not active.');
            }
        }

        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$categoryId, $name, $description, redirect($redirectTo)];
        }
        return [$categoryId, $name, $description, null];
    }

    private function requireSubcategory(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Sub-category not found.');
            return redirect('/subcategories');
        }
        return $row;
    }
}
