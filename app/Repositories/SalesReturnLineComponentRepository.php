<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SalesReturnLineComponentRepository
{
    /** @return array<int, array<string, mixed>> */
    public function byLine(int $returnLineId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT srlc.*, p.unit AS product_unit
             FROM sales_return_line_components srlc
             JOIN products p ON p.id = srlc.component_product_id
             WHERE srlc.sales_return_line_id = ?
             ORDER BY srlc.id ASC'
        );
        $stmt->execute([$returnLineId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(int $returnLineId, array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO sales_return_line_components
             (sales_return_line_id, original_component_id, component_product_id,
              component_name, component_unit, quantity_returned, cogs_per_unit, cogs_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $returnLineId,
            $data['original_component_id'],
            $data['component_product_id'],
            $data['component_name'],
            $data['component_unit'],
            $data['quantity_returned'],
            $data['cogs_per_unit'],
            $data['cogs_total'],
        ]);
        return (int) Db::pdo()->lastInsertId();
    }
}
