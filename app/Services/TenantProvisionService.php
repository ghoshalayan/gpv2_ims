<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TenantRepository;
use App\Support\Db;
use App\Support\Tenant;

/**
 * Creates a new business from the superadmin console.
 *
 * All tenants share one database, so there is nothing to provision at the
 * hosting level — a new business is a row in `tenants` plus the opening records
 * every install needs before anyone can use it.
 *
 * Those opening rows are written with the NEW tenant bound to the connection
 * (via Tenant::runAs), so the insert triggers stamp them correctly. Without that
 * they would be stamped with whatever tenant the superadmin last had selected,
 * which is exactly the kind of silent mis-assignment this design is meant to
 * make impossible.
 *
 * The whole thing runs in one transaction: a half-created business — a tenant
 * row with no administrator to sign in as — would be worse than no business.
 */
class TenantProvisionService
{
    /** Bootstrap administrator created for every new business. */
    public const OWNER_USERNAME = 'owner';

    public function __construct(
        private TenantRepository $repo = new TenantRepository(),
    ) {}

    /**
     * @return array{tenant_id:int, username:string, password:string}
     *         The generated password is shown once and never stored in clear.
     */
    public function create(string $key, string $label, ?string $remarks, ?string $ownerPassword = null): array
    {
        if (!Db::multiTenant()) {
            throw new \RuntimeException(
                'This database has not been converted for multi-tenancy yet. '
                . 'Run database/control/multitenancy_convert.sql first.'
            );
        }

        $password = $ownerPassword !== null && $ownerPassword !== ''
            ? $ownerPassword
            : self::generatePassword();

        return Db::transaction(function () use ($key, $label, $remarks, $password) {
            $tenantId = $this->repo->insertTenant([
                'tenant_key' => $key,
                'label'      => $label,
                'remarks'    => $remarks,
            ]);

            $row = ['id' => $tenantId, 'tenant_key' => $key, 'label' => $label];

            Tenant::runAs($row, function () use ($label, $password) {
                $this->seedBaseline($label, $password);
            });

            return ['tenant_id' => $tenantId, 'username' => self::OWNER_USERNAME, 'password' => $password];
        });
    }

    /**
     * The minimum a business needs to be usable on first sign-in.
     *
     * Roles, permissions and units are shared across tenants, so they are already
     * there. What is per-tenant is the business profile, the fallback brand that
     * products fall back to, the settings toggles, and an administrator.
     */
    private function seedBaseline(string $label, string $password): void
    {
        $pdo = Db::pdo();

        // Business profile — a singleton per tenant, id is fixed at 1 by the app.
        $pdo->prepare(
            'INSERT INTO business_profile (id, legal_name, display_name, state, financial_year_start_month)
             VALUES (1, ?, ?, ?, 4)'
        )->execute([$label, $label, 'West Bengal']);

        // System settings, matching the defaults a fresh install gets.
        $settings = [
            ['commission_accrues_on', 'invoice_finalization'],
            ['enable_round_off', '1'],
            ['low_stock_dashboard_widget', '1'],
            ['enable_brand_slab_rates', '0'],
        ];
        $st = $pdo->prepare('INSERT INTO system_settings (setting_key, value) VALUES (?, ?)');
        foreach ($settings as [$k, $v]) {
            $st->execute([$k, $v]);
        }

        // Fallback brand. Products require a brand, so a new business cannot add
        // its first product without one.
        $pdo->prepare('INSERT INTO brands (name, description) VALUES (?, ?)')
            ->execute(['Generic', 'System fallback brand for un-branded or unclassified products.']);

        // The administrator. Protected, so ordinary administration inside the
        // business cannot deactivate or rename it and lock everyone out.
        $roleId = $pdo->query("SELECT id FROM roles WHERE role_key = 'admin' LIMIT 1")->fetchColumn();
        if ($roleId === false) {
            throw new \RuntimeException('No admin role found — the shared roles table is missing its seed data.');
        }
        $pdo->prepare(
            'INSERT INTO users (name, phone, username, password_hash, role_id, status, is_protected)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $label . ' Owner',
            self::placeholderPhone(),
            self::OWNER_USERNAME,
            \password_hash($password, PASSWORD_BCRYPT),
            (int) $roleId,
            'active',
        ]);
    }

    /**
     * Phone is unique per tenant, so 'owner' accounts in different businesses
     * cannot share a placeholder. A short random suffix keeps them distinct until
     * the real number is entered.
     */
    private static function placeholderPhone(): string
    {
        return 'SA-' . \strtoupper(\bin2hex(\random_bytes(4)));
    }

    /** Readable but not guessable — it is read off the screen once and typed in. */
    public static function generatePassword(): string
    {
        $words = ['Harbour','Lantern','Copper','Meadow','Cobalt','Trellis','Juniper','Quartz',
                  'Bastion','Ironwood','Sentinel','Falcon','Granite','Beacon','Anchor','Summit'];
        return $words[\random_int(0, 15)] . '-' . $words[\random_int(0, 15)] . '-' . \random_int(10000, 99999);
    }
}
