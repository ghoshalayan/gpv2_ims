<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class UnitRepository
{
    /** @return array<int, array<string, mixed>> All units with their base unit's code resolved. */
    public function all(): array
    {
        return Db::pdo()->query(
            "SELECT u.*, b.code AS base_code, b.name AS base_name
             FROM units u
             LEFT JOIN units b ON b.id = u.base_unit_id
             ORDER BY u.family, u.code"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listActive(): array
    {
        return Db::pdo()->query(
            "SELECT u.id, u.code, u.name, u.family, u.factor_to_base, u.base_unit_id,
                    u.decimals, b.code AS base_code
             FROM units u
             LEFT JOIN units b ON b.id = u.base_unit_id
             WHERE u.is_active = 1
             ORDER BY u.family, u.code"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT u.*, b.code AS base_code, b.name AS base_name
             FROM units u
             LEFT JOIN units b ON b.id = u.base_unit_id
             WHERE u.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM units WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByCode(string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM units WHERE code = ?';
        $params = [$code];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(array $data, int $userId): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO units (code, name, family, base_unit_id, factor_to_base,
                                decimals, is_active, description, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['code'], $data['name'], $data['family'],
            $data['base_unit_id'], $data['factor_to_base'],
            $data['decimals'], $data['is_active'] ?? 1,
            $data['description'] ?? null,
            $userId, $userId,
        ]);
        $id = (int) Db::pdo()->lastInsertId();
        // If this is a base unit (no base_unit_id supplied), self-reference so
        // every row has a non-null base_unit_id (matches the seed pattern).
        if (empty($data['base_unit_id'])) {
            Db::pdo()->prepare('UPDATE units SET base_unit_id = ? WHERE id = ?')
                     ->execute([$id, $id]);
        }
        return $id;
    }

    public function update(int $id, array $data, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE units
             SET code = ?, name = ?, family = ?, base_unit_id = ?, factor_to_base = ?,
                 decimals = ?, is_active = ?, description = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['code'], $data['name'], $data['family'],
            $data['base_unit_id'], $data['factor_to_base'],
            $data['decimals'], $data['is_active'] ?? 1,
            $data['description'] ?? null,
            $userId, $id,
        ]);
    }

    public function delete(int $id): void
    {
        // Base units self-reference (base_unit_id = id) so the FK blocks deletion.
        // Null the column first, then delete.
        Db::pdo()->prepare('UPDATE units SET base_unit_id = NULL WHERE id = ?')->execute([$id]);
        Db::pdo()->prepare('DELETE FROM units WHERE id = ?')->execute([$id]);
    }

    public function isInUse(int $id): bool
    {
        $usages = [
            'SELECT 1 FROM products WHERE unit_id = ? LIMIT 1',
            'SELECT 1 FROM product_units WHERE unit_id = ? LIMIT 1',
            'SELECT 1 FROM units WHERE base_unit_id = ? AND id <> ? LIMIT 1',
        ];
        foreach ($usages as $i => $sql) {
            $stmt = Db::pdo()->prepare($sql);
            $stmt->execute($i === 2 ? [$id, $id] : [$id]);
            if ($stmt->fetchColumn()) { return true; }
        }
        return false;
    }
}
