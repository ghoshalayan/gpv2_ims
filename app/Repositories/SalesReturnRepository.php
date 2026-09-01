<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SalesReturnRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT sr.id, sr.return_number, sr.return_date, sr.status, sr.refund_method,
                    sr.grand_total, sr.total_cogs, sr.total_commission_reversed,
                    si.invoice_number AS original_invoice_number,
                    c.name AS customer_name
             FROM sales_returns sr
             JOIN sales_invoices si ON si.id = sr.original_sales_invoice_id
             JOIN customers c       ON c.id  = sr.customer_id
             ORDER BY sr.return_date DESC, sr.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['status']))    { $where[] = 'sr.status = ?';      $params[] = (string) $filters['status']; }
        if (!empty($filters['date_from'])) { $where[] = 'sr.return_date >= ?'; $params[] = (string) $filters['date_from']; }
        if (!empty($filters['date_to']))   { $where[] = 'sr.return_date <= ?'; $params[] = (string) $filters['date_to']; }
        if ($search !== '') {
            $where[]  = '(sr.return_number LIKE ? OR c.name LIKE ? OR osi.invoice_number LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM sales_returns sr
             JOIN sales_invoices osi ON osi.id = sr.original_sales_invoice_id
             JOIN customers c        ON c.id   = sr.customer_id
             {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT sr.id, sr.return_number, sr.return_date, sr.status, sr.refund_method,
                    sr.grand_total, sr.total_cogs, sr.total_commission_reversed,
                    osi.invoice_number AS original_invoice_number,
                    c.name AS customer_name
             FROM sales_returns sr
             JOIN sales_invoices osi ON osi.id = sr.original_sales_invoice_id
             JOIN customers c        ON c.id   = sr.customer_id
             {$whereSql}
             ORDER BY sr.return_date DESC, sr.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT sr.*,
                    si.invoice_number AS original_invoice_number,
                    si.bill_format    AS original_bill_format,
                    si.tax_treatment  AS original_tax_treatment,
                    si.subtotal       AS original_subtotal,
                    si.final_discount_amount AS original_final_discount,
                    si.total_extras   AS original_total_extras,
                    si.total_extras_tax AS original_total_extras_tax,
                    c.name AS customer_name, c.gstin AS customer_gstin,
                    c.phone AS customer_phone, c.address AS customer_address
             FROM sales_returns sr
             JOIN sales_invoices si ON si.id = sr.original_sales_invoice_id
             JOIN customers c       ON c.id  = sr.customer_id
             WHERE sr.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insertDraft(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO sales_returns
             (original_sales_invoice_id, customer_id, return_date, reason, refund_method,
              refund_account_kind, refund_account_id,
              bill_format, tax_treatment, is_intra_state, invoice_level_tax_rate,
              remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['original_sales_invoice_id'], $data['customer_id'], $data['return_date'],
            $data['reason'] ?? null, $data['refund_method'],
            $data['refund_account_kind'] ?? null, $data['refund_account_id'] ?? null,
            $data['bill_format'], $data['tax_treatment'], $data['is_intra_state'],
            $data['invoice_level_tax_rate'],
            $data['remarks'] ?? null, $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function updateDraftHeader(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_returns
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
            'UPDATE sales_returns SET cash_refund_amount = ? WHERE id = ?'
        );
        $stmt->execute([$amount, $id]);
    }

    public function updateTotals(int $id, array $totals): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_returns
             SET subtotal = ?, final_discount_share = ?, extras_share = ?, extras_tax_share = ?,
                 total_tax = ?, total_cgst = ?, total_sgst = ?, total_igst = ?,
                 total_cogs = ?, total_commission_reversed = ?,
                 return_charge_amount = ?, return_charge_tax_amount = ?,
                 round_off_amount = ?, grand_total = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $totals['subtotal'], $totals['final_discount_share'], $totals['extras_share'], $totals['extras_tax_share'],
            $totals['total_tax'], $totals['total_cgst'], $totals['total_sgst'], $totals['total_igst'],
            $totals['total_cogs'], $totals['total_commission_reversed'],
            $totals['return_charge_amount'] ?? 0, $totals['return_charge_tax_amount'] ?? 0,
            $totals['round_off_amount'], $totals['grand_total'],
            $id,
        ]);
    }

    public function markFinalized(int $id, string $returnNumber, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE sales_returns
             SET status = 'finalized', return_number = ?,
                 finalized_at = NOW(), finalized_by = ?, updated_by = ?
             WHERE id = ?"
        );
        $stmt->execute([$returnNumber, $userId, $userId, $id]);
    }

    public function cancelDraft(int $id, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE sales_returns
             SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, updated_by = ?
             WHERE id = ? AND status = 'draft'"
        );
        $stmt->execute([$userId, $userId, $id]);
    }

    /** Return number sequence: GP-RET/{FY}/{NNNNN}. */
    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(return_number, "/", -1) AS UNSIGNED)), 0)
             FROM sales_returns
             WHERE return_number LIKE ? AND status = "finalized"'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }

    /** Returns finalized sales invoices the customer can return against (most recent first). */
    public function returnableInvoices(): array
    {
        return Db::pdo()->query(
            "SELECT si.id, si.invoice_number, si.invoice_date, si.grand_total, si.due_amount,
                    c.id AS customer_id, c.name AS customer_name
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id
             WHERE si.status = 'finalized'
             ORDER BY si.invoice_date DESC, si.id DESC
             LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
