<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CustomerRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\UserRepository;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;
use App\Support\IndianStates;
use App\Support\Validator;

class EmployeeController
{
    private const ID_PROOF_TYPES = ['Aadhaar', 'PAN', 'Driving License', 'Voter ID', 'Passport', 'Other'];

    public function __construct(
        private EmployeeRepository $repo      = new EmployeeRepository(),
        private CustomerRepository $customers = new CustomerRepository(),
        private UserRepository     $users     = new UserRepository(),
    ) {}

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();
        $filters = ['status' => (string) ($_GET['status'] ?? '')];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('employees/index', [
            'title'     => 'Employees',
            'employees' => $result['rows'],
            'filters'   => $filters,
            'pg'        => $p->buildContext($result['total'], url('/employees'), $filters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        return view('employees/create', [
            'title'      => 'New Employee',
            'states'     => IndianStates::LIST,
            'idProofs'   => self::ID_PROOF_TYPES,
            'users'      => $this->listLinkableUsers(),
        ]);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateInput($_POST, '/employees/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'])) {
            flash_old($_POST);
            Flash::error("An employee named '{$data['name']}' already exists.");
            return redirect('/employees/create');
        }
        if ($this->repo->existsByPhone($data['phone'])) {
            flash_old($_POST);
            Flash::error("Phone number '{$data['phone']}' is already used by another employee.");
            return redirect('/employees/create');
        }

        // Smart-merge auto-link to customer (per D41)
        $existing = $this->customers->findByNameAndPhone($data['name'], $data['phone']);
        if ($existing === null
            && ($this->customers->existsByName($data['name']) || $this->customers->existsByPhone($data['phone']))) {
            flash_old($_POST);
            Flash::error("Another customer matches this name OR phone (but not both). Resolve that conflict first.");
            return redirect('/employees/create');
        }
        if ($existing !== null && !empty($existing['linked_employee_id'])) {
            flash_old($_POST);
            Flash::error("Customer '{$existing['name']}' is already linked to another employee.");
            return redirect('/employees/create');
        }

        Db::transaction(function () use ($data, $existing): void {
            $userId     = (int) Auth::id();
            $employeeId = $this->repo->insert($data, $userId);
            if ($existing !== null) {
                $this->customers->setLinkedEmployee((int) $existing['id'], $employeeId, $userId);
                $customerId = (int) $existing['id'];
            } else {
                $customerId = $this->customers->insert([
                    'name'                => $data['name'],
                    'phone'               => $data['phone'],
                    'email'               => $data['email'],
                    'address'             => $data['address'],
                    'gstin'               => null,
                    'state'               => $data['state'],
                    'default_salesman_id' => null,
                    'credit_limit'        => 0,
                    'linked_salesman_id'  => null,
                    'linked_employee_id'  => $employeeId,
                    'remarks'             => 'Auto-created mirror of employee record.',
                ], $userId);
            }
            $this->repo->setLinkedCustomer($employeeId, $customerId);
        });

        Flash::success("Employee '{$data['name']}' created (linked to customer record).");
        return redirect('/employees');
    }

    public function edit(array $params): string
    {
        $employee = $this->requireEmployee((int) $params['id']);
        if (\is_string($employee)) {
            return $employee;
        }
        return view('employees/edit', [
            'title'    => 'Edit Employee',
            'employee' => $employee,
            'states'   => IndianStates::LIST,
            'idProofs' => self::ID_PROOF_TYPES,
            'users'    => $this->listLinkableUsers((int) ($employee['linked_user_id'] ?? 0)),
        ]);
    }

    public function update(array $params): string
    {
        $id       = (int) $params['id'];
        $employee = $this->requireEmployee($id);
        if (\is_string($employee)) {
            return $employee;
        }

        [$data, $errorRedirect] = $this->validateInput($_POST, "/employees/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'], $id)) {
            flash_old($_POST);
            Flash::error("Another employee named '{$data['name']}' already exists.");
            return redirect("/employees/{$id}/edit");
        }
        if ($this->repo->existsByPhone($data['phone'], $id)) {
            flash_old($_POST);
            Flash::error("Phone number '{$data['phone']}' is already used by another employee.");
            return redirect("/employees/{$id}/edit");
        }

        $linkedCustomerId = (int) ($employee['linked_customer_id'] ?? 0);
        if ($linkedCustomerId > 0) {
            if ($this->customers->existsByName($data['name'], $linkedCustomerId)
                || $this->customers->existsByPhone($data['phone'], $linkedCustomerId)) {
                flash_old($_POST);
                Flash::error("Another customer with that name or phone exists — the mirror record can't adopt these values.");
                return redirect("/employees/{$id}/edit");
            }
        }

        Db::transaction(function () use ($id, $data, $linkedCustomerId): void {
            $userId = (int) Auth::id();
            $this->repo->update($id, $data, $userId);
            if ($linkedCustomerId > 0) {
                $this->customers->updateMirrorFromSalesman($linkedCustomerId, $data, $userId);
            }
        });

        Flash::success("Employee '{$data['name']}' updated.");
        return redirect('/employees');
    }

