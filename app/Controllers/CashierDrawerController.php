<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CashierDrawerRepository;
use App\Repositories\InterAccountTransferRepository;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;
use App\Support\Validator;

class CashierDrawerController
{
    public function __construct(
        private CashierDrawerRepository        $repo      = new CashierDrawerRepository(),
        private InterAccountTransferRepository $transfers = new InterAccountTransferRepository(),
    ) {}

    public function index(): string
    {
        return view('cashier-drawers/index', [
            'title'   => 'Cashier Drawers',
            'drawers' => $this->repo->all(),
        ]);
    }

    public function create(): string
    {
        return view('cashier-drawers/create', [
            'title' => 'New Cashier Drawer',
            'users' => $this->activeUsers(),
        ]);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateInput($_POST, '/cashier-drawers/create');
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }
        if ($this->repo->existsByName($data['name'])) {
            flash_old($_POST);
            Flash::error("A drawer named '{$data['name']}' already exists.");
            return redirect('/cashier-drawers/create');
        }
        $this->repo->insert($data, (int) Auth::id());
        Flash::success("Drawer '{$data['name']}' created.");
        return redirect('/cashier-drawers');
    }

    public function show(array $params): string
    {
        $drawer = $this->require((int) $params['id']);
        if (\is_string($drawer)) { return $drawer; }
        return view('cashier-drawers/show', [
            'title'     => $drawer['name'],
            'drawer'    => $drawer,
            'transfers' => $this->transfers->byAccount('cashier_drawer', (int) $drawer['id']),
        ]);
    }

    public function edit(array $params): string
    {
        $drawer = $this->require((int) $params['id']);
        if (\is_string($drawer)) { return $drawer; }
        return view('cashier-drawers/edit', [
            'title'  => 'Edit Cashier Drawer',
            'drawer' => $drawer,
            'users'  => $this->activeUsers(),
        ]);
    }

    public function update(array $params): string
    {
        $id     = (int) $params['id'];
        $drawer = $this->require($id);
        if (\is_string($drawer)) { return $drawer; }
        [$data, $errorRedirect] = $this->validateInput($_POST, "/cashier-drawers/{$id}/edit");
        if ($errorRedirect !== null) { return $errorRedirect; }
        if ($this->repo->existsByName($data['name'], $id)) {
            flash_old($_POST);
            Flash::error("Another drawer named '{$data['name']}' already exists.");
            return redirect("/cashier-drawers/{$id}/edit");
        }
        $this->repo->update($id, $data, (int) Auth::id());
        Flash::success("Drawer '{$data['name']}' updated.");
        return redirect('/cashier-drawers');
    }

    public function toggleStatus(array $params): string
    {
        $id     = (int) $params['id'];
        $drawer = $this->require($id);
        if (\is_string($drawer)) { return $drawer; }
        $newStatus = $drawer['status'] === 'active' ? 'inactive' : 'active';
        $this->repo->setStatus($id, $newStatus, (int) Auth::id());
        Flash::success("Drawer '{$drawer['name']}' is now {$newStatus}.");
        return redirect('/cashier-drawers');
    }

    /** @return array{0:array, 1:?string} */
    private function validateInput(array $input, string $redirectTo): array
    {
        $data = [
            'name'             => \trim((string) ($input['name'] ?? '')),
            'location'         => \trim((string) ($input['location'] ?? '')) ?: null,
            'assigned_user_id' => ((int) ($input['assigned_user_id'] ?? 0)) ?: null,
            'opening_balance'  => (float) ($input['opening_balance'] ?? 0),
            'remarks'          => \trim((string) ($input['remarks'] ?? '')) ?: null,
        ];
        $v = Validator::make(['name' => $data['name']])
            ->required('name')
            ->max('name', 100);
        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, redirect($redirectTo)];
        }
        return [$data, null];
    }

    /** @return array<int, array<string, mixed>> */
    private function activeUsers(): array
    {
        return Db::pdo()->query(
            "SELECT id, name, username FROM users WHERE status = 'active' ORDER BY name"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function require(int $id): array|string
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            Flash::error('Drawer not found.');
            return redirect('/cashier-drawers');
        }
        return $row;
    }
}
