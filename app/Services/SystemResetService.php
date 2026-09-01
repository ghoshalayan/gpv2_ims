<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\ActivityLog;
use App\Support\Db;
use PDO;

/**
 * Hard reset — truncates every user table and re-seeds defaults.
 *
 * Preserves only the `migrations` table (so we don't accidentally re-run any
 * migration on next deploy). Everything else — masters, transactions, audit
 * log — is wiped.
 *
 * After truncation, re-seeds:
 *   1. business_profile (singleton id=1 placeholder, mirrors 0011 migration seed)
 *   2. system_settings  (defaults, mirrors 0011 migration seed)
 *   3. role_permissions (via RolePermissionService::seedDefaults)
 *   4. users            (one admin from supplied creds, or admin/admin123)
 *
 * Logs a single 'purge' activity_log entry AFTER reseed so the new audit trail
 * starts with the reset event.
 *
 * NOTE: TRUNCATE is auto-commit in MySQL — cannot be wrapped in a transaction.
 * If a TRUNCATE midway fails, the DB is left half-cleared. We re-enable
 * FOREIGN_KEY_CHECKS in the catch path so the connection stays usable.
 */
class SystemResetService
{
    public function __construct(
        private RolePermissionService $perms = new RolePermissionService(),
    ) {}

    /**
     * @param array{username:string, password:string, name:string, phone:string, email:?string} $newAdmin
     */
    public function reset(array $newAdmin): void
    {
        $pdo = Db::pdo();
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($this->listTables($pdo) as $table) {
                if ($table === 'migrations') {
                    continue;
                }
                $pdo->exec('TRUNCATE TABLE `' . $table . '`');
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->seedBusinessProfile($pdo);
        $this->seedSystemSettings($pdo);
        $this->seedRoles($pdo);              // roles must exist before permissions / users
        $this->perms->seedDefaults();
        $this->seedUnits($pdo);              // units master must exist before any product can be created
        $adminId = $this->seedAdminUser($pdo, $newAdmin);

        ActivityLog::log('purge', 'system_reset', null, [
            'label'     => 'Hard reset',
            'user_id'   => $adminId,
            'user_name' => $newAdmin['name'],
            'notes'     => 'All data wiped; defaults reseeded; admin user recreated.',
        ]);
    }

    /** Seed the default role catalogue. Mirrors migration 0043's INSERT. */
    private function seedRoles(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO roles (role_key, label, description, is_system, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $defaults = [
            ['admin',    'Administrator',     'Full access. Always allowed (master switch). Cannot be deleted.', 1, 1],
            ['subadmin', 'Sub-Administrator', 'Manages day-to-day operations; cannot reset/delete or change settings.', 0, 10],
            ['cashier',  'Cashier',           'Front-desk POS staff; sales workflow only.', 0, 20],
        ];
        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    }

    /**
     * Seed the default units master. Mirrors migration 0048's seed: insert each
     * unit, then wire converted units to their base + factor, then self-reference
     * the base units so every row has a non-null base_unit_id.
     */
    private function seedUnits(PDO $pdo): void
    {
        // [code, name, family, decimals, baseCode|null, factorToBase]
        $defs = [
            ['PCS',   'Pieces',           'count',  0, null,  1],
            ['DOZEN', 'Dozen',            'count',  0, 'PCS', 12],
            ['KG',    'Kilogram',         'mass',   3, null,  1],
            ['G',     'Gram',             'mass',   3, 'KG',  0.001],
            ['LTR',   'Litre',            'volume', 3, null,  1],
            ['ML',    'Millilitre',       'volume', 3, 'LTR', 0.001],
            ['M',     'Metre',            'length', 3, null,  1],
            ['CM',    'Centimetre',       'length', 2, 'M',   0.01],
            ['CASE',  'Case (packaging)', 'pack',   0, null,  1],
            ['BOX',   'Box (packaging)',  'pack',   0, null,  1],
            ['PACK',  'Pack (packaging)', 'pack',   0, null,  1],
        ];

        $insert = $pdo->prepare(
            'INSERT INTO units (code, name, family, base_unit_id, factor_to_base, decimals)
             VALUES (?, ?, ?, NULL, ?, ?)'
        );
        $ids = [];
        foreach ($defs as [$code, $name, $family, $dec, , $factor]) {
            $insert->execute([$code, $name, $family, $factor, $dec]);
            $ids[$code] = (int) $pdo->lastInsertId();
        }

        $setBase = $pdo->prepare('UPDATE units SET base_unit_id = ? WHERE id = ?');
        foreach ($defs as [$code, , , , $baseCode, ]) {
            // Converted units point at their base; base units self-reference.
            $baseId = $baseCode !== null ? $ids[$baseCode] : $ids[$code];
            $setBase->execute([$baseId, $ids[$code]]);
        }
    }

    /** @return array<int, string> */
    private function listTables(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function seedBusinessProfile(PDO $pdo): void
    {
        $pdo->prepare(
            "INSERT INTO business_profile
             (id, legal_name, display_name, state, financial_year_start_month)
             VALUES (1, 'Your Business Legal Name', 'GP ENTERPRISE', 'West Bengal', 4)"
        )->execute();
    }

    private function seedSystemSettings(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, value, description) VALUES (?, ?, ?)'
        );
        $defaults = [
            ['commission_accrues_on', 'invoice_finalization',
             'When commission posts: invoice_finalization | invoice_payment | manual_only'],
            ['enable_round_off', '1',
             'Round invoice grand total to nearest rupee (1 = yes, 0 = no)'],
            ['low_stock_dashboard_widget', '1',
             'Show low-stock items on dashboard (1 = yes, 0 = no)'],
        ];
        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    }

    /**
     * @param array{username:string, password:string, name:string, phone:string, email:?string} $admin
     */
    private function seedAdminUser(PDO $pdo, array $admin): int
    {
        $adminRoleId = (int) $pdo->query(
            "SELECT id FROM roles WHERE role_key = 'admin' LIMIT 1"
        )->fetchColumn();
        if ($adminRoleId === 0) {
            throw new \RuntimeException('Admin role missing after reseed — cannot create admin user.');
        }
        $hash = \password_hash($admin['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, phone, email, username, password_hash, role_id, status)
             VALUES (?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([
            $admin['name'],
            $admin['phone'],
            $admin['email'],
            $admin['username'],
            $hash,
            $adminRoleId,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
