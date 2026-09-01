<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class CapitalMovementRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(?string $typeFilter = null): array
    {
        $sql = "SELECT cm.*,
                       CASE cm.account_kind
                           WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = cm.account_id)
                           WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = cm.account_id)
                       END AS account_name
                FROM capital_movements cm";
        $params = [];
        if ($typeFilter !== null && $typeFilter !== '') {
            $sql .= ' WHERE cm.txn_type = ?';
            $params[] = $typeFilter;
        }
        $sql .= ' ORDER BY cm.movement_date DESC, cm.id DESC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['txn_type']))  { $where[] = 'cm.txn_type = ?';  $params[] = (string) $filters['txn_type']; }
        if (!empty($filters['direction'])) { $where[] = 'cm.direction = ?'; $params[] = (string) $filters['direction']; }
        if (!empty($filters['status']))    { $where[] = 'cm.status = ?';    $params[] = (string) $filters['status']; }
        if (!empty($filters['date_from'])) { $where[] = 'cm.movement_date >= ?'; $params[] = (string) $filters['date_from']; }
        if (!empty($filters['date_to']))   { $where[] = 'cm.movement_date <= ?'; $params[] = (string) $filters['date_to']; }
        if ($search !== '') {
            $where[]  = '(cm.movement_number LIKE ? OR cm.counterparty_name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM capital_movements cm {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT cm.*,
                    CASE cm.account_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = cm.account_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = cm.account_id)
                    END AS account_name
             FROM capital_movements cm
             {$whereSql}
             ORDER BY cm.movement_date DESC, cm.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT cm.*,
                    CASE cm.account_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = cm.account_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = cm.account_id)
                    END AS account_name
             FROM capital_movements cm
             WHERE cm.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO capital_movements
             (movement_number, movement_date, txn_type, direction, counterparty_name, amount,
              account_kind, account_id, mode, reference, remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['movement_number'], $data['movement_date'], $data['txn_type'], $data['direction'],
            $data['counterparty_name'], $data['amount'],
            $data['account_kind'], $data['account_id'],
            $data['mode'], $data['reference'] ?? null, $data['remarks'] ?? null,
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function markVoided(int $id, string $reason, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE capital_movements
             SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ?, updated_by = ?
             WHERE id = ? AND status = 'posted'"
        );
        $stmt->execute([$userId, $reason, $userId, $id]);
    }

    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(movement_number, "/", -1) AS UNSIGNED)), 0)
             FROM capital_movements
             WHERE movement_number LIKE ?'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Outstanding loan balance per lender:
     *   loan_in (posted) − loan_repay (posted), grouped by counterparty_name.
     * Returns rows where the net is > 0 (still owed) sorted by largest first.
     */
    public function outstandingLoans(): array
    {
        $stmt = Db::pdo()->query(
            "SELECT counterparty_name,
                    SUM(CASE WHEN txn_type = 'loan_in'    THEN amount ELSE 0 END) AS borrowed,
                    SUM(CASE WHEN txn_type = 'loan_repay' THEN amount ELSE 0 END) AS repaid,
                    SUM(CASE WHEN txn_type = 'loan_in' THEN amount ELSE 0 END)
                  - SUM(CASE WHEN txn_type = 'loan_repay' THEN amount ELSE 0 END) AS outstanding
             FROM capital_movements
             WHERE status = 'posted'
               AND txn_type IN ('loan_in', 'loan_repay')
             GROUP BY counterparty_name
             HAVING outstanding > 0
             ORDER BY outstanding DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Net capital injected per partner/owner: capital_in − drawing.
     * Positive = net injection still in the business; negative = net taken out.
     */
    public function ownerCapitalSummary(): array
    {
        $stmt = Db::pdo()->query(
            "SELECT counterparty_name,
                    SUM(CASE WHEN txn_type = 'capital_in' THEN amount ELSE 0 END) AS injected,
                    SUM(CASE WHEN txn_type = 'drawing'    THEN amount ELSE 0 END) AS drawn,
                    SUM(CASE WHEN txn_type = 'capital_in' THEN amount ELSE 0 END)
                  - SUM(CASE WHEN txn_type = 'drawing'    THEN amount ELSE 0 END) AS net
             FROM capital_movements
             WHERE status = 'posted'
               AND txn_type IN ('capital_in', 'drawing')
             GROUP BY counterparty_name
             ORDER BY net DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
