<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class CategoryRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()
            ->query('SELECT id, name, description, status, created_at, updated_at
                     FROM categories ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{id:int, name:string}> */
    public function listActive(): array
    {
        return Db::pdo()
            ->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active categories plus the given id (even if inactive). Used by edit forms so the currently
     * selected parent stays visible in the dropdown when it has been disabled.
     *
     * @return array<int, array{id:int, name:string, status:string}>
     */
    public function listActiveOrIncluding(?int $alsoIncludeId): array
    {
        if ($alsoIncludeId === null) {
            $stmt = Db::pdo()->query(
                "SELECT id, name, status FROM categories WHERE status = 'active' ORDER BY name"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = Db::pdo()->prepare(
            "SELECT id, name, status FROM categories WHERE status = 'active' OR id = ? ORDER BY name"
        );
        $stmt->execute([$alsoIncludeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, description, status FROM categories WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM categories WHERE name = ?';
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
            'INSERT INTO categories (name, description, created_by, updated_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $description, $createdBy, $createdBy]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE categories SET name = ?, description = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$name, $description, $updatedBy, $id]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE categories SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }
}
