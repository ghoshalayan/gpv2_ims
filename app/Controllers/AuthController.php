<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TenantRepository;
use App\Services\AuthService;
use App\Support\ActivityLog;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;
use App\Support\Superadmin;
use App\Support\Tenant;

/**
 * Sign-in for both kinds of identity.
 *
 *   A tenant is chosen first, then the account is authenticated against THAT
 *   tenant's own `users` table. Choosing "System Administration" instead
 *   authenticates against the control database's `superadmins` table.
 *
 * The tenant must be selected before authenticating, because the credentials
 * only exist inside the tenant's own database — there is no global user list.
 */
class AuthController
{
    /** Value of the tenant selector that means "sign in to the control plane". */
    private const CONTROL_CHOICE = 'system';

    public function __construct(
        private AuthService      $auth    = new AuthService(),
        private TenantRepository $tenants = new TenantRepository(),
    ) {}

    public function showLogin(): string
    {
        if (Superadmin::check()) {
            return redirect('/superadmin');
        }
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        // Before the control database exists this is still a single-tenant install;
        // the picker is simply hidden and sign-in behaves exactly as it always has.
        $multiTenant = Db::controlAvailable();

        return view('auth/login', [
            'title'       => 'Sign in',
            'username'    => $_SESSION['_old_username'] ?? '',
            'multiTenant' => $multiTenant,
            'tenants'     => $multiTenant ? $this->tenants->allTenants(activeOnly: true) : [],
            'chosen'      => (string) ($_SESSION['_old_tenant'] ?? ''),
            'controlChoice' => self::CONTROL_CHOICE,
        ]);
    }

    public function login(): string
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $choice   = trim((string) ($_POST['tenant'] ?? ''));

        if ($username === '' || $password === '') {
            Flash::error('Username and password are required.');
            return $this->backToLogin($username, $choice);
        }

        $multiTenant = Db::controlAvailable();

        // ---- Control plane: the superadmin above all tenants -----------------
        if ($multiTenant && $choice === self::CONTROL_CHOICE) {
            return $this->loginSuperadmin($username, $password);
        }

        // ---- A tenant -------------------------------------------------------
        if ($multiTenant) {
            if ($choice === '') {
                Flash::error('Choose which business you are signing in to.');
                return $this->backToLogin($username, $choice);
            }
            $tenant = $this->tenants->findActiveTenant((int) $choice);
            if ($tenant === null) {
                Flash::error('That business is not available.');
                return $this->backToLogin($username, '');
            }
            // Select BEFORE authenticating: the credentials live in that tenant's own
            // database, so the connection has to point there first.
            Tenant::select($tenant);
        }

        $user = $this->auth->attempt($username, $password);
        if ($user === null) {
            ActivityLog::log('login', 'auth', null, [
                'label' => $username,
                'notes' => 'failed login attempt',
            ]);
            if ($multiTenant) {
                Tenant::clear();
            }
            Flash::error('Invalid credentials, or account is inactive.');
            return $this->backToLogin($username, $choice);
        }

        Auth::login($user);
        unset($_SESSION['_old_username'], $_SESSION['_old_tenant']);
        ActivityLog::log('login', 'user', (int) $user['id'], [
            'label' => $user['name'],
            'user_id' => (int) $user['id'],
            'user_name' => $user['name'],
            'notes' => 'role=' . $user['role'],
        ]);
        Flash::success('Welcome back, ' . $user['name'] . '.');
        return redirect('/dashboard');
    }

    private function loginSuperadmin(string $username, string $password): string
    {
        $row = $this->tenants->findSuperadminByUsername($username);
        $ok  = $row !== null
            && $row['status'] === 'active'
            && \password_verify($password, (string) $row['password_hash']);

        if (!$ok) {
            $this->tenants->logSuperadminAction('login_failed', [
                'username' => $username,
                'notes'    => 'invalid credentials or inactive account',
            ]);
            Flash::error('Invalid credentials, or account is inactive.');
            return $this->backToLogin($username, self::CONTROL_CHOICE);
        }

        // A superadmin starts outside every tenant and enters one deliberately.
        Tenant::clear();
        Auth::logout();
        Superadmin::login($row);
        $this->tenants->touchSuperadminLogin((int) $row['id']);
        $this->tenants->logSuperadminAction('login', [
            'superadmin_id' => (int) $row['id'],
            'username'      => (string) $row['username'],
        ]);
        unset($_SESSION['_old_username'], $_SESSION['_old_tenant']);
        Flash::success('Signed in as ' . $row['name'] . '.');
        return redirect('/superadmin');
    }

    public function logout(): string
    {
        if (Superadmin::check()) {
            $this->tenants->logSuperadminAction('logout', [
                'superadmin_id' => Superadmin::id(),
                'username'      => Superadmin::username(),
            ]);
            Superadmin::logout();
            Flash::info('You have been logged out.');
            return redirect('/login');
        }

        $u = Auth::user();
        if ($u !== null) {
            ActivityLog::log('logout', 'user', (int) $u['id'], [
                'label' => $u['name'] ?? null,
            ]);
        }
        Auth::logout();
        Tenant::clear();
        Flash::info('You have been logged out.');
        return redirect('/login');
    }

    private function backToLogin(string $username, string $choice): string
    {
        $_SESSION['_old_username'] = $username;
        $_SESSION['_old_tenant']   = $choice;
        return redirect('/login');
    }
}
