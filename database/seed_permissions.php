<?php
declare(strict_types=1);

/**
 * ADDITIVE permission seeder — grants any permission keys that exist in
 * MenuPermissionRegistry but are not yet recorded for a role.
 *
 * Run this after deploying a release that introduces new permission keys, so the
 * non-admin roles pick up their defaults. (The 'admin' role is a master switch and
 * always sees everything, so it needs nothing here.)
 *
 * SAFE TO RUN REPEATEDLY. It uses RolePermissionService::seedDefaults(), which only
 * INSERTs the keys a role is missing — existing and hand-customised grants are left
 * exactly as they are, and nothing is ever revoked.
 *
 * >>> This is NOT reseed_permissions.php. <<<
 * That script DELETES every row and re-seeds from defaults, wiping any custom role
 * grants. Never run it on production unless you intend exactly that.
 *
 * Usage:  php database/seed_permissions.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\RolePermissionService;

if (PHP_SAPI !== 'cli') {
    \http_response_code(403);
    exit("Run from CLI only.\n");
}

try {
    $written = (new RolePermissionService())->seedDefaults();
    if ($written === 0) {
        echo "Nothing to grant — every role already holds its default permissions.\n";
    } else {
        echo "Granted {$written} missing permission(s). Existing grants untouched.\n";
    }
    exit(0);
} catch (\Throwable $e) {
    \fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
