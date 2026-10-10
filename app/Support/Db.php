<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

class Db
{
    /** Connection to the CURRENT TENANT's database — all business data. */
    private static ?PDO $pdo = null;

    /** Connection to the CONTROL database — tenants and superadmins only. */
    private static ?PDO $control = null;

    /**
     * The current tenant's database connection.
     *
     * Every business query in the app goes through here, and none of them name a
     * schema, so they all follow whichever tenant database is connected. That is
     * what keeps tenants isolated: it is enforced by the connection, not by a
     * WHERE clause that somebody has to remember.
     *
     * Throws when no tenant is selected — a deliberate hard failure, so a bug can
     * never silently read the wrong business's books.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (Tenant::check()) {
            return self::$pdo = self::connect(Tenant::database());
        }

        // No tenant selected. What that means depends on whether multi-tenancy is set up:
        //
        //  - Control plane configured -> this is a bug or a tampered session. Fail hard
        //    rather than guess, so a request can never silently read the wrong business.
        //  - No control plane -> this is still a single-tenant install (including the
        //    window between uploading this code and creating the control database), so
        //    fall back to DB_NAME and behave exactly as before.
        if (self::controlAvailable()) {
            throw new \RuntimeException(
                'No tenant selected for this request. A tenant must be chosen at sign-in, '
                . 'or entered from the superadmin console, before business data can be read.'
            );
        }

        return self::$pdo = self::connect((string) env('DB_NAME', 'gpv2_ims'));
    }

    /**
     * The control database: which tenants exist and who the superadmins are.
     * Holds no business data.
     *
     * Falls back to DB_NAME when CONTROL_DB_NAME is unset, which keeps a
     * pre-multi-tenancy install working until the control database is created.
     */
    public static function control(): PDO
    {
        if (self::$control instanceof PDO) {
            return self::$control;
        }
        $name = (string) env('CONTROL_DB_NAME', (string) env('DB_NAME', 'gpv2_ims'));
        return self::$control = self::connect($name);
    }

    /** True when a control database is configured and reachable. */
    public static function controlAvailable(): bool
    {
        try {
            self::control()->query('SELECT 1 FROM tenants LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Drop the tenant connection so the next query reconnects to a newly selected tenant. */
    public static function resetTenantConnection(): void
    {
        self::$pdo = null;
    }

    private static function connect(string $database): PDO
    {
        if (!Tenant::isValidDatabaseName($database)) {
            throw new \RuntimeException('Refusing to connect: invalid database name.');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            $database
        );
        return new PDO(
            $dsn,
            env('DB_USER', 'root'),
            env('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    /** Nesting depth — only the outermost call issues BEGIN/COMMIT on the PDO connection. */
    private static int $txnDepth = 0;

    /**
     * Run $fn inside a DB transaction. Re-entrant: nested calls execute the closure
     * inside the current transaction without issuing a second BEGIN (PDO/MySQL native
     * transactions don't nest). If any nested closure throws, the WHOLE outer transaction
     * is rolled back — we mark a "rollback only" flag so the outer level finalizes
     * with rollBack() instead of commit().
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $isOuter = self::$txnDepth === 0;
        if ($isOuter) {
            $pdo->beginTransaction();
        }
        self::$txnDepth++;
        try {
            $result = $fn($pdo);
            self::$txnDepth--;
            if ($isOuter) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            self::$txnDepth--;
            if ($isOuter && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
