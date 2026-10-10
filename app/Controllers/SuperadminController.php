<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TenantRepository;
use App\Support\Db;
use App\Support\Flash;
use App\Support\Auth;
use App\Support\Superadmin;
use App\Support\Tenant;

/**
 * The superadmin console — management of the tenants that sit beneath it.
 *
 * Tenant admins are subordinate to this: they exist inside one tenant database and
 * can neither see this console nor reach the control database. A superadmin can
 * list tenants, register new ones, suspend them, and enter one to work inside it.
 *
 * Entering a tenant means reading another business's complete books, so every
 * entry and exit is recorded in the control database's audit table.
 */
class SuperadminController
{
    public function __construct(
        private TenantRepository $repo = new TenantRepository(),
    ) {}

    /** The console: every tenant, with whether its database is actually provisioned. */
    public function index(): string
    {
        $tenants = $this->repo->allTenants();
        foreach ($tenants as &$t) {
            $t['inspect'] = $this->repo->inspectTenantDatabase((string) $t['database_name']);
        }
        unset($t);

        return view('superadmin/index', [
            'title'    => 'Tenants',
            'tenants'  => $tenants,
            'activity' => $this->repo->recentSuperadminActivity(15),
            'admin'    => Superadmin::user(),
        ], 'superadmin');
    }

    /** Form to register an existing database as a new tenant. */
    public function createForm(): string
    {
        return view('superadmin/create', ['title' => 'Add tenant'], 'superadmin');
    }

    /**
     * Register a tenant.
     *
     * This records an EXISTING database as a tenant; it does not create the
     * database. Creating a database needs panel or shell access that the app does
     * not have, and should not have — an application that can create databases is
     * an application that can be made to create databases.
     */
    public function store(): string
    {
        $key    = \strtolower(\trim((string) ($_POST['tenant_key'] ?? '')));
        $label  = \trim((string) ($_POST['label'] ?? ''));
        $dbName = \trim((string) ($_POST['database_name'] ?? ''));
        $remarks= \trim((string) ($_POST['remarks'] ?? '')) ?: null;

        $errors = [];
        if (!\preg_match('/^[a-z0-9][a-z0-9_-]{1,39}$/', $key)) {
            $errors[] = 'Key must be 2-40 characters: lowercase letters, digits, hyphen or underscore.';
        }
        if ($label === '')                        { $errors[] = 'Name is required.'; }
        if (!Tenant::isValidDatabaseName($dbName)) { $errors[] = 'Database name may contain only letters, digits and underscore.'; }
        if ($errors === [] && $this->repo->tenantKeyExists($key))        { $errors[] = "Key '{$key}' is already in use."; }
        if ($errors === [] && $this->repo->databaseNameExists($dbName))  { $errors[] = "Database '{$dbName}' is already registered to another tenant."; }

        // Refuse to register a database that is not actually there — otherwise the
        // failure surfaces later as a confusing sign-in error.
        if ($errors === []) {
            $i = $this->repo->inspectTenantDatabase($dbName);
            if (!$i['exists']) {
                $errors[] = "Database '{$dbName}' does not exist or is not reachable. "
                          . 'Create it in the hosting panel and apply the migrations first.';
            } elseif (!$i['ready']) {
                $errors[] = "Database '{$dbName}' exists but has no migrations applied. "
                          . 'Apply the IMS schema to it before registering it.';
            }
        }

        if ($errors !== []) {
            flash_old($_POST);
            Flash::error($errors[0]);
            return redirect('/superadmin/tenants/create');
        }

        $id = $this->repo->insertTenant([
            'tenant_key'    => $key,
            'label'         => $label,
            'database_name' => $dbName,
            'remarks'       => $remarks,
        ]);
        $this->repo->logSuperadminAction('create_tenant', [
            'superadmin_id' => Superadmin::id(),
            'username'      => Superadmin::username(),
            'tenant_id'     => $id,
            'tenant_key'    => $key,
            'notes'         => "database={$dbName}",
        ]);

        Flash::success("Tenant '{$label}' registered.");
        return redirect('/superadmin');
    }

