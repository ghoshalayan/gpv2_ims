<?php
declare(strict_types=1);

/**
 * Repair the `migrations` tracking table to match an ALREADY-COMPLETE schema.
 *
 * Use ONLY when `migrate.php` fails with "table already exists" because the
 * tracking log drifted from the real schema — e.g. a database that was copied
 * or restored without its `migrations` rows. It marks every migration file as
 * applied (INSERT IGNORE) WITHOUT running them.
 *
 * SAFETY: this is only correct when the schema is genuinely complete. The script
 * verifies that by checking a sentinel from the LATEST migration (the
 * entry_unit_id column from 0050 + the units table from 0048). If those are
 * missing, it refuses — because then there ARE real pending migrations and you
 * should run `migrate.php` on a clean database instead.
 *
 * This is a local-dev convenience; it is NOT part of normal deploys.
 *
 * Usage:  php database/sync_migration_log.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Db;

if (PHP_SAPI !== 'cli') {
    \http_response_code(403);
    exit("Run from CLI only.\n");
}

$pdo = Db::pdo();

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $s->execute([$t]);
    return (bool) $s->fetchColumn();
};
$columnExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $s->execute([$t, $c]);
    return (bool) $s->fetchColumn();
};

// Sentinel: schema must look complete through the latest migration.
if (!$tableExists('units') || !$columnExists('sales_invoice_lines', 'entry_unit_id')) {
    \fwrite(STDERR,
        "Refusing: the schema does not look complete (missing artifacts from the latest migrations).\n" .
        "This tool only repairs a drifted log on an ALREADY-migrated database.\n" .
        "On a fresh/partial DB, run `php database/migrate.php` instead.\n"
    );
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$files = \glob(__DIR__ . '/migrations/*.sql') ?: [];
\sort($files);
$stmt = $pdo->prepare('INSERT IGNORE INTO migrations (filename) VALUES (?)');
$added = 0;
foreach ($files as $f) {
    $stmt->execute([\basename($f)]);
    $added += $stmt->rowCount();
}

echo "Migration log synced. Marked {$added} previously-untracked migration(s) as applied.\n";
echo "`php database/migrate.php` should now report \"Nothing to migrate.\"\n";
