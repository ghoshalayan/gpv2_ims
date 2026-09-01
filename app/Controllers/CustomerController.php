<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CustomerRepository;
use App\Repositories\LegacyObligationRepository;
use App\Repositories\SalesmanRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\IndianStates;
use App\Support\Validator;

class CustomerController
{
    public function __construct(
        private CustomerRepository         $repo            = new CustomerRepository(),
        private SalesmanRepository         $salesmen        = new SalesmanRepository(),
        private LegacyObligationRepository $legacyOblig = new LegacyObligationRepository(),
    ) {}

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();
        $filters = ['status' => (string) ($_GET['status'] ?? '')];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('customers/index', [
            'title'     => 'Customers',
            'customers' => $result['rows'],
            'filters'   => $filters,
            'pg'        => $p->buildContext($result['total'], url('/customers'), $filters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        return view('customers/create', [
            'title'    => 'New Customer',
            'states'   => IndianStates::LIST,
            'salesmen' => $this->salesmen->listActive(),
        ]);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateInput($_POST, '/customers/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'])) {
            flash_old($_POST);
            Flash::error("A customer named '{$data['name']}' already exists.");
            return redirect('/customers/create');
        }
        if ($this->repo->existsByPhone($data['phone'])) {
            flash_old($_POST);
            Flash::error("Phone number '{$data['phone']}' is already used by another customer.");
            return redirect('/customers/create');
        }

        $this->repo->insert($data, (int) Auth::id());
        Flash::success("Customer '{$data['name']}' created.");
        return redirect('/customers');
    }

    public function edit(array $params): string
    {
        $customer = $this->requireCustomer((int) $params['id']);
        if (\is_string($customer)) {
            return $customer;
        }
        $entityId = (int) $customer['id'];
        return view('customers/edit', [
            'title'           => 'Edit Customer',
            'customer'        => $customer,
            'states'          => IndianStates::LIST,
            'salesmen'        => $this->salesmen->listActive(),
            'oldRecordsRollup' => $this->legacyOblig->rollupForEntity('customer', $entityId),
        ]);
    }

    public function update(array $params): string
    {
        $id       = (int) $params['id'];
        $customer = $this->requireCustomer($id);
        if (\is_string($customer)) {
            return $customer;
        }

        [$data, $errorRedirect] = $this->validateInput($_POST, "/customers/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'], $id)) {
            flash_old($_POST);
            Flash::error("Another customer named '{$data['name']}' already exists.");
            return redirect("/customers/{$id}/edit");
        }
        if ($this->repo->existsByPhone($data['phone'], $id)) {
            flash_old($_POST);
            Flash::error("Phone number '{$data['phone']}' is already used by another customer.");
            return redirect("/customers/{$id}/edit");
        }

        $this->repo->update($id, $data, (int) Auth::id());
        Flash::success("Customer '{$data['name']}' updated.");
        return redirect('/customers');
    }

    public function toggleStatus(array $params): string
    {
        $id       = (int) $params['id'];
        $customer = $this->requireCustomer($id);
        if (\is_string($customer)) {
            return $customer;
        }
        $newStatus = $customer['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Customer '{$customer['name']}' is now {$newStatus}.");
        return redirect('/customers');
    }

    /** @return array{0:array, 1:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $data = [
            'name'                => \trim((string) ($input['name']    ?? '')),
            'phone'               => \trim((string) ($input['phone']   ?? '')),
            'email'               => \trim((string) ($input['email']   ?? '')) ?: null,
            'address'             => \trim((string) ($input['address'] ?? '')) ?: null,
            'gstin'               => \strtoupper(\trim((string) ($input['gstin'] ?? ''))) ?: null,
            'state'               => \trim((string) ($input['state']   ?? '')) ?: null,
            'default_salesman_id' => ((int) ($input['default_salesman_id'] ?? 0)) ?: null,
            'credit_limit'        => (float) ($input['credit_limit'] ?? 0),
            'remarks'             => \trim((string) ($input['remarks'] ?? '')) ?: null,
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
        if ($data['credit_limit'] < 0) {
            flash_old($input);
            Flash::error('Credit limit cannot be negative.');
            return [$data, redirect($redirectTo)];
        }
        if ($data['default_salesman_id'] !== null) {
            $allowed = \array_column($this->salesmen->listActive(), 'id');
            if (!\in_array($data['default_salesman_id'], $allowed, true)) {
                flash_old($input);
                Flash::error('Selected salesman is not active.');
                return [$data, redirect($redirectTo)];
            }
        }

        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, redirect($redirectTo)];
        }
        return [$data, null];
    }

    private function requireCustomer(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Customer not found.');
            return redirect('/customers');
        }
        return $row;
    }
}
