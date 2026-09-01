<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class PurchaseReturnRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT pr.id, pr.return_number, pr.return_date, pr.status, pr.refund_method,
                    pr.grand_total, pr.total_stock_out_value,
                    pi.invoice_number AS original_invoice_number,
                    s.name AS supplier_name
             FROM purchase_returns pr
             JOIN purchase_invoices pi ON pi.id = pr.original_purchase_invoice_id
             JOIN suppliers s          ON s.id  = pr.supplier_id
             ORDER BY pr.return_date DESC, pr.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['status']))    { $where[] = 'pr.status = ?';        $params[] = (string) $filters['status']; }
        if (!empty($filters['date_from'])) { $where[] = 'pr.return_date >= ?';  $params[] = (string) $filters['date_from']; }
        if (!empty($filters['date_to']))   { $where[] = 'pr.return_date <= ?';  $params[] = (string) $filters['date_to']; }
        if ($search !== '') {
            $where[]  = '(pr.return_number LIKE ? OR s.name LIKE ? OR pi.invoice_number LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM purchase_returns pr
             JOIN purchase_invoices pi ON pi.id = pr.original_purchase_invoice_id
             JOIN suppliers s          ON s.id  = pr.supplier_id
             {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT pr.id, pr.return_number, pr.return_date, pr.status, pr.refund_method,
                    pr.grand_total, pr.total_stock_out_value,
                    pi.invoice_number AS original_invoice_number,
                    s.name AS supplier_name
             FROM purchase_returns pr
             JOIN purchase_invoices pi ON pi.id = pr.original_purchase_invoice_id
             JOIN suppliers s          ON s.id  = pr.supplier_id
             {$whereSql}
             ORDER BY pr.return_date DESC, pr.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT pr.*,
                    pi.invoice_number AS original_invoice_number,
                    pi.bill_format    AS original_bill_format,
                    pi.tax_treatment  AS original_tax_treatment,
                    pi.subtotal       AS original_subtotal,
                    pi.final_discount_amount AS original_final_discount,
                    pi.total_expenses AS original_total_expenses,
                    pi.total_expense_tax AS original_total_expense_tax,
                    s.name  AS supplier_name,
                    s.gstin AS supplier_gstin,
                    s.phone AS supplier_phone,
                    s.address AS supplier_address
             FROM purchase_returns pr
             JOIN purchase_invoices pi ON pi.id = pr.original_purchase_invoice_id
             JOIN suppliers s          ON s.id  = pr.supplier_id
             WHERE pr.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insertDraft(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO purchase_returns
             (original_purchase_invoice_id, supplier_id, return_date, reason, refund_method,
              refund_account_kind, refund_account_id,
              bill_format, tax_treatment, is_intra_state, invoice_level_tax_rate, gst_claimable,
              remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['original_purchase_invoice_id'], $data['supplier_id'], $data['return_date'],
            $data['reason'] ?? null, $data['refund_method'],
            $data['refund_account_kind'] ?? null, $data['refund_account_id'] ?? null,
            $data['bill_format'], $data['tax_treatment'], $data['is_intra_state'],
            $data['invoice_level_tax_rate'], $data['gst_claimable'],
            $data['remarks'] ?? null, $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function updateDraftHeader(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_returns
             SET return_date = ?, reason = ?, refund_method = ?,
                 refund_account_kind = ?, refund_account_id = ?,
                 include_original_extras = ?, return_charge_mode = ?, return_charge_value = ?,
                 return_charge_taxable = ?, return_charge_tax_rate = ?,
                 remarks = ?, updated_by = ?
             WHERE id = ? AND status = \'draft\''
        );
        $stmt->execute([
            $data['return_date'], $data['reason'] ?? null, $data['refund_method'],
            $data['refund_account_kind'] ?? null, $data['refund_account_id'] ?? null,
            $data['include_original_extras'] ?? 0, $data['return_charge_mode'] ?? 'none',
            $data['return_charge_value'] ?? 0, $data['return_charge_taxable'] ?? 0,
            $data['return_charge_tax_rate'] ?? 0,
            $data['remarks'] ?? null, $updatedBy, $id,
        ]);
    }

    /** Record the cash actually refunded at finalize (0 for credit-note / fully-netted). */
    public function recordCashRefund(int $id, float $amount): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_returns SET cash_refund_amount = ? WHERE id = ?'
        );
        $stmt->execute([$amount, $id]);
    }

    public function updateTotals(int $id, array $totals): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_returns
             SET subtotal = ?, final_discount_share = ?, expenses_share = ?, expenses_tax_share = ?,
                 total_tax = ?, total_cgst = ?, total_sgst = ?, total_igst = ?,
                 total_stock_out_value = ?,
                 return_charge_amount = ?, return_charge_tax_amount = ?,
                 round_off_amount = ?, grand_total = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $totals['subtotal'], $totals['final_discount_share'], $totals['expenses_share'], $totals['expenses_tax_share'],
            $totals['total_tax'], $totals['total_cgst'], $totals['total_sgst'], $totals['total_igst'],
            $totals['total_stock_out_value'],
            $totals['return_charge_amount'] ?? 0, $totals['return_charge_tax_amount'] ?? 0,
            $totals['round_off_amount'], $totals['grand_total'],
            $id,
        ]);
    }

    public function markFinalized(int $id, string $returnNumber, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE purchase_returns
             SET status = 'finalized', return_number = ?,
                 finalized_at = NOW(), finalized_by = ?, updated_by = ?
             WHERE id = ?"
        );
        $stmt->execute([$returnNumber, $userId, $userId, $id]);
    }

    public function cancelDraft(int $id, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE purchase_returns
             SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, updated_by = ?
             WHERE id = ? AND status = 'draft'"
        );
        $stmt->execute([$userId, $userId, $id]);
    }

    public function deleteDraft(int $id): int
    {
        $stmt = Db::pdo()->prepare("DELETE FROM purchase_returns WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /** Return number sequence: GP-PRET/{FY}/{NNNNN}. */
    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(return_number, "/", -1) AS UNSIGNED)), 0)
             FROM purchase_returns
             WHERE return_number LIKE ? AND status = "finalized"'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }

    /** Returns finalized purchase invoices that can be returned against. */
    public function returnableInvoices(): array
    {
        return Db::pdo()->query(
            "SELECT pi.id, pi.invoice_number, pi.invoice_date, pi.grand_total, pi.due_amount,
                    s.id AS supplier_id, s.name AS supplier_name
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             WHERE pi.status = 'finalized'
             ORDER BY pi.invoice_date DESC, pi.id DESC
             LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