    /** Toggle status active <-> exited; auto-set / clear exit_date. */
    public function toggleStatus(array $params): string
    {
        $id       = (int) $params['id'];
        $employee = $this->requireEmployee($id);
        if (\is_string($employee)) {
            return $employee;
        }
        if ($employee['status'] === 'active') {
            $this->repo->setStatus($id, 'exited', \date('Y-m-d'), (int) Auth::id());
            Flash::success("Employee '{$employee['name']}' marked as exited.");
        } else {
            $this->repo->setStatus($id, 'active', null, (int) Auth::id());
            Flash::success("Employee '{$employee['name']}' marked as active.");
        }
        return redirect('/employees');
    }

    /** Available users for the linked-user dropdown (any role, excluding those already linked). */
    private function listLinkableUsers(int $alsoIncludeId = 0): array
    {
        $rows = Db::pdo()->query(
            "SELECT u.id, u.name, u.username, r.role_key AS role, r.label AS role_label
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE NOT EXISTS (SELECT 1 FROM employees e WHERE e.linked_user_id = u.id)
             ORDER BY u.name"
        )->fetchAll(\PDO::FETCH_ASSOC);

        if ($alsoIncludeId > 0) {
            $found = false;
            foreach ($rows as $r) {
                if ((int) $r['id'] === $alsoIncludeId) { $found = true; break; }
            }
            if (!$found) {
                $u = $this->users->findById($alsoIncludeId);
                if ($u !== null) {
                    $rows[] = $u;
                }
            }
        }
        return $rows;
    }

    /** @return array{0:array, 1:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $data = [
            'name'                     => \trim((string) ($input['name']    ?? '')),
            'phone'                    => \trim((string) ($input['phone']   ?? '')),
            'email'                    => \trim((string) ($input['email']   ?? '')) ?: null,
            'address'                  => \trim((string) ($input['address'] ?? '')) ?: null,
            'state'                    => \trim((string) ($input['state']   ?? '')) ?: null,
            'designation'              => \trim((string) ($input['designation'] ?? '')),
            'joining_date'             => \trim((string) ($input['joining_date'] ?? '')),
            'salary_amount'            => (float) ($input['salary_amount'] ?? 0),
            'id_proof_type'            => \trim((string) ($input['id_proof_type'] ?? '')) ?: null,
            'id_proof_number'          => \trim((string) ($input['id_proof_number'] ?? '')) ?: null,
            'pan'                      => \strtoupper(\trim((string) ($input['pan'] ?? ''))) ?: null,
            'aadhaar'                  => \preg_replace('/\s+/', '', (string) ($input['aadhaar'] ?? '')) ?: null,
            'bank_account_number'      => \trim((string) ($input['bank_account_number'] ?? '')) ?: null,
            'bank_ifsc'                => \strtoupper(\trim((string) ($input['bank_ifsc'] ?? ''))) ?: null,
            'bank_branch'              => \trim((string) ($input['bank_branch'] ?? '')) ?: null,
            'bank_account_holder_name' => \trim((string) ($input['bank_account_holder_name'] ?? '')) ?: null,
            'linked_user_id'           => ((int) ($input['linked_user_id'] ?? 0)) ?: null,
            'remarks'                  => \trim((string) ($input['remarks'] ?? '')) ?: null,
        ];

        $v = Validator::make([
                'name'         => $data['name'],
                'phone'        => $data['phone'],
                'email'        => $data['email'] ?? '',
                'designation'  => $data['designation'],
                'joining_date' => $data['joining_date'],
                'pan'          => $data['pan'] ?? '',
                'aadhaar'      => $data['aadhaar'] ?? '',
                'bank_ifsc'    => $data['bank_ifsc'] ?? '',
            ])
            ->required('name')->max('name', 150)
            ->required('phone')->max('phone', 20)->phone('phone')
            ->required('designation')->max('designation', 150)
            ->required('joining_date')
            ->max('email', 150)
            ->pan('pan')
            ->aadhaar('aadhaar')
            ->ifsc('bank_ifsc');

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
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['joining_date'])) {
            flash_old($input);
            Flash::error('Joining date must be in YYYY-MM-DD format.');
            return [$data, redirect($redirectTo)];
        }
        if ($data['salary_amount'] < 0) {
            flash_old($input);
            Flash::error('Salary amount cannot be negative.');
            return [$data, redirect($redirectTo)];
        }

        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, redirect($redirectTo)];
        }
        return [$data, null];
    }

    private function requireEmployee(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Employee not found.');
            return redirect('/employees');
        }
        return $row;
    }
}
