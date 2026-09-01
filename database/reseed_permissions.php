<?php
declare(strict_types=1);

/**
 * Re-seed role permissions to the corrected defaults.
 *
 * Run this ONCE after deploying the route-level permission enforcement change.
 * It wipes role_permissions and re-seeds from MenuPermissionRegistry's corrected
 * defaults (which mirror the legacy role tiers exactly). Necessary because the
 * earlier defaults over-granted subadmin (e.g. users/reports) — harmless while
 * permissions only drove the sidebar, but a security issue now that they gate
 * routes. The plain seeder is additive (INSERT IGNORE) and cannot remove the
 * stale over-grants, so we delete + reseed here.
 *
 * Safe: subadmin/cashier permissions were never enforced before this change, so
 * nothing anyone relied on is altered. The 'admin' role is unaffected
 * (master-switch). Any custom roles' grants ARE wiped — re-grant them via the
 * Role Permissions tree afterwards (run BEFORE creating custom roles ideally).
 *
 * Usage:  php database/reseed_permissions.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\RolePermissionService;

if (PHP_SAPI !== 'cli') {
    \http_response_code(403);
    exit("Run from CLI only.\n");
}

try {
    (new RolePermissionService())->resetToDefaults();
    echo "Role permissions reset to corrected defaults.\n";
} catch (\Throwable $e) {
    \fwrite(STDERR, "Failed: " . $e->getMessage() . "\n");
    exit(1);
}
