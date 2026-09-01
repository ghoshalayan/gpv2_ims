<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BusinessProfileRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockAdjustmentLineRepository;
use App\Repositories\StockAdjustmentRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Stock-adjustment posting + voiding (D15 + D6 oversell-block).
 *
 * Lifecycle:
 *   post()   — header + N lines in one DB transaction. Each line snapshots cost_at_adjustment
 *              (current moving-avg for direction='out'; admin-entered for direction='in')
 *              and calls InventoryService::applyStockOut or applyStockIn. Header total_value
 *              is set after all lines are processed.
 *   void()   — reverses each line. out → applyStockIn at the snapshotted cost (units re-enter
 *              at their original cost basis, source='other' so latest_purchase_landed_cost
 *              is untouched). in → applyStockOut at current moving-avg (refused if stock has
 *              dropped below the line qty since post).
 *
 * Reason × direction matrix (enforced in post()):
 *   damage / expiry / theft → direction must be 'out'
 *   count_correction        → either 'out' (missing) or 'in' (found)
 *   other                   → either
 */
class StockAdjustmentService
{
    public function __construct(
        private StockAdjustmentRepository     $repo     = new StockAdjustmentRepository(),
        private StockAdjustmentLineRepository $lines    = new StockAdjustmentLineRepository(),
        private ProductRepository             $products = new ProductRepository(),
        private InventoryService              $inventory = new InventoryService(),
        private BusinessProfileRepository     $business = new BusinessProfileRepository(),
    ) {}

    public function post(array $input, int $userId): int
    {
        $reason     = (string) ($input['reason'] ?? '');
        $direction  = (string) ($input['direction'] ?? '');
        $date       = \trim((string) ($input['adjustment_date'] ?? \date('Y-m-d')));
        $remarks    = \trim((string) ($input['remarks'] ?? '')) ?: null;
        $linesRaw   = (array)  ($input['lines'] ?? []);

        if (!\in_array($reason, ['damage', 'expiry', 'theft', 'count_correction', 'other'], true)) {
            throw new \RuntimeException('Pick a valid reason.');
        }
        if (!\in_array($direction, ['out', 'in'], true)) {
            throw new \RuntimeException('Pick direction: out (stock leaves) or in (stock enters).');
        }
        if (\in_array($reason, ['damage', 'expiry', 'theft'], true) && $direction !== 'out') {
            throw new \RuntimeException("Reason '{$reason}' must be direction='out'. For found stock use count_correction.");
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \RuntimeException('Adjustment date must be YYYY-MM-DD.');
        }

        // Normalize line input: array of {product_id, quantity, cost?, notes?}
        $cleanLines = [];
        foreach ($linesRaw as $idx => $rawLine) {
            $rawLine = (array) $rawLine;
            $pid = (int) ($rawLine['product_id'] ?? 0);
            $qty = \round((float) ($rawLine['quantity'] ?? 0), 3);
            if ($pid <= 0 || $qty <= 0.001) { continue; }
            $cleanLines[] = [
                'product_id' => $pid,
                'quantity'   => $qty,
                'cost'       => isset($rawLine['cost']) ? \trim((string) $rawLine['cost']) : '',
                'notes'      => isset($rawLine['notes']) ? (\trim((string) $rawLine['notes']) ?: null) : null,
            ];
        }
        if ($cleanLines === []) {
            throw new \RuntimeException('Add at least one line with a product and quantity.');
        }

        $newId = 0;
        Db::transaction(function () use ($reason, $direction, $date, $remarks, $cleanLines, $userId, &$newId): void {
            $fy   = $this->fiscalYearLabel($date);
            $next = $this->repo->maxSequenceForFy("GP-ADJ/{$fy}") + 1;
            $adjNumber = \sprintf('GP-ADJ/%s/%05d', $fy, $next);

            $headerId = $this->repo->insert([
                'adjustment_number' => $adjNumber,
                'adjustment_date'   => $date,
                'reason'            => $reason,
                'direction'         => $direction,
                'total_value'       => 0.00,                  // patched after lines post
                'remarks'           => $remarks,
            ], $userId);

            $totalValue = 0.0;
            foreach ($cleanLines as $i => $ln) {
                $product = $this->products->find($ln['product_id']);
                if ($product === null) {
                    throw new \RuntimeException("Line " . ($i + 1) . ": product not found.");
                }
                $currentQty = (int) $product['quantity_on_hand'];
                $currentAvg = (float) $product['moving_avg_cost'];

                // Determine cost-at-adjustment per direction.
                if ($direction === 'out') {
                    // Block oversell (D6 default).
                    if ($currentQty + 0.001 < $ln['quantity']) {
                        throw new \RuntimeException(\sprintf(
                            'Line %d: only %d %s of "%s" on hand, cannot %s %s.',
                            $i + 1, $currentQty, $product['unit'], $product['name'],
                            $reason, $ln['quantity']
                        ));
                    }
                    if ($currentAvg <= 0) {
                        throw new \RuntimeException(\sprintf(
                            'Line %d: "%s" has no moving-avg cost — cannot post a write-off (no cost basis to charge).',
                            $i + 1, $product['name']
                        ));
                    }
                    $costAt = \round($currentAvg, 4);
                    $this->inventory->applyStockOut($ln['product_id'], $ln['quantity'], false, [
                        'source'          => 'adjustment',
                        'source_ref_type' => 'stock_adjustment',
                        'source_ref_id'   => $headerId,
                        'notes'           => 'Write-off via ' . $reason,
                        'user_id'         => $userId,
                    ]);
                } else {
                    // direction='in' — admin must enter cost (system has no anchor for never-tracked stock).
                    if ($ln['cost'] === '') {
                        throw new \RuntimeException(\sprintf(
                            'Line %d: enter the cost-per-unit for the found stock of "%s" (no system cost basis).',
                            $i + 1, $product['name']
                        ));
                    }
                    $costAt = \round((float) $ln['cost'], 4);
                    if ($costAt <= 0) {
                        throw new \RuntimeException("Line " . ($i + 1) . ": cost-per-unit must be greater than zero (zero would dilute moving-avg).");
                    }
                    $this->inventory->applyStockIn($ln['product_id'], $ln['quantity'], $costAt, [
                        'source'          => 'other',
                        'source_ref_type' => 'stock_adjustment',
                        'source_ref_id'   => $headerId,
                        'notes'           => 'Stock found via ' . $reason,
                        'user_id'         => $userId,
                    ]);
                }

                $lineValue = \round($ln['quantity'] * $costAt, 2);
                $this->lines->insert([
                    'stock_adjustment_id' => $headerId,
                    'line_no'             => $i + 1,
                    'product_id'          => $ln['product_id'],
                    'quantity'            => $ln['quantity'],
                    'cost_at_adjustment'  => $costAt,
                    'line_value'          => $lineValue,
                    'notes'               => $ln['notes'],
                ]);
                $totalValue += $lineValue;
            }
            $this->repo->setTotalValue($headerId, $totalValue);
            $newId = $headerId;
            ActivityLog::log('create', 'stock_adjustment', $headerId, [
                'label' => $adjNumber,
                'notes' => 'reason=' . $reason . ', direction=' . $direction
                         . ', lines=' . \count($cleanLines)
                         . ', total_value=' . \round($totalValue, 2),
            ]);
        });
        return $newId;
    }

