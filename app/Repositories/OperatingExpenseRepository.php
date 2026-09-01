<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class OperatingExpenseRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            "SELECT oe.*,
                    cat.name AS category_name,
                    CASE oe.paid_from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = oe.paid_from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = oe.paid_from_id)
                    END AS paid_from_name
             FROM operating_expenses oe
             JOIN operating_expense_categories cat ON cat.id = oe.category_id
             ORDER BY oe.expense_date DESC, oe.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['category_id']))    { $where[] = 'oe.category_id = ?';    $params[] = (int) $filters['category_id']; }
        if (!empty($filters['status']))         { $where[] = 'oe.status = ?';         $params[] = (string) $filters['status']; }
        if (!empty($filters['paid_from_kind'])) { $where[] = 'oe.paid_from_kind = ?'; $params[] = (string) $filters['paid_from_kind']; }
        if (!empty($filters['paid_from_id']))   { $where[] = 'oe.paid_from_id = ?';   $params[] = (int) $filters['paid_from_id']; }
        if (!empty($filters['date_from']))      { $where[] = 'oe.expense_date >= ?';  $params[] = (string) $filters['date_from']; }
        if (!empty($filters['date_to']))        { $where[] = 'oe.expense_date <= ?';  $params[] = (string) $filters['date_to']; }
        if ($search !== '') {
            $where[]  = '(oe.expense_number LIKE ? OR oe.vendor_name LIKE ? OR cat.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM operating_expenses oe
             JOIN operating_expense_categories cat ON cat.id = oe.category_id
             {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT oe.*,
                    cat.name AS category_name,
                    CASE oe.paid_from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = oe.paid_from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = oe.paid_from_id)
                    END AS paid_from_name
             FROM operating_expenses oe
             JOIN operating_expense_categories cat ON cat.id = oe.category_id
             {$whereSql}
             ORDER BY oe.expense_date DESC, oe.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT oe.*,
                    cat.name AS category_name,
                    CASE oe.paid_from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = oe.paid_from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = oe.paid_from_id)
                    END AS paid_from_name
             FROM operating_expenses oe
             JOIN operating_expense_categories cat ON cat.id = oe.category_id
             WHERE oe.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Lock the row inside the surrounding transaction. Used by editPost to prevent concurrent edits. */
    public function findForUpdate(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM operating_expenses WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO operating_expenses
             (expense_number, expense_date, category_id, vendor_name,
              amount, gst_rate, gst_amount, gst_claimable, total_with_gst,
              paid_from_kind, paid_from_id, payment_mode, payment_reference,
              remarks, spawned_from_recurring_id, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['expense_number'], $data['expense_date'], $data['category_id'], $data['vendor_name'],
            $data['amount'], $data['gst_rate'], $data['gst_amount'], $data['gst_claimable'],
            $data['total_with_gst'],
            $data['paid_from_kind'], $data['paid_from_id'], $data['payment_mode'], $data['payment_reference'],
            $data['remarks'], $data['spawned_from_recurring_id'] ?? null,
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /** Replaces all editable fields on an existing posted expense (used by the edit flow). */
    public function updateRow(int $id, array $data, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE operating_expenses
             SET expense_date = ?, category_id = ?, vendor_name = ?,
                 amount = ?, gst_rate = ?, gst_amount = ?, gst_claimable = ?, total_with_gst = ?,
                 paid_from_kind = ?, paid_from_id = ?, payment_mode = ?, payment_reference = ?,
                 remarks = ?, updated_by = ?
             WHERE id = ? AND status = 'posted'"
        );
        $stmt->execute([
            $data['expense_date'], $data['category_id'], $data['vendor_name'],
            $data['amount'], $data['gst_rate'], $data['gst_amount'], $data['gst_claimable'], $data['total_with_gst'],
            $data['paid_from_kind'], $data['paid_from_id'], $data['payment_mode'], $data['payment_reference'],
            $data['remarks'], $userId, $id,
        ]);
    }

    public function markVoided(int $id, string $reason, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE operating_expenses
             SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ?, updated_by = ?
             WHERE id = ? AND status = 'posted'"
        );
        $stmt->execute([$userId, $reason, $userId, $id]);
    }

    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(expense_number, "/", -1) AS UNSIGNED)), 0)
             FROM operating_expenses
             WHERE expense_number LIKE ?'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Aggregate spending by category for a date range.
     * @return array<int, array{category_name:string, total:float, count:int}>
     */
    public function summaryByCategory(?string $fromDate = null, ?string $toDate = null): array
    {
        $sql = "SELECT cat.name AS category_name,
                       SUM(oe.total_with_gst) AS total,
                       COUNT(*) AS count
                FROM operating_expenses oe
                JOIN operating_expense_categories cat ON cat.id = oe.category_id
                WHERE oe.status = 'posted'";
        $params = [];
        if ($fromDate !== null) { $sql .= ' AND oe.expense_date >= ?'; $params[] = $fromDate; }
        if ($toDate   !== null) { $sql .= ' AND oe.expense_date <= ?'; $params[] = $toDate;   }
        $sql .= ' GROUP BY cat.id, cat.name ORDER BY total DESC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
