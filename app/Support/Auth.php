<?php
declare(strict_types=1);

namespace App\Support;

class Auth
{
    /**
     * The 'admin' role key is the only hard-coded role string in the system —
     * it identifies the master-switch role (Permissions::can() short-circuits
     * for it). All other roles are dynamic (created via the Roles UI).
     *
     * ROLE_SUBADMIN/ROLE_CASHIER are kept as legacy convenience constants
     * (used by route-level RBAC for the seeded default roles); they don't
     * grant any privilege beyond what permissions allow.
     */
    public const ROLE_ADMIN    = 'admin';
    public const ROLE_SUBADMIN = 'subadmin';
    public const ROLE_CASHIER  = 'cashier';

    private const SESSION_KEY = '_auth_user';

    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function roleId(): ?int
    {
        $r = self::user()['role_id'] ?? null;
        return $r === null ? null : (int) $r;
    }

    public static function is(string ...$roles): bool
    {
        $current = self::role();
        return $current !== null && \in_array($current, $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::role() === self::ROLE_ADMIN;
    }

    /**
     * Is there an identity entitled to work inside a business?
     *
     * Either one of that business's own users, OR a superadmin who has entered
     * it. A superadmin has no user row in any business, so Auth::check() alone
     * is the wrong question everywhere outside the sign-in itself.
     *
     * Kept in one place deliberately: this test was previously written out by
     * hand in each middleware, they drifted, and a superadmin ended up locked
     * out of every business page by the one copy that was missed.
     */
    public static function signedIn(): bool
    {
        return self::check() || (Superadmin::check() && Tenant::check());
    }

    /**
     * Does the current identity hold administrator powers in this business?
     * A superadmin outranks every role a business can define.
     */
    public static function actsAsAdmin(): bool
    {
        return self::isAdmin() || (Superadmin::check() && Tenant::check());
    }

    /**
     * Name, username and role for display. Falls back to the superadmin's own
     * identity, so screens do not have to special-case a null user.
     *
     * @return array{name:string, username:string, role:string}
     */
    public static function displayUser(): array
    {
        $u = self::user();
        if ($u !== null) {
            return [
                'name'     => (string) ($u['name'] ?? ''),
                'username' => (string) ($u['username'] ?? ''),
                'role'     => (string) ($u['role_label'] ?? $u['role'] ?? ''),
            ];
        }
        $sa = Superadmin::user();
        if ($sa !== null) {
            return [
                'name'     => (string) $sa['name'],
                'username' => (string) $sa['username'],
                'role'     => 'System Administrator',
            ];
        }
        return ['name' => '', 'username' => '', 'role' => ''];
    }

    public static function login(array $user): void
    {
        \session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id'       => (int) $user['id'],
            'name'     => $user['name'],
            'username' => $user['username'],
            'role_id'  => (int) $user['role_id'],
            'role'     => (string) $user['role'],     // role_key — the public name
            'role_label' => (string) ($user['role_label'] ?? $user['role']),
        ];
        Csrf::rotate();
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        Csrf::rotate();
        \session_regenerate_id(true);
    }
}
