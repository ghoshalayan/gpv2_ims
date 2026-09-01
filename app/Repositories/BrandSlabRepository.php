<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * CR-1b — brand quantity slabs and the per-product rates inside them.
 *
 * Reads only from its own two tables plus products/brands. Nothing here writes to any
 * invoice, stock, or cost column.
 */
class BrandSlabRepository
{
    /** @return array<int, array<string, mixed>> */
    public function slabsForBrand(int $brandId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM brand_purchase_slabs WHERE brand_id = ?'
             . ($activeOnly ? " AND status = 'active'" : '')
             . ' ORDER BY min_qty ASC, id ASC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSlab(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM brand_purchase_slabs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insertSlab(array $d, int $userId): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO brand_purchase_slabs (brand_id, label, min_qty, max_qty, sort_order, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['brand_id'], $d['label'], $d['min_qty'], $d['max_qty'],
            $d['sort_order'] ?? 0, $userId, $userId,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function updateSlab(int $id, array $d, int $userId): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE brand_purchase_slabs
             SET label = ?, min_qty = ?, max_qty = ?, status = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([$d['label'], $d['min_qty'], $d['max_qty'], $d['status'], $userId, $id]);
    }

    /** Cascades to product_slab_rates via the FK. */
    public function deleteSlab(int $id): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM brand_purchase_slabs WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Rates for one product, keyed by slab id.
     * @return array<int, float>
     */
    public function ratesForProduct(int $productId): array
    {
        $stmt = Db::pdo()->prepare('SELECT slab_id, rate FROM product_slab_rates WHERE product_id = ?');
        $stmt->execute([$productId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['slab_id']] = (float) $r['rate'];
        }
        return $out;
    }

    /**
     * One product's configured bands, lowest first — what the rate resolver walks.
     * Only active slabs count, so disabling a slab removes its suggestion without
     * deleting the rates recorded under it.
     *
     * @return array<int, array{min:float, max:float|null, rate:float, label:string}>
     */
    public function ratesForProductWithBands(int $productId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT psr.rate, bps.min_qty, bps.max_qty, bps.label
             FROM product_slab_rates psr
             JOIN brand_purchase_slabs bps ON bps.id = psr.slab_id
             WHERE psr.product_id = ? AND bps.status = 'active'
             ORDER BY bps.min_qty ASC"
        );
        $stmt->execute([$productId]);

        return \array_map(static fn(array $r) => [
            'min'   => (float) $r['min_qty'],
            'max'   => $r['max_qty'] === null ? null : (float) $r['max_qty'],
            'rate'  => (float) $r['rate'],
            'label' => (string) $r['label'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Upsert; a null/blank rate clears the entry so the product falls back to no suggestion. */
    public function setRate(int $productId, int $slabId, ?float $rate, int $userId): void
    {
        if ($rate === null) {
            $stmt = Db::pdo()->prepare('DELETE FROM product_slab_rates WHERE product_id = ? AND slab_id = ?');
            $stmt->execute([$productId, $slabId]);
            return;
        }
        $stmt = Db::pdo()->prepare(
            'INSERT INTO product_slab_rates (product_id, slab_id, rate, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rate = VALUES(rate), updated_by = VALUES(updated_by)'
        );
        $stmt->execute([$productId, $slabId, $rate, $userId]);
    }

    /**
     * Every active product of a brand with its configured rates — the grid the
     * brand's slab-rate editor renders.
     *
     * @return array<int, array<string, mixed>>
     */
    public function productsWithRates(int $brandId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT p.id, p.name, p.unit, p.moving_avg_cost, p.latest_purchase_landed_cost
             FROM products p
             WHERE p.brand_id = ? AND p.status = 'active'
             ORDER BY p.name"
        );
        $stmt->execute([$brandId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // One query for all rates rather than per-product (avoids N+1 on large brands).
        $rates = [];
        $stmt = Db::pdo()->prepare(
            'SELECT psr.product_id, psr.slab_id, psr.rate
             FROM product_slab_rates psr
             JOIN brand_purchase_slabs bps ON bps.id = psr.slab_id
             WHERE bps.brand_id = ?'
        );
        $stmt->execute([$brandId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rates[(int) $r['product_id']][(int) $r['slab_id']] = (float) $r['rate'];
        }

        foreach ($products as &$p) {
            $p['rates'] = $rates[(int) $p['id']] ?? [];
        }
        return $products;
    }

    /**
     * Slab definitions + rates for every active product of a brand, shaped for the
     * purchase line form's client-side lookup.
     *
     * @return array<int, array<int, array{min:float, max:float|null, rate:float, label:string}>>
     *         product_id => list of bands
     */
    public function slabMapForBrand(int $brandId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT psr.product_id, psr.rate, bps.min_qty, bps.max_qty, bps.label
             FROM product_slab_rates psr
             JOIN brand_purchase_slabs bps ON bps.id = psr.slab_id
             JOIN products p ON p.id = psr.product_id
             WHERE bps.brand_id = ? AND bps.status = 'active' AND p.status = 'active'
             ORDER BY psr.product_id, bps.min_qty"
        );
        $stmt->execute([$brandId]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['product_id']][] = [
                'min'   => (float) $r['min_qty'],
                'max'   => $r['max_qty'] === null ? null : (float) $r['max_qty'],
                'rate'  => (float) $r['rate'],
                'label' => (string) $r['label'],
            ];
        }
        return $map;
    }

    /** Slabs that overlap the given range — used to reject overlapping definitions. */
    public function overlappingSlabs(int $brandId, float $minQty, ?float $maxQty, ?int $excludeId = null): array
    {
        // Half-open ranges [min, max): two ranges overlap when each starts before the
        // other ends. NULL max is treated as +infinity.
        $sql = 'SELECT * FROM brand_purchase_slabs
                WHERE brand_id = ?
                  AND (? IS NULL OR min_qty < ?)
                  AND (max_qty IS NULL OR max_qty > ?)';
        $params = [$brandId, $maxQty, $maxQty, $minQty];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
