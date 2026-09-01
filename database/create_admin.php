<?php
declare(strict_types=1);

/**
 * Bootstrap the first admin user.
 *
 * Usage:
 *   php database/create_admin.php
 *     [--username=admin] [--password=admin123] [--name="Administrator"]
 *     [--phone=0000000000] [--email=admin@example.com]
 *
 * Idempotent: if a user with the given username already exists, the script exits without changes.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\RolePermissionService;
use App\Support\Db;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run from CLI only.\n");
}

$args = parseArgs($argv);
$username = $args['username'] ?? 'admin';
$password = $args['password'] ?? 'admin123';
$name     = $args['name']     ?? 'Administrator';
$phone    = $args['phone']    ?? '0000000000';
$email    = $args['email']    ?? 'admin@example.com';

try {
    $pdo = Db::pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "Cannot connect to database: " . $e->getMessage() . "\n");
    exit(1);
}

// Resolve admin role_id (created by migration 0043). If missing, the migration
// hasn't been applied — bail with a clear error.
$adminRoleId = (int) $pdo->query("SELECT id FROM roles WHERE role_key = 'admin' LIMIT 1")->fetchColumn();
if ($adminRoleId === 0) {
    fwrite(STDERR, "Admin role not found. Run database/migrate.php first.\n");
    exit(1);
}

$check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$check->execute([$username]);
if ($check->fetchColumn() !== false) {
    echo "User '{$username}' already exists. Skipping user creation.\n";
} else {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $insert = $pdo->prepare(
        'INSERT INTO users (name, phone, email, username, password_hash, role_id, status)
         VALUES (?, ?, ?, ?, ?, ?, "active")'
    );
    $insert->execute([$name, $phone, $email, $username, $hash, $adminRoleId]);
    echo "Admin user created.\n";
    echo "  username: {$username}\n";
    echo "  password: {$password}\n";
    echo "\n*** SECURITY: change this password immediately after first login. ***\n";
}

// Seed default role permissions (idempotent — INSERT IGNORE skips existing rows).
try {
    $written = (new RolePermissionService())->seedDefaults();
    echo "Role permissions seeded ({$written} cells written/skipped).\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Warning: could not seed role permissions: " . $e->getMessage() . "\n");
}

function parseArgs(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$k, $v] = explode('=', substr($arg, 2), 2);
            $out[$k] = $v;
        }
    }
    return $out;
}
