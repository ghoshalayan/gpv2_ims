<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Which business the current request belongs to.
 *
 * All tenants share one database. Every business table is a view filtered by the
 * `@app_tenant` session variable, which Db binds from the id held here — so two
 * tenants can hold the same customer, product or supplier as entirely unrelated
 * records, and no query in the app has to mention a tenant at all.
 *
 * The selection lives in the session and is re-read each request. It is set by
 * signing in to a business, or by a superadmin entering one.
 */
class Tenant
{
    private const SESSION_KEY = '_tenant';

    /** A business is selected for this request. */
    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    /**
     * The active tenant, or null when none is selected — the normal state for a
     * superadmin sitting in the console, and for the login page.
     *
     * @return array{id:int, tenant_key:string, label:string}|null
     */
    public static function current(): ?array
    {
        $t = $_SESSION[self::SESSION_KEY] ?? null;
        return (\is_array($t) && isset($t['id'])) ? $t : null;
    }

    public static function id(): ?int
    {
        $t = self::current();
        return $t === null ? null : (int) $t['id'];
    }

    public static function key(): ?string
    {
        return self::current()['tenant_key'] ?? null;
    }

    public static function label(): ?string
    {
        return self::current()['label'] ?? null;
    }

    /**
     * Select a business for this session. Expects a row already read from the
     * `tenants` table — never raw user input.
     */
    public static function select(array $row): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'id'         => (int) $row['id'],
            'tenant_key' => (string) $row['tenant_key'],
            'label'      => (string) $row['label'],
        ];
        // Keep the bootstrap guard's stamp in step, so a legitimate selection is
        // not mistaken for tampering.
        $_SESSION['_tenant_stamp'] = (string) $row['tenant_key'];
        Db::rebindTenant();
    }

    /** Leave the current business — a superadmin returning to the console. */
    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        $_SESSION['_tenant_stamp'] = null;
        Db::rebindTenant();
    }

    /**
     * Run a callback with a different tenant bound, then restore.
     *
     * Used when seeding a newly created business: the superadmin is not "in" that
     * tenant, but its opening rows have to be written as that tenant. Restores the
     * previous selection even if the callback throws, so a failed seed cannot
     * leave the session pointing somewhere unexpected.
     */
    public static function runAs(array $row, callable $fn): mixed
    {
        $previous = self::current();
        self::select($row);
        try {
            return $fn();
        } finally {
            if ($previous === null) {
                self::clear();
            } else {
                self::select($previous);
            }
        }
    }
}
