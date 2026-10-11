<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * One database, many tenants.
 *
 * Every business table is a VIEW over `<table>__data` filtered by `app_tenant()`,
 * which reads the `@app_tenant` session variable. So the tenant is bound to the
 * CONNECTION, and all 869 of the app's queries are filtered without any of them
 * mentioning a tenant. Isolation is enforced by the database rather than by a
 * WHERE clause somebody has to remember to write.
 *
 * It fails closed: with no tenant bound, `@app_tenant` is NULL, the views match
 * nothing, and the insert triggers refuse to write. A missing tenant shows up as
 * an empty screen or a rejected save, never as another business's data.
 *
 * The control tables (`tenants`, `superadmins`, `superadmin_activity`) are real
 * tables, not views, so they stay readable whatever the bound tenant is — which
 * is what lets the login page list businesses before anyone has signed in.
 */
class Db
{
    private static ?PDO $pdo = null;

    /**
     * Which tenant the live connection is currently bound to.
     * `false` means "not yet bound"; null is a real value meaning "no tenant".
     */
    private static int|null|false $bound = false;

    private static ?bool $multiTenant = null;

    /**
     * The database connection, with `@app_tenant` bound to the selected tenant.
     *
     * Re-binds whenever the selection changes — a superadmin entering a tenant
     * switches tenant mid-request, and the same connection has to follow.
     */
    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            self::$pdo   = self::connect();
            self::$bound = false;
        }

        $want = self::multiTenant() ? Tenant::id() : null;
        if (self::$bound !== $want) {
            // Integer-cast, never interpolated from user input.
            self::$pdo->exec('SET @app_tenant = ' . ($want === null ? 'NULL' : (int) $want));
            self::$bound = $want;
        }
        return self::$pdo;
    }

    /**
     * The control tables live in this same database and are not views, so this is
     * the same connection. Kept as a separate name so call sites read clearly and
     * so a future move to separate databases only has to change this method.
     */
    public static function control(): PDO
    {
        return self::pdo();
    }

    /**
     * Has the multi-tenancy conversion been applied to this database?
     *
     * Detected rather than configured, so the same code runs before and after the
     * conversion: no `tenants` table means this is still a single-business
     * install, the tenant is never bound, and the plain tables behave as always.
     */
    public static function multiTenant(): bool
    {
        if (self::$multiTenant !== null) {
            return self::$multiTenant;
        }
        try {
            self::rawPdo()->query('SELECT 1 FROM tenants LIMIT 1');
            return self::$multiTenant = true;
        } catch (\Throwable) {
            return self::$multiTenant = false;
        }
    }

    /** Force the next query to re-bind — called when the selected tenant changes. */
    public static function rebindTenant(): void
    {
        self::$bound = false;
    }

    /** The connection without touching tenant binding (used by multiTenant()). */
    private static function rawPdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            self::$pdo   = self::connect();
            self::$bound = false;
        }
        return self::$pdo;
    }

    private static function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'gpv2_ims')
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
     * is rolled back â€” we mark a "rollback only" flag so the outer level finalizes
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
