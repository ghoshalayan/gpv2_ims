<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * Append-only stock ledger. One signed row per stock event (see migration 0054).
 * Invariant: products.quantity_on_hand == SUM(quantity_delta) per product.
 */
class StockMovementRepository
{
    /**
     * @param array{
     *   direction:string, quantity_delta:float, unit_cost?:float, balance_after?:float,
     *   source?:string, source_ref_type?:?string, source_ref_id?:?int, notes?:?string, user_id?:?int
     * } $data
     */
    public function insert(int $productId, array $data): void
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO stock_movements
             (product_id, direction, quantity_delta, unit_cost, balance_after,
              source, source_ref_type, source_ref_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $productId,
            $data['direction'],
            $data['quantity_delta'],
            $data['unit_cost']       ?? 0,
            $data['balance_after']   ?? null,
            $data['source']          ?? 'other',
            $data['source_ref_type'] ?? null,
            $data['source_ref_id']   ?? null,
            $data['notes']           ?? null,
            $data['user_id']         ?? null,
        ]);
    }

    /** @return array<int, array<string, mixed>> Most-recent movements for a product. */
    public function byProduct(int $productId, int $limit = 100): array
    {
        $limit = \max(1, $limit);
        $stmt = Db::pdo()->prepare(
            "SELECT * FROM stock_movements WHERE product_id = ?
             ORDER BY id DESC LIMIT {$limit}"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
