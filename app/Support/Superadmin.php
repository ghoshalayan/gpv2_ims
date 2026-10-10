<?php
declare(strict_types=1);

namespace App\Support;

/**
 * The superadmin session — an account that sits ABOVE every tenant.
 *
 * Deliberately a separate session key from Auth (tenant users), because the two
 * are different kinds of identity and can be held at once: a superadmin who has
 * entered a tenant is a superadmin *and* currently operating inside that tenant.
 *
 * A superadmin is not a user of any business. It exists to manage tenants and,
 * when needed, enter one — which is audited in the control database.
 */
class Superadmin
{
    private const SESSION_KEY = '_superadmin';

    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    /** @return array{id:int, name:string, username:string}|null */
    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u === null ? null : (int) $u['id'];
    }

    public static function username(): ?string
    {
        return self::user()['username'] ?? null;
    }

    public static function login(array $row): void
    {
        \session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id'       => (int) $row['id'],
            'name'     => (string) $row['name'],
            'username' => (string) $row['username'],
        ];
        Csrf::rotate();
    }

    /** Signing out of the control plane also leaves any tenant that was entered. */
    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        Tenant::clear();
        Auth::logout();
    }
}
