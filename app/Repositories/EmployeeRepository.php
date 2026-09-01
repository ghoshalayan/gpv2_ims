<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class EmployeeRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT e.id, e.name, e.phone, e.email, e.designation, e.joining_date, e.exit_date,
                    e.salary_amount, e.state, e.linked_user_id, e.linked_customer_id, e.status,
                    u.username AS linked_username
             FROM employees e
             LEFT JOIN users u ON u.id = e.linked_user_id
             ORDER BY e.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'e.status = ?'; $params[] = (string) $filters['status']; }
        if ($search !== '') {
            $where[] = '(e.name LIKE ? OR e.phone LIKE ? OR e.email LIKE ? OR e.designation LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM employees e {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT e.id, e.name, e.phone, e.email, e.designation, e.joining_date, e.exit_date,
                    e.salary_amount, e.state, e.linked_user_id, e.linked_customer_id, e.status,
                    u.username AS linked_username
             FROM employees e
             LEFT JOIN users u ON u.id = e.linked_user_id
             {$whereSql}
             ORDER BY e.name
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM employees WHERE id = ? LIMIT 1');
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
        $sql = "SELECT 1 FROM employees WHERE {$field} = ?";
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
            'INSERT INTO employees
             (name, phone, email, address, state, designation, joining_date,
              salary_amount, id_proof_type, id_proof_number, pan, aadhaar,
              bank_account_number, bank_ifsc, bank_branch, bank_account_holder_name,
              linked_user_id, remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['phone'], $data['email'], $data['address'], $data['state'],
            $data['designation'], $data['joining_date'], $data['salary_amount'],
            $data['id_proof_type'], $data['id_proof_number'], $data['pan'], $data['aadhaar'],
            $data['bank_account_number'], $data['bank_ifsc'], $data['bank_branch'], $data['bank_account_holder_name'],
            $data['linked_user_id'], $data['remarks'],
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE employees
             SET name = ?, phone = ?, email = ?, address = ?, state = ?,
                 designation = ?, joining_date = ?, salary_amount = ?,
                 id_proof_type = ?, id_proof_number = ?, pan = ?, aadhaar = ?,
                 bank_account_number = ?, bank_ifsc = ?, bank_branch = ?, bank_account_holder_name = ?,
                 linked_user_id = ?, remarks = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'], $data['phone'], $data['email'], $data['address'], $data['state'],
            $data['designation'], $data['joining_date'], $data['salary_amount'],
            $data['id_proof_type'], $data['id_proof_number'], $data['pan'], $data['aadhaar'],
            $data['bank_account_number'], $data['bank_ifsc'], $data['bank_branch'], $data['bank_account_holder_name'],
            $data['linked_user_id'], $data['remarks'], $updatedBy, $id,
        ]);
    }

    public function setLinkedCustomer(int $employeeId, int $customerId): void
    {
        $stmt = Db::pdo()->prepare('UPDATE employees SET linked_customer_id = ? WHERE id = ?');
        $stmt->execute([$customerId, $employeeId]);
    }

    public function setStatus(int $id, string $status, ?string $exitDate, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE employees SET status = ?, exit_date = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $exitDate, $updatedBy, $id]);
    }
}
