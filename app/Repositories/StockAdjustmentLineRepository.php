<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class StockAdjustmentLineRepository
{
    /** @return array<int, array<string, mixed>> */
    public function byHeader(int $headerId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT sal.*, p.name AS product_name, p.unit AS product_unit
             FROM stock_adjustment_lines sal
             JOIN products p ON p.id = sal.product_id
             WHERE sal.stock_adjustment_id = ?
             ORDER BY sal.line_no ASC'
        );
        $stmt->execute([$headerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO stock_adjustment_lines
             (stock_adjustment_id, line_no, product_id, quantity,
              cost_at_adjustment, line_value, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['stock_adjustment_id'], $data['line_no'], $data['product_id'],
            $data['quantity'], $data['cost_at_adjustment'], $data['line_value'],
            $data['notes'] ?? null,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }
}
