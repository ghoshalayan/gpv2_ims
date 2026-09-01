<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RoleRepository;
use App\Services\RolePermissionService;
use App\Support\Auth;
use App\Support\Flash;

class RolePermissionController
{
    public function __construct(
        private RolePermissionService $service = new RolePermissionService(),
        private RoleRepository        $roles   = new RoleRepository(),
    ) {}

    public function index(): string
    {
        return view('role-permissions/index', [
            'title' => 'Role Permissions',
            'roles' => $this->roles->all(),
        ]);
    }

    public function edit(array $params): string
    {
        $roleId = (int) $params['id'];
        try {
            $editor = $this->service->buildRoleEditor($roleId);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            return redirect('/role-permissions');
        }

        return view('role-permissions/edit', [
            'title'         => 'Permissions — ' . $editor['role']['label'],
            'role'          => $editor['role'],
            'tree'          => $editor['tree'],
            'abilityLabels' => $editor['ability_labels'],
            'granted'       => $editor['granted'],
        ]);
    }

    public function update(array $params): string
    {
        $roleId = (int) $params['id'];
        $role   = $this->roles->findById($roleId);
        if ($role === null) {
            Flash::error('Role not found.');
            return redirect('/role-permissions');
        }

        $checked = (array) ($_POST['permissions'] ?? []);
        $count = $this->service->saveRoleGrants($roleId, $checked, (int) Auth::id());
        Flash::success("Permissions saved for '{$role['label']}' ({$count} granted).");
        return redirect('/role-permissions/' . $roleId . '/edit');
    }

    public function resetDefaults(): string
    {
        $this->service->resetToDefaults();
        Flash::success('All role permissions reset to factory defaults.');
        return redirect('/role-permissions');
    }
}
