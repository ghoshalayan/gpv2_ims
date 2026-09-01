<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * Manages per-user permission overrides on top of the user's role.
 *
 *   allowed = 1 → grant beyond the role
 *   allowed = 0 → deny something the role would otherwise grant
 *
 * Absence of a row → "no opinion" (fall through to role grant).
 */
class UserPermissionOverrideRepository
{
    /** @return array<string, bool> permission_key => allowed (override map) */
    public function mapFor(int $userId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT permission_key, allowed FROM user_permission_overrides WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['permission_key']] = (bool) (int) $r['allowed'];
        }
        return $out;
    }

    /**
     * Replace a user's overrides atomically. $grant + $deny are the keys to
     * record as allowed=1 / allowed=0. All other keys are cleared (no opinion).
     *
     * @param array<int, string> $grants
     * @param array<int, string> $denies
     */
    public function replaceOverrides(int $userId, array $grants, array $denies, int $updatedBy): void
    {
        Db::transaction(function () use ($userId, $grants, $denies, $updatedBy): void {
            Db::pdo()->prepare('DELETE FROM user_permission_overrides WHERE user_id = ?')
                     ->execute([$userId]);
            $stmt = Db::pdo()->prepare(
                'INSERT INTO user_permission_overrides (user_id, permission_key, allowed, updated_by)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($grants as $key) {
                $stmt->execute([$userId, $key, 1, $updatedBy]);
            }
            foreach ($denies as $key) {
                if (\in_array($key, $grants, true)) { continue; } // grant beats deny on dupes
                $stmt->execute([$userId, $key, 0, $updatedBy]);
            }
        });
    }

    public function deleteAllForUser(int $userId): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM user_permission_overrides WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}
