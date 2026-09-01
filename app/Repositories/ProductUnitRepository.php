<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

class ProductUnitRepository
{
    /** @return array<int, array<string, mixed>> Per-product unit overrides with the unit's name + family. */
    public function byProduct(int $productId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT pu.*, u.code AS unit_code, u.name AS unit_name, u.family AS unit_family
             FROM product_units pu
             JOIN units u ON u.id = pu.unit_id
             WHERE pu.product_id = ?
             ORDER BY pu.is_default_purchase DESC, pu.is_default_sale DESC, u.code"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM product_units WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Resolve a single (product, unit) override — used for entry→base conversion. */
    public function findByProductAndUnit(int $productId, int $unitId): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM product_units WHERE product_id = ? AND unit_id = ? LIMIT 1'
        );
        $stmt->execute([$productId, $unitId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Units selectable for a product on a transaction line: the base stock unit
     * (factor 1) plus configured overrides flagged for the given direction.
     *
     * @param string $direction 'purchase' | 'sale'
     * @return array<int, array{unit_id:int, code:string, name:string, factor:float, is_default:bool, is_base:bool}>
     */
    public function selectableForProduct(int $productId, int $baseUnitId, string $direction): array
    {
        // Base unit always first.
        $base = Db::pdo()->prepare('SELECT id, code, name FROM units WHERE id = ?');
        $base->execute([$baseUnitId]);
        $b = $base->fetch(PDO::FETCH_ASSOC);
        $out = [];
        if ($b !== false) {
            $out[] = [
                'unit_id' => (int) $b['id'], 'code' => $b['code'], 'name' => $b['name'],
                'factor' => 1.0, 'is_default' => true, 'is_base' => true,
            ];
        }
        $flag = $direction === 'purchase' ? 'is_purchase' : 'is_sale';
        $defFlag = $direction === 'purchase' ? 'is_default_purchase' : 'is_default_sale';
        $stmt = Db::pdo()->prepare(
            "SELECT pu.unit_id, u.code, u.name, pu.factor_to_product_base AS factor, pu.{$defFlag} AS is_default
             FROM product_units pu JOIN units u ON u.id = pu.unit_id
             WHERE pu.product_id = ? AND pu.{$flag} = 1 AND pu.unit_id <> ?
             ORDER BY pu.{$defFlag} DESC, u.code"
        );
        $stmt->execute([$productId, $baseUnitId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'unit_id' => (int) $r['unit_id'], 'code' => $r['code'], 'name' => $r['name'],
                'factor' => (float) $r['factor'], 'is_default' => (bool) (int) $r['is_default'], 'is_base' => false,
            ];
        }
        // If an alternate is the configured default, it should win over the base default flag.
        $hasAltDefault = false;
        foreach ($out as $o) { if (!$o['is_base'] && $o['is_default']) { $hasAltDefault = true; break; } }
        if ($hasAltDefault) { $out[0]['is_default'] = false; }
        return $out;
    }

    public function existsForProduct(int $productId, int $unitId, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM product_units WHERE product_id = ? AND unit_id = ?';
        $params = [$productId, $unitId];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = Db::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO product_units
             (product_id, unit_id, factor_to_product_base,
              is_purchase, is_sale, is_default_purchase, is_default_sale, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['product_id'], $data['unit_id'], $data['factor_to_product_base'],
            $data['is_purchase'] ? 1 : 0, $data['is_sale'] ? 1 : 0,
            $data['is_default_purchase'] ? 1 : 0, $data['is_default_sale'] ? 1 : 0,
            $data['notes'] ?? null,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Db::pdo()->prepare(
            'UPDATE product_units
             SET factor_to_product_base = ?, is_purchase = ?, is_sale = ?,
                 is_default_purchase = ?, is_default_sale = ?, notes = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['factor_to_product_base'],
            $data['is_purchase'] ? 1 : 0, $data['is_sale'] ? 1 : 0,
            $data['is_default_purchase'] ? 1 : 0, $data['is_default_sale'] ? 1 : 0,
            $data['notes'] ?? null, $id,
        ]);
    }

    public function delete(int $id): void
    {
        Db::pdo()->prepare('DELETE FROM product_units WHERE id = ?')->execute([$id]);
    }

    /**
     * Build, in 2 queries, the selectable-units map for a set of products and a
     * direction — for embedding in a line-entry form as a JS lookup. Each entry
     * is the base unit (factor 1) plus configured overrides for that direction.
     *
     * @param array<int,int> $productIds
     * @param string $direction 'purchase' | 'sale'
     * @return array<int, array<int, array{unit_id:int, code:string, factor:float, is_default:bool, is_base:bool}>>
     */
    public function unitMapForProducts(array $productIds, string $direction): array
    {
        $ids = \array_values(\array_unique(\array_map('intval', $productIds)));
        if ($ids === []) { return []; }
        $ph = \implode(',', \array_fill(0, \count($ids), '?'));

        $stmt = Db::pdo()->prepare(
            "SELECT p.id AS product_id, p.unit_id, u.code
             FROM products p JOIN units u ON u.id = p.unit_id
             WHERE p.id IN ({$ph})"
        );
        $stmt->execute($ids);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['product_id']] = [[
                'unit_id' => (int) $r['unit_id'], 'code' => $r['code'],
                'factor' => 1.0, 'is_default' => true, 'is_base' => true,
            ]];
        }

        $flag    = $direction === 'purchase' ? 'is_purchase' : 'is_sale';
        $defFlag = $direction === 'purchase' ? 'is_default_purchase' : 'is_default_sale';
        $stmt = Db::pdo()->prepare(
            "SELECT pu.product_id, pu.unit_id, u.code,
                    pu.factor_to_product_base AS factor, pu.{$defFlag} AS is_default
             FROM product_units pu JOIN units u ON u.id = pu.unit_id
             WHERE pu.product_id IN ({$ph}) AND pu.{$flag} = 1
             ORDER BY pu.{$defFlag} DESC, u.code"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pid = (int) $r['product_id'];
            if (!isset($map[$pid])) { continue; }
            $isDefault = (bool) (int) $r['is_default'];
            if ($isDefault) { $map[$pid][0]['is_default'] = false; }
            $map[$pid][] = [
                'unit_id' => (int) $r['unit_id'], 'code' => $r['code'],
                'factor' => (float) $r['factor'], 'is_default' => $isDefault, 'is_base' => false,
            ];
        }
        return $map;
    }

    /** Reset other rows' default flag for the given (product, kind). Used to enforce single-default. */
    public function clearDefaultsForProduct(int $productId, string $kind, ?int $exceptId = null): void
    {
        $col = $kind === 'purchase' ? 'is_default_purchase' : 'is_default_sale';
        $sql = "UPDATE product_units SET {$col} = 0 WHERE product_id = ?";
        $params = [$productId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        Db::pdo()->prepare($sql)->execute($params);
    }
}
