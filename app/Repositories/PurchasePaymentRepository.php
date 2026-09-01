<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class PurchasePaymentRepository
{
    /** @return array<int, array<string, mixed>> */
    public function byInvoice(int $invoiceId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT pp.*,
                    CASE pp.paid_from_kind
                        WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = pp.paid_from_id)
                        WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = pp.paid_from_id)
                    END AS paid_from_name,
                    (SELECT advance_number FROM supplier_advances WHERE id = pp.from_advance_id) AS from_advance_number
             FROM purchase_payments pp
             WHERE pp.purchase_invoice_id = ?
             ORDER BY pp.payment_date DESC, pp.id DESC"
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM purchase_payments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO purchase_payments
             (purchase_invoice_id, payment_date, cash_amount, online_amount, online_reference,
              cheque_amount, cheque_number, cheque_date, cheque_bank_name, cheque_status,
              paid_from_kind, paid_from_id,
              from_advance_amount, from_advance_id,
              total_amount, remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['purchase_invoice_id'], $data['payment_date'],
            $data['cash_amount'], $data['online_amount'], $data['online_reference'],
            $data['cheque_amount'], $data['cheque_number'], $data['cheque_date'], $data['cheque_bank_name'],
            $data['cheque_status'] ?? 'pending',
            $data['paid_from_kind'] ?? null, $data['paid_from_id'] ?? null,
            $data['from_advance_amount'] ?? 0, $data['from_advance_id'] ?? null,
            $data['total_amount'], $data['remarks'], $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM purchase_payments WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function updateChequeStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_payments SET cheque_status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }

    /**
     * Effective paid: cash + online + counted-cheque PLUS any from_advance_amount applied.
     * Bounced/cancelled cheques drop out. Advance applications stay (real prepaid credit).
     */
    public function effectivePaidForInvoice(int $invoiceId): float
    {
        $stmt = Db::pdo()->prepare(
            "SELECT COALESCE(SUM(
                cash_amount + online_amount + from_advance_amount +
                CASE WHEN cheque_status IN ('pending','cleared') THEN cheque_amount ELSE 0 END
             ), 0)
             FROM purchase_payments WHERE purchase_invoice_id = ?"
        );
        $stmt->execute([$invoiceId]);
        return (float) $stmt->fetchColumn();
    }
}
