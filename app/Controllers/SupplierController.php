<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\LegacyObligationRepository;
use App\Repositories\SupplierRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\IndianStates;
use App\Support\Validator;

class SupplierController
{
    public function __construct(
        private SupplierRepository         $repo        = new SupplierRepository(),
        private LegacyObligationRepository $legacyOblig = new LegacyObligationRepository(),
    ) {}

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();
        $filters = ['status' => (string) ($_GET['status'] ?? '')];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('suppliers/index', [
            'title'     => 'Suppliers',
            'suppliers' => $result['rows'],
            'filters'   => $filters,
            'pg'        => $p->buildContext($result['total'], url('/suppliers'), $filters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        return view('suppliers/create', [
            'title'  => 'New Supplier',
            'states' => IndianStates::LIST,
        ]);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateInput($_POST, '/suppliers/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'])) {
            flash_old($_POST);
            Flash::error("A supplier named '{$data['name']}' already exists.");
            return redirect('/suppliers/create');
        }
        if ($this->repo->existsByPhone($data['phone'])) {
            flash_old($_POST);
            Flash::error("Phone number '{$data['phone']}' is already used by another supplier.");
            return redirect('/suppliers/create');
        }

        $this->repo->insert($data, (int) Auth::id());
        Flash::success("Supplier '{$data['name']}' created.");
        return redirect('/suppliers');
    }

    public function edit(array $params): string
    {
        $supplier = $this->requireSupplier((int) $params['id']);
        if (\is_string($supplier)) {
            return $supplier;
        }
        $entityId = (int) $supplier['id'];
        return view('suppliers/edit', [
            'title'            => 'Edit Supplier',
            'supplier'         => $supplier,
            'states'           => IndianStates::LIST,
            'oldRecordsRollup' => $this->legacyOblig->rollupForEntity('supplier', $entityId),
        ]);
    }

    public function update(array $params): string
    {
        $id       = (int) $params['id'];
        $supplier = $this->requireSupplier($id);
        if (\is_string($supplier)) {
            return $supplier;
        }

        [$data, $errorRedirect] = $this->validateInput($_POST, "/suppliers/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'], $id)) {
            flash_old($_POST);
            Flash::error("Another supplier named '{$data['name']}' already exists.");
            return redirect("/suppliers/{$id}/edit");
        }
        if ($this->repo->existsByPhone($data['phone'], $id)) {
            flash_old($_POST);
            Flash::error("Phone number '{$data['phone']}' is already used by another supplier.");
            return redirect("/suppliers/{$id}/edit");
        }

        $this->repo->update($id, $data, (int) Auth::id());
        Flash::success("Supplier '{$data['name']}' updated.");
        return redirect('/suppliers');
    }

    public function toggleStatus(array $params): string
    {
        $id       = (int) $params['id'];
        $supplier = $this->requireSupplier($id);
        if (\is_string($supplier)) {
            return $supplier;
        }
        $newStatus = $supplier['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Supplier '{$supplier['name']}' is now {$newStatus}.");
        return redirect('/suppliers');
    }

    /** @return array{0:array, 1:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $data = [
            'name'    => \trim((string) ($input['name']    ?? '')),
            'phone'   => \trim((string) ($input['phone']   ?? '')),
            'email'   => \trim((string) ($input['email']   ?? '')) ?: null,
            'address' => \trim((string) ($input['address'] ?? '')) ?: null,
            'gstin'   => \strtoupper(\trim((string) ($input['gstin'] ?? ''))) ?: null,
            'state'   => \trim((string) ($input['state']   ?? '')) ?: null,
            'remarks' => \trim((string) ($input['remarks'] ?? '')) ?: null,
        ];

        $v = Validator::make([
                'name'  => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? '',
                'gstin' => $data['gstin'] ?? '',
            ])
            ->required('name')
            ->max('name', 150)
            ->required('phone')
            ->max('phone', 20)
            ->phone('phone')
            ->max('email', 150)
            ->gstin('gstin');

        if ($data['email'] !== null && !\filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            flash_old($input);
            Flash::error('Email must be a valid address.');
            return [$data, redirect($redirectTo)];
        }
        if ($data['state'] !== null && !IndianStates::isValid($data['state'])) {
            flash_old($input);
            Flash::error('State must be chosen from the list.');
            return [$data, redirect($redirectTo)];
        }

        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, redirect($redirectTo)];
        }
        return [$data, null];
    }

    private function requireSupplier(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Supplier not found.');
            return redirect('/suppliers');
        }
        return $row;
    }
}
