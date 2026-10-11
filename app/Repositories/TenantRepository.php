<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use App\Support\Tenant;
use PDO;

/**
 * The control plane: which tenants exist, and who the superadmins are.
 *
 * Every query here goes through Db::control(), NOT Db::pdo(). This repository must
 * never touch a tenant's business data — and conversely no business repository
 * reads these tables.
 */
class TenantRepository
{
    // ---------------------------------------------------------------- tenants

    /** @return array<int, array<string, mixed>> */
    public function allTenants(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM tenants'
             . ($activeOnly ? " WHERE status = 'active'" : '')
             . ' ORDER BY label';
        return Db::control()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findTenant(int $id): ?array
    {
        $st = Db::control()->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function findTenantByKey(string $key): ?array
    {
        $st = Db::control()->prepare('SELECT * FROM tenants WHERE tenant_key = ? LIMIT 1');
        $st->execute([$key]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    /** Only an ACTIVE tenant may be signed in to or entered. */
    public function findActiveTenant(int $id): ?array
    {
        $t = $this->findTenant($id);
        return ($t !== null && $t['status'] === 'active') ? $t : null;
    }

    public function tenantKeyExists(string $key, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM tenants WHERE tenant_key = ?';
        $p = [$key];
        if ($excludeId !== null) { $sql .= ' AND id <> ?'; $p[] = $excludeId; }
        $st = Db::control()->prepare($sql . ' LIMIT 1');
        $st->execute($p);
        return (bool) $st->fetchColumn();
    }

    public function insertTenant(array $d): int
    {
        $st = Db::control()->prepare(
            'INSERT INTO tenants (tenant_key, label, status, remarks) VALUES (?, ?, ?, ?)'
        );
        $st->execute([
            $d['tenant_key'], $d['label'], $d['status'] ?? 'active', $d['remarks'] ?? null,
        ]);
        return (int) Db::control()->lastInsertId();
    }

    public function updateTenantStatus(int $id, string $status): void
    {
        $st = Db::control()->prepare('UPDATE tenants SET status = ? WHERE id = ?');
        $st->execute([$status, $id]);
    }

    // ------------------------------------------------------------ superadmins

    public function findSuperadminByUsername(string $username): ?array
    {
        $st = Db::control()->prepare('SELECT * FROM superadmins WHERE username = ? LIMIT 1');
        $st->execute([$username]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function findSuperadmin(int $id): ?array
    {
        $st = Db::control()->prepare('SELECT * FROM superadmins WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function touchSuperadminLogin(int $id): void
    {
        Db::control()->prepare('UPDATE superadmins SET last_login_at = NOW() WHERE id = ?')
            ->execute([$id]);
    }

    public function updateSuperadminPassword(int $id, string $hash): void
    {
        Db::control()->prepare('UPDATE superadmins SET password_hash = ? WHERE id = ?')
            ->execute([$hash, $id]);
    }

    // ---------------------------------------------------------------- audit

    /**
     * Record a superadmin action. Entering a tenant means reading another
     * business's complete books, so it is deliberately logged.
     */
    public function logSuperadminAction(string $action, array $opts = []): void
    {
        try {
            Db::control()->prepare(
                'INSERT INTO superadmin_activity
                 (superadmin_id, username, action, tenant_id, tenant_key, ip_address, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $opts['superadmin_id'] ?? null,
                $opts['username']      ?? null,
                $action,
                $opts['tenant_id']     ?? null,
                $opts['tenant_key']    ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $opts['notes']         ?? null,
            ]);
        } catch (\Throwable $e) {
            // Audit failure must never abort the action being audited.
            \error_log('[superadmin_activity] ' . $e->getMessage());
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function recentSuperadminActivity(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        return Db::control()->query(
            "SELECT * FROM superadmin_activity ORDER BY id DESC LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
