<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SubcategoryRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT s.id, s.name, s.description, s.status,
                    c.id AS category_id, c.name AS category_name, c.status AS category_status
             FROM subcategories s
             JOIN categories c ON c.id = s.category_id
             ORDER BY c.name, s.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{id:int, name:string, category_id:int}> Active subcategories under active categories. */
    public function listActive(): array
    {
        return Db::pdo()->query(
            "SELECT s.id, s.name, s.category_id
             FROM subcategories s
             JOIN categories c ON c.id = s.category_id
             WHERE s.status = 'active' AND c.status = 'active'
             ORDER BY s.name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{id:int, name:string}> */
    public function listActiveByCategory(int $categoryId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT id, name FROM subcategories
             WHERE category_id = ? AND status = 'active' ORDER BY name"
        );
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, category_id, name, description, status FROM subcategories WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByName(int $categoryId, string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM subcategories WHERE category_id = ? AND name = ?';
        $params = [$categoryId, $name];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(int $categoryId, string $name, ?string $description, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO subcategories (category_id, name, description, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$categoryId, $name, $description, $createdBy, $createdBy]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, int $categoryId, string $name, ?string $description, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE subcategories SET category_id = ?, name = ?, description = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([$categoryId, $name, $description, $updatedBy, $id]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE subcategories SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }
}
