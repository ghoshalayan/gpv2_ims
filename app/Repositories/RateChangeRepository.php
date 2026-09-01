<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * CR-4 — persistence for bulk selling-price changes.
 *
 * Writes only products.default_selling_price plus this feature's own two audit tables.
 * Never touches stock, moving_avg_cost, or any posted document.
 */
class RateChangeRepository
{
    /**
     * Active products matching the selection, in the order the preview shows them.
     * Inactive products are left out: re-pricing something the business has retired is
     * noise, and it would inflate the "affected" count on the confirmation screen.
     *
     * @param array{category_id?:int|null, subcategory_id?:int|null, brand_id?:int|null} $filters
     * @return array<int, array<string, mixed>>
     */
    public function productsMatching(array $filters): array
    {
        $where  = ["p.status = 'active'"];
        $params = [];

        if (!empty($filters['category_id']))    { $where[] = 'sc.category_id = ?';   $params[] = (int) $filters['category_id']; }
        if (!empty($filters['subcategory_id'])) { $where[] = 'p.subcategory_id = ?'; $params[] = (int) $filters['subcategory_id']; }
        if (!empty($filters['brand_id']))       { $where[] = 'p.brand_id = ?';       $params[] = (int) $filters['brand_id']; }

        $stmt = Db::pdo()->prepare(
            'SELECT p.id, p.name, p.unit, p.default_selling_price, p.moving_avg_cost,
                    p.quantity_on_hand,
                    b.name  AS brand_name,
                    sc.name AS subcategory_name,
                    c.name  AS category_name
             FROM products p
             JOIN brands b        ON b.id  = p.brand_id
             JOIN subcategories sc ON sc.id = p.subcategory_id
             JOIN categories c    ON c.id  = sc.category_id
             WHERE ' . \implode(' AND ', $where) . '
             ORDER BY c.name, sc.name, p.name'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateSellingPrice(int $productId, float $price, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE products SET default_selling_price = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$price, $userId, $productId]);
    }

    public function currentSellingPrice(int $productId): ?float
    {
        $stmt = Db::pdo()->prepare('SELECT default_selling_price FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (float) $v;
    }

    public function insertBatch(array $d, int $userId): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO rate_change_batches
             (batch_number, change_date, category_id, subcategory_id, brand_id,
              mode, value, round_to_rupee, products_affected, remarks, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['batch_number'], $d['change_date'],
            $d['category_id'], $d['subcategory_id'], $d['brand_id'],
            $d['mode'], $d['value'], $d['round_to_rupee'],
            $d['products_affected'], $d['remarks'], $userId,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function insertLine(int $batchId, int $productId, float $oldPrice, float $newPrice): void
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO rate_change_lines (batch_id, product_id, old_price, new_price)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$batchId, $productId, $oldPrice, $newPrice]);
    }

    public function findBatch(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT rcb.*, c.name AS category_name, sc.name AS subcategory_name, b.name AS brand_name
             FROM rate_change_batches rcb
             LEFT JOIN categories c     ON c.id  = rcb.category_id
             LEFT JOIN subcategories sc ON sc.id = rcb.subcategory_id
             LEFT JOIN brands b         ON b.id  = rcb.brand_id
             WHERE rcb.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Locked read, so two concurrent reverts of the same batch serialise. */
    public function findBatchForUpdate(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM rate_change_batches WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function linesForBatch(int $batchId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT rcl.*, p.name AS product_name, p.default_selling_price AS current_price
             FROM rate_change_lines rcl
             JOIN products p ON p.id = rcl.product_id
             WHERE rcl.batch_id = ?
             ORDER BY p.name'
        );
        $stmt->execute([$batchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markReverted(int $batchId, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE rate_change_batches
             SET status = 'reverted', reverted_at = NOW(), reverted_by = ?
             WHERE id = ?"
        );
        $stmt->execute([$userId, $batchId]);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginateBatches(int $page, int $perPage): array
    {
        $total = (int) Db::pdo()->query('SELECT COUNT(*) FROM rate_change_batches')->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT rcb.*, c.name AS category_name, sc.name AS subcategory_name, b.name AS brand_name,
                    u.username AS created_by_name
             FROM rate_change_batches rcb
             LEFT JOIN categories c     ON c.id  = rcb.category_id
             LEFT JOIN subcategories sc ON sc.id = rcb.subcategory_id
             LEFT JOIN brands b         ON b.id  = rcb.brand_id
             LEFT JOIN users u          ON u.id  = rcb.created_by
             ORDER BY rcb.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function maxSequenceForFy(string $fyPrefix): int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(batch_number, "/", -1) AS UNSIGNED)), 0)
             FROM rate_change_batches
             WHERE batch_number LIKE ?'
        );
        $stmt->execute([$fyPrefix . '/%']);
        return (int) $stmt->fetchColumn();
    }
}
