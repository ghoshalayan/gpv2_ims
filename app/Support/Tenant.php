<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Which tenant the current request belongs to.
 *
 * The app runs one codebase over several tenant databases. Every business table
 * lives inside a tenant database, so two tenants can hold the same customer,
 * product or supplier as completely unrelated records — isolation is a property
 * of the connection, not of remembering a WHERE clause.
 *
 * The selected tenant lives in the session and is re-read on every request. It is
 * set by signing in to a tenant, or by a superadmin entering one.
 *
 * SECURITY: the database name is never taken from user input. It is read from the
 * `tenants` table in the control database and additionally format-checked here
 * before it reaches a DSN, so a tampered session cannot point the app at an
 * arbitrary database.
 */
class Tenant
{
    private const SESSION_KEY = '_tenant';

    /** Cached for the life of the request only — PHP is shared-nothing. */
    private static ?array $current = null;

    /** A tenant is selected (a tenant user signed in, or a superadmin entered one). */
    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['database_name']);
    }

    /**
     * The active tenant, or null when none is selected — which is the normal state
     * for a superadmin sitting in the console.
     *
     * @return array{id:int, tenant_key:string, label:string, database_name:string}|null
     */
    public static function current(): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $t = $_SESSION[self::SESSION_KEY] ?? null;
        if (!\is_array($t) || !isset($t['database_name'])) {
            return null;
        }
        // Re-validate on every request. A session is client-held state; the database
        // name must look like a database name before it is used to build a DSN.
        if (!self::isValidDatabaseName((string) $t['database_name'])) {
            self::clear();
            return null;
        }
        return self::$current = $t;
    }

    /** The active tenant, or a hard failure. Used where a tenant is required. */
    public static function require(): array
    {
        $t = self::current();
        if ($t === null) {
            throw new \RuntimeException(
                'No tenant selected for this request. A tenant must be chosen at sign-in, '
                . 'or entered from the superadmin console, before business data can be read.'
            );
        }
        return $t;
    }

    public static function key(): ?string
    {
        return self::current()['tenant_key'] ?? null;
    }

    public static function label(): ?string
    {
        return self::current()['label'] ?? null;
    }

    public static function database(): string
    {
        return self::require()['database_name'];
    }

    public static function id(): ?int
    {
        $t = self::current();
        return $t === null ? null : (int) $t['id'];
    }

    /**
     * Select a tenant for this session. Expects a row already read from the control
     * database — never raw user input.
     */
    public static function select(array $tenantRow): void
    {
        $db = (string) ($tenantRow['database_name'] ?? '');
        if (!self::isValidDatabaseName($db)) {
            throw new \RuntimeException("Refusing to select tenant with an invalid database name.");
        }
        $_SESSION[self::SESSION_KEY] = [
            'id'            => (int) $tenantRow['id'],
            'tenant_key'    => (string) $tenantRow['tenant_key'],
            'label'         => (string) $tenantRow['label'],
            'database_name' => $db,
        ];
        self::$current = null;   // force a re-read
        // Keep the bootstrap guard's stamp in step, so a legitimate selection is not
        // mistaken for tampering. Any change to _tenant that does NOT come through
        // here or clear() will fail the stamp check on the next request.
        $_SESSION['_tenant_stamp'] = (string) $tenantRow['tenant_key'];
        Db::resetTenantConnection();
    }

    /** Leave the current tenant — a superadmin returning to the console. */
    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        $_SESSION['_tenant_stamp'] = null;
        self::$current = null;
        Db::resetTenantConnection();
    }

    /**
     * Database names are `[a-z0-9_]` by convention here (MilesWeb prefixes them,
     * e.g. `srasinge1_gp`). Anything else is rejected rather than passed to a DSN.
     */
    public static function isValidDatabaseName(string $name): bool
    {
        return $name !== ''
            && \strlen($name) <= 64
            && \preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
    }
}
