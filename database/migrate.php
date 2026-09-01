<?php
declare(strict_types=1);

/**
 * Migration runner.
 *
 * Usage:   php database/migrate.php
 *
 * - Creates a `migrations` tracking table on first run.
 * - Scans database/migrations/*.sql in lexical order.
 * - Applies every file not yet recorded; records on success.
 * - Splits each file on `;` so multi-statement migrations work.
 * - On failure, prints the offending statement and exits non-zero.
 *   (Note: MySQL implicitly commits each DDL statement, so partial
 *   migrations can leave the DB partly-changed. Make migrations
 *   idempotent where possible — e.g. CREATE TABLE IF NOT EXISTS.)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Db;

try {
    $pdo = Db::pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "Cannot connect to database: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Check .env (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD) and that the database exists.\n");
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$appliedSet = array_flip($applied);

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

$pending = array_filter($files, fn ($f) => !isset($appliedSet[basename($f)]));

if ($pending === []) {
    echo "Nothing to migrate. (" . count($applied) . " already applied)\n";
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    echo "Applying {$name} ... ";

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "SKIPPED (empty)\n";
        continue;
    }

    $statements = splitSqlStatements($sql);

    $current = '';
    try {
        foreach ($statements as $stmt) {
            $current = $stmt;
            $pdo->exec($stmt);
        }
        $insert = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
        $insert->execute([$name]);
        echo "OK\n";
    } catch (\Throwable $e) {
        echo "FAILED\n";
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        fwrite(STDERR, "While running:\n" . $current . "\n");
        exit(1);
    }
}

echo "Done. " . count($pending) . " applied.\n";

/**
 * Strip SQL comments, then split on semicolons that aren't inside quotes.
 * Naive but sufficient for our DDL migrations.
 */
function splitSqlStatements(string $sql): array
{
    // Strip /* ... */ block comments
    $sql = preg_replace('!/\*.*?\*/!s', '', $sql) ?? $sql;
    // Strip -- and # line comments anywhere on a line (full-line OR inline trailing).
    // MySQL requires `-- ` (dash + dash + space) for inline comments.
    $sql = preg_replace('/(--\s|#)[^\n\r]*/', '', $sql) ?? $sql;

    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($c === "'" && !$inDouble && !$inBacktick && $prev !== '\\') {
            $inSingle = !$inSingle;
        } elseif ($c === '"' && !$inSingle && !$inBacktick && $prev !== '\\') {
            $inDouble = !$inDouble;
        } elseif ($c === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        }

        if ($c === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $trim = trim($buffer);
            if ($trim !== '') {
                $statements[] = $trim;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $c;
    }

    $trim = trim($buffer);
    if ($trim !== '') {
        $statements[] = $trim;
    }

    return $statements;
}
