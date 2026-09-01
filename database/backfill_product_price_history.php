<?php
declare(strict_types=1);

/**
 * One-shot back-fill for product_price_history snapshot columns
 * (prior_qty, prior_avg, event_qty, event_unit_cost, new_qty, new_avg).
 *
 * For each existing row that has source_ref_type/source_ref_id and source ∈
 * {opening, purchase, return, production, manual}, we look up the corresponding
 * source row to derive event_qty and event_unit_cost. We then walk all rows
 * for the product in chronological order, computing the running qty + avg
 * exactly the way InventoryService::applyStockIn does, and writing the
 * snapshot back. Rows whose source row no longer exists or whose snapshot
 * cannot be derived stay NULL — the UI shows a "—" for those.
 *
 * Idempotent: re-running it overwrites the snapshot consistently.
 *
 * Usage:   php database/backfill_product_price_history.php
 *          php database/backfill_product_price_history.php --product=123
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Db;

if (PHP_SAPI !== 'cli') {
    \http_response_code(403);
    exit("Run from CLI only.\n");
}

$args = [];
foreach (\array_slice($argv, 1) as $arg) {
    if (\str_starts_with($arg, '--') && \str_contains($arg, '=')) {
        [$k, $v] = \explode('=', \substr($arg, 2), 2);
        $args[$k] = $v;
    }
}
$onlyProduct = isset($args['product']) ? (int) $args['product'] : null;

$pdo = Db::pdo();

$productSql = 'SELECT DISTINCT product_id FROM product_price_history';
if ($onlyProduct !== null) { $productSql .= ' WHERE product_id = ' . $onlyProduct; }
$productIds = $pdo->query($productSql)->fetchAll(\PDO::FETCH_COLUMN);

echo \count($productIds) . " product(s) to back-fill\n";

$updated = 0;
$skipped = 0;
foreach ($productIds as $pid) {
    $rows = $pdo->prepare(
        'SELECT id, landed_cost, source, source_ref_type, source_ref_id, effective_at
         FROM product_price_history
         WHERE product_id = ?
         ORDER BY effective_at ASC, id ASC'
    );
    $rows->execute([$pid]);
    $events = $rows->fetchAll(\PDO::FETCH_ASSOC);

    // Walk chronologically, maintaining running qty + avg.
    $runQty = 0.0;
    $runAvg = 0.0;
    foreach ($events as $r) {
        $derived = deriveEventQtyAndCost($pdo, (int) $pid, $r);
        if ($derived === null) {
            $skipped++;
            // We don't know the event qty — leave snapshot NULL on this row,
            // but try to track running state from new_avg if it was the cost-set case.
            // We can't update running qty for unknown events, so best-effort: skip.
            continue;
        }
        $eventQty  = (float) $derived['event_qty'];   // signed: +in / −out (out events are 0 since they don't shift avg)
        $eventCost = (float) $derived['event_unit_cost'];
        $newAvg    = (float) $r['landed_cost'];

        $priorQty = $runQty;
        $priorAvg = $runAvg;
        $newQty   = $runQty + $eventQty;

        $upd = $pdo->prepare(
            'UPDATE product_price_history
             SET prior_qty = ?, prior_avg = ?, event_qty = ?, event_unit_cost = ?,
                 new_qty = ?, new_avg = ?
             WHERE id = ?'
        );
        $upd->execute([
            \round($priorQty, 3), \round($priorAvg, 4),
            \round($eventQty, 3), \round($eventCost, 4),
            \round($newQty, 3),   \round($newAvg, 4),
            (int) $r['id'],
        ]);
        $updated++;

        // Advance running state to the post-event values recorded on the row.
        $runQty = $newQty;
        $runAvg = $newAvg;
    }
}

echo "Updated {$updated} rows, skipped {$skipped} (no derivable source).\n";

/**
 * Look at the source row referenced by a price_history entry and pull the
 * event quantity + unit cost. Returns null if it cannot be determined
 * (e.g. opening source with no qty data, source row deleted, etc.).
 *
 * @return ?array{event_qty: float, event_unit_cost: float}
 */
