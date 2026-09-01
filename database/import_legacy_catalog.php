<?php
declare(strict_types=1);

/**
 * One-time importer: bring UNIQUE products / categories / subcategories from an older
 * phpMyAdmin JSON export (subratag_ims.json) into the gpv2_ims schema.
 *
 * Scope (deliberate): catalog master data ONLY — no stock, cost, prices-as-balances,
 * customers, or transactions. Imported products start at quantity_on_hand = 0 and
 * moving_avg_cost = 0, so the stock-movement ledger stays reconciled.
 *
 * "Unique" rules (dedupe within the source AND against existing gpv2_ims rows):
 *   - categories     : by lower(trim(name))
 *   - subcategories  : by (mapped category_id, lower(trim(name)))
 *   - products       : by lower(trim(name))    (skips the ~79 repeated legacy names)
 *
 * Idempotent: re-running skips anything already present (matched by the same keys).
 *
 * Usage:  php database/import_legacy_catalog.php [path/to/subratag_ims.json]
 *         (defaults to the file one level above the app root, i.e. next to gpv2_ims/)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Db;

$jsonPath = $argv[1] ?? null;
if ($jsonPath === null) {
    foreach ([
        dirname(__DIR__, 2) . '/subratag_ims.json',   // one level above the app root
        dirname(__DIR__) . '/subratag_ims.json',       // app root
        __DIR__ . '/subratag_ims.json',                // database/ dir
    ] as $cand) {
        if (\is_file($cand)) { $jsonPath = $cand; break; }
    }
}
if ($jsonPath === null || !\is_file($jsonPath)) {
    \fwrite(STDERR, "ERROR: JSON export not found. Pass the path: php database/import_legacy_catalog.php /path/to/subratag_ims.json\n");
    exit(1);
}

$raw = \json_decode((string) \file_get_contents($jsonPath), true);
if (!\is_array($raw)) {
    \fwrite(STDERR, "ERROR: could not parse JSON at {$jsonPath}\n");
    exit(1);
}

// ---- Pull the three phpMyAdmin table blocks -------------------------------------
$tables = [];
foreach ($raw as $obj) {
    if (($obj['type'] ?? '') === 'table') {
        $tables[$obj['name']] = $obj['data'] ?? [];
    }
}
$srcCats = $tables['category_tbl']    ?? [];
$srcSubs = $tables['subcategory_tbl'] ?? [];
$srcProd = $tables['products_tbl']    ?? [];
if ($srcProd === []) {
    \fwrite(STDERR, "ERROR: products_tbl not found / empty in the export.\n");
    exit(1);
}

$pdo = Db::pdo();

// ---- Helpers --------------------------------------------------------------------
$norm     = static fn (string $s): string => \strtolower(\trim($s));
$status   = static fn (?string $s): string => \stripos((string) $s, 'unavailable') !== false ? 'inactive' : 'active';
$clip     = static fn (?string $s, int $n): string => \substr(\trim((string) $s), 0, $n);
$nullable = static function (?string $s, int $n): ?string {
    $s = \trim((string) $s);
    return $s === '' ? null : \substr($s, 0, $n);
};
$cleanHsn = static function (?string $s): ?string {
    $s = \trim((string) $s);
    if ($s === '' || \in_array(\strtolower($s), ['0', 'na', 'n/a', 'nil', '-', 'null'], true)) {
        return null;
    }
    return \substr($s, 0, 20);
};

// Unit normalisation: messy legacy strings → a canonical code that EXISTS in `units`.
$unitCode = static function (string $raw): string {
    $u = \strtolower(\trim($raw));
    $u = \str_replace('\\', '/', $u);
    static $map = [
        'kg' => 'KG', 'gm' => 'G', 'gram' => 'G',
        'ml' => 'ML',
        'liter' => 'LTR', 'litter' => 'LTR', 'ltr' => 'LTR', 'leter' => 'LTR', 'lier' => 'LTR',
        'litrer' => 'LTR', 'liter.' => 'LTR', 'litre' => 'LTR', 'liver' => 'LTR',
        'feet' => 'M', 'foot' => 'M', 'ft' => 'M', 'm' => 'M', 'meter' => 'M', 'metre' => 'M',
        'miter' => 'M', 'mitter' => 'M', 'mtr' => 'M',
        'box' => 'BOX', 'box/piece' => 'BOX',
        'bag' => 'PACK', 'bags' => 'PACK', 'pac' => 'PACK', 'pack' => 'PACK', 'packet' => 'PACK',
    ];
    if (isset($map[$u])) { return $map[$u]; }
    // pieces family + everything unrecognised (na, "", O, 0, RS/- …) → PCS
    return 'PCS';
};

$num = static function ($v): float {
    $f = \is_numeric($v) ? (float) $v : (float) \preg_replace('/[^0-9.\-]/', '', (string) $v);
    return \is_finite($f) ? $f : 0.0;
};

// ---- Load units code → id -------------------------------------------------------
$unitIdByCode = [];
foreach ($pdo->query('SELECT id, code FROM units') as $u) {
    $unitIdByCode[\strtoupper($u['code'])] = (int) $u['id'];
}
$pcsId = $unitIdByCode['PCS'] ?? (int) $pdo->query('SELECT id FROM units ORDER BY id LIMIT 1')->fetchColumn();

// ---- Everything in ONE transaction ---------------------------------------------
$made = ['brand' => 0, 'categories' => 0, 'subcategories' => 0, 'products' => 0];
$skip = ['categories' => 0, 'subcategories' => 0, 'products' => 0];
$unitStats = [];

Db::transaction(function () use (
    $pdo, $srcCats, $srcSubs, $srcProd, $norm, $status, $clip, $nullable, $cleanHsn,
    $unitCode, $num, $unitIdByCode, $pcsId, &$made, &$skip, &$unitStats
): void {
    // Ensure a default brand for imported products.
    $brandId = (int) ($pdo->query("SELECT id FROM brands WHERE name = 'General' LIMIT 1")->fetchColumn() ?: 0);
    if ($brandId === 0) {
        $pdo->prepare("INSERT INTO brands (name, status) VALUES ('General', 'active')")->execute();
        $brandId = (int) $pdo->lastInsertId();
        $made['brand'] = 1;
    }

    // ---- existing name maps (dedupe against what's already there) ----
    $catByName = [];
    foreach ($pdo->query('SELECT id, name FROM categories') as $r) {
        $catByName[$norm($r['name'])] = (int) $r['id'];
    }
    $subByKey = []; // "catId|name" => id
    foreach ($pdo->query('SELECT id, category_id, name FROM subcategories') as $r) {
        $subByKey[$r['category_id'] . '|' . $norm($r['name'])] = (int) $r['id'];
    }
    $prodByName = [];
    foreach ($pdo->query('SELECT id, name FROM products') as $r) {
        $prodByName[$norm($r['name'])] = (int) $r['id'];
    }

    $insCat = $pdo->prepare('INSERT INTO categories (name, description, status) VALUES (?, ?, ?)');
    $insSub = $pdo->prepare('INSERT INTO subcategories (category_id, name, description, status) VALUES (?, ?, ?, ?)');
    $insPrd = $pdo->prepare(
        'INSERT INTO products (name, brand_id, subcategory_id, unit, unit_id, hsn_code, tax_rate,
                               default_selling_price, description, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $getOrMakeCat = function (string $name, string $desc, string $st) use ($insCat, $norm, $clip, &$catByName, &$made): int {
        $key = $norm($name);
        if ($key === '') { $key = 'uncategorized'; $name = 'Uncategorized'; }
        if (isset($catByName[$key])) { return $catByName[$key]; }
        $insCat->execute([$clip($name, 150), $desc !== '' ? $clip($desc, 255) : null, $st]);
        $id = (int) \App\Support\Db::pdo()->lastInsertId();
        $catByName[$key] = $id; $made['categories']++;
        return $id;
    };

    // Catch-all buckets for orphaned rows.
    $uncatCatId = $getOrMakeCat('Uncategorized', 'Legacy rows with no valid category', 'active');

    // ---- 1. categories : old cat_id → new id ----
    $catMap = [];
    foreach ($srcCats as $c) {
        $name = \trim((string) ($c['cat_name'] ?? ''));
        if ($name === '') { $skip['categories']++; continue; }
        $key = $norm($name);
        $existed = isset($catByName[$key]);
        $id = $getOrMakeCat($name, (string) ($c['cat_desc'] ?? ''), $status($c['cat_status'] ?? ''));
        if ($existed) { $skip['categories']++; }
        $catMap[(string) $c['cat_id']] = $id;
    }

    // ---- 2. subcategories : old subcat_id → new id ----
    $subMap = [];
    foreach ($srcSubs as $s) {
        $name = \trim((string) ($s['subcat_name'] ?? ''));
        if ($name === '') { $skip['subcategories']++; continue; }
        $catId = $catMap[(string) ($s['cat_id'] ?? '')] ?? $uncatCatId;
        $key = $catId . '|' . $norm($name);
        if (isset($subByKey[$key])) { $subMap[(string) $s['subcat_id']] = $subByKey[$key]; $skip['subcategories']++; continue; }
        $insSub->execute([$catId, $clip($name, 150),
            \trim((string) ($s['subcat_desc'] ?? '')) !== '' ? $clip((string) $s['subcat_desc'], 255) : null,
            $status($s['subcat_status'] ?? '')]);
        $id = (int) $pdo->lastInsertId();
        $subByKey[$key] = $id; $subMap[(string) $s['subcat_id']] = $id; $made['subcategories']++;
    }

    // Catch-all subcategory for products whose subcat_id doesn't resolve.
    $uncatKey = $uncatCatId . '|uncategorized';
    if (isset($subByKey[$uncatKey])) {
        $uncatSubId = $subByKey[$uncatKey];
    } else {
        $insSub->execute([$uncatCatId, 'Uncategorized', 'Legacy products with no valid sub-category', 'active']);
        $uncatSubId = (int) $pdo->lastInsertId();
        $subByKey[$uncatKey] = $uncatSubId; $made['subcategories']++;
    }

    // ---- 3. products : unique by name ----
    foreach ($srcProd as $p) {
        $name = \trim((string) ($p['pname'] ?? ''));
        if ($name === '') { $skip['products']++; continue; }
        $key = $norm($name);
        if (isset($prodByName[$key])) { $skip['products']++; continue; }   // dup within source or already in DB

        $subId = $subMap[(string) ($p['subcat_id'] ?? '')] ?? $uncatSubId;
        $code  = $unitCode((string) ($p['punit'] ?? ''));
        $unitStats[$code] = ($unitStats[$code] ?? 0) + 1;
        $unitId = $unitIdByCode[$code] ?? $pcsId;

        $tax  = \round($num($p['tax_rate'] ?? 0), 2);
        if ($tax < 0 || $tax > 100) { $tax = 0.0; }
        $sell = \round($num($p['paverage_selling_price'] ?? 0), 2);
        if ($sell < 0) { $sell = 0.0; }

        $insPrd->execute([
            $clip($name, 150), $brandId, $subId, $code, $unitId,
            $cleanHsn($p['hsn_code'] ?? null), $tax, $sell,
            $nullable($p['pdescription'] ?? null, 500), $status($p['pstatus'] ?? ''),
        ]);
        $prodByName[$key] = (int) $pdo->lastInsertId();
        $made['products']++;
    }
});

\ksort($unitStats);
echo "Import complete (source: " . \basename($jsonPath) . ")\n";
echo "  brand 'General' created:  " . ($made['brand'] ? 'yes' : 'already existed') . "\n";
echo "  categories    : created {$made['categories']}, skipped {$skip['categories']} (dupes/existing)\n";
echo "  subcategories : created {$made['subcategories']}, skipped {$skip['subcategories']}\n";
echo "  products      : created {$made['products']}, skipped {$skip['products']}\n";
echo "  unit mapping  : " . \json_encode($unitStats) . "\n";