    public function void(int $id, string $reason, int $userId): void
    {
        Db::transaction(function () use ($id, $reason, $userId): void {
            $hdr = $this->repo->find($id);
            if ($hdr === null) {
                throw new \RuntimeException('Stock adjustment not found.');
            }
            if ($hdr['status'] !== 'posted') {
                throw new \RuntimeException("Adjustment is already {$hdr['status']}.");
            }

            $lineRows = $this->lines->byHeader($id);
            $direction = (string) $hdr['direction'];

            if ($direction === 'in') {
                // Pre-flight: ensure all products have ≥ qty currently on hand to be removed.
                foreach ($lineRows as $ln) {
                    $cur = $this->products->find((int) $ln['product_id']);
                    if ($cur === null) {
                        throw new \RuntimeException("Product '{$ln['product_name']}' not found while voiding.");
                    }
                    if ((float) $cur['quantity_on_hand'] + 0.001 < (float) $ln['quantity']) {
                        throw new \RuntimeException(\sprintf(
                            'Cannot void: "%s" has %s on hand, but the in-adjustment added %s. Some have been sold.',
                            $cur['name'], $cur['quantity_on_hand'], $ln['quantity']
                        ));
                    }
                }
            }

            foreach ($lineRows as $ln) {
                if ($direction === 'out') {
                    // Reverse out → reintroduce at snapshot cost (origin-cost basis, source='other').
                    $this->inventory->applyStockIn(
                        (int) $ln['product_id'],
                        (float) $ln['quantity'],
                        (float) $ln['cost_at_adjustment'],
                        [
                            'source'          => 'other',
                            'source_ref_type' => 'stock_adjustment_void',
                            'source_ref_id'   => $id,
                            'notes'           => 'Reversed write-off',
                            'user_id'         => $userId,
                        ]
                    );
                } else {
                    // Reverse in → consume at current moving-avg (units leave again).
                    $this->inventory->applyStockOut((int) $ln['product_id'], (float) $ln['quantity'], false, [
                        'source'          => 'adjustment',
                        'source_ref_type' => 'stock_adjustment_void',
                        'source_ref_id'   => $id,
                        'notes'           => 'Reversed found-stock',
                        'user_id'         => $userId,
                    ]);
                }
            }
            $this->repo->markVoided($id, $reason, $userId);
            ActivityLog::log('void', 'stock_adjustment', $id, [
                'label' => $hdr['adjustment_number'] ?? null,
                'notes' => 'reason=' . $reason,
            ]);
        });
    }

    private function fiscalYearLabel(string $dateStr): string
    {
        $bp = $this->business->get();
        $fyStartMonth = (int) ($bp['financial_year_start_month'] ?? 4);
        $ts    = \strtotime($dateStr) ?: \time();
        $year  = (int) \date('Y', $ts);
        $month = (int) \date('n', $ts);
        if ($month >= $fyStartMonth) {
            return \sprintf('%02d-%02d', $year % 100, ($year + 1) % 100);
        }
        return \sprintf('%02d-%02d', ($year - 1) % 100, $year % 100);
    }
}
