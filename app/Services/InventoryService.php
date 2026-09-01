<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductPriceHistoryRepository;
use App\Repositories\StockMovementRepository;
use App\Support\Db;

/**
 * Only place that mutates products.quantity_on_hand and products.moving_avg_cost.
 * Every transactional service (purchase, sale, return, write-off) calls through here.
 *
 * Moving-average-on-landed-cost per D10:
 *   new_avg = ((old_qty * old_avg) + (delta_qty_in * landed_cost_per_unit)) / (old_qty + delta_qty_in)
 *
 * If old_qty is 0 or negative at stock-in, we clamp the weighting floor at 0 — the incoming
 * landed cost fully sets the new average. This is defensive against oversell deficit scenarios (D16).
 */
class InventoryService
{
    public function __construct(
        private ProductPriceHistoryRepository $priceHistory = new ProductPriceHistoryRepository(),
        private StockMovementRepository       $movements    = new StockMovementRepository(),
    ) {}

    /**
     * Apply a stock-in event (purchase line finalize, production, sales return acceptance).
     * Uses a row-level lock to serialize concurrent finalizes on the same product.
     *
     * @param array{source?:string, source_ref_type?:string, source_ref_id?:int, notes?:string, user_id?:int} $meta
     *   Optional metadata for the price-history entry. Defaults to source='other'.
     */
    public function applyStockIn(int $productId, float $quantityIn, float $landedCostPerUnit, array $meta = []): void
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT quantity_on_hand, moving_avg_cost FROM products WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException("Product #{$productId} not found for stock-in.");
        }

        $oldQty  = (float) $row['quantity_on_hand'];
        $oldAvg  = (float) $row['moving_avg_cost'];
        $effectiveOldQty = \max(0.0, $oldQty);
        $newQty  = $oldQty + $quantityIn;

        if ($effectiveOldQty + $quantityIn <= 0) {
            $newAvg = $landedCostPerUnit;
        } else {
            $newAvg = (($effectiveOldQty * $oldAvg) + ($quantityIn * $landedCostPerUnit))
                    / ($effectiveOldQty + $quantityIn);
        }

        $rounded = \round($landedCostPerUnit, 4);
        // Only purchase/production events update latest_purchase_landed_cost. Returns
        // (sales/purchase) restock at a snapshotted cost which isn't a fresh "latest"
        // — leave that column untouched so it still reflects the most recent real buy.
        $source = (string) ($meta['source'] ?? 'other');
        $touchesLatest = \in_array($source, ['purchase', 'production'], true);
        if ($touchesLatest) {
            $update = $pdo->prepare(
                'UPDATE products
                 SET quantity_on_hand = ?, moving_avg_cost = ?,
                     latest_purchase_landed_cost = ?, latest_purchase_at = NOW()
                 WHERE id = ?'
            );
            $update->execute([$newQty, \round($newAvg, 4), $rounded, $productId]);
        } else {
            $update = $pdo->prepare(
                'UPDATE products
                 SET quantity_on_hand = ?, moving_avg_cost = ?
                 WHERE id = ?'
            );
            $update->execute([$newQty, \round($newAvg, 4), $productId]);
        }

        // Price history entry — with full moving-avg breakdown so the product
        // edit page can render the formula:
        //   prior_qty=A @ prior_avg=B  +  event_qty=C @ event_unit_cost=D
        //                                       =>  new_qty=A+C @ new_avg=…
        $this->priceHistory->insert(
            $productId,
            $rounded,
            $meta['source']          ?? 'other',
            $meta['source_ref_type'] ?? null,
            $meta['source_ref_id']   ?? null,
            $meta['notes']           ?? null,
            $meta['user_id']         ?? null,
            [
                'prior_qty'       => $oldQty,
                'prior_avg'       => $oldAvg,
                'event_qty'       => $quantityIn,
                'event_unit_cost' => $rounded,
                'new_qty'         => $newQty,
                'new_avg'         => \round($newAvg, 4),
            ]
        );

        // Stock ledger (signed). Invariant: quantity_on_hand == SUM(quantity_delta).
        $this->movements->insert($productId, [
            'direction'       => 'in',
            'quantity_delta'  => $quantityIn,
            'unit_cost'       => $rounded,
            'balance_after'   => $newQty,
            'source'          => (string) ($meta['source'] ?? 'other'),
            'source_ref_type' => $meta['source_ref_type'] ?? null,
            'source_ref_id'   => $meta['source_ref_id'] ?? null,
            'notes'           => $meta['notes'] ?? null,
            'user_id'         => $meta['user_id'] ?? null,
        ]);
    }

    /**
     * Manually update a product's latest-purchase landed cost WITHOUT changing stock or moving-avg.
     * Used by admin when the latest known replacement price changes (e.g. supplier announced new rate)
     * but no physical purchase has happened yet.
     */
    public function setLatestPrice(int $productId, float $newLandedCost, int $userId, ?string $notes = null): void
    {
        if ($newLandedCost < 0) {
            throw new \RuntimeException('Latest cost cannot be negative.');
        }
        $pdo = Db::pdo();
        $rounded = \round($newLandedCost, 4);

        $cur = $pdo->prepare('SELECT quantity_on_hand, moving_avg_cost FROM products WHERE id = ?');
        $cur->execute([$productId]);
        $curRow = $cur->fetch(\PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            'UPDATE products SET latest_purchase_landed_cost = ?, latest_purchase_at = NOW(),
                                 updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([$rounded, $userId, $productId]);
        if ($stmt->rowCount() === 0 && !$curRow) {
            throw new \RuntimeException("Product #{$productId} not found.");
        }
        // Manual cost change: stock + moving_avg untouched, latest_purchase_landed_cost is.
        // Snapshot: prior_qty/avg = current state; event = "manual cost set" (no qty); new_qty/avg unchanged.
        $qty = (float) ($curRow['quantity_on_hand'] ?? 0);
        $avg = (float) ($curRow['moving_avg_cost']  ?? 0);
        $this->priceHistory->insert(
            $productId,
            $rounded,
            'manual',
            null, null,
            $notes,
            $userId,
            [
                'prior_qty'       => $qty,
                'prior_avg'       => $avg,
                'event_qty'       => 0,
                'event_unit_cost' => $rounded,
                'new_qty'         => $qty,
                'new_avg'         => $avg,
            ]
        );
    }

    /**
     * Distribute a brand-rebate amount across a candidate product set as a moving-avg
     * cost reduction. Caller passes the candidate list (typically resolved from a target
     * purchase invoice or all on-hand stock for a brand). Returns:
     *
     *   [
     *     'adjustments' => [ [product_id, on_hand_qty_at_post, moving_avg_before,
     *                         amount_applied, moving_avg_delta, moving_avg_after], ... ],
     *     'applied_total'    => float,   // amount actually applied to inventory
     *     'spillover'        => float,   // rebate_amount − applied_total (>= 0)
     *   ]
     *
     * Distribution is by on_hand_value share. If rebate exceeds total on_hand_value,
     * each product takes its full on_hand_value and the rest spills (caller posts as income).
     *
     * Caller MUST persist the returned adjustments (BrandRebateRepository::insertCostAdjustment)
     * so void can reverse the exact deltas posted here.
     *
     * @param int[]  $productIds   on-hand candidate set (already filtered by brand or invoice)
     * @param float  $rebateAmount total credit-note value to absorb into inventory
     */
    public function applyRebateCostReduction(array $productIds, float $rebateAmount): array
    {
        if ($rebateAmount <= 0) {
            return ['adjustments' => [], 'applied_total' => 0.0, 'spillover' => 0.0];
        }
        $pdo = Db::pdo();

        // Lock all candidate rows in a stable order to avoid deadlocks.
        \sort($productIds);
        $candidates = [];
        $totalOnHandValue = 0.0;
        foreach ($productIds as $pid) {
            $stmt = $pdo->prepare(
                'SELECT id, quantity_on_hand, moving_avg_cost FROM products WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$pid]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new \RuntimeException("Product #{$pid} not found while applying rebate.");
            }
            $qty = (float) $row['quantity_on_hand'];
            $avg = (float) $row['moving_avg_cost'];
            if ($qty <= 0 || $avg <= 0) { continue; }       // skip non-eligible
            $value = $qty * $avg;
            $candidates[] = ['id' => (int) $row['id'], 'qty' => $qty, 'avg' => $avg, 'value' => $value];
            $totalOnHandValue += $value;
        }

        if ($candidates === [] || $totalOnHandValue <= 0.0001) {
            // Nothing to absorb — entire rebate spills to income.
            return ['adjustments' => [], 'applied_total' => 0.0, 'spillover' => \round($rebateAmount, 2)];
        }

        $cap         = \min($rebateAmount, $totalOnHandValue);
        $spillover   = \round($rebateAmount - $cap, 2);
        $appliedTot  = 0.0;
        $adjustments = [];

        $update = $pdo->prepare(
            'UPDATE products SET moving_avg_cost = ? WHERE id = ?'
        );

        // Distribute by on-hand value share. Last candidate gets the rounding slack.
        $last = \count($candidates) - 1;
        foreach ($candidates as $idx => $c) {
            if ($idx === $last) {
                $share = \round($cap - $appliedTot, 2);
            } else {
                $share = \round($cap * ($c['value'] / $totalOnHandValue), 2);
            }
            if ($share <= 0.001) { continue; }
            $perUnit = \round($share / $c['qty'], 4);
            $newAvg  = \max(0.0, \round($c['avg'] - $perUnit, 4));
            $update->execute([$newAvg, $c['id']]);
            $adjustments[] = [
                'product_id'          => $c['id'],
                'on_hand_qty_at_post' => $c['qty'],
                'moving_avg_before'   => $c['avg'],
                'amount_applied'      => $share,
                'moving_avg_delta'    => $perUnit,
                'moving_avg_after'    => $newAvg,
            ];
            $appliedTot += $share;
        }

        return [
            'adjustments'   => $adjustments,
            'applied_total' => \round($appliedTot, 2),
            'spillover'     => $spillover,
        ];
    }

    /**
     * Reverse a prior cost-reduction adjustment. Refuses if the product's current
     * moving_avg_cost has drifted from the recorded `moving_avg_after` (which means
     * subsequent purchases/returns have shifted the cost basis — manual correction
     * required).
     *
     * @param array<int, array{product_id:int, moving_avg_before:float, moving_avg_after:float}> $adjustments
     */
    public function reverseRebateCostReduction(array $adjustments): void
    {
        $pdo = Db::pdo();
        foreach ($adjustments as $adj) {
            $stmt = $pdo->prepare('SELECT moving_avg_cost FROM products WHERE id = ? FOR UPDATE');
            $stmt->execute([$adj['product_id']]);
            $cur = $stmt->fetchColumn();
            if ($cur === false) {
                throw new \RuntimeException("Product #{$adj['product_id']} not found while reversing rebate.");
            }
            if (\abs(((float) $cur) - ((float) $adj['moving_avg_after'])) > 0.0001) {
                throw new \RuntimeException(\sprintf(
                    'Cannot reverse rebate: product #%d moving-avg cost has drifted (now ₹%s, expected ₹%s). ' .
                    'A subsequent purchase or return shifted the cost basis. Post a manual correction instead.',
                    $adj['product_id'], $cur, $adj['moving_avg_after']
                ));
            }
            $upd = $pdo->prepare('UPDATE products SET moving_avg_cost = ? WHERE id = ?');
            $upd->execute([(float) $adj['moving_avg_before'], $adj['product_id']]);
        }
    }

    /**
     * Apply a stock-out event (sale line finalize, purchase return, write-off).
     * Does NOT change moving_avg_cost (cost basis stays). Returns the COGS value
     * (qty × current moving_avg) that callers post to the P&L ledger.
     *
     * Per D6 ("block oversell by default") this primitive enforces non-negative stock
     * AS A SAFETY NET — every caller already validates before calling, but the lock-
     * and-check at the row level closes the race window where two concurrent stock-outs
     * pass their stale validations. Pass $allowOversell=true to opt into the D16
     * deficit-tracking flow (deferred to v2).
     */
    public function applyStockOut(int $productId, float $quantityOut, bool $allowOversell = false, array $meta = []): float
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT quantity_on_hand, moving_avg_cost FROM products WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException("Product #{$productId} not found for stock-out.");
        }

        $currentQty = (float) $row['quantity_on_hand'];
        $currentAvg = (float) $row['moving_avg_cost'];
        $newQty     = $currentQty - $quantityOut;

        if (!$allowOversell && $newQty < -0.0001) {
            throw new \RuntimeException(\sprintf(
                'Oversell blocked at primitive level: product #%d has %s on hand, cannot stock-out %s. ' .
                '(Caller should have validated this earlier — this is the safety net.)',
                $productId, $currentQty, $quantityOut
            ));
        }

        $cogs = \round($quantityOut * $currentAvg, 2);

        $update = $pdo->prepare(
            'UPDATE products SET quantity_on_hand = ? WHERE id = ?'
        );
        $update->execute([$newQty, $productId]);

        // Stock ledger (signed, negative for an out). Invariant: quantity_on_hand == SUM(quantity_delta).
        $this->movements->insert($productId, [
            'direction'       => 'out',
            'quantity_delta'  => -$quantityOut,
            'unit_cost'       => $currentAvg,
            'balance_after'   => $newQty,
            'source'          => (string) ($meta['source'] ?? 'stock_out'),
            'source_ref_type' => $meta['source_ref_type'] ?? null,
            'source_ref_id'   => $meta['source_ref_id'] ?? null,
            'notes'           => $meta['notes'] ?? null,
            'user_id'         => $meta['user_id'] ?? null,
        ]);

        return $cogs;
    }
}
