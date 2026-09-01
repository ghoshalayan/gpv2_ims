<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\RolePermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserPermissionOverrideRepository;
use App\Support\ActivityLog;
use App\Support\MenuPermissionRegistry;
use App\Support\Permissions;

class RolePermissionService
{
    public function __construct(
        private RolePermissionRepository       $roleGrants  = new RolePermissionRepository(),
        private UserPermissionOverrideRepository $userOverrides = new UserPermissionOverrideRepository(),
        private RoleRepository                 $roles       = new RoleRepository(),
    ) {}

    /**
     * Idempotent — seeds default grants for the seeded default roles
     * (admin / subadmin / cashier). Only writes rows for roles that exist,
     * so it's safe to call after the migration has populated them.
     *
     * Existing rows for a role are preserved — new ones are inserted via
     * INSERT IGNORE so customised grants survive a re-seed.
     */
    public function seedDefaults(): int
    {
        $written = 0;
        $defaults = MenuPermissionRegistry::defaultGrantsByRoleKey();
        foreach ($defaults as $roleKey => $permissionKeys) {
            $role = $this->roles->findByKey($roleKey);
            if ($role === null) { continue; }
            $existing = \array_flip($this->roleGrants->grantsFor((int) $role['id']));
            $missing = [];
            foreach ($permissionKeys as $key) {
                if (!isset($existing[$key])) { $missing[] = $key; }
            }
            if ($missing !== []) {
                // Append (don't replace) — preserve admin's customisation.
                $this->insertManyAllowed((int) $role['id'], $missing);
                $written += count($missing);
            }
        }
        Permissions::flush();
        return $written;
    }

    /** Wipe all role permission rows and re-seed defaults. Used by SystemResetService. */
    public function resetToDefaults(): void
    {
        \App\Support\Db::pdo()->exec('DELETE FROM role_permissions');
        $this->seedDefaults();
    }

    /**
     * Build the tree-view editor payload for a single role.
     *
     * @return array{
     *     role: array<string, mixed>,
     *     tree: array<string, array<string, array<string, array{label:string, abilities:array<int,string>, section:string, group:?string}>>>,
     *     ability_labels: array<string, string>,
     *     granted: array<string, true>,
     * }
     */
    public function buildRoleEditor(int $roleId): array
    {
        $role = $this->roles->findById($roleId);
        if ($role === null) {
            throw new \RuntimeException("Role #{$roleId} not found.");
        }
        $granted = \array_flip($this->roleGrants->grantsFor($roleId));
        return [
            'role'           => $role,
            'tree'           => MenuPermissionRegistry::tree(),
            'ability_labels' => MenuPermissionRegistry::ABILITY_LABELS,
            'granted'        => $granted,
        ];
    }

    /**
     * Save posted permissions for a role. Posted form has flat keys like
     *   permissions[users.view]=1
     *   permissions[users.create]=1
     * — anything not present is treated as denied (cleared from the role).
     *
     * Admin role is special: we still write rows (so the UI shows them as
     * checked) but the master switch in Permissions::can() makes them moot.
     */
    public function saveRoleGrants(int $roleId, array $checkedFlat, int $updatedBy): int
    {
        $valid = MenuPermissionRegistry::flatPermissionSet();
        $keep  = [];
        foreach ($checkedFlat as $key => $_) {
            if (isset($valid[$key])) { $keep[] = (string) $key; }
        }
        $this->roleGrants->replaceGrants($roleId, $keep, $updatedBy);

        Permissions::flush();
        ActivityLog::log('update', 'role_permissions', $roleId, [
            'label' => 'role grants saved',
            'notes' => count($keep) . ' permissions',
        ]);
        return count($keep);
    }

    /**
     * Build the tree-view editor payload for a single user (override layer).
     *
     * @return array{
     *     user: array<string, mixed>,
     *     tree: array<string, array<string, array<string, array{label:string, abilities:array<int,string>, section:string, group:?string}>>>,
     *     ability_labels: array<string, string>,
     *     role_grants: array<string, true>,
     *     overrides:   array<string, bool>,
     *     effective:   array<string, true>,
     * }
     */
    public function buildUserOverrideEditor(int $userId): array
    {
        $resolved   = Permissions::resolveForUser($userId);
        $roleGrants = \array_flip($resolved['role_grants']);
        $effective  = \array_flip($resolved['effective']);
        return [
            'tree'           => MenuPermissionRegistry::tree(),
            'ability_labels' => MenuPermissionRegistry::ABILITY_LABELS,
            'role_grants'    => $roleGrants,
            'overrides'      => $resolved['overrides'],
            'effective'      => $effective,
        ];
    }

    /**
     * Save user overrides. Posted form uses three states per permission:
     *   override[<key>] = '' (no opinion — clear), 'allow' (grant), 'deny' (deny).
     */
    public function saveUserOverrides(int $userId, array $postedStates, int $updatedBy): int
    {
        $valid  = MenuPermissionRegistry::flatPermissionSet();
        $grants = [];
        $denies = [];
        foreach ($postedStates as $key => $state) {
            if (!isset($valid[$key])) { continue; }
            if ($state === 'allow') { $grants[] = (string) $key; }
            elseif ($state === 'deny') { $denies[] = (string) $key; }
            // '' or anything else → no opinion (skip)
        }
        $this->userOverrides->replaceOverrides($userId, $grants, $denies, $updatedBy);

        Permissions::flush();
        ActivityLog::log('update', 'user_permission_overrides', $userId, [
            'label' => 'user overrides saved',
            'notes' => count($grants) . ' grants, ' . count($denies) . ' denies',
        ]);
        return count($grants) + count($denies);
    }

    /** @param array<int, string> $keys */
    private function insertManyAllowed(int $roleId, array $keys): void
    {
        $stmt = \App\Support\Db::pdo()->prepare(
            'INSERT IGNORE INTO role_permissions (role_id, permission_key, allowed)
             VALUES (?, ?, 1)'
        );
        foreach ($keys as $key) {
            $stmt->execute([$roleId, $key]);
        }
    }
}
