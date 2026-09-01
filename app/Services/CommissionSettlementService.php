<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\CommissionSettlementRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SalesmanRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Posts a salesman commission settlement (D21).
 *
 * Modes:
 *   - cash : debit a bank/drawer by cash_amount.
 *   - goods: stock-OUT a product at its current moving_avg_cost, snapshot that cost on
 *            the row, set total_value = qty × moving_avg.
 *
 * Both modes commit one row to salesman_commission_settlements; total_settled_amount
 * records the value applied against the salesman's commission outstanding (which is
 * computed elsewhere as earned − reversed − settled).
 *
 * Void reverses the original effect:
 *   - cash : credit account back.
 *   - goods: stock-IN at the snapshotted moving_avg (D54-style — no cost-basis drift).
 */
class CommissionSettlementService
{
    public function __construct(
        private CommissionSettlementRepository $repo      = new CommissionSettlementRepository(),
        private SalesmanRepository             $salesmen  = new SalesmanRepository(),
        private ProductRepository              $products  = new ProductRepository(),
        private BankAccountRepository          $banks     = new BankAccountRepository(),
        private CashierDrawerRepository        $drawers   = new CashierDrawerRepository(),
        private BusinessProfileRepository      $business  = new BusinessProfileRepository(),
        private InventoryService               $inventory = new InventoryService(),
        private PricingService                 $pricing   = new PricingService(),
    ) {}

