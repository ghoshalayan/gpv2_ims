<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class BrandRepository
{
    public const SYSTEM_BRAND = 'Generic';

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()
            ->query('SELECT id, name, description, status, created_at, updated_at
                     FROM brands ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{id:int, name:string}> */
    public function listActive(): array
    {
        return Db::pdo()
            ->query("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, description, status FROM brands WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM brands WHERE name = ?';
        $params = [$name];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(string $name, ?string $description, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO brands (name, description, created_by, updated_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $description, $createdBy, $createdBy]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE brands SET name = ?, description = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$name, $description, $updatedBy, $id]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE brands SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }
}
