<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class OperatingExpenseCategoryRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT * FROM operating_expense_categories ORDER BY status DESC, name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    public function listActive(): array
    {
        return Db::pdo()->query(
            "SELECT id, name FROM operating_expense_categories WHERE status='active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM operating_expense_categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM operating_expense_categories WHERE name = ?';
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
            'INSERT INTO operating_expense_categories (name, description, created_by, updated_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $description, $createdBy, $createdBy]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE operating_expense_categories SET name = ?, description = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$name, $description, $updatedBy, $id]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE operating_expense_categories SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }
}
