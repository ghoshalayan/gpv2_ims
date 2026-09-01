<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BusinessProfileRepository;
use App\Repositories\RateChangeRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * CR-4 — bulk selling-price changes, applied category / subcategory / brand-wise.
 *
 * Deliberately a one-time bulk UPDATE of products.default_selling_price rather than an
 * inherited category-level rule: products keep owning their price, so no pricing read
 * path (sales lines, invoices, reports, print templates) changes at all.
 *
 * Only default_selling_price is written. Stock, moving_avg_cost and every posted
 * document are untouched, so no accounting invariant can be affected by this feature.
 *
 * The flow is always preview → confirm. `preview()` is pure (no writes) and returns the
 * exact rows `apply()` will change, so the confirmation screen cannot lie about scope.
 */
class RateChangeService
{
    public const MODES = ['percent', 'flat_amount', 'set_price'];

    public function __construct(
        private RateChangeRepository      $repo     = new RateChangeRepository(),
        private BusinessProfileRepository $business = new BusinessProfileRepository(),
    ) {}

    /**
     * Compute what a change WOULD do. No writes.
     *
     * @param array{category_id?:int|null, subcategory_id?:int|null, brand_id?:int|null} $filters
     * @return array{rows: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     *         rows    = products whose price actually changes
     *         skipped = products matched by the selection but left alone, with a reason
     */
    public function preview(array $filters, string $mode, float $value, bool $roundToRupee): array
    {
        $this->assertMode($mode, $value);

        $rows = [];
        $skipped = [];

        foreach ($this->repo->productsMatching($filters) as $p) {
            $old = (float) $p['default_selling_price'];
            $new = $this->computeNewPrice($old, $mode, $value, $roundToRupee);

            if ($new < 0) {
                // A percentage or flat cut can take a cheap item below zero. Never write
                // a negative price — report it so the user can adjust the input.
                $skipped[] = $p + ['reason' => 'would go below zero', 'new_price' => $new];
                continue;
            }
            if (\abs($new - $old) < 0.005) {
                $skipped[] = $p + ['reason' => 'no change', 'new_price' => $new];
                continue;
            }
            $rows[] = $p + ['new_price' => $new, 'delta' => \round($new - $old, 2)];
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * Apply the change. Recomputes the preview inside the transaction rather than
     * trusting anything posted from the form, so a stale confirmation screen can never
     * write prices the user did not actually see priced from current data.
     *
     * @return array{batch_id:int, batch_number:string, affected:int}
     */
    public function apply(array $filters, string $mode, float $value, bool $roundToRupee,
                          ?string $remarks, int $userId): array
    {
        $this->assertMode($mode, $value);

        return Db::transaction(function () use ($filters, $mode, $value, $roundToRupee, $remarks, $userId) {
            $preview = $this->preview($filters, $mode, $value, $roundToRupee);
            $rows    = $preview['rows'];

            if ($rows === []) {
                throw new \RuntimeException('No products would change price with these settings.');
            }

            $date = \date('Y-m-d');
            $fy   = $this->fiscalYearLabel($date);
            $next = $this->repo->maxSequenceForFy("GP-RATE/{$fy}") + 1;

            $batchId = $this->repo->insertBatch([
                'batch_number'      => \sprintf('GP-RATE/%s/%05d', $fy, $next),
                'change_date'       => $date,
                'category_id'       => $filters['category_id']    ?? null,
                'subcategory_id'    => $filters['subcategory_id'] ?? null,
                'brand_id'          => $filters['brand_id']       ?? null,
                'mode'              => $mode,
                'value'             => $value,
                'round_to_rupee'    => $roundToRupee ? 1 : 0,
                'products_affected' => \count($rows),
                'remarks'           => $remarks,
            ], $userId);

            foreach ($rows as $r) {
                $this->repo->insertLine($batchId, (int) $r['id'],
                    (float) $r['default_selling_price'], (float) $r['new_price']);
                $this->repo->updateSellingPrice((int) $r['id'], (float) $r['new_price'], $userId);
            }

            $batch = $this->repo->findBatch($batchId);
            ActivityLog::log('create', 'rate_change_batch', $batchId, [
                'label' => $batch['batch_number'] ?? '',
                'after' => ['mode' => $mode, 'value' => $value, 'affected' => \count($rows)],
                'notes' => 'Bulk selling-price change applied.',
            ]);

            return [
                'batch_id'     => $batchId,
                'batch_number' => (string) ($batch['batch_number'] ?? ''),
                'affected'     => \count($rows),
            ];
        });
    }

    /**
     * Restore every product in a batch to its pre-change price.
     *
     * Refuses when any product's current price differs from what this batch set — that
     * means someone changed it deliberately afterwards, and blindly restoring would
     * silently discard their change. Same drift-detection stance as brand-rebate void.
     */
    public function revert(int $batchId, int $userId): int
    {
        return Db::transaction(function () use ($batchId, $userId) {
            $batch = $this->repo->findBatchForUpdate($batchId);
            if ($batch === null) {
                throw new \RuntimeException('Rate change batch not found.');
            }
            if ($batch['status'] === 'reverted') {
                throw new \RuntimeException('This batch has already been reverted.');
            }

            $lines = $this->repo->linesForBatch($batchId);
            $drift = [];
            foreach ($lines as $l) {
                $current = $this->repo->currentSellingPrice((int) $l['product_id']);
                if ($current === null) {
                    $drift[] = $l['product_name'] . ' (product missing)';
                } elseif (\abs($current - (float) $l['new_price']) > 0.005) {
                    $drift[] = \sprintf('%s (now %.2f, batch set %.2f)',
                        $l['product_name'], $current, (float) $l['new_price']);
                }
            }
            if ($drift !== []) {
                throw new \RuntimeException(
                    'Cannot revert — these products were re-priced after this batch: '
                    . \implode('; ', \array_slice($drift, 0, 5))
                    . (\count($drift) > 5 ? \sprintf(' and %d more.', \count($drift) - 5) : '.')
                );
            }

            foreach ($lines as $l) {
                $this->repo->updateSellingPrice((int) $l['product_id'], (float) $l['old_price'], $userId);
            }
            $this->repo->markReverted($batchId, $userId);

            ActivityLog::log('update', 'rate_change_batch', $batchId, [
                'label' => (string) $batch['batch_number'],
                'after' => ['status' => 'reverted'],
                'notes' => 'Bulk selling-price change reverted; prices restored.',
            ]);

            return \count($lines);
        });
    }

    private function computeNewPrice(float $old, string $mode, float $value, bool $roundToRupee): float
    {
        $new = match ($mode) {
            'percent'     => $old * (1 + $value / 100),
            'flat_amount' => $old + $value,
            'set_price'   => $value,
        };
        return $roundToRupee ? \round($new, 0) : \round($new, 2);
    }

    private function assertMode(string $mode, float $value): void
    {
        if (!\in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException("Unknown rate-change mode: {$mode}");
        }
        if ($mode === 'set_price' && $value < 0) {
            throw new \InvalidArgumentException('A fixed price cannot be negative.');
        }
        if ($mode === 'percent' && $value <= -100) {
            throw new \InvalidArgumentException('A reduction of 100% or more is not allowed.');
        }
    }

    /** Same FY convention the rebate and commission numbering already uses. */
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
