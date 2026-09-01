<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class ProductRepository
{
    /** @return array<int, array<string, mixed>> Full list with brand + subcategory + category names joined. */
    public function all(): array
    {
        return Db::pdo()->query(
            'SELECT p.id, p.name, p.unit, p.hsn_code, p.tax_rate,
                    p.default_selling_price, p.quantity_on_hand, p.moving_avg_cost,
                    p.latest_purchase_landed_cost, p.latest_purchase_at,
                    p.low_stock_threshold, p.status,
                    p.is_composite, p.is_irreversible, p.expose_components,
                    CASE WHEN p.is_composite = 1 THEN COALESCE((
                        SELECT SUM(pci.quantity * cp.latest_purchase_landed_cost)
                        FROM product_composition_items pci
                        JOIN product_compositions pc ON pc.id = pci.composition_id
                        JOIN products cp ON cp.id = pci.component_product_id
                        WHERE pc.product_id = p.id
                    ), 0) ELSE 0 END AS derived_market_price,
                    CASE WHEN p.is_composite = 1 THEN COALESCE((
                        SELECT SUM(pci.quantity * cp.moving_avg_cost)
                        FROM product_composition_items pci
                        JOIN product_compositions pc ON pc.id = pci.composition_id
                        JOIN products cp ON cp.id = pci.component_product_id
                        WHERE pc.product_id = p.id
                    ), 0) ELSE 0 END AS derived_avg_cost,
                    b.id  AS brand_id, b.name  AS brand_name, b.status AS brand_status,
                    sc.id AS subcategory_id, sc.name AS subcategory_name, sc.status AS subcategory_status,
                    c.id  AS category_id, c.name AS category_name, c.status AS category_status
             FROM products p
             JOIN brands b        ON b.id  = p.brand_id
             JOIN subcategories sc ON sc.id = p.subcategory_id
             JOIN categories c     ON c.id  = sc.category_id
             ORDER BY p.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{
     *   brand_id?:int|null, subcategory_id?:int|null, category_id?:int|null,
     *   status?:string, stock_state?:string, price_min?:float|null, price_max?:float|null
     * } $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage, string $search = ''): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['brand_id']))       { $where[] = 'p.brand_id = ?';       $params[] = (int) $filters['brand_id']; }
        if (!empty($filters['subcategory_id'])) { $where[] = 'p.subcategory_id = ?'; $params[] = (int) $filters['subcategory_id']; }
        if (!empty($filters['status']))         { $where[] = 'p.status = ?';         $params[] = (string) $filters['status']; }

        // CR-9 — category filter. Subcategories carry category_id, so this reaches
        // categories through sc without needing the categories table itself.
        if (!empty($filters['category_id']))    { $where[] = 'sc.category_id = ?';   $params[] = (int) $filters['category_id']; }

        // CR-9 — stock state. Mirrors the low-stock definition the dashboard uses and
        // is backed by idx_products_low_stock (quantity_on_hand, low_stock_threshold).
        $where[] = match ($filters['stock_state'] ?? '') {
            'in_stock'     => 'p.quantity_on_hand > 0',
            'out_of_stock' => 'p.quantity_on_hand <= 0',
            'low_stock'    => 'p.quantity_on_hand > 0 AND p.quantity_on_hand <= p.low_stock_threshold',
            default        => '1 = 1',
        };

        // CR-9 — selling-price band. Nulls mean "unbounded on that side".
        if (isset($filters['price_min']) && $filters['price_min'] !== null) {
            $where[] = 'p.default_selling_price >= ?'; $params[] = (float) $filters['price_min'];
        }
        if (isset($filters['price_max']) && $filters['price_max'] !== null) {
            $where[] = 'p.default_selling_price <= ?'; $params[] = (float) $filters['price_max'];
        }

        if ($search !== '') {
            // CR-9 — widened from name/HSN/brand/subcategory to also match the category
            // and the free-text description, so a part number or a note finds the product.
            $where[]  = '(p.name LIKE ? OR p.hsn_code LIKE ? OR p.description LIKE ?
                          OR b.name LIKE ? OR sc.name LIKE ? OR sc2c.name LIKE ?)';
            $like = '%' . $search . '%';
            for ($i = 0; $i < 6; $i++) { $params[] = $like; }
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        // The categories join is INNER on a NOT NULL FK, so it cannot change the count;
        // it is present only so the category name is searchable in both queries.
        $count = Db::pdo()->prepare(
            "SELECT COUNT(*)
             FROM products p
             JOIN brands b          ON b.id  = p.brand_id
             JOIN subcategories sc  ON sc.id = p.subcategory_id
             JOIN categories sc2c   ON sc2c.id = sc.category_id
             {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT p.id, p.name, p.unit, p.hsn_code, p.tax_rate,
                    p.default_selling_price, p.quantity_on_hand, p.moving_avg_cost,
                    p.latest_purchase_landed_cost, p.latest_purchase_at,
                    p.low_stock_threshold, p.status,
                    p.is_composite, p.is_irreversible, p.expose_components,
                    CASE WHEN p.is_composite = 1 THEN COALESCE((
                        SELECT SUM(pci.quantity * cp.latest_purchase_landed_cost)
                        FROM product_composition_items pci
                        JOIN product_compositions pc ON pc.id = pci.composition_id
                        JOIN products cp ON cp.id = pci.component_product_id
                        WHERE pc.product_id = p.id
                    ), 0) ELSE 0 END AS derived_market_price,
                    CASE WHEN p.is_composite = 1 THEN COALESCE((
                        SELECT SUM(pci.quantity * cp.moving_avg_cost)
                        FROM product_composition_items pci
                        JOIN product_compositions pc ON pc.id = pci.composition_id
                        JOIN products cp ON cp.id = pci.component_product_id
                        WHERE pc.product_id = p.id
                    ), 0) ELSE 0 END AS derived_avg_cost,
                    b.id  AS brand_id, b.name  AS brand_name, b.status AS brand_status,
                    sc.id AS subcategory_id, sc.name AS subcategory_name, sc.status AS subcategory_status,
                    sc2c.id AS category_id, sc2c.name AS category_name, sc2c.status AS category_status
             FROM products p
             JOIN brands b          ON b.id  = p.brand_id
             JOIN subcategories sc  ON sc.id = p.subcategory_id
             JOIN categories sc2c   ON sc2c.id = sc.category_id
             {$whereSql}
             ORDER BY p.name
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Selectable products for purchase/sales line dropdowns. Hides any product
     * whose own status, brand status, subcategory status, or category status is inactive.
     * (This is the cascade discussed earlier — one place to enforce it.)
     *
     * @return array<int, array{id:int, name:string, unit:string}>
     */
    public function listSelectable(): array
    {
        return Db::pdo()->query(
            "SELECT p.id, p.name, p.unit
             FROM products p
             JOIN brands b         ON b.id = p.brand_id
             JOIN subcategories sc ON sc.id = p.subcategory_id
             JOIN categories c     ON c.id = sc.category_id
             WHERE p.status = 'active'
               AND b.status = 'active'
               AND sc.status = 'active'
               AND c.status = 'active'
             ORDER BY p.name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active products under one brand (for brand-scoped pickers).
     * @return array<int, array{id:int, name:string, unit:string, quantity_on_hand:int, moving_avg_cost:string}>
     */
    public function listByBrand(int $brandId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT id, name, unit, quantity_on_hand, moving_avg_cost
             FROM products
             WHERE brand_id = ? AND status = 'active'
             ORDER BY name"
        );
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT p.*, sc.category_id
             FROM products p
             JOIN subcategories sc ON sc.id = p.subcategory_id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM products WHERE name = ?';
        $params = [$name];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(array $data, int $createdBy): int
    {
        // latest_purchase_at is set only when a latest cost is actually known (> 0).
        $openingLatest = (float) ($data['opening_latest_cost'] ?? 0);
        $latestAt      = $openingLatest > 0 ? \date('Y-m-d H:i:s') : null;

        $stmt = Db::pdo()->prepare(
            'INSERT INTO products
             (name, brand_id, subcategory_id, unit, unit_id, hsn_code, tax_rate,
              default_selling_price, quantity_on_hand, moving_avg_cost,
              latest_purchase_landed_cost, latest_purchase_at,
              low_stock_threshold, description,
              is_composite, is_irreversible, expose_components,
              created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['brand_id'],
            $data['subcategory_id'],
            $data['unit'],
            $data['unit_id'],
            $data['hsn_code'],
            $data['tax_rate'],
            $data['default_selling_price'],
            $data['opening_quantity'] ?? 0,
            $data['opening_avg_cost']  ?? 0,
            $openingLatest,
            $latestAt,
            $data['low_stock_threshold'],
            $data['description'],
            $data['is_composite']      ?? 0,
            $data['is_irreversible']   ?? 0,
            $data['expose_components'] ?? 0,
            $createdBy,
            $createdBy,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /** Updates only the master fields. Stock + cost are NOT touched here. */
    public function update(int $id, array $data, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE products
             SET name = ?, brand_id = ?, subcategory_id = ?, unit = ?, unit_id = ?, hsn_code = ?,
                 tax_rate = ?, default_selling_price = ?, low_stock_threshold = ?,
                 description = ?,
                 is_composite = ?, is_irreversible = ?, expose_components = ?,
                 updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['brand_id'],
            $data['subcategory_id'],
            $data['unit'],
            $data['unit_id'],
            $data['hsn_code'],
            $data['tax_rate'],
            $data['default_selling_price'],
            $data['low_stock_threshold'],
            $data['description'],
            $data['is_composite']      ?? 0,
            $data['is_irreversible']   ?? 0,
            $data['expose_components'] ?? 0,
            $updatedBy,
            $id,
        ]);
    }

    public function setStatus(int $id, string $status, int $updatedBy): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE products SET status = ?, updated_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $updatedBy, $id]);
    }
}
