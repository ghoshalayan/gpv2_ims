<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BrandRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class BrandController
{
    public function __construct(private BrandRepository $repo = new BrandRepository()) {}

    public function index(): string
    {
        return view('brands/index', [
            'title'  => 'Brands',
            'brands' => $this->repo->all(),
            'systemBrand' => BrandRepository::SYSTEM_BRAND,
        ]);
    }

    public function create(): string
    {
        return view('brands/create', ['title' => 'New Brand']);
    }

    public function store(): string
    {
        [$name, $description, $errorRedirect] = $this->validateInput($_POST, '/brands/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($name)) {
            flash_old($_POST);
            Flash::error("A brand named '{$name}' already exists.");
            return redirect('/brands/create');
        }

        $this->repo->insert($name, $description, (int) Auth::id());
        Flash::success("Brand '{$name}' created.");
        return redirect('/brands');
    }

    public function edit(array $params): string
    {
        $brand = $this->requireBrand((int) $params['id']);
        if (\is_string($brand)) {
            return $brand;
        }
        return view('brands/edit', [
            'title' => 'Edit Brand',
            'brand' => $brand,
            'isSystem' => $brand['name'] === BrandRepository::SYSTEM_BRAND,
        ]);
    }

    public function update(array $params): string
    {
        $id    = (int) $params['id'];
        $brand = $this->requireBrand($id);
        if (\is_string($brand)) {
            return $brand;
        }

        [$name, $description, $errorRedirect] = $this->validateInput($_POST, "/brands/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        // Protect the system brand: name cannot change.
        if ($brand['name'] === BrandRepository::SYSTEM_BRAND && $name !== BrandRepository::SYSTEM_BRAND) {
            Flash::error("The system brand '" . BrandRepository::SYSTEM_BRAND . "' cannot be renamed.");
            return redirect("/brands/{$id}/edit");
        }

        if ($this->repo->existsByName($name, $id)) {
            flash_old($_POST);
            Flash::error("Another brand named '{$name}' already exists.");
            return redirect("/brands/{$id}/edit");
        }

        $this->repo->update($id, $name, $description, (int) Auth::id());
        Flash::success("Brand '{$name}' updated.");
        return redirect('/brands');
    }

    public function toggleStatus(array $params): string
    {
        $id    = (int) $params['id'];
        $brand = $this->requireBrand($id);
        if (\is_string($brand)) {
            return $brand;
        }
        if ($brand['name'] === BrandRepository::SYSTEM_BRAND) {
            Flash::error("The system brand '" . BrandRepository::SYSTEM_BRAND . "' cannot be disabled.");
            return redirect('/brands');
        }
        $newStatus = $brand['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Brand '{$brand['name']}' is now {$newStatus}.");
        return redirect('/brands');
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

    private function requireBrand(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Brand not found.');
            return redirect('/brands');
        }
        return $row;
    }
}
