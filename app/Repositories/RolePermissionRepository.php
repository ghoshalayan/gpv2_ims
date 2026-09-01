<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * Manages role-level permission grants (role_permissions).
 *
 * Convention: only "allowed=1" rows are stored. Absence of a row = deny.
 * (Per-user denials live in user_permission_overrides; see UserPermissionOverrideRepository.)
 */
class RolePermissionRepository
{
    /** @return array<int, string> Permission keys granted to a role. */
    public function grantsFor(int $roleId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT permission_key FROM role_permissions WHERE role_id = ? AND allowed = 1'
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Replace a role's grants atomically. The submitted set is the new authoritative
     * list — any row not in $permissionKeys is removed, missing rows are inserted.
     *
     * @param array<int, string> $permissionKeys
     */
    public function replaceGrants(int $roleId, array $permissionKeys, int $updatedBy): void
    {
        Db::transaction(function () use ($roleId, $permissionKeys, $updatedBy): void {
            Db::pdo()->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
            if ($permissionKeys === []) { return; }
            $stmt = Db::pdo()->prepare(
                'INSERT INTO role_permissions (role_id, permission_key, allowed, updated_by)
                 VALUES (?, ?, 1, ?)'
            );
            foreach ($permissionKeys as $key) {
                $stmt->execute([$roleId, $key, $updatedBy]);
            }
        });
    }

    public function deleteAllForRole(int $roleId): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM role_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);
    }

    public function isEmpty(): bool
    {
        return (int) Db::pdo()->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn() === 0;
    }
}
