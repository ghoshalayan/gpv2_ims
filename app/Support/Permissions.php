<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Per-request, user-scoped cache over role_permissions + user_permission_overrides.
 *
 * Resolution algorithm:
 *   1. If the user has role 'admin' → allowed (master switch).
 *   2. Otherwise, allowed-set = role_permissions(role_id, allowed=1).
 *   3. Layer user_permission_overrides:
 *        allowed=1 → add to set (grant beyond role)
 *        allowed=0 → remove from set (deny override)
 *
 * Permission key is 'resource.ability' (e.g. 'sales_invoices.view'). Resources
 * and supported abilities come from MenuPermissionRegistry — this class only
 * resolves whether the current user holds a particular key.
 */
class Permissions
{
    /** @var array<string, true>|null cache of "resource.ability" keys the user is allowed. */
    private static ?array $allowed = null;
    private static ?int   $loadedUserId = null;

    public static function can(string $resourceKey, string $ability = 'view'): bool
    {
        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }
        if (Auth::isAdmin()) {
            return true;
        }
        if (self::$loadedUserId !== $userId || self::$allowed === null) {
            self::loadFor($userId);
        }
        return isset(self::$allowed[$resourceKey . '.' . $ability]);
    }

    public static function canView(string $resourceKey): bool
    {
        return self::can($resourceKey, 'view');
    }

    /** Re-read after a permission save so the editing admin's session reflects the new grid. */
    public static function flush(): void
    {
        self::$allowed = null;
        self::$loadedUserId = null;
    }

    /**
     * Resolve a user's effective permission set offline (no session). Used by the
     * user-overrides editor to show "your role grants X / your override layers Y / net = Z".
     *
     * @return array{role_grants: array<int, string>, overrides: array<string, bool>, effective: array<int, string>}
     */
    public static function resolveForUser(int $userId): array
    {
        $pdo = Db::pdo();

        $roleGrants = [];
        $stmt = $pdo->prepare(
            'SELECT rp.permission_key
             FROM role_permissions rp
             JOIN users u ON u.role_id = rp.role_id
             WHERE u.id = ? AND rp.allowed = 1'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            $roleGrants[$key] = true;
        }

        $overrides = [];
        $stmt = $pdo->prepare(
            'SELECT permission_key, allowed FROM user_permission_overrides WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $overrides[$row['permission_key']] = (bool) (int) $row['allowed'];
        }

        $effective = $roleGrants;
        foreach ($overrides as $key => $allowed) {
            if ($allowed) { $effective[$key] = true; }
            else          { unset($effective[$key]); }
        }

        return [
            'role_grants' => \array_keys($roleGrants),
            'overrides'   => $overrides,
            'effective'   => \array_keys($effective),
        ];
    }

    private static function loadFor(int $userId): void
    {
        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'SELECT rp.permission_key
             FROM role_permissions rp
             JOIN users u ON u.role_id = rp.role_id
             WHERE u.id = ? AND rp.allowed = 1'
        );
        $stmt->execute([$userId]);
        $allowed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            $allowed[$key] = true;
        }

        $stmt = $pdo->prepare(
            'SELECT permission_key, allowed FROM user_permission_overrides WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int) $row['allowed'] === 1) {
                $allowed[$row['permission_key']] = true;
            } else {
                unset($allowed[$row['permission_key']]);
            }
        }

        self::$allowed = $allowed;
        self::$loadedUserId = $userId;
    }
}
