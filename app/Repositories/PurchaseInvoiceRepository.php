<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class PurchaseInvoiceRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT pi.id, pi.invoice_number, pi.invoice_date, pi.status, pi.payment_status,
                    pi.grand_total, pi.paid_amount, pi.due_amount,
                    s.name AS supplier_name, b.name AS brand_name
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             JOIN brands b    ON b.id = pi.brand_id
             ORDER BY pi.invoice_date DESC, pi.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['status']))         { $where[] = 'pi.status = ?';          $params[] = (string) $filters['status']; }
        if (!empty($filters['payment_status'])) { $where[] = 'pi.payment_status = ?';  $params[] = (string) $filters['payment_status']; }
        if (!empty($filters['supplier_id']))    { $where[] = 'pi.supplier_id = ?';     $params[] = (int) $filters['supplier_id']; }
        if (!empty($filters['brand_id']))       { $where[] = 'pi.brand_id = ?';        $params[] = (int) $filters['brand_id']; }
        if (!empty($filters['date_from']))      { $where[] = 'pi.invoice_date >= ?';   $params[] = (string) $filters['date_from']; }
        if (!empty($filters['date_to']))        { $where[] = 'pi.invoice_date <= ?';   $params[] = (string) $filters['date_to']; }
        if ($search !== '') {
            $where[] = '(pi.invoice_number LIKE ? OR s.name LIKE ?)';
            $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             JOIN brands b    ON b.id = pi.brand_id
             {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT pi.id, pi.invoice_number, pi.invoice_date, pi.status, pi.payment_status,
                    pi.grand_total, pi.paid_amount, pi.due_amount,
                    s.name AS supplier_name, b.name AS brand_name
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             JOIN brands b    ON b.id = pi.brand_id
             {$whereSql}
             ORDER BY pi.invoice_date DESC, pi.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT pi.*, s.name AS supplier_name, s.state AS supplier_state,
                    b.name AS brand_name
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             JOIN brands b    ON b.id = pi.brand_id
             WHERE pi.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsBySupplierAndNumber(int $supplierId, string $invoiceNumber, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM purchase_invoices WHERE supplier_id = ? AND invoice_number = ?';
        $params = [$supplierId, $invoiceNumber];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insertDraft(array $data, int $createdBy): int
    {
        // invoice_level_tax_rate + final_discount_amount are footer-editable (Mode A only),
        // set via updateFooter(). Header insert always stores 0.
        $stmt = Db::pdo()->prepare(
            'INSERT INTO purchase_invoices
             (invoice_number, supplier_id, brand_id, invoice_date,
              bill_format, tax_treatment, gst_claimable, is_intra_state,
              remarks, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['invoice_number'], $data['supplier_id'], $data['brand_id'], $data['invoice_date'],
            $data['bill_format'], $data['tax_treatment'], $data['gst_claimable'],
            $data['is_intra_state'],
            $data['remarks'], $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function updateDraftHeader(int $id, array $data, int $updatedBy): void
    {
        // Header never touches invoice_level_tax_rate / final_discount_amount.
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_invoices
             SET invoice_number = ?, invoice_date = ?, bill_format = ?, tax_treatment = ?,
                 gst_claimable = ?, is_intra_state = ?, remarks = ?, updated_by = ?
             WHERE id = ? AND status = \'draft\''
        );
        $stmt->execute([
            $data['invoice_number'], $data['invoice_date'], $data['bill_format'],
            $data['tax_treatment'], $data['gst_claimable'],
            $data['is_intra_state'], $data['remarks'], $updatedBy, $id,
        ]);
    }

    /** Footer-driven fields. Draft only. Mode A uses both; Mode B should call zeroFooterFields. */
    public function updateFooter(int $id, float $invoiceLevelTaxRate, float $finalDiscountAmount, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_invoices
             SET invoice_level_tax_rate = ?, final_discount_amount = ?, updated_by = ?
             WHERE id = ? AND status = \'draft\''
        );
        $stmt->execute([$invoiceLevelTaxRate, $finalDiscountAmount, $updatedBy, $id]);
    }

    public function zeroFooterFields(int $id): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_invoices
             SET invoice_level_tax_rate = 0, final_discount_amount = 0
             WHERE id = ? AND status = \'draft\''
        );
        $stmt->execute([$id]);
    }

    public function updateTotals(int $id, array $totals): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_invoices
             SET subtotal = ?, total_tax = ?, total_cgst = ?, total_sgst = ?, total_igst = ?,
                 total_expenses = ?, total_expense_tax = ?,
                 final_discount_amount = ?,
                 round_off_amount = ?, grand_total = ?, due_amount = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $totals['subtotal'], $totals['total_tax'], $totals['total_cgst'], $totals['total_sgst'], $totals['total_igst'],
            $totals['total_expenses'], $totals['total_expense_tax'],
            $totals['final_discount_amount'] ?? 0,
            $totals['round_off_amount'], $totals['grand_total'], $totals['grand_total'],
            $id,
        ]);
    }

    public function markFinalized(int $id, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE purchase_invoices
             SET status = 'finalized', finalized_at = NOW(), finalized_by = ?, updated_by = ?
             WHERE id = ?"
        );
        $stmt->execute([$userId, $userId, $id]);
    }

    public function cancelDraft(int $id, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE purchase_invoices
             SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, updated_by = ?
             WHERE id = ? AND status = 'draft'"
        );
        $stmt->execute([$userId, $userId, $id]);
    }

    /**
     * Hard delete a draft (lines / expenses cascade via FK). Returns rows deleted.
     */
    public function deleteDraft(int $id): int
    {
        $stmt = Db::pdo()->prepare("DELETE FROM purchase_invoices WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Unpaid / partial finalized invoices for a supplier (oldest first — drives bulk-payment allocation order).
     * @return array<int, array<string, mixed>> rows of {id, invoice_number, invoice_date, grand_total, due_amount}
     */
    public function unpaidForSupplier(int $supplierId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT id, invoice_number, invoice_date, grand_total, due_amount
             FROM purchase_invoices
             WHERE supplier_id = ?
               AND status = 'finalized'
               AND payment_status IN ('unpaid', 'partial')
               AND due_amount > 0
             ORDER BY invoice_date ASC, id ASC"
        );
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
