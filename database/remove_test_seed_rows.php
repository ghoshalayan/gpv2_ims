<?php
declare(strict_types=1);

/**
 * One-off cleanup: remove the built-in TEST/placeholder rows that predate the real
 * catalog import — products P_default / P_legacy / P_ltr, brand "TestBrand", and
 * category "TestCat" (+ its test subcategories).
 *
 * SAFE BY DESIGN — nothing is deleted unless it is provably unused:
 *   - a test product is removed only if it has ZERO references in any transactional
 *     table (sales / purchase / returns / adjustments / production / recipes / rebates
 *     / commission). Its derived rows (stock_movements, price history, product_units,
 *     compositions) fall away via ON DELETE CASCADE.
 *   - "TestBrand" is removed only if no product / rebate / purchase-invoice references it.
 *   - a "TestCat" subcategory is removed only if no product references it; the category
 *     only once it has no subcategories left.
 *
 * Idempotent (re-run = no-op) and transactional. Prints exactly what it did / skipped.
 * Run on local and/or server:  php database/remove_test_seed_rows.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Db;

$pdo = Db::pdo();

// Transactional tables that mean a product is genuinely in use (all ON DELETE RESTRICT).
$productRefs = [
    ['brand_rebate_cost_adjustments', 'product_id'],
    ['brand_rebates', 'free_product_id'],
    ['product_composition_items', 'component_product_id'],
    ['production_order_lines', 'component_product_id'],
    ['production_orders', 'product_id'],
    ['purchase_invoice_lines', 'product_id'],
    ['purchase_return_lines', 'product_id'],
    ['sales_invoice_line_components', 'component_product_id'],
    ['sales_invoice_lines', 'product_id'],
    ['sales_return_line_components', 'component_product_id'],
    ['sales_return_lines', 'product_id'],
    ['salesman_commission_settlements', 'goods_product_id'],
    ['stock_adjustment_lines', 'product_id'],
];

$refCount = static function (PDO $pdo, array $refs, string $col, int $id): array {
    $hits = [];
    foreach ($refs as [$t, $c]) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM {$t} WHERE {$c} = ?");
        $s->execute([$id]);
        $n = (int) $s->fetchColumn();
        if ($n > 0) { $hits[] = "{$t}({$n})"; }
    }
    return $hits;
};

$log = [];

Db::transaction(function () use ($pdo, $productRefs, $refCount, &$log): void {
    // ---- 1. test products ----
    $tp = $pdo->query("SELECT id, name FROM products WHERE name IN ('P_default','P_legacy','P_ltr')")
              ->fetchAll(PDO::FETCH_ASSOC);
    $del = $pdo->prepare('DELETE FROM products WHERE id = ?');
    foreach ($tp as $p) {
        $hits = $refCount($pdo, $productRefs, 'id', (int) $p['id']);
        if ($hits !== []) {
            $log[] = "SKIP product {$p['name']} — in use by " . \implode(', ', $hits);
            continue;
        }
        $del->execute([(int) $p['id']]);   // CASCADE clears stock_movements / price history / units / compositions
        $log[] = "deleted product {$p['name']}";
    }

    // ---- 2. TestCat subcategories, then TestCat ----
    $cat = $pdo->query("SELECT id FROM categories WHERE name = 'TestCat' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $catId = (int) $cat['id'];
        $subs = $pdo->prepare('SELECT id, name FROM subcategories WHERE category_id = ?');
        $subs->execute([$catId]);
        $delSub = $pdo->prepare('DELETE FROM subcategories WHERE id = ?');
        $prodInSub = $pdo->prepare('SELECT COUNT(*) FROM products WHERE subcategory_id = ?');
        foreach ($subs->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $prodInSub->execute([(int) $s['id']]);
            if ((int) $prodInSub->fetchColumn() > 0) {
                $log[] = "SKIP subcategory {$s['name']} — still has products";
                continue;
            }
            $delSub->execute([(int) $s['id']]);
            $log[] = "deleted subcategory {$s['name']}";
        }
        $left = $pdo->prepare('SELECT COUNT(*) FROM subcategories WHERE category_id = ?');
        $left->execute([$catId]);
        if ((int) $left->fetchColumn() === 0) {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$catId]);
            $log[] = 'deleted category TestCat';
        } else {
            $log[] = 'SKIP category TestCat — still has subcategories';
        }
    }

    // ---- 3. TestBrand (only if fully unused) ----
    $brand = $pdo->query("SELECT id FROM brands WHERE name = 'TestBrand' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($brand) {
        $bid = (int) $brand['id'];
        $hits = $refCount($pdo, [['products', 'brand_id'], ['brand_rebates', 'brand_id'], ['purchase_invoices', 'brand_id']], 'id', $bid);
        if ($hits !== []) {
            $log[] = 'SKIP brand TestBrand — in use by ' . \implode(', ', $hits);
        } else {
            $pdo->prepare('DELETE FROM brands WHERE id = ?')->execute([$bid]);
            $log[] = 'deleted brand TestBrand';
        }
    }
});

if ($log === []) {
    echo "Nothing to remove — no test/placeholder rows found.\n";
} else {
    echo "Test-seed cleanup:\n";
    foreach ($log as $line) { echo "  {$line}\n"; }
}
