<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SalesInvoiceRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT si.id, si.invoice_number, si.invoice_date, si.bill_type, si.status, si.payment_status,
                    si.grand_total, si.paid_amount, si.due_amount,
                    c.name AS customer_name,
                    sm.name AS header_salesman_name
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id
             LEFT JOIN salesmen sm ON sm.id = si.header_salesman_id
             ORDER BY si.invoice_date DESC, si.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Server-side paginated listing with optional status filter + free-text search on
     * invoice_number / customer_name. Returns ['rows' => array, 'total' => int].
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'si.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $where[]  = 'si.payment_status = ?';
            $params[] = (string) $filters['payment_status'];
        }
        if (!empty($filters['customer_id'])) {
            $where[]  = 'si.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'si.invoice_date >= ?';
            $params[] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'si.invoice_date <= ?';
            $params[] = (string) $filters['date_to'];
        }
        if ($search !== '') {
            $where[]  = '(si.invoice_number LIKE ? OR c.name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
                     FROM sales_invoices si
                     JOIN customers c ON c.id = si.customer_id
                     {$whereSql}";
        $countStmt = Db::pdo()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $sql = "SELECT si.id, si.invoice_number, si.invoice_date, si.bill_type, si.status, si.payment_status,
                       si.grand_total, si.paid_amount, si.due_amount,
                       c.name AS customer_name,
                       sm.name AS header_salesman_name
                FROM sales_invoices si
                JOIN customers c ON c.id = si.customer_id
                LEFT JOIN salesmen sm ON sm.id = si.header_salesman_id
                {$whereSql}
                ORDER BY si.invoice_date DESC, si.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT si.*,
                    c.name AS customer_name, c.gstin AS customer_gstin, c.state AS customer_state,
                    c.phone AS customer_phone, c.email AS customer_email, c.address AS customer_address,
                    sm.name AS header_salesman_name
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id
             LEFT JOIN salesmen sm ON sm.id = si.header_salesman_id
             WHERE si.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insertDraft(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO sales_invoices
             (customer_id, invoice_date, bill_format, tax_treatment, invoice_level_tax_rate,
              is_intra_state, header_salesman_id, final_discount_amount, remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        // invoice_level_tax_rate + final_discount_amount now live in the footer;
        // header form supplies 0 defaults. Footer endpoint updates them separately.
        $stmt->execute([
            $data['customer_id'], $data['invoice_date'], $data['bill_format'],
            $data['tax_treatment'], $data['invoice_level_tax_rate'] ?? 0,
            $data['is_intra_state'], $data['header_salesman_id'],
            $data['final_discount_amount'] ?? 0,
            $data['remarks'], $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function updateDraftHeader(int $id, array $data, int $updatedBy): void
    {
        // Header only carries: date, bill_format, tax_treatment, intra/inter, salesman, remarks.
        // Discount + invoice-level tax rate are set via updateFooter().
        // If bill_format changes from discount_then_tax → per_line_tax, the service is
        // responsible for zeroing invoice_level_tax_rate + final_discount_amount.
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_invoices
             SET invoice_date = ?, bill_format = ?, tax_treatment = ?,
                 is_intra_state = ?, header_salesman_id = ?,
                 remarks = ?, updated_by = ?
             WHERE id = ? AND status = \'draft\''
        );
        $stmt->execute([
            $data['invoice_date'], $data['bill_format'], $data['tax_treatment'],
            $data['is_intra_state'],
            $data['header_salesman_id'],
            $data['remarks'], $updatedBy, $id,
        ]);
    }

    /**
     * Zero out footer-driven fields when bill_format switches to per_line_tax
     * (Mode B has neither invoice-level tax rate nor final discount).
     */
    public function zeroFooterFields(int $id): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_invoices
             SET invoice_level_tax_rate = 0, final_discount_amount = 0
             WHERE id = ? AND status = \'draft\''
        );
        $stmt->execute([$id]);
    }

    public function updateTotals(int $id, array $totals): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_invoices
             SET subtotal = ?, total_tax = ?, total_cgst = ?, total_sgst = ?, total_igst = ?,
                 total_cogs = ?, total_commission = ?,
                 total_extras = ?, total_extras_tax = ?,
                 final_discount_amount = ?,
                 round_off_amount = ?, grand_total = ?, due_amount = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $totals['subtotal'], $totals['total_tax'], $totals['total_cgst'], $totals['total_sgst'], $totals['total_igst'],
            $totals['total_cogs'], $totals['total_commission'],
            $totals['total_extras'] ?? 0, $totals['total_extras_tax'] ?? 0,
            $totals['final_discount_amount'] ?? 0,
            $totals['round_off_amount'], $totals['grand_total'], $totals['grand_total'],
            $id,
        ]);
    }

    /**
     * Footer-driven fields (invoice-level tax rate + final discount). Draft only.
     * These live in the footer of the edit screen, not the header form.
     */
    public function updateFooter(int $id, float $invoiceLevelTaxRate, float $finalDiscountAmount, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_invoices
             SET invoice_level_tax_rate = ?, final_discount_amount = ?, updated_by = ?
             WHERE id = ? AND status = \'draft\''
        );
        $stmt->execute([$invoiceLevelTaxRate, $finalDiscountAmount, $updatedBy, $id]);
    }

    public function markFinalized(int $id, string $invoiceNumber, string $billType, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE sales_invoices
             SET status = 'finalized', invoice_number = ?, bill_type = ?,
                 finalized_at = NOW(), finalized_by = ?, updated_by = ?
             WHERE id = ?"
        );
        $stmt->execute([$invoiceNumber, $billType, $userId, $userId, $id]);
    }

    public function cancelDraft(int $id, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE sales_invoices
             SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, updated_by = ?
             WHERE id = ? AND status = 'draft'"
        );
        $stmt->execute([$userId, $userId, $id]);
    }

    /**
     * Hard delete a draft (lines / extras / line-components cascade via FK).
     * Returns the number of rows deleted (0 if not a draft / not found).
     */
    public function deleteDraft(int $id): int
    {
        $stmt = Db::pdo()->prepare("DELETE FROM sales_invoices WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Customer's outstanding due from all OTHER finalized invoices (excludes the one we're printing).
     * Used on printed bills to show "Previous Due" alongside the current invoice balance.
     */
    /**
     * Everything else the customer owes, besides the invoice being printed.
     *
     * Includes legacy obligations - opening dues carried in from before the
     * system - so the printed total matches the Due List and the passbook.
     * Leaving them out understated what a customer owed on every bill.
     * Advances are not netted off, for the same reason the Due List does not.
     */
    public function previousDueForCustomer(int $customerId, int $excludeInvoiceId): float
    {
        $stmt = Db::pdo()->prepare(
            "SELECT
               (SELECT COALESCE(SUM(due_amount), 0)
                  FROM sales_invoices
                 WHERE customer_id = ?
                   AND status = 'finalized'
                   AND payment_status IN ('unpaid', 'partial')
                   AND id <> ?)
             + (SELECT COALESCE(SUM(due_amount), 0)
                  FROM legacy_obligations
                 WHERE customer_id = ?
                   AND due_amount > 0)"
        );
        $stmt->execute([$customerId, $excludeInvoiceId, $customerId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Unpaid / partial finalized invoices for a customer (most recent first).
     * @return array<int, array<string, mixed>> rows of {id, invoice_number, invoice_date, grand_total, due_amount}
     */
    public function unpaidForCustomer(int $customerId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT id, invoice_number, invoice_date, grand_total, due_amount
             FROM sales_invoices
             WHERE customer_id = ?
               AND status = 'finalized'
               AND payment_status IN ('unpaid', 'partial')
               AND due_amount > 0
             ORDER BY invoice_date ASC, id ASC"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find the highest sequence number used in the given fiscal-year prefix.
     * Returns 0 if none. Caller adds 1 to get the next number.
     */
    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_number, "/", -1) AS UNSIGNED)), 0)
             FROM sales_invoices
             WHERE invoice_number LIKE ? AND status = "finalized"'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }
}
