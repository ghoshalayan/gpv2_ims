<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class CashierDrawerRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT cd.*, u.name AS assigned_user_name
             FROM cashier_drawers cd
             LEFT JOIN users u ON u.id = cd.assigned_user_id
             ORDER BY cd.status DESC, cd.name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    public function listActive(): array
    {
        return Db::pdo()->query(
            "SELECT id, name, location, current_balance FROM cashier_drawers WHERE status='active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT cd.*, u.name AS assigned_user_name
             FROM cashier_drawers cd
             LEFT JOIN users u ON u.id = cd.assigned_user_id
             WHERE cd.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM cashier_drawers WHERE name = ?';
        $params = [$name];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO cashier_drawers
             (name, location, assigned_user_id, opening_balance, current_balance, remarks,
              created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['location'], $data['assigned_user_id'],
            $data['opening_balance'], $data['opening_balance'],
            $data['remarks'] ?? null,
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE cashier_drawers
             SET name = ?, location = ?, assigned_user_id = ?, remarks = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'], $data['location'], $data['assigned_user_id'],
            $data['remarks'] ?? null, $updatedBy, $id,
        ]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE cashier_drawers SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }

    /**
     * Atomic balance mutation. Throws if the row doesn't exist (defensive guard against
     * silent no-ops where a stale/invalid id masks a missing cash leg as success).
     */
    public function applyDelta(int $id, float $delta): void
    {
        $check = Db::pdo()->prepare('SELECT 1 FROM cashier_drawers WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            throw new \RuntimeException("Cashier drawer #{$id} not found — cannot applyDelta.");
        }
        $stmt = Db::pdo()->prepare(
            'UPDATE cashier_drawers SET current_balance = current_balance + ? WHERE id = ?'
        );
        $stmt->execute([$delta, $id]);
    }
}
