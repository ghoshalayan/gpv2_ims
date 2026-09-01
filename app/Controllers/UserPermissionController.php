<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Services\RolePermissionService;
use App\Support\Auth;
use App\Support\Flash;

class UserPermissionController
{
    public function __construct(
        private RolePermissionService $service = new RolePermissionService(),
        private UserRepository        $users   = new UserRepository(),
    ) {}

    public function edit(array $params): string
    {
        $userId = (int) $params['id'];
        $user = $this->users->findById($userId);
        if ($user === null) {
            Flash::error('User not found.');
            return redirect('/users');
        }
        $editor = $this->service->buildUserOverrideEditor($userId);
        return view('user-permissions/edit', [
            'title'         => 'Permission Overrides — ' . $user['name'],
            'user'          => $user,
            'tree'          => $editor['tree'],
            'abilityLabels' => $editor['ability_labels'],
            'roleGrants'    => $editor['role_grants'],
            'overrides'     => $editor['overrides'],
            'effective'     => $editor['effective'],
        ]);
    }

    public function update(array $params): string
    {
        $userId = (int) $params['id'];
        $user = $this->users->findById($userId);
        if ($user === null) {
            Flash::error('User not found.');
            return redirect('/users');
        }

        $posted = (array) ($_POST['override'] ?? []);
        $count = $this->service->saveUserOverrides($userId, $posted, (int) Auth::id());
        Flash::success("Overrides saved for '{$user['name']}' ({$count} entries).");
        return redirect('/users/' . $userId . '/permissions');
    }
}
