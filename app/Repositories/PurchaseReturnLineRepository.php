<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class PurchaseReturnLineRepository
{
    /** @return array<int, array<string, mixed>> */
    public function byReturn(int $returnId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT prl.*,
                    p.name AS product_name,
                    p.unit AS product_unit
             FROM purchase_return_lines prl
             JOIN products p ON p.id = prl.product_id
             WHERE prl.purchase_return_id = ?
             ORDER BY prl.line_no ASC, prl.id ASC'
        );
        $stmt->execute([$returnId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM purchase_return_lines WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(int $returnId, array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO purchase_return_lines
             (purchase_return_id, original_purchase_line_id, line_no,
              product_id, quantity_returned, unit_price, addsub_percent,
              subtotal, tax_rate, tax_amount, cgst_amount, sgst_amount, igst_amount, line_total,
              stock_out_cost_per_unit, stock_out_cost_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $returnId,
            $data['original_purchase_line_id'],
            $data['line_no'],
            $data['product_id'],
            $data['quantity_returned'],
            $data['unit_price'],
            $data['addsub_percent'],
            $data['subtotal']    ?? 0,
            $data['tax_rate']    ?? 0,
            $data['tax_amount']  ?? 0,
            $data['cgst_amount'] ?? 0,
            $data['sgst_amount'] ?? 0,
            $data['igst_amount'] ?? 0,
            $data['line_total']  ?? 0,
            $data['stock_out_cost_per_unit'] ?? 0,
            $data['stock_out_cost_total']    ?? 0,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function updateComputed(int $id, array $totals): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE purchase_return_lines
             SET subtotal = ?, tax_amount = ?, cgst_amount = ?, sgst_amount = ?, igst_amount = ?, line_total = ?,
                 stock_out_cost_per_unit = ?, stock_out_cost_total = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $totals['subtotal'], $totals['tax_amount'], $totals['cgst_amount'],
            $totals['sgst_amount'], $totals['igst_amount'], $totals['line_total'],
            $totals['stock_out_cost_per_unit'], $totals['stock_out_cost_total'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM purchase_return_lines WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function nextLineNo(int $returnId): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(line_no), 0) + 1 FROM purchase_return_lines WHERE purchase_return_id = ?'
        );
        $stmt->execute([$returnId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Qty already returned across drafts + finalized for the given originating purchase line.
     * Excludes a specific draft when provided so the editor doesn't double-count its own row.
     */
    public function returnedQtyForOriginalLine(int $originalPurchaseLineId, ?int $excludeReturnId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(prl.quantity_returned), 0)
                FROM purchase_return_lines prl
                JOIN purchase_returns pr ON pr.id = prl.purchase_return_id
                WHERE prl.original_purchase_line_id = ?
                  AND pr.status IN (\'draft\', \'finalized\')';
        $params = [$originalPurchaseLineId];
        if ($excludeReturnId !== null) {
            $sql .= ' AND pr.id <> ?';
            $params[] = $excludeReturnId;
        }
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}
