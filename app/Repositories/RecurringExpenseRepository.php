<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class RecurringExpenseRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            "SELECT re.*,
                    cat.name AS category_name,
                    CASE re.paid_from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = re.paid_from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = re.paid_from_id)
                    END AS paid_from_name
             FROM recurring_expenses re
             JOIN operating_expense_categories cat ON cat.id = re.category_id
             ORDER BY re.status DESC, re.next_run_date ASC, re.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT re.*,
                    cat.name AS category_name,
                    CASE re.paid_from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = re.paid_from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = re.paid_from_id)
                    END AS paid_from_name
             FROM recurring_expenses re
             JOIN operating_expense_categories cat ON cat.id = re.category_id
             WHERE re.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Active templates whose next_run_date is on or before $asOf — these are due to fire. */
    public function dueAsOf(string $asOf): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT * FROM recurring_expenses
             WHERE status = 'active' AND next_run_date <= ?
             ORDER BY next_run_date ASC, id ASC"
        );
        $stmt->execute([$asOf]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO recurring_expenses
             (name, category_id, vendor_name, amount, gst_rate, gst_claimable,
              paid_from_kind, paid_from_id, payment_mode, payment_reference, remarks,
              frequency, interval_count, start_date, end_date, next_run_date,
              created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['category_id'], $data['vendor_name'], $data['amount'],
            $data['gst_rate'], $data['gst_claimable'],
            $data['paid_from_kind'], $data['paid_from_id'], $data['payment_mode'],
            $data['payment_reference'], $data['remarks'],
            $data['frequency'], $data['interval_count'], $data['start_date'], $data['end_date'],
            $data['next_run_date'],
            $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE recurring_expenses
             SET name = ?, category_id = ?, vendor_name = ?, amount = ?, gst_rate = ?, gst_claimable = ?,
                 paid_from_kind = ?, paid_from_id = ?, payment_mode = ?, payment_reference = ?, remarks = ?,
                 frequency = ?, interval_count = ?, start_date = ?, end_date = ?, next_run_date = ?,
                 updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'], $data['category_id'], $data['vendor_name'], $data['amount'],
            $data['gst_rate'], $data['gst_claimable'],
            $data['paid_from_kind'], $data['paid_from_id'], $data['payment_mode'],
            $data['payment_reference'], $data['remarks'],
            $data['frequency'], $data['interval_count'], $data['start_date'], $data['end_date'],
            $data['next_run_date'],
            $updatedBy, $id,
        ]);
    }

    public function recordRun(int $id, string $newNextRunDate, string $newStatus): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE recurring_expenses
             SET next_run_date = ?, last_run_at = NOW(), runs_count = runs_count + 1, status = ?
             WHERE id = ?'
        );
        $stmt->execute([$newNextRunDate, $newStatus, $id]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE recurring_expenses SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM recurring_expenses WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Generated children of a template (most-recent first). */
    public function spawnedExpenses(int $recurringId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT id, expense_number, expense_date, total_with_gst, status
             FROM operating_expenses
             WHERE spawned_from_recurring_id = ?
             ORDER BY expense_date DESC, id DESC"
        );
        $stmt->execute([$recurringId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