    public function post(array $input, int $userId): int
    {
        $salesmanId       = (int)    ($input['salesman_id']       ?? 0);
        $payoutMode       = (string) ($input['payout_mode']       ?? '');
        $settlementDate   = \trim((string) ($input['settlement_date'] ?? \date('Y-m-d')));
        $remarks          = \trim((string) ($input['remarks']     ?? '')) ?: null;

        if (!\in_array($payoutMode, ['cash', 'goods'], true)) {
            throw new \RuntimeException('Pick cash or goods.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $settlementDate)) {
            throw new \RuntimeException('Settlement date must be YYYY-MM-DD.');
        }
        $salesman = $this->salesmen->find($salesmanId);
        if ($salesman === null) {
            throw new \RuntimeException('Salesman not found.');
        }

        // Mode-specific validation + delta.
        $shaped = ['salesman_id' => $salesmanId, 'payout_mode' => $payoutMode,
                   'settlement_date' => $settlementDate, 'remarks' => $remarks];

        if ($payoutMode === 'cash') {
            $cashAmount   = (float)  ($input['cash_amount']      ?? 0);
            $paidFromKind = (string) ($input['paid_from_kind']   ?? '');
            $paidFromId   = (int)    ($input['paid_from_id']     ?? 0);
            $paymentMode  = (string) ($input['payment_mode']     ?? 'cash');
            $paymentRef   = \trim((string) ($input['payment_reference'] ?? '')) ?: null;

            if ($cashAmount <= 0) {
                throw new \RuntimeException('Cash amount must be greater than zero.');
            }
            if (!\in_array($paidFromKind, ['bank_account', 'cashier_drawer'], true)) {
                throw new \RuntimeException('Pick a paying account.');
            }
            if (!\in_array($paymentMode, ['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'], true)) {
                throw new \RuntimeException('Invalid payment mode.');
            }
            $accRow = $this->resolveAccount($paidFromKind, $paidFromId);
            if ($accRow === null) {
                throw new \RuntimeException('Paying account not found.');
            }
            if ($accRow['status'] !== 'active') {
                throw new \RuntimeException("Paying account '{$accRow['name']}' is inactive.");
            }

            $shaped += [
                'cash_amount'           => $cashAmount,
                'paid_from_kind'        => $paidFromKind,
                'paid_from_id'          => $paidFromId,
                'payment_mode'          => $paymentMode,
                'payment_reference'     => $paymentRef,
                'total_settled_amount'  => $cashAmount,
            ];
        } else {
            // goods mode
            $productId = (int)   ($input['goods_product_id'] ?? 0);
            $qty       = (float) ($input['goods_quantity']   ?? 0);
            if ($productId <= 0) { throw new \RuntimeException('Pick a product.'); }
            if ($qty <= 0)       { throw new \RuntimeException('Quantity must be greater than zero.'); }

            $product = $this->products->find($productId);
            if ($product === null) { throw new \RuntimeException('Product not found.'); }
            if ($product['status'] !== 'active') {
                throw new \RuntimeException("Product '{$product['name']}' is inactive.");
            }
            if ((float) $product['quantity_on_hand'] < $qty - 0.0001) {
                throw new \RuntimeException(\sprintf(
                    "Insufficient stock for '%s'. Available: %s, requested: %s.",
                    $product['name'],
                    \rtrim(\rtrim((string) $product['quantity_on_hand'], '0'), '.'),
                    \rtrim(\rtrim((string) $qty, '0'), '.')
                ));
            }
            $unitCost  = (float) $product['moving_avg_cost'];
            $totalValue = $this->pricing->money($qty * $unitCost);
            if ($totalValue <= 0) {
                throw new \RuntimeException("Product '{$product['name']}' has no moving-avg cost yet (no purchase history).");
            }

            $shaped += [
                'goods_product_id'      => $productId,
                'goods_quantity'        => $qty,
                'goods_unit_cost'       => \round($unitCost, 4),
                'goods_total_value'     => $totalValue,
                'total_settled_amount'  => $totalValue,
            ];
        }

        $newId = 0;
        Db::transaction(function () use ($shaped, $userId, $payoutMode, &$newId): void {
            $fy   = $this->fiscalYearLabel((string) $shaped['settlement_date']);
            $next = $this->repo->maxSequenceForFy("GP-COMM/{$fy}") + 1;
            $shaped['settlement_number'] = \sprintf('GP-COMM/%s/%05d', $fy, $next);

            $newId = $this->repo->insert($shaped, $userId);

            if ($payoutMode === 'cash') {
                // Debit the paying account.
                $this->applyDelta(
                    (string) $shaped['paid_from_kind'],
                    (int) $shaped['paid_from_id'],
                    -(float) $shaped['cash_amount']
                );
            } else {
                // Stock-OUT the product. applyStockOut returns the COGS at current moving avg
                // (and does NOT change moving_avg_cost — the cost basis stays for remaining stock).
                $this->inventory->applyStockOut(
                    (int) $shaped['goods_product_id'],
                    (float) $shaped['goods_quantity'],
                    false,
                    ['source' => 'commission_goods']
                );
            }
            ActivityLog::log('create', 'commission_settlement', $newId, [
                'label' => $shaped['settlement_number'],
                'notes' => 'mode=' . $payoutMode . ', total=' . ($shaped['total_settled_amount'] ?? '?'),
            ]);
        });
        return $newId;
    }

    /** Reverse a posted settlement. Admin only at the route layer. */
    public function void(int $settlementId, string $reason, int $userId): void
    {
        Db::transaction(function () use ($settlementId, $reason, $userId): void {
            $row = $this->repo->find($settlementId);
            if ($row === null) {
                throw new \RuntimeException("Settlement #{$settlementId} not found.");
            }
            if ($row['status'] !== 'posted') {
                throw new \RuntimeException("Settlement is already {$row['status']}.");
            }

            if ($row['payout_mode'] === 'cash') {
                // Credit the paying account back.
                $this->applyDelta(
                    (string) $row['paid_from_kind'],
                    (int) $row['paid_from_id'],
                    (float) $row['cash_amount']
                );
            } else {
                // Stock-IN at the snapshotted moving_avg so cost basis stays intact (D54 style).
                $this->inventory->applyStockIn(
                    (int) $row['goods_product_id'],
                    (float) $row['goods_quantity'],
                    (float) $row['goods_unit_cost'],
                    [
                        'source'          => 'return',
                        'source_ref_type' => 'commission_settlement_void',
                        'source_ref_id'   => $settlementId,
                        'user_id'         => $userId,
                    ]
                );
            }
            $this->repo->markVoided($settlementId, $reason, $userId);
            ActivityLog::log('void', 'commission_settlement', $settlementId, [
                'label' => $row['settlement_number'] ?? null,
                'notes' => 'reason=' . $reason,
            ]);
        });
    }

    private function resolveAccount(string $kind, int $id): ?array
    {
        return $kind === 'bank_account' ? $this->banks->find($id) : $this->drawers->find($id);
    }

    private function applyDelta(string $kind, int $id, float $delta): void
    {
        if ($kind === 'bank_account') {
            $this->banks->applyDelta($id, $delta);
        } else {
            $this->drawers->applyDelta($id, $delta);
        }
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
