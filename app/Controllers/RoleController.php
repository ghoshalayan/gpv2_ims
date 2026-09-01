<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Support\ActivityLog;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class RoleController
{
    public function __construct(
        private RoleRepository $repo  = new RoleRepository(),
        private UserRepository $users = new UserRepository(),
    ) {}

    public function index(): string
    {
        return view('roles/index', [
            'title' => 'Roles',
            'roles' => $this->repo->all(),
        ]);
    }

    public function create(): string
    {
        return view('roles/create', ['title' => 'New Role']);
    }

    public function store(): string
    {
        [$data, $errorRedirect] = $this->validateInput($_POST, '/roles/create');
        if ($errorRedirect !== null) { return $errorRedirect; }

        if ($this->repo->existsByKey($data['role_key'])) {
            flash_old($_POST);
            Flash::error("Role key '{$data['role_key']}' is already taken.");
            return redirect('/roles/create');
        }
        $id = $this->repo->insert($data['role_key'], $data['label'], $data['description'], $data['sort_order']);
        ActivityLog::log('create', 'role', $id, ['label' => $data['label']]);
        Flash::success("Role '{$data['label']}' created. Configure its permissions.");
        return redirect("/role-permissions/{$id}/edit");
    }

    public function edit(array $params): string
    {
        $role = $this->requireRole((int) $params['id']);
        if (\is_string($role)) { return $role; }
        return view('roles/edit', ['title' => 'Edit Role', 'role' => $role]);
    }

    public function update(array $params): string
    {
        $id   = (int) $params['id'];
        $role = $this->requireRole($id);
        if (\is_string($role)) { return $role; }

        [$data, $errorRedirect] = $this->validateInput($_POST, "/roles/{$id}/edit");
        if ($errorRedirect !== null) { return $errorRedirect; }

        // System role: lock the role_key to prevent breaking the master-switch.
        if ((int) $role['is_system'] === 1) {
            $data['role_key'] = $role['role_key'];
        } elseif ($this->repo->existsByKey($data['role_key'], $id)) {
            flash_old($_POST);
            Flash::error("Role key '{$data['role_key']}' is already taken.");
            return redirect("/roles/{$id}/edit");
        }

        $this->repo->update($id, $data['role_key'], $data['label'], $data['description'], $data['sort_order']);
        ActivityLog::log('update', 'role', $id, ['label' => $data['label']]);
        Flash::success("Role '{$data['label']}' updated.");
        return redirect('/roles');
    }

    public function destroy(array $params): string
    {
        $id   = (int) $params['id'];
        $role = $this->requireRole($id);
        if (\is_string($role)) { return $role; }

        if ((int) $role['is_system'] === 1) {
            Flash::error("Role '{$role['label']}' is a system role and cannot be deleted.");
            return redirect('/roles');
        }
        $userCount = $this->users->countByRoleId($id);
        if ($userCount > 0) {
            Flash::error("Cannot delete '{$role['label']}' — {$userCount} user(s) still assigned. Reassign them first.");
            return redirect('/roles');
        }

        $this->repo->delete($id);
        ActivityLog::log('delete', 'role', $id, ['label' => $role['label']]);
        Flash::success("Role '{$role['label']}' deleted.");
        return redirect('/roles');
    }

    /**
     * @return array{0:array, 1:?string} [data, redirect-or-null]
     */
    private function validateInput(array $input, string $redirectTo): array
    {
        $data = [
            'role_key'    => \strtolower(\trim((string) ($input['role_key']    ?? ''))),
            'label'       => \trim((string) ($input['label']       ?? '')),
            'description' => \trim((string) ($input['description'] ?? '')) ?: null,
            'sort_order'  => (int) ($input['sort_order'] ?? 100),
        ];

        $v = Validator::make([
                'role_key' => $data['role_key'],
                'label'    => $data['label'],
            ])
            ->required('role_key')->max('role_key', 40)
            ->required('label')->max('label', 100);

        // Stricter regex for role_key — slug only.
        if ($data['role_key'] !== '' && !\preg_match('/^[a-z][a-z0-9_]*$/', $data['role_key'])) {
            flash_old($input);
            Flash::error('Role key must be lowercase letters, digits and underscores only (start with a letter).');
            return [$data, redirect($redirectTo)];
        }
        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, redirect($redirectTo)];
        }
        return [$data, null];
    }

    private function requireRole(int $id): array|string
    {
        $row = $this->repo->findById($id);
        if ($row === null) {
            Flash::error('Role not found.');
            return redirect('/roles');
        }
        return $row;
    }
}
