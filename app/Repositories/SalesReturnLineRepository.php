<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SalesReturnLineRepository
{
    /** @return array<int, array<string, mixed>> */
    public function byReturn(int $returnId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT srl.*,
                    sm.name AS salesman_name,
                    p.unit AS product_unit
             FROM sales_return_lines srl
             LEFT JOIN salesmen sm ON sm.id = srl.salesman_id
             LEFT JOIN products p  ON p.id  = srl.product_id
             WHERE srl.sales_return_id = ?
             ORDER BY srl.line_no ASC, srl.id ASC'
        );
        $stmt->execute([$returnId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM sales_return_lines WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(int $returnId, array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO sales_return_lines
             (sales_return_id, original_sales_line_id, line_no,
              product_id, is_composite_line, display_name, salesman_id,
              quantity_returned, unit_price, addsub_percent,
              subtotal, tax_rate, tax_amount, cgst_amount, sgst_amount, igst_amount, line_total,
              cogs_per_unit, cogs_total, commission_rate, commission_reversed)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $returnId,
            $data['original_sales_line_id'],
            $data['line_no'],
            $data['product_id'],
            (int) $data['is_composite_line'],
            $data['display_name'],
            $data['salesman_id'],
            $data['quantity_returned'],
            $data['unit_price'],
            $data['addsub_percent'],
            $data['subtotal'] ?? 0,
            $data['tax_rate'] ?? 0,
            $data['tax_amount'] ?? 0,
            $data['cgst_amount'] ?? 0,
            $data['sgst_amount'] ?? 0,
            $data['igst_amount'] ?? 0,
            $data['line_total'] ?? 0,
            $data['cogs_per_unit'] ?? 0,
            $data['cogs_total'] ?? 0,
            $data['commission_rate'] ?? 0,
            $data['commission_reversed'] ?? 0,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function updateComputed(int $id, array $totals): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_return_lines
             SET subtotal = ?, tax_amount = ?, cgst_amount = ?, sgst_amount = ?, igst_amount = ?, line_total = ?,
                 cogs_total = ?, commission_reversed = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $totals['subtotal'], $totals['tax_amount'], $totals['cgst_amount'],
            $totals['sgst_amount'], $totals['igst_amount'], $totals['line_total'],
            $totals['cogs_total'], $totals['commission_reversed'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM sales_return_lines WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function nextLineNo(int $returnId): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(line_no), 0) + 1 FROM sales_return_lines WHERE sales_return_id = ?'
        );
        $stmt->execute([$returnId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * For a given originating sales invoice line, the qty already returned across ALL
     * finalized + draft returns (excludes a specific draft if provided so the editor
     * doesn't double-count its own row).
     */
    public function returnedQtyForOriginalLine(int $originalSalesLineId, ?int $excludeReturnId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(srl.quantity_returned), 0)
                FROM sales_return_lines srl
                JOIN sales_returns sr ON sr.id = srl.sales_return_id
                WHERE srl.original_sales_line_id = ?
                  AND sr.status IN (\'draft\', \'finalized\')';
        $params = [$originalSalesLineId];
        if ($excludeReturnId !== null) {
            $sql .= ' AND sr.id <> ?';
            $params[] = $excludeReturnId;
        }
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}
