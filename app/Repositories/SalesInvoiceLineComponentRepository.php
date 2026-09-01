<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class SalesInvoiceLineComponentRepository
{
    public function insert(int $lineId, array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO sales_invoice_line_components
             (sales_invoice_line_id, component_product_id, component_name, component_unit,
              recipe_quantity, quantity_consumed)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $lineId,
            $data['component_product_id'], $data['component_name'], $data['component_unit'],
            $data['recipe_quantity'], $data['quantity_consumed'],
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function byLine(int $lineId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM sales_invoice_line_components
             WHERE sales_invoice_line_id = ? ORDER BY id'
        );
        $stmt->execute([$lineId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateCogs(int $id, float $cogsPerUnit, float $cogsTotal): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE sales_invoice_line_components
             SET cogs_per_unit = ?, cogs_total = ? WHERE id = ?'
        );
        $stmt->execute([$cogsPerUnit, $cogsTotal, $id]);
    }
}
