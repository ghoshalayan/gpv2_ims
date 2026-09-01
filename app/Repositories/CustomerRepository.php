<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class CustomerRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT c.id, c.name, c.phone, c.email, c.gstin, c.state,
                    c.credit_limit, c.linked_salesman_id, c.linked_employee_id, c.status,
                    c.default_salesman_id, sm.name AS default_salesman_name
             FROM customers c
             LEFT JOIN salesmen sm ON sm.id = c.default_salesman_id
             ORDER BY c.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'c.status = ?'; $params[] = (string) $filters['status']; }
        if ($search !== '') {
            $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.gstin LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM customers c {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT c.id, c.name, c.phone, c.email, c.gstin, c.state,
                    c.credit_limit, c.linked_salesman_id, c.linked_employee_id, c.status,
                    c.default_salesman_id, sm.name AS default_salesman_name
             FROM customers c
             LEFT JOIN salesmen sm ON sm.id = c.default_salesman_id
             {$whereSql}
             ORDER BY c.name
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** @return array<int, array{id:int, name:string}> */
    public function listActive(): array
    {
        return Db::pdo()
            ->query("SELECT id, name FROM customers WHERE status = 'active' ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, phone, email, address, gstin, state,
                    default_salesman_id, credit_limit,
                    linked_salesman_id, linked_employee_id, remarks, status
             FROM customers WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Look up an existing customer that matches BOTH name and phone — used by auto-link. */
    public function findByNameAndPhone(string $name, string $phone): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, phone, linked_salesman_id, linked_employee_id
             FROM customers WHERE name = ? AND phone = ? LIMIT 1'
        );
        $stmt->execute([$name, $phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function setLinkedSalesman(int $customerId, ?int $salesmanId, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE customers SET linked_salesman_id = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$salesmanId, $updatedBy, $customerId]);
    }

    public function setLinkedEmployee(int $customerId, ?int $employeeId, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE customers SET linked_employee_id = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$employeeId, $updatedBy, $customerId]);
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
        $sql = "SELECT 1 FROM customers WHERE {$field} = ?";
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
            'INSERT INTO customers
             (name, phone, email, address, gstin, state, default_salesman_id,
              credit_limit, linked_salesman_id, remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['phone'], $data['email'], $data['address'],
            $data['gstin'], $data['state'], $data['default_salesman_id'] ?? null,
            $data['credit_limit'] ?? 0,
            $data['linked_salesman_id'] ?? null,
            $data['remarks'] ?? null,
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE customers
             SET name = ?, phone = ?, email = ?, address = ?, gstin = ?, state = ?,
                 default_salesman_id = ?, credit_limit = ?, remarks = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'], $data['phone'], $data['email'], $data['address'],
            $data['gstin'], $data['state'], $data['default_salesman_id'],
            $data['credit_limit'], $data['remarks'], $updatedBy, $id,
        ]);
    }

    /**
     * Partial update: only the fields mirrored from a salesman (per D38).
     * Called when a linked salesman is edited.
     */
    public function updateMirrorFromSalesman(int $customerId, array $salesmanData, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE customers
             SET name = ?, phone = ?, email = ?, address = ?, state = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $salesmanData['name'], $salesmanData['phone'], $salesmanData['email'],
            $salesmanData['address'], $salesmanData['state'], $updatedBy, $customerId,
        ]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE customers SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }
}
