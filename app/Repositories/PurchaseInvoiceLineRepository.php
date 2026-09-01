<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class PurchaseInvoiceLineRepository
{
    /** @return array<int, array<string, mixed>> */
    public function byInvoice(int $invoiceId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT pil.*, p.name AS product_name, p.unit AS product_unit, p.brand_id AS product_brand_id,
                    eu.code AS entry_unit_code
             FROM purchase_invoice_lines pil
             JOIN products p ON p.id = pil.product_id
             LEFT JOIN units eu ON eu.id = pil.entry_unit_id
             WHERE pil.purchase_invoice_id = ?
             ORDER BY pil.line_no, pil.id'
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLine(int $lineId, int $invoiceId): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT pil.*, p.name AS product_name, p.unit AS product_unit
             FROM purchase_invoice_lines pil
             JOIN products p ON p.id = pil.product_id
             WHERE pil.id = ? AND pil.purchase_invoice_id = ? LIMIT 1'
        );
        $stmt->execute([$lineId, $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function nextLineNo(int $invoiceId): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(line_no), 0) + 1 FROM purchase_invoice_lines WHERE purchase_invoice_id = ?'
        );
        $stmt->execute([$invoiceId]);
        return (int) $stmt->fetchColumn();
    }

    public function insert(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO purchase_invoice_lines
             (purchase_invoice_id, line_no, product_id, quantity, entry_unit_id, entry_quantity,
              unit_price, addsub_percent,
              subtotal, tax_rate, tax_amount, cgst_amount, sgst_amount, igst_amount, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['purchase_invoice_id'], $data['line_no'], $data['product_id'],
            $data['quantity'], $data['entry_unit_id'] ?? null, $data['entry_quantity'] ?? null,
            $data['unit_price'], $data['addsub_percent'],
            $data['subtotal'], $data['tax_rate'], $data['tax_amount'],
            $data['cgst_amount'], $data['sgst_amount'], $data['igst_amount'],
            $data['line_total'],
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function deleteLine(int $lineId, int $invoiceId): void
    {
        $stmt = Db::pdo()->prepare(
            'DELETE FROM purchase_invoice_lines WHERE id = ? AND purchase_invoice_id = ?'
        );
        $stmt->execute([$lineId, $invoiceId]);
    }

    /**
     * Update the user-entered fields of a draft line (qty, price, addsub, tax)
     * AND the recomputed totals. Product and invoice id are immutable — use
     * delete+add to swap a product.
     */
    public function updateLine(int $lineId, int $invoiceId, array $data): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_invoice_lines
             SET quantity = ?, entry_unit_id = ?, entry_quantity = ?,
                 unit_price = ?, addsub_percent = ?,
                 subtotal = ?, tax_rate = ?, tax_amount = ?,
                 cgst_amount = ?, sgst_amount = ?, igst_amount = ?, line_total = ?
             WHERE id = ? AND purchase_invoice_id = ?'
        );
        $stmt->execute([
            $data['quantity'], $data['entry_unit_id'] ?? null, $data['entry_quantity'] ?? null,
            $data['unit_price'], $data['addsub_percent'],
            $data['subtotal'], $data['tax_rate'], $data['tax_amount'],
            $data['cgst_amount'], $data['sgst_amount'], $data['igst_amount'],
            $data['line_total'], $lineId, $invoiceId,
        ]);
    }

    public function updateLineComputed(int $lineId, array $data): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_invoice_lines
             SET subtotal = ?, tax_amount = ?, cgst_amount = ?, sgst_amount = ?, igst_amount = ?,
                 line_total = ?, allocated_expense_share = ?, allocated_expense_tax_share = ?,
                 landed_cost_per_unit = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['subtotal'], $data['tax_amount'], $data['cgst_amount'], $data['sgst_amount'], $data['igst_amount'],
            $data['line_total'], $data['allocated_expense_share'], $data['allocated_expense_tax_share'],
            $data['landed_cost_per_unit'], $lineId,
        ]);
    }
}