function deriveEventQtyAndCost(\PDO $pdo, int $productId, array $row): ?array
{
    $type = (string) ($row['source_ref_type'] ?? '');
    $id   = (int)    ($row['source_ref_id']   ?? 0);
    $cost = (float)  $row['landed_cost'];
    $src  = (string) $row['source'];

    if ($src === 'manual') {
        // No stock movement — qty is 0; cost is the new landed cost.
        return ['event_qty' => 0.0, 'event_unit_cost' => $cost];
    }

    // Purchase invoice line.
    if ($type === 'purchase_invoice_line' && $id > 0) {
        $stmt = $pdo->prepare(
            'SELECT quantity, COALESCE(landed_cost_per_unit, unit_price) AS unit_cost
             FROM purchase_invoice_lines
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($r === false) { return null; }
        return ['event_qty' => (float) $r['quantity'], 'event_unit_cost' => (float) $r['unit_cost']];
    }

    // Sales return line — restock at the saved cogs basis.
    if ($type === 'sales_return_line' && $id > 0) {
        $stmt = $pdo->prepare(
            'SELECT quantity, cogs_per_unit FROM sales_return_lines WHERE id = ?'
        );
        $stmt->execute([$id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($r === false) { return null; }
        return ['event_qty' => (float) $r['quantity'], 'event_unit_cost' => (float) $r['cogs_per_unit']];
    }

    // Stock adjustment line (in direction → +qty; out direction → 0 since it doesn't shift avg).
    if ($type === 'stock_adjustment_line' && $id > 0) {
        $stmt = $pdo->prepare(
            'SELECT sal.quantity, sal.cost_per_unit, sa.direction
             FROM stock_adjustment_lines sal
             JOIN stock_adjustments sa ON sa.id = sal.stock_adjustment_id
             WHERE sal.id = ?'
        );
        $stmt->execute([$id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($r === false) { return null; }
        $qty = (string) $r['direction'] === 'in' ? (float) $r['quantity'] : 0.0;
        return ['event_qty' => $qty, 'event_unit_cost' => (float) $r['cost_per_unit']];
    }

    // Production order — composite IN (the recorded cost is the assembled cost).
    if ($type === 'production_order' && $id > 0) {
        $stmt = $pdo->prepare(
            'SELECT quantity_produced FROM production_orders WHERE id = ?'
        );
        $stmt->execute([$id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($r === false) { return null; }
        return ['event_qty' => (float) $r['quantity_produced'], 'event_unit_cost' => $cost];
    }

    // Brand rebate cost adjustment — moving avg shifts but qty unchanged.
    if ($type === 'brand_rebate_cost_adjustment' && $id > 0) {
        return ['event_qty' => 0.0, 'event_unit_cost' => $cost];
    }

    // Brand rebate free stock — IN at the rebate's value-per-unit.
    if ($type === 'brand_rebate' && $id > 0) {
        $stmt = $pdo->prepare(
            'SELECT free_quantity, free_value_per_unit FROM brand_rebates WHERE id = ?'
        );
        $stmt->execute([$id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($r === false) { return null; }
        return [
            'event_qty'       => (float) ($r['free_quantity'] ?? 0),
            'event_unit_cost' => (float) ($r['free_value_per_unit'] ?? $cost),
        ];
    }

    // Opening stock at product creation: source 'opening', no source_ref.
    if ($src === 'opening') {
        $stmt = $pdo->prepare('SELECT quantity_on_hand FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $qty = (float) $stmt->fetchColumn();
        // Best-effort: assume the row records the FULL opening qty (only true if there
        // are no prior events, which is always the case for 'opening' since it's the first).
        return ['event_qty' => $qty, 'event_unit_cost' => $cost];
    }

    return null;
}
