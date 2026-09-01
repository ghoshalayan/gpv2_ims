<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BusinessProfileRepository;
use App\Repositories\ProductCompositionRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\ProductRepository;
use App\Support\Db;

/**
 * Atomic production: consume components from stock, produce composite stock,
 * snapshot the recipe on the order. Only works for IRREVERSIBLE composites.
 *
 * Steps (inside one transaction):
 *  1. Load composite product, validate it's active + irreversible + has a recipe with items.
 *  2. For each recipe item: lock the component row, check sufficient stock, read moving_avg.
 *  3. For each item: apply stock-out (returns COGS) and record snapshot line data.
 *  4. Sum total component cost; compute landed_cost_per_unit for the composite.
 *  5. Apply stock-in to composite (D10 moving-avg update).
 *  6. Write production_orders header + lines.
 *  7. Assign order number PR/{FY}/{NNNNN}.
 */
class ProductionOrderService
{
    public function __construct(
        private ProductionOrderRepository    $orders       = new ProductionOrderRepository(),
        private ProductRepository            $products     = new ProductRepository(),
        private ProductCompositionRepository $compositions = new ProductCompositionRepository(),
        private BusinessProfileRepository    $business     = new BusinessProfileRepository(),
        private InventoryService             $inventory    = new InventoryService(),
    ) {}

    public function produce(int $productId, float $quantityProduced, string $producedDate, ?string $notes, int $userId): int
    {
        if ($quantityProduced <= 0) {
            throw new \RuntimeException('Quantity produced must be greater than zero.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $producedDate)) {
            throw new \RuntimeException('Produced date must be YYYY-MM-DD.');
        }

        return Db::transaction(function () use ($productId, $quantityProduced, $producedDate, $notes, $userId): int {
            // 1. Validate composite product
            $product = $this->products->find($productId);
            if ($product === null) {
                throw new \RuntimeException('Product not found.');
            }
            if ((int) $product['is_composite'] !== 1 || (int) $product['is_irreversible'] !== 1) {
                throw new \RuntimeException("Product '{$product['name']}' is not an Irreversible composite.");
            }
            if ($product['status'] !== 'active') {
                throw new \RuntimeException("Product '{$product['name']}' is inactive.");
            }

            $composition = $this->compositions->findByProduct($productId);
            if ($composition === null) {
                throw new \RuntimeException('No recipe defined for this composite. Add components on the recipe page first.');
            }
            $items = $this->compositions->itemsByComposition((int) $composition['id']);
            if ($items === []) {
                throw new \RuntimeException('Recipe has no components. Add at least one before producing.');
            }

            // 2. Pre-check stock for every component (row-lock + verify).
            //    Doing a full pre-check before any stock-out gives us a clean all-or-nothing feel
            //    (matches user expectation: either the whole production succeeds or nothing changes).
            $pdo = Db::pdo();
            foreach ($items as $item) {
                $needed = (float) $item['quantity'] * $quantityProduced;
                $stmt = $pdo->prepare(
                    'SELECT name, unit, quantity_on_hand, moving_avg_cost, status
                     FROM products WHERE id = ? FOR UPDATE'
                );
                $stmt->execute([$item['component_product_id']]);
                $c = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($c === false) {
                    throw new \RuntimeException("Component #{$item['component_product_id']} not found.");
                }
                if ($c['status'] !== 'active') {
                    throw new \RuntimeException("Component '{$c['name']}' is inactive — cannot be used in production.");
                }
                if ((float) $c['quantity_on_hand'] < $needed - 0.0001) {
                    throw new \RuntimeException(sprintf(
                        "Insufficient '%s': need %s %s, only %s available.",
                        $c['name'],
                        \rtrim(\rtrim((string) $needed, '0'), '.'),
                        $c['unit'],
                        \rtrim(\rtrim((string) $c['quantity_on_hand'], '0'), '.')
                    ));
                }
            }

            // 3. + 4. Consume components, snapshot lines, accumulate cost.
            $totalCost = 0.0;
            $snapshots = [];
            foreach ($items as $item) {
                $componentId = (int) $item['component_product_id'];
                $needed      = (float) $item['quantity'] * $quantityProduced;

                // Re-read moving_avg (will be same as the pre-check read — no other stock ops happened yet in this txn)
                $stmt = $pdo->prepare('SELECT name, unit, moving_avg_cost FROM products WHERE id = ?');
                $stmt->execute([$componentId]);
                $c = $stmt->fetch(\PDO::FETCH_ASSOC);

                $avgCost = (float) $c['moving_avg_cost'];
                $lineCost = \round($needed * $avgCost, 2);
                $totalCost += $lineCost;

                // Stock-out via InventoryService (keeps moving_avg unchanged per D10)
                $this->inventory->applyStockOut($componentId, $needed, false, ['source' => 'production']);

                $snapshots[] = [
                    'component_product_id'          => $componentId,
                    'component_name'                => (string) $c['name'],
                    'component_unit'                => (string) $c['unit'],
                    'recipe_quantity'               => (float) $item['quantity'],
                    'quantity_consumed'             => $needed,
                    'moving_avg_cost_at_production' => \round($avgCost, 4),
                    'line_cost'                     => $lineCost,
                ];
            }

            // 5. Produce composite stock at computed landed cost.
            $landedCostPerUnit = \round($totalCost / $quantityProduced, 4);
            $this->inventory->applyStockIn($productId, $quantityProduced, $landedCostPerUnit, [
                'source'          => 'production',
                'source_ref_type' => 'production_order',
                // source_ref_id is set below after insert — price history entry here references it.
                'user_id'         => $userId,
                'notes'           => \sprintf('Produced %s units', $quantityProduced),
            ]);

            // 6. Persist header + lines.
            $orderId = $this->orders->insertHeader([
                'product_id'            => $productId,
                'produced_date'         => $producedDate,
                'quantity_produced'     => $quantityProduced,
                'total_component_cost'  => \round($totalCost, 2),
                'landed_cost_per_unit'  => $landedCostPerUnit,
                'notes'                 => $notes,
            ], $userId);

            foreach ($snapshots as $snap) {
                $this->orders->insertLine($orderId, $snap);
            }

            // 7. Assign PR number
            $fy = $this->fiscalYearLabel($producedDate);
            $next = $this->orders->maxSequenceForFy("PR/{$fy}") + 1;
            $orderNumber = sprintf('PR/%s/%05d', $fy, $next);
            $this->orders->setOrderNumber($orderId, $orderNumber);

            return $orderId;
        });
    }

    private function fiscalYearLabel(string $date): string
    {
        $bp = $this->business->get();
        $fyStartMonth = (int) ($bp['financial_year_start_month'] ?? 4);
        $ts    = \strtotime($date) ?: \time();
        $year  = (int) \date('Y', $ts);
        $month = (int) \date('n', $ts);
        if ($month >= $fyStartMonth) {
            return sprintf('%02d-%02d', $year % 100, ($year + 1) % 100);
        }
        return sprintf('%02d-%02d', ($year - 1) % 100, $year % 100);
    }
}
