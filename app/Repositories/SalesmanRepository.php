<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SalesmanRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT id, name, phone, email, state, gstin, commission_rate,
                    linked_customer_id, status, created_at, updated_at
             FROM salesmen ORDER BY name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'status = ?'; $params[] = (string) $filters['status']; }
        if ($search !== '') {
            $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR gstin LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM salesmen {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT id, name, phone, email, state, gstin, commission_rate,
                    linked_customer_id, status, created_at, updated_at
             FROM salesmen
             {$whereSql}
             ORDER BY name
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** @return array<int, array{id:int, name:string}> */
    public function listActive(): array
    {
        return Db::pdo()
            ->query("SELECT id, name FROM salesmen WHERE status = 'active' ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, phone, email, address, state, gstin, commission_rate,
                    linked_customer_id, remarks, status
             FROM salesmen WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        return $this->existsByField('name', $name, $excludeId);
    }

    public function existsByPhone(string $phone, ?int $excludeId = null): bool
    {
        return $this->existsByField('phone', $phone, $excludeId);
    }

    private function existsByField(string $field, string $value, ?int $excludeId): bool
    {
        $sql = "SELECT 1 FROM salesmen WHERE {$field} = ?";
        $params = [$value];
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
            'INSERT INTO salesmen
             (name, phone, email, address, state, gstin, commission_rate, remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['phone'], $data['email'], $data['address'],
            $data['state'], $data['gstin'], $data['commission_rate'], $data['remarks'],
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE salesmen
             SET name = ?, phone = ?, email = ?, address = ?, state = ?, gstin = ?,
                 commission_rate = ?, remarks = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'], $data['phone'], $data['email'], $data['address'],
            $data['state'], $data['gstin'], $data['commission_rate'], $data['remarks'],
            $updatedBy, $id,
        ]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE salesmen SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }

    public function setLinkedCustomer(int $salesmanId, int $customerId): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE salesmen SET linked_customer_id = ? WHERE id = ?'
        );
        $stmt->execute([$customerId, $salesmanId]);
    }

    /**
     * Reverse-lookup: find the salesman whose linked_customer_id matches a customer.
     * (Each customer has at most one linked salesman in the v1 schema.)
     */
    public function findByLinkedCustomer(int $customerId): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT id, name, phone, commission_rate, linked_customer_id
             FROM salesmen
             WHERE linked_customer_id = ? AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
