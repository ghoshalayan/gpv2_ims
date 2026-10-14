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
 * There is no business picker. The username alone decides where the sign-in
 * lands: it is matched first against the `superadmins` table, and otherwise
 * against every business's users. Usernames are therefore globally unique
 * (see database/control/global_usernames.sql), which is what makes that
 * lookup unambiguous.
 *
 * The password is verified BEFORE the tenant is bound to the connection, so a
 * failed attempt never causes the application to operate inside a business's
 * books even momentarily.
 */
class AuthController
{
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

        // No business picker: the username decides which business the sign-in
        // belongs to, so asking first would be redundant - and it would leak the
        // list of businesses to anyone who loads the page.
        return view('auth/login', [
            'title'    => 'Sign in',
            'username' => $_SESSION['_old_username'] ?? '',
        ]);
    }

    public function login(): string
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            Flash::error('Username and password are required.');
            return $this->backToLogin($username);
        }

        $multiTenant = Db::multiTenant();

        // Whatever the session was, start from nothing. Otherwise a failed
        // sign-in could leave the previous tenant bound to the connection.
        if ($multiTenant) {
            Tenant::clear();
        }
        Auth::logout();

        // ---- The superadmin sits above every business, in its own table. ----
        if ($multiTenant && $this->tenants->findSuperadminByUsername($username) !== null) {
            return $this->loginSuperadmin($username, $password);
        }

        // ---- A business user. The username tells us which business. ---------
        // The password is checked before any tenant is bound: a wrong password
        // must never cause the app to start operating inside someone's books.
        $user = $multiTenant
            ? $this->auth->attemptAcrossTenants($username, $password)
            : $this->auth->attempt($username, $password);

        if ($user === null) {
            ActivityLog::log('login', 'auth', null, [
                'label' => $username,
                'notes' => 'failed login attempt',
            ]);
            Flash::error('Invalid credentials, or account is inactive.');
            return $this->backToLogin($username);
        }

        if ($multiTenant) {
            $tenant = $this->tenants->findTenant((int) $user['tenant_id']);
            if ($tenant === null) {
                Flash::error('Invalid credentials, or account is inactive.');
                return $this->backToLogin($username);
            }
            if ($tenant['status'] !== 'active') {
                // Deliberately specific: this is an administrative state, not a
                // credential problem, and the user cannot fix it by retyping.
                Flash::error($tenant['label'] . ' is suspended. Contact your administrator.');
                return $this->backToLogin($username);
            }
            Tenant::select($tenant);

            // last_login_at lives in the tenant's own rows, so it can only be
            // written once the tenant is bound.
            $this->auth->touchLogin((int) $user['id']);
        }

        Auth::login($user);
        unset($_SESSION['_old_username']);
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
            return $this->backToLogin($username);
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
        unset($_SESSION['_old_username']);
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

    private function backToLogin(string $username): string
    {
        $_SESSION['_old_username'] = $username;
        return redirect('/login');
    }
}
