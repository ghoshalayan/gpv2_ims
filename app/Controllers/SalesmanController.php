<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CustomerRepository;
use App\Repositories\LegacyObligationRepository;
use App\Repositories\SalesmanRepository;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;
use App\Support\IndianStates;
use App\Support\Validator;

class SalesmanController
{
    public function __construct(
        private SalesmanRepository         $repo        = new SalesmanRepository(),
        private CustomerRepository         $customers   = new CustomerRepository(),
        private LegacyObligationRepository $legacyOblig = new LegacyObligationRepository(),
    ) {}

    public function index(): string
    {
        $p = \App\Support\Paginator::fromQuery();
        $filters = ['status' => (string) ($_GET['status'] ?? '')];
        $result = $this->repo->paginate($filters, $p->page, $p->perPage, $p->search);
        return view('salesmen/index', [
            'title'    => 'Salesmen',
            'salesmen' => $result['rows'],
            'filters'  => $filters,
            'pg'       => $p->buildContext($result['total'], url('/salesmen'), $filters + ['q' => $p->search]),
        ]);
    }

    public function create(): string
    {
        return view('salesmen/create', [
            'title'  => 'New Salesman',
            'states' => IndianStates::LIST,
        ]);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateInput($_POST, '/salesmen/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'])) {
            flash_old($_POST);
            Flash::error("A salesman named '{$data['name']}' already exists.");
            return redirect('/salesmen/create');
        }
        if ($this->repo->existsByPhone($data['phone'])) {
            flash_old($_POST);
            Flash::error("Phone number '{$data['phone']}' is already used by another salesman.");
            return redirect('/salesmen/create');
        }

        // Smart-merge: reuse an existing customer if name+phone match (same person);
        // otherwise create a fresh customer. Block ambiguous cases.
        $existing = $this->customers->findByNameAndPhone($data['name'], $data['phone']);
        if ($existing === null
            && ($this->customers->existsByName($data['name']) || $this->customers->existsByPhone($data['phone']))) {
            flash_old($_POST);
            Flash::error("Another customer matches this name OR phone (but not both). Resolve that conflict first.");
            return redirect('/salesmen/create');
        }
        if ($existing !== null && !empty($existing['linked_salesman_id'])) {
            flash_old($_POST);
            Flash::error("Customer '{$existing['name']}' is already linked to another salesman.");
            return redirect('/salesmen/create');
        }

        Db::transaction(function () use ($data, $existing): void {
            $userId     = (int) Auth::id();
            $salesmanId = $this->repo->insert($data, $userId);
            if ($existing !== null) {
                $this->customers->setLinkedSalesman((int) $existing['id'], $salesmanId, $userId);
                $customerId = (int) $existing['id'];
            } else {
                $customerId = $this->customers->insert([
                    'name'                => $data['name'],
                    'phone'               => $data['phone'],
                    'email'               => $data['email'],
                    'address'             => $data['address'],
                    'gstin'               => $data['gstin'],
                    'state'               => $data['state'],
                    'default_salesman_id' => $salesmanId,
                    'credit_limit'        => 0,
                    'linked_salesman_id'  => $salesmanId,
                    'remarks'             => 'Auto-created mirror of salesman record.',
                ], $userId);
            }
            $this->repo->setLinkedCustomer($salesmanId, $customerId);
        });

        Flash::success("Salesman '{$data['name']}' created (linked to customer record).");
        return redirect('/salesmen');
    }

    public function edit(array $params): string
    {
        $salesman = $this->requireSalesman((int) $params['id']);
        if (\is_string($salesman)) {
            return $salesman;
        }
        $entityId = (int) $salesman['id'];
        return view('salesmen/edit', [
            'title'            => 'Edit Salesman',
            'salesman'         => $salesman,
            'states'           => IndianStates::LIST,
            'oldRecordsRollup' => $this->legacyOblig->rollupForEntity('salesman', $entityId),
        ]);
    }

    public function update(array $params): string
    {
        $id       = (int) $params['id'];
        $salesman = $this->requireSalesman($id);
        if (\is_string($salesman)) {
            return $salesman;
        }

        [$data, $errorRedirect] = $this->validateInput($_POST, "/salesmen/{$id}/edit");
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByName($data['name'], $id)) {
            flash_old($_POST);
            Flash::error("Another salesman named '{$data['name']}' already exists.");
            return redirect("/salesmen/{$id}/edit");
        }
        if ($this->repo->existsByPhone($data['phone'], $id)) {
            flash_old($_POST);
            Flash::error("Phone number '{$data['phone']}' is already used by another salesman.");
            return redirect("/salesmen/{$id}/edit");
        }

        // If a linked customer exists, the mirror must also stay unique on name + phone.
        $linkedCustomerId = (int) ($salesman['linked_customer_id'] ?? 0);
        if ($linkedCustomerId > 0) {
            if ($this->customers->existsByName($data['name'], $linkedCustomerId)) {
                flash_old($_POST);
                Flash::error("Another customer named '{$data['name']}' exists — the mirror record can't adopt this name.");
                return redirect("/salesmen/{$id}/edit");
            }
            if ($this->customers->existsByPhone($data['phone'], $linkedCustomerId)) {
                flash_old($_POST);
                Flash::error("Another customer with phone '{$data['phone']}' exists — the mirror record can't adopt this phone.");
                return redirect("/salesmen/{$id}/edit");
            }
        }

        Db::transaction(function () use ($id, $data, $linkedCustomerId): void {
            $userId = (int) Auth::id();
            $this->repo->update($id, $data, $userId);
            if ($linkedCustomerId > 0) {
                $this->customers->updateMirrorFromSalesman($linkedCustomerId, $data, $userId);
            }
        });

        Flash::success("Salesman '{$data['name']}' updated.");
        return redirect('/salesmen');
    }

    public function toggleStatus(array $params): string
    {
        $id       = (int) $params['id'];
        $salesman = $this->requireSalesman($id);
        if (\is_string($salesman)) {
            return $salesman;
        }
        $newStatus = $salesman['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Salesman '{$salesman['name']}' is now {$newStatus}.");
        return redirect('/salesmen');
    }

    /** @return array{0:array, 1:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $data = [
            'name'            => \trim((string) ($input['name']    ?? '')),
            'phone'           => \trim((string) ($input['phone']   ?? '')),
            'email'           => \trim((string) ($input['email']   ?? '')) ?: null,
            'address'         => \trim((string) ($input['address'] ?? '')) ?: null,
            'state'           => \trim((string) ($input['state']   ?? '')) ?: null,
            'gstin'           => \strtoupper(\trim((string) ($input['gstin'] ?? ''))) ?: null,
            'commission_rate' => (float) ($input['commission_rate'] ?? 0),
            'remarks'         => \trim((string) ($input['remarks'] ?? '')) ?: null,
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
        if ($data['commission_rate'] < 0 || $data['commission_rate'] > 100) {
            flash_old($input);
            Flash::error('Commission rate must be between 0 and 100.');
            return [$data, redirect($redirectTo)];
        }

        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, redirect($redirectTo)];
        }
        return [$data, null];
    }

    private function requireSalesman(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Salesman not found.');
            return redirect('/salesmen');
        }
        return $row;
    }
}
