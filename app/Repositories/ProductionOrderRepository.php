<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class ProductionOrderRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT po.id, po.order_number, po.produced_date, po.quantity_produced,
                    po.total_component_cost, po.landed_cost_per_unit, po.created_at,
                    p.name AS product_name, p.unit AS product_unit,
                    u.name AS created_by_name
             FROM production_orders po
             JOIN products p  ON p.id  = po.product_id
             LEFT JOIN users u ON u.id = po.created_by
             ORDER BY po.produced_date DESC, po.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT po.*, p.name AS product_name, p.unit AS product_unit,
                    p.is_irreversible, u.name AS created_by_name
             FROM production_orders po
             JOIN products p ON p.id = po.product_id
             LEFT JOIN users u ON u.id = po.created_by
             WHERE po.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function linesByOrder(int $orderId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM production_order_lines
             WHERE production_order_id = ? ORDER BY id'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertHeader(array $data, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO production_orders
             (product_id, produced_date, quantity_produced,
              total_component_cost, landed_cost_per_unit, notes, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['product_id'], $data['produced_date'], $data['quantity_produced'],
            $data['total_component_cost'], $data['landed_cost_per_unit'],
            $data['notes'], $createdBy, $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function insertLine(int $orderId, array $line): void
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO production_order_lines
             (production_order_id, component_product_id, component_name, component_unit,
              recipe_quantity, quantity_consumed, moving_avg_cost_at_production, line_cost)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orderId, $line['component_product_id'], $line['component_name'], $line['component_unit'],
            $line['recipe_quantity'], $line['quantity_consumed'],
            $line['moving_avg_cost_at_production'], $line['line_cost'],
        ]);
    }

    public function setOrderNumber(int $id, string $orderNumber): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE production_orders SET order_number = ? WHERE id = ?'
        );
        $stmt->execute([$orderNumber, $id]);
    }

    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(order_number, "/", -1) AS UNSIGNED)), 0)
             FROM production_orders WHERE order_number LIKE ?'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }
}
