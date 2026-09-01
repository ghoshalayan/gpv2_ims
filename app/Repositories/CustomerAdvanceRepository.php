<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class CustomerAdvanceRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(?int $customerId = null, ?string $statusFilter = null): array
    {
        $sql = "SELECT ca.*,
                       c.name AS customer_name,
                       CASE ca.received_into_kind
                           WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = ca.received_into_id)
                           WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = ca.received_into_id)
                       END AS received_into_name
                FROM customer_advances ca
                JOIN customers c ON c.id = ca.customer_id";
        $params = [];
        $conds = [];
        if ($customerId !== null) { $conds[] = 'ca.customer_id = ?'; $params[] = $customerId; }
        if ($statusFilter !== null && $statusFilter !== '') {
            $conds[] = 'ca.status = ?';
            $params[] = $statusFilter;
        }
        if ($conds !== []) {
            $sql .= ' WHERE ' . \implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY ca.advance_date DESC, ca.id DESC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['customer_id'])) { $where[] = 'ca.customer_id = ?'; $params[] = (int) $filters['customer_id']; }
        if (!empty($filters['status']))      { $where[] = 'ca.status = ?';      $params[] = (string) $filters['status']; }
        if ($search !== '') {
            $where[]  = '(ca.advance_number LIKE ? OR c.name LIKE ?)';
            $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM customer_advances ca JOIN customers c ON c.id = ca.customer_id {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT ca.*,
                    c.name AS customer_name,
                    CASE ca.received_into_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = ca.received_into_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = ca.received_into_id)
                    END AS received_into_name
             FROM customer_advances ca
             JOIN customers c ON c.id = ca.customer_id
             {$whereSql}
             ORDER BY ca.advance_date DESC, ca.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT ca.*,
                    c.name AS customer_name,
                    CASE ca.received_into_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = ca.received_into_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = ca.received_into_id)
                    END AS received_into_name
             FROM customer_advances ca
             JOIN customers c ON c.id = ca.customer_id
             WHERE ca.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * SELECT ... FOR UPDATE — locks the row inside the surrounding transaction.
     * Use this when applying or refunding so concurrent attempts can't double-spend.
     */
    public function findForUpdate(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM customer_advances WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO customer_advances
             (advance_number, advance_date, customer_id, amount, balance,
              received_into_kind, received_into_id, payment_mode, payment_reference, remarks,
              created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['advance_number'], $data['advance_date'], $data['customer_id'],
            $data['amount'], $data['amount'],   // balance starts equal to amount
            $data['received_into_kind'], $data['received_into_id'],
            $data['payment_mode'], $data['payment_reference'] ?? null, $data['remarks'] ?? null,
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /**
     * Atomic balance mutation — `delta` can be negative (apply) or positive (revert).
     * If the resulting balance falls to zero, status is auto-flipped to 'exhausted'.
     * If the resulting balance becomes positive again (revert), status flips back to 'active'.
     * Refunded advances cannot have their balance changed.
     */
    public function applyDelta(int $id, float $delta): void
    {
        $row = $this->findForUpdate($id);
        if ($row === null) {
            throw new \RuntimeException("Customer advance #{$id} vanished during apply.");
        }
        if ($row['status'] === 'refunded') {
            throw new \RuntimeException("Customer advance #{$id} is refunded — cannot mutate balance.");
        }
        $newBalance = (float) $row['balance'] + $delta;
        if ($newBalance < -0.0001) {
            throw new \RuntimeException(\sprintf(
                'Customer advance #%d balance would go negative (current %s, delta %s).',
                $id, $row['balance'], $delta
            ));
        }
        if ($newBalance > (float) $row['amount'] + 0.0001) {
            throw new \RuntimeException(\sprintf(
                'Customer advance #%d revert would exceed original amount (%s > %s).',
                $id, $newBalance, $row['amount']
            ));
        }
        $newBalance = \round($newBalance, 2);
        $newStatus  = $newBalance <= 0.001 ? 'exhausted' : 'active';
        $stmt = Db::pdo()->prepare(
            'UPDATE customer_advances SET balance = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([$newBalance, $newStatus, $id]);
    }

    public function markRefunded(int $id, string $reason, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE customer_advances
             SET balance = 0, status = 'refunded',
                 refunded_at = NOW(), refunded_by = ?, refund_reason = ?, updated_by = ?
             WHERE id = ? AND status IN ('active', 'exhausted')"
        );
        $stmt->execute([$userId, $reason, $userId, $id]);
    }

    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(advance_number, "/", -1) AS UNSIGNED)), 0)
             FROM customer_advances
             WHERE advance_number LIKE ?'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }

    /** Sum of available balances for a customer (active advances only). */
    public function availableBalanceForCustomer(int $customerId): float
    {
        $stmt = Db::pdo()->prepare(
            "SELECT COALESCE(SUM(balance), 0)
             FROM customer_advances
             WHERE customer_id = ? AND status = 'active'"
        );
        $stmt->execute([$customerId]);
        return (float) $stmt->fetchColumn();
    }

    /** Active advances for a customer (newest first), suitable for picker UI. */
    public function activeForCustomer(int $customerId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT id, advance_number, advance_date, amount, balance
             FROM customer_advances
             WHERE customer_id = ? AND status = 'active' AND balance > 0
             ORDER BY advance_date ASC, id ASC"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