    /** Suspend or re-activate a tenant. Suspended tenants cannot be signed in to. */
    public function toggleStatus(array $params): string
    {
        $id = (int) $params['id'];
        $t  = $this->repo->findTenant($id);
        if ($t === null) {
            Flash::error('Tenant not found.');
            return redirect('/superadmin');
        }
        $new = $t['status'] === 'active' ? 'suspended' : 'active';
        $this->repo->updateTenantStatus($id, $new);
        $this->repo->logSuperadminAction('tenant_' . $new, [
            'superadmin_id' => Superadmin::id(),
            'username'      => Superadmin::username(),
            'tenant_id'     => $id,
            'tenant_key'    => (string) $t['tenant_key'],
        ]);
        Flash::success("'{$t['label']}' is now {$new}.");
        return redirect('/superadmin');
    }

    /**
     * Enter a tenant and work inside it.
     *
     * The superadmin identity is kept alongside the tenant selection, so the app
     * knows it is a superadmin operating inside a tenant rather than one of that
     * tenant's own users. Permissions::can() short-circuits for a superadmin, so
     * no user row is needed in the tenant database.
     */
    public function enterTenant(array $params): string
    {
        $id = (int) $params['id'];
        $t  = $this->repo->findActiveTenant($id);
        if ($t === null) {
            Flash::error('Tenant not found, or it is suspended.');
            return redirect('/superadmin');
        }

        $i = $this->repo->inspectTenantDatabase((string) $t['database_name']);
        if (!$i['ready']) {
            Flash::error("'{$t['label']}' is not provisioned yet — its database has no schema.");
            return redirect('/superadmin');
        }

        Tenant::select($t);
        $this->repo->logSuperadminAction('enter_tenant', [
            'superadmin_id' => Superadmin::id(),
            'username'      => Superadmin::username(),
            'tenant_id'     => $id,
            'tenant_key'    => (string) $t['tenant_key'],
        ]);

        Flash::info('You are now working inside ' . $t['label'] . '.');
        return redirect('/dashboard');
    }

    /** Leave the tenant and return to the console. */
    public function leaveTenant(): string
    {
        $t = Tenant::current();
        if ($t !== null) {
            $this->repo->logSuperadminAction('leave_tenant', [
                'superadmin_id' => Superadmin::id(),
                'username'      => Superadmin::username(),
                'tenant_id'     => (int) $t['id'],
                'tenant_key'    => (string) $t['tenant_key'],
            ]);
        }
        Tenant::clear();
        Auth::logout();
        Flash::info('Returned to the tenant console.');
        return redirect('/superadmin');
    }

    /** Change the superadmin's own password. */
    public function showPassword(): string
    {
        return view('superadmin/password', ['title' => 'Change password'], 'superadmin');
    }

    public function updatePassword(): string
    {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $row = $this->repo->findSuperadmin((int) Superadmin::id());
        if ($row === null || !\password_verify($current, (string) $row['password_hash'])) {
            Flash::error('Current password is incorrect.');
            return redirect('/superadmin/password');
        }
        if (\strlen($new) < 8) {
            Flash::error('New password must be at least 8 characters.');
            return redirect('/superadmin/password');
        }
        if ($new !== $confirm) {
            Flash::error('New password and confirmation do not match.');
            return redirect('/superadmin/password');
        }

        $this->repo->updateSuperadminPassword((int) $row['id'], \password_hash($new, PASSWORD_BCRYPT));
        $this->repo->logSuperadminAction('password_changed', [
            'superadmin_id' => (int) $row['id'],
            'username'      => (string) $row['username'],
        ]);
        Flash::success('Password changed.');
        return redirect('/superadmin');
    }
}
