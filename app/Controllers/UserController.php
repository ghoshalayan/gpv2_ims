<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\Validator;

class UserController
{
    public function __construct(
        private UserRepository $repo  = new UserRepository(),
        private RoleRepository $roles = new RoleRepository(),
    ) {}

    public function index(): string
    {
        return view('users/index', [
            'title' => 'Users',
            'users' => $this->repo->all(),
            'currentUserId' => (int) Auth::id(),
        ]);
    }

    public function create(): string
    {
        return view('users/create', [
            'title' => 'New User',
            'roles' => $this->roles->all(),
        ]);
    }

    public function store(): string
    {
        [$data, $password, $errorRedirect] = $this->validateInput($_POST, '/users/create', isCreate: true);
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByUsername($data['username'])) {
            flash_old($_POST);
            Flash::error("Username '{$data['username']}' is already taken.");
            return redirect('/users/create');
        }
        if ($this->repo->existsByPhone($data['phone'])) {
            flash_old($_POST);
            Flash::error("Phone '{$data['phone']}' is already used by another user.");
            return redirect('/users/create');
        }

        $hash = \password_hash($password, PASSWORD_BCRYPT);
        $this->repo->insert($data, $hash);
        Flash::success("User '{$data['name']}' created.");
        return redirect('/users');
    }

    public function edit(array $params): string
    {
        $user = $this->requireUser((int) $params['id']);
        if (\is_string($user)) {
            return $user;
        }
        return view('users/edit', [
            'title' => 'Edit User',
            'user'  => $user,
            'roles' => $this->roles->all(),
            'isSelf' => (int) $user['id'] === (int) Auth::id(),
        ]);
    }

    public function update(array $params): string
    {
        $id   = (int) $params['id'];
        $user = $this->requireUser($id);
        if (\is_string($user)) {
            return $user;
        }
        $isSelf = $id === (int) Auth::id();

        [$data, $password, $errorRedirect] = $this->validateInput($_POST, "/users/{$id}/edit", isCreate: false);
        if ($errorRedirect !== null) {
            return $errorRedirect;
        }

        if ($this->repo->existsByUsername($data['username'], $id)) {
            flash_old($_POST);
            Flash::error("Username '{$data['username']}' is already taken.");
            return redirect("/users/{$id}/edit");
        }
        if ($this->repo->existsByPhone($data['phone'], $id)) {
            flash_old($_POST);
            Flash::error("Phone '{$data['phone']}' is already used by another user.");
            return redirect("/users/{$id}/edit");
        }

        // Protected accounts (break-glass admin): identity, role and status are fixed.
        // The password and cosmetic fields stay editable on purpose - see migration 0060.
        $protectedGuard = $this->guardProtectedAccount($user, $data);
        if ($protectedGuard !== null) {
            flash_old($_POST);
            Flash::error($protectedGuard);
            return redirect("/users/{$id}/edit");
        }

        // Self-protection and last-admin protection.
        $guard = $this->guardRoleAndStatusChange($user, $data, $isSelf);
        if ($guard !== null) {
            flash_old($_POST);
            Flash::error($guard);
            return redirect("/users/{$id}/edit");
        }

        $this->repo->update($id, $data);
        if ($password !== null) {
            $this->repo->updatePassword($id, \password_hash($password, PASSWORD_BCRYPT));
            Flash::success("User '{$data['name']}' updated (password changed).");
        } else {
            Flash::success("User '{$data['name']}' updated.");
        }
        return redirect('/users');
    }

    public function toggleStatus(array $params): string
    {
        $id   = (int) $params['id'];
        $user = $this->requireUser($id);
        if (\is_string($user)) {
            return $user;
        }
        if ($id === (int) Auth::id()) {
            Flash::error('You cannot deactivate your own account.');
            return redirect('/users');
        }
        if ((int) ($user['is_protected'] ?? 0) === 1 && $user['status'] === 'active') {
            Flash::error("'{$user['name']}' is a protected account and cannot be deactivated.");
            return redirect('/users');
        }
        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        if ($newStatus === 'inactive'
            && $user['role'] === Auth::ROLE_ADMIN
            && $this->repo->countActiveAdminsExcept($id) === 0) {
            Flash::error("Cannot deactivate '{$user['name']}' — they are the last active admin.");
            return redirect('/users');
        }
        $this->repo->setStatus($id, $newStatus);
        Flash::success("User '{$user['name']}' is now {$newStatus}.");
        return redirect('/users');
    }

    /**
     * @return array{0:array, 1:?string, 2:?string} [data, plaintext password or null, redirect or null]
     */
    private function validateInput(array $input, string $redirectTo, bool $isCreate): array
    {
        $data = [
            'name'     => \trim((string) ($input['name']     ?? '')),
            'phone'    => \trim((string) ($input['phone']    ?? '')),
            'email'    => \trim((string) ($input['email']    ?? '')) ?: null,
            'address'  => \trim((string) ($input['address']  ?? '')) ?: null,
            'username' => \trim((string) ($input['username'] ?? '')),
            'role'     => \trim((string) ($input['role']     ?? 'subadmin')),
            'status'   => \trim((string) ($input['status']   ?? 'active')),
        ];
        $password             = (string) ($input['password']              ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');

        // Resolve dynamic role_key → role_id (and validate it exists).
        $roleRow = $this->roles->findByKey($data['role']);
        if ($roleRow === null) {
            flash_old($input);
            Flash::error("Role '{$data['role']}' does not exist.");
            return [$data, null, redirect($redirectTo)];
        }
        $data['role_id'] = (int) $roleRow['id'];

        $v = Validator::make([
                'name'     => $data['name'],
                'phone'    => $data['phone'],
                'email'    => $data['email'] ?? '',
                'username' => $data['username'],
                'status'   => $data['status'],
            ])
            ->required('name')->max('name', 150)
            ->required('phone')->max('phone', 20)->phone('phone')
            ->max('email', 150)
            ->required('username')->max('username', 150)
            ->in('status', ['active', 'inactive'], 'Status is invalid.');

        if ($data['email'] !== null && !\filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            flash_old($input);
            Flash::error('Email must be a valid address.');
            return [$data, null, redirect($redirectTo)];
        }

        // Password rules: required on create; optional on edit (empty = leave unchanged).
        $changingPassword = $isCreate || $password !== '' || $passwordConfirmation !== '';
        if ($changingPassword) {
            $pv = Validator::make([
                    'password'              => $password,
                    'password_confirmation' => $passwordConfirmation,
                ])
                ->required('password')
                ->min('password', 6, 'Password must be at least 6 characters.')
                ->confirmed('password', 'password_confirmation');
            if ($pv->fails()) {
                flash_old($input);
                Flash::error($pv->firstError() ?? 'Password failed validation.');
                return [$data, null, redirect($redirectTo)];
            }
        }

        if ($v->fails()) {
            flash_old($input);
            Flash::error($v->firstError() ?? 'Validation failed.');
            return [$data, null, redirect($redirectTo)];
        }

        return [$data, $changingPassword ? $password : null, null];
    }

    /**
     * Enforces self-protection and last-admin protection.
     * Returns an error message (string) if blocked, or null if allowed.
     */
    /**
     * Protected ("break-glass") accounts: username, role and status are immutable, so
     * ordinary in-app administration can never lock the business out of its own system.
     *
     * The password is intentionally NOT protected. An account that can neither be
     * disabled nor have its password rotated would be an unfixable backdoor; keeping
     * rotation available is the control that still works on an account you cannot
     * switch off.
     *
     * @return string|null error message, or null when the change is allowed
     */
    private function guardProtectedAccount(array $user, array $data): ?string
    {
        if ((int) ($user['is_protected'] ?? 0) !== 1) {
            return null;
        }
        if (isset($data['username']) && $data['username'] !== $user['username']) {
            return "'{$user['name']}' is a protected account - its username cannot be changed.";
        }
        if (isset($data['role_id']) && (int) $data['role_id'] !== (int) $user['role_id']) {
            return "'{$user['name']}' is a protected account - its role cannot be changed.";
        }
        if (isset($data['status']) && $data['status'] !== 'active') {
            return "'{$user['name']}' is a protected account - it cannot be deactivated.";
        }
        return null;
    }

    private function guardRoleAndStatusChange(array $current, array $new, bool $isSelf): ?string
    {
        $id = (int) $current['id'];

        // Self: can't demote out of admin, can't deactivate self
        if ($isSelf) {
            if ($current['role'] === Auth::ROLE_ADMIN && $new['role'] !== Auth::ROLE_ADMIN) {
                return 'You cannot demote your own admin role. Another admin must do this.';
            }
            if ($new['status'] === 'inactive') {
                return 'You cannot deactivate your own account.';
            }
        }

        // Last-admin protection: can't demote or deactivate the last active admin
        $currentIsAdmin = $current['role'] === Auth::ROLE_ADMIN && $current['status'] === 'active';
        $losingAdmin    = $currentIsAdmin && ($new['role'] !== Auth::ROLE_ADMIN || $new['status'] === 'inactive');
        if ($losingAdmin && $this->repo->countActiveAdminsExcept($id) === 0) {
            return "Cannot change this user — they are the last active admin.";
        }

        return null;
    }

    private function requireUser(int $id): array|string
    {
        $row = $this->repo->findById($id);
        if ($row === null) {
            Flash::error('User not found.');
            return redirect('/users');
        }
        return $row;
    }
}
