<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SalesInvoiceLineRepository
{
    /** @return array<int, array<string, mixed>> */
    public function byInvoice(int $invoiceId): array
    {
        // LEFT JOIN because ad-hoc composite lines have product_id = NULL.
        $stmt = Db::pdo()->prepare(
            'SELECT sil.*,
                    p.name AS product_name, p.unit AS product_unit, p.brand_id AS product_brand_id,
                    p.is_composite AS product_is_composite,
                    p.is_irreversible AS product_is_irreversible,
                    p.expose_components AS product_expose_components,
                    b.name AS brand_name,
                    sm.name AS salesman_name,
                    eu.code AS entry_unit_code
             FROM sales_invoice_lines sil
             LEFT JOIN products p   ON p.id = sil.product_id
             LEFT JOIN brands b     ON b.id = p.brand_id
             LEFT JOIN salesmen sm  ON sm.id = sil.salesman_id
             LEFT JOIN units eu     ON eu.id = sil.entry_unit_id
             WHERE sil.sales_invoice_id = ?
             ORDER BY sil.line_no, sil.id'
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function nextLineNo(int $invoiceId): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(line_no), 0) + 1 FROM sales_invoice_lines WHERE sales_invoice_id = ?'
        );
        $stmt->execute([$invoiceId]);
        return (int) $stmt->fetchColumn();
    }

    public function insert(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO sales_invoice_lines
             (sales_invoice_id, line_no, product_id, is_composite_line, display_name, salesman_id,
              quantity, entry_unit_id, entry_quantity, unit_price, addsub_percent,
              subtotal, tax_rate, tax_amount, cgst_amount, sgst_amount, igst_amount, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['sales_invoice_id'], $data['line_no'], $data['product_id'],
            $data['is_composite_line'] ?? 0,
            $data['display_name'], $data['salesman_id'],
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
            'DELETE FROM sales_invoice_lines WHERE id = ? AND sales_invoice_id = ?'
        );
        $stmt->execute([$lineId, $invoiceId]);
    }

    public function findLine(int $lineId, int $invoiceId): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM sales_invoice_lines WHERE id = ? AND sales_invoice_id = ? LIMIT 1'
        );
        $stmt->execute([$lineId, $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Update the editable fields of a draft line. Caller recomputes subtotal/tax. */
    public function updateLineEdit(int $lineId, array $data): void
    {
        // Editing happens in the base stock unit, so clear any entry-unit record
        // (the line no longer reflects an alternate-unit entry).
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_invoice_lines
             SET display_name = ?, quantity = ?, entry_unit_id = NULL, entry_quantity = NULL,
                 unit_price = ?, addsub_percent = ?,
                 tax_rate = ?, salesman_id = ?,
                 subtotal = ?, tax_amount = ?,
                 cgst_amount = ?, sgst_amount = ?, igst_amount = ?,
                 line_total = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['display_name'], $data['quantity'], $data['unit_price'], $data['addsub_percent'],
            $data['tax_rate'], $data['salesman_id'],
            $data['subtotal'], $data['tax_amount'],
            $data['cgst_amount'], $data['sgst_amount'], $data['igst_amount'],
            $data['line_total'], $lineId,
        ]);
    }

    public function updateLineComputed(int $lineId, array $data): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_invoice_lines
             SET subtotal = ?, tax_amount = ?, cgst_amount = ?, sgst_amount = ?, igst_amount = ?,
                 line_total = ?, cogs_per_unit = ?, cogs_total = ?,
                 commission_rate = ?, commission_amount = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['subtotal'], $data['tax_amount'], $data['cgst_amount'], $data['sgst_amount'], $data['igst_amount'],
            $data['line_total'], $data['cogs_per_unit'], $data['cogs_total'],
            $data['commission_rate'], $data['commission_amount'],
            $lineId,
        ]);
    }
}
