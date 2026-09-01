<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class RoleRepository
{
    /** @return array<int, array<string, mixed>> All roles, ordered by sort_order then label. */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT r.id, r.role_key, r.label, r.description, r.is_system, r.sort_order,
                    r.created_at, r.updated_at,
                    (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id AND rp.allowed = 1) AS perm_count
             FROM roles r
             ORDER BY r.sort_order, r.label'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM roles WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findByKey(string $roleKey): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM roles WHERE role_key = ? LIMIT 1');
        $stmt->execute([$roleKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByKey(string $roleKey, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM roles WHERE role_key = ?';
        $params = [$roleKey];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(string $roleKey, string $label, ?string $description, int $sortOrder): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO roles (role_key, label, description, is_system, sort_order)
             VALUES (?, ?, ?, 0, ?)'
        );
        $stmt->execute([$roleKey, $label, $description, $sortOrder]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, string $roleKey, string $label, ?string $description, int $sortOrder): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE roles SET role_key = ?, label = ?, description = ?, sort_order = ?
             WHERE id = ?'
        );
        $stmt->execute([$roleKey, $label, $description, $sortOrder, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM roles WHERE id = ? AND is_system = 0');
        $stmt->execute([$id]);
    }

    /** Map of role_key => row, for bulk lookups. @return array<string, array<string, mixed>> */
    public function keyMap(): array
    {
        $rows = Db::pdo()->query('SELECT * FROM roles')->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) { $out[$r['role_key']] = $r; }
        return $out;
    }
}
