<?php
declare(strict_types=1);

/**
 * One-time importer (run AFTER import_legacy_catalog.php): set opening stock for the
 * imported products from the old export's pqty_available (qty) valued at
 * paverage_buying_price (cost).
 *
 * Rules (per owner decisions):
 *   - qty numeric > 0  ............ use it, keep the product's current unit.
 *   - qty like "10 box" / "2 roll"  parse BOTH: qty = number, unit = word → set the
 *                                    product's unit to that (creates ROLL if needed).
 *   - cost missing / 0 ........... SKIP opening stock (never fabricate a cost basis).
 *   - pure garbage qty ("O") ..... skip.  negative qty ..... skip.
 *   - absurd qty (> 1,000,000) ... skip and LIST for manual review.
 *
 * For each qualifying product it sets quantity_on_hand + moving_avg_cost and writes ONE
 * 'opening' stock_movements row, so the ledger invariant (qty == SUM(delta)) holds.
 *
 * Idempotent: only touches products that currently have quantity_on_hand = 0 AND no
 * stock-movement rows, so re-running is a no-op for already-seeded products.
 *
 * Usage:  php database/import_legacy_opening_stock.php [path/to/subratag_ims.json]
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Db;

const QTY_ABSURD_CAP = 1000000.0;

$jsonPath = $argv[1] ?? null;
if ($jsonPath === null) {
    foreach ([dirname(__DIR__, 2) . '/subratag_ims.json', dirname(__DIR__) . '/subratag_ims.json', __DIR__ . '/subratag_ims.json'] as $cand) {
        if (\is_file($cand)) { $jsonPath = $cand; break; }
    }
}
if ($jsonPath === null || !\is_file($jsonPath)) {
    \fwrite(STDERR, "ERROR: JSON export not found. Pass the path explicitly.\n"); exit(1);
}
$raw = \json_decode((string) \file_get_contents($jsonPath), true);
$srcProd = [];
foreach ((array) $raw as $obj) {
    if (($obj['type'] ?? '') === 'table' && $obj['name'] === 'products_tbl') { $srcProd = $obj['data'] ?? []; }
}
if ($srcProd === []) { \fwrite(STDERR, "ERROR: products_tbl not found in export.\n"); exit(1); }

$pdo  = Db::pdo();
$norm = static fn (string $s): string => \strtolower(\trim($s));
$num  = static fn ($v): float => \is_numeric(\trim((string) $v)) ? (float) \trim((string) $v) : NAN;

// word (from "N word") → canonical unit code
$wordToCode = static function (string $w): ?string {
    $w = \strtolower(\trim($w));
    static $m = [
        'box' => 'BOX', 'roll' => 'ROLL', 'piece' => 'PCS', 'pcs' => 'PCS', 'pices' => 'PCS',
        'kg' => 'KG', 'gm' => 'G', 'pck' => 'PACK', 'pac' => 'PACK', 'pack' => 'PACK', 'packet' => 'PACK',
        'ltr' => 'LTR', 'liter' => 'LTR', 'litter' => 'LTR', 'ml' => 'ML',
    ];
    return $m[$w] ?? null;   // unknown (e.g. "o") → skip
};

// Parse pqty_available → [qty, ?unitOverrideCode, reasonIfSkipped]
$parseQty = static function (string $q) use ($num, $wordToCode): array {
    $q = \trim($q);
    if ($q === '') { return [null, null, 'blank']; }
    if (\is_numeric($q)) {
        $v = (float) $q;
        if ($v < 0)  { return [null, null, 'negative']; }
        if ($v == 0) { return [null, null, 'zero']; }
        return [$v, null, null];
    }
    if (\preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z\/]+)$/', $q, $mm)) {
        $v = (float) $mm[1];
        $code = $wordToCode($mm[2]);
        if ($code === null) { return [null, null, 'garbage']; }   // e.g. "2o"
        if ($v <= 0) { return [null, null, 'zero']; }
        return [$v, $code, null];
    }
    return [null, null, 'garbage'];   // "O", "2 roll xyz", etc.
};

// Build source-by-name (FIRST occurrence — mirrors the catalog import's dedupe).
$srcByName = [];
foreach ($srcProd as $p) {
    $k = $norm((string) ($p['pname'] ?? ''));
    if ($k === '' || isset($srcByName[$k])) { continue; }
    $srcByName[$k] = $p;
}

$stats = ['applied' => 0, 'no_cost' => 0, 'bad_qty' => 0, 'absurd' => 0, 'unit_fixed' => 0, 'skipped_has_stock' => 0];
$absurdList = [];
$rollCreated = false;

Db::transaction(function () use (
    $pdo, $srcByName, $parseQty, $norm, $num, &$stats, &$absurdList, &$rollCreated
): void {
    // Ensure ROLL unit exists (self-based packaging unit, like BOX).
    $rollId = (int) ($pdo->query("SELECT id FROM units WHERE code = 'ROLL' LIMIT 1")->fetchColumn() ?: 0);
    if ($rollId === 0) {
        $pdo->prepare("INSERT INTO units (code, name, family, base_unit_id, factor_to_base, decimals, is_active)
                       VALUES ('ROLL', 'Roll', 'pack', NULL, 1, 0, 1)")->execute();
        $rollId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE units SET base_unit_id = ? WHERE id = ?')->execute([$rollId, $rollId]);
        $rollCreated = true;
    }
    $unitIdByCode = [];
    foreach ($pdo->query('SELECT id, code FROM units') as $u) { $unitIdByCode[\strtoupper($u['code'])] = (int) $u['id']; }

    // Candidate products: untouched (0 stock) AND no ledger rows yet.
    $rows = $pdo->query(
        "SELECT p.id, p.name FROM products p
         WHERE p.quantity_on_hand = 0
           AND NOT EXISTS (SELECT 1 FROM stock_movements sm WHERE sm.product_id = p.id)"
    )->fetchAll(\PDO::FETCH_ASSOC);

    $updProd = $pdo->prepare('UPDATE products SET quantity_on_hand = ?, moving_avg_cost = ? WHERE id = ?');
    $updUnit = $pdo->prepare('UPDATE products SET unit = ?, unit_id = ?, quantity_on_hand = ?, moving_avg_cost = ? WHERE id = ?');
    $insMove = $pdo->prepare(
        "INSERT INTO stock_movements (product_id, direction, quantity_delta, unit_cost, balance_after, source, notes)
         VALUES (?, 'opening', ?, ?, ?, 'opening', 'Legacy opening stock import')"
    );

    foreach ($rows as $r) {
        $src = $srcByName[$norm($r['name'])] ?? null;
        if ($src === null) { continue; }   // not a legacy-imported product

        $cost = $num($src['paverage_buying_price'] ?? '');
        if (!\is_finite($cost) || $cost <= 0) { $stats['no_cost']++; continue; }   // per decision: skip

        [$qty, $unitCode, $why] = $parseQty((string) ($src['pqty_available'] ?? ''));
        if ($qty === null) { $stats[$why === 'negative' || $why === 'garbage' ? 'bad_qty' : 'bad_qty']++; continue; }
        if ($qty > QTY_ABSURD_CAP) { $stats['absurd']++; $absurdList[] = $r['name'] . ' = ' . $qty; continue; }

        $pid  = (int) $r['id'];
        $cost = \round($cost, 4);
        $qty  = \round($qty, 3);

        if ($unitCode !== null && isset($unitIdByCode[$unitCode])) {
            $updUnit->execute([$unitCode, $unitIdByCode[$unitCode], $qty, $cost, $pid]);
            $stats['unit_fixed']++;
        } else {
            $updProd->execute([$qty, $cost, $pid]);
        }
        $insMove->execute([$pid, $qty, $cost, $qty]);
        $stats['applied']++;
    }
});

echo "Opening-stock import complete (source: " . \basename($jsonPath) . ")\n";
echo "  ROLL unit created: " . ($rollCreated ? 'yes' : 'already existed') . "\n";
echo "  opening stock applied : {$stats['applied']} products";
echo $stats['unit_fixed'] ? " (unit corrected on {$stats['unit_fixed']} via \"N unit\" strings)\n" : "\n";
echo "  skipped — no cost     : {$stats['no_cost']}\n";
echo "  skipped — bad/garbage qty : {$stats['bad_qty']}\n";
echo "  skipped — absurd qty  : {$stats['absurd']}\n";
foreach ($absurdList as $a) { echo "      review: {$a}\n"; }
