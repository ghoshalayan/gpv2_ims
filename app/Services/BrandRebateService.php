<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BrandRebateRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PurchaseInvoiceLineRepository;
use App\Repositories\PurchaseInvoiceRepository;
use App\Repositories\SupplierAdvanceRepository;
use App\Repositories\SupplierRepository;
use App\Support\ActivityLog;
use App\Support\Db;

/**
 * Brand-rebate posting + voiding. The brand issues a credit note when a target is hit;
 * we capture it as a rebate row in one of four modes (D22 + D39 + 2026-04-26 user clarification).
 *
 * Every post is one DB transaction. Mode dispatch:
 *
 *   cash_back      → +amount on chosen bank/drawer (current_balance += amount).
 *                    Visible on account passbook as a credit; on P&L as Other Operating Income.
 *
 *   reduce_payable → auto-create non-cash supplier_advance (paid_from = NULL,
 *                    payment_mode = 'rebate'). Existing apply-from-advance flow on the
 *                    next purchase payment redeems it. P&L impact deferred until the next
 *                    bill is offset (then it shows as reduced purchase cost).
 *
 *   cost_reduction → InventoryService::applyRebateCostReduction recomputes moving-avg on
 *                    on-hand stock for the brand (or scoped to one purchase invoice).
 *                    Spillover (rebate − on_hand_value, if positive) credits Other Income.
 *                    Per-product trace stored in brand_rebate_cost_adjustments so void can
 *                    reverse the exact deltas.
 *
 *   free_stock     → InventoryService::applyStockIn at admin-entered value_per_unit
 *                    (defaults to current moving-avg). AS-2 compliant: stock value = value;
 *                    matching Brand Rebate Income credit is implicit in the value-at-cost
 *                    basis, P&L sees the rebate at receipt time.
 *
 * Void reverses each mode's effects (with safety guards — see `void()`).
 */
class BrandRebateService
{
    public function __construct(
        private BrandRebateRepository      $repo      = new BrandRebateRepository(),
        private SupplierRepository         $suppliers = new SupplierRepository(),
        private BankAccountRepository      $banks     = new BankAccountRepository(),
        private CashierDrawerRepository    $drawers   = new CashierDrawerRepository(),
        private SupplierAdvanceRepository  $advances  = new SupplierAdvanceRepository(),
        private PurchaseInvoiceRepository  $invoices  = new PurchaseInvoiceRepository(),
        private PurchaseInvoiceLineRepository $lines  = new PurchaseInvoiceLineRepository(),
        private ProductRepository          $products  = new ProductRepository(),
        private InventoryService           $inventory = new InventoryService(),
        private BusinessProfileRepository  $business  = new BusinessProfileRepository(),
    ) {}

    public function post(array $input, int $userId): int
    {
        $brandId           = (int)    ($input['brand_id'] ?? 0);
        $supplierId        = (int)    ($input['supplier_id'] ?? 0);
        $mode              = (string) ($input['mode'] ?? '');
        $amount            = \round((float) ($input['amount'] ?? 0), 2);
        $rebateDate        = \trim((string) ($input['rebate_date'] ?? \date('Y-m-d')));
        $creditNoteNumber  = \trim((string) ($input['credit_note_number'] ?? ''));
        $creditNoteDate    = \trim((string) ($input['credit_note_date'] ?? ''));
        $remarks           = \trim((string) ($input['remarks'] ?? '')) ?: null;

        if ($brandId <= 0)    { throw new \RuntimeException('Pick a brand.'); }
        if ($supplierId <= 0) { throw new \RuntimeException('Pick the supplier who issued the credit note.'); }
        if ($amount <= 0)     { throw new \RuntimeException('Rebate amount must be greater than zero.'); }
        if (!\in_array($mode, ['cash_back', 'reduce_payable', 'cost_reduction', 'free_stock'], true)) {
            throw new \RuntimeException('Invalid rebate mode.');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $rebateDate)) {
            throw new \RuntimeException('Rebate date must be YYYY-MM-DD.');
        }
        if ($creditNoteNumber === '') {
            throw new \RuntimeException('Credit note number is required (the brand issues this with the rebate).');
        }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $creditNoteDate)) {
            throw new \RuntimeException('Credit note date must be YYYY-MM-DD.');
        }

        $supplier = $this->suppliers->find($supplierId);
        if ($supplier === null || $supplier['status'] !== 'active') {
            throw new \RuntimeException('Pick an active supplier.');
        }

        $newId = 0;
        Db::transaction(function () use (
            $brandId, $supplierId, $mode, $amount, $rebateDate,
            $creditNoteNumber, $creditNoteDate, $remarks, $input, $userId, &$newId
        ): void {
            $fy   = $this->fiscalYearLabel($rebateDate);
            $next = $this->repo->maxSequenceForFy("GP-RBT/{$fy}") + 1;
            $rebateNumber = \sprintf('GP-RBT/%s/%05d', $fy, $next);

            $base = [
                'rebate_number'      => $rebateNumber,
                'rebate_date'        => $rebateDate,
                'brand_id'           => $brandId,
                'supplier_id'        => $supplierId,
                'mode'               => $mode,
                'amount'             => $amount,
                'credit_note_number' => $creditNoteNumber,
                'credit_note_date'   => $creditNoteDate,
                'remarks'            => $remarks,
            ];

            switch ($mode) {
                case 'cash_back':
                    $rebateId = $this->postCashBack($base, $input, $userId);
                    break;
                case 'reduce_payable':
                    $rebateId = $this->postReducePayable($base, $userId);
                    break;
                case 'cost_reduction':
                    $rebateId = $this->postCostReduction($base, $input, $userId);
                    break;
                case 'free_stock':
                    $rebateId = $this->postFreeStock($base, $input, $userId);
                    break;
                default:
                    throw new \RuntimeException('Invalid rebate mode.');
            }
            $newId = $rebateId;
            ActivityLog::log('create', 'brand_rebate', $rebateId, [
                'label' => $rebateNumber,
                'notes' => 'mode=' . $mode . ', amount=' . $amount . ', credit_note=' . $creditNoteNumber,
            ]);
        });
        return $newId;
    }

    /* ---------- mode handlers ---------- */

    private function postCashBack(array $base, array $input, int $userId): int
    {
        $kind = (string) ($input['received_into_kind'] ?? '');
        $accId = (int)   ($input['received_into_id']   ?? 0);
        $payMode = (string) ($input['payment_mode'] ?? 'cash');
        $payRef  = \trim((string) ($input['payment_reference'] ?? '')) ?: null;

        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true) || $accId <= 0) {
            throw new \RuntimeException('Pick the bank or drawer that receives the cash-back.');
        }
        if (!\in_array($payMode, ['cash', 'neft', 'imps', 'rtgs', 'upi', 'cheque', 'other'], true)) {
            throw new \RuntimeException('Invalid payment mode.');
        }
        $acc = $this->resolveAccount($kind, $accId);
        if ($acc === null) { throw new \RuntimeException('Receiving account not found.'); }
        if ($acc['status'] !== 'active') { throw new \RuntimeException("Account '{$acc['name']}' is inactive."); }

        $rebateId = $this->repo->insert($base + [
            'received_into_kind' => $kind,
            'received_into_id'   => $accId,
            'payment_mode'       => $payMode,
            'payment_reference'  => $payRef,
        ], $userId);

        // Credit the receiving account (money in).
        $this->applyAccountDelta($kind, $accId, +$base['amount']);
        return $rebateId;
    }

    private function postReducePayable(array $base, int $userId): int
    {
        // 1. Insert the rebate row first (no advance link yet — set after we create one).
        $rebateId = $this->repo->insert($base, $userId);

        // 2. Create a non-cash supplier_advance with payment_mode='rebate'.
        $fy   = $this->fiscalYearLabel($base['rebate_date']);
        $next = $this->advances->maxSequenceForFy("GP-SADV/{$fy}") + 1;
        $advanceNumber = \sprintf('GP-SADV/%s/%05d', $fy, $next);

        $advanceId = $this->advances->insert([
            'advance_number'    => $advanceNumber,
            'advance_date'      => $base['rebate_date'],
            'supplier_id'       => $base['supplier_id'],
            'amount'            => $base['amount'],
            'paid_from_kind'    => null,
            'paid_from_id'      => null,
            'payment_mode'      => 'rebate',
            'payment_reference' => $base['credit_note_number'],
            'remarks'           => 'From brand rebate ' . $base['rebate_number'],
        ], $userId);

        // 3. Backlink advance to rebate (for void traceability).
        $this->repo->attachLinkedAdvance($rebateId, $advanceId);
        return $rebateId;
    }

    private function postCostReduction(array $base, array $input, int $userId): int
    {
        $targetInvoiceId = ((int) ($input['target_invoice_id'] ?? 0)) ?: null;

        // Resolve candidate product set.
        $productIds = $this->resolveCostReductionProducts($base['brand_id'], $base['supplier_id'], $targetInvoiceId);
        if ($productIds === []) {
            throw new \RuntimeException('No on-hand stock for this brand to absorb the rebate. Pick a different mode or scope.');
        }

        $result = $this->inventory->applyRebateCostReduction($productIds, $base['amount']);

        $rebateId = $this->repo->insert($base + [
            'target_invoice_id' => $targetInvoiceId,
            'income_spillover'  => $result['spillover'],
        ], $userId);

        foreach ($result['adjustments'] as $adj) {
            $this->repo->insertCostAdjustment([
                'rebate_id'           => $rebateId,
                'product_id'          => $adj['product_id'],
                'on_hand_qty_at_post' => $adj['on_hand_qty_at_post'],
                'moving_avg_before'   => $adj['moving_avg_before'],
                'amount_applied'      => $adj['amount_applied'],
                'moving_avg_delta'    => $adj['moving_avg_delta'],
                'moving_avg_after'    => $adj['moving_avg_after'],
            ]);
        }
        return $rebateId;
    }

    private function postFreeStock(array $base, array $input, int $userId): int
    {
        $productId       = (int)   ($input['free_product_id'] ?? 0);
        $quantity        = \round((float) ($input['free_quantity'] ?? 0), 3);
        $valuePerUnitRaw = \trim((string) ($input['free_value_per_unit'] ?? ''));

        if ($productId <= 0) { throw new \RuntimeException('Pick the free product.'); }
        if ($quantity  <= 0) { throw new \RuntimeException('Free quantity must be greater than zero.'); }
        if (\abs($quantity - \round($quantity)) > 0.001) {
            throw new \RuntimeException('Free quantity must be a whole number — products track integer stock in v1.');
        }
        $quantity = (float) (int) \round($quantity);

        $product = $this->products->find($productId);
        if ($product === null) { throw new \RuntimeException('Product not found.'); }
        if ((int) $product['brand_id'] !== (int) $base['brand_id']) {
            throw new \RuntimeException("Product '{$product['name']}' is not under the selected brand.");
        }

        // Default value_per_unit = current moving-avg (preserves stock valuation; income recognised
        // implicitly at receipt). Admin override allowed for unusual cases.
        $valuePerUnit = $valuePerUnitRaw === ''
            ? \round((float) $product['moving_avg_cost'], 4)
            : \round((float) $valuePerUnitRaw, 4);
        if ($valuePerUnit < 0) {
            throw new \RuntimeException('Value per unit cannot be negative.');
        }
        // Sanity: declared value × qty should match rebate amount within ₹1 (admin check).
        $derived = \round($valuePerUnit * $quantity, 2);
        if (\abs($derived - (float) $base['amount']) > 1.00) {
            throw new \RuntimeException(\sprintf(
                'Free-stock value (qty %s × ₹%s/unit = ₹%s) doesn\'t match rebate amount ₹%s. ' .
                'Adjust quantity, value-per-unit, or rebate amount so they reconcile.',
                $quantity, $valuePerUnit, $derived, $base['amount']
            ));
        }

        // Snapshot the moving-avg BEFORE the stock-in so void can reverse the avg shift
        // if no intervening events have occurred. Mirrors brand_rebate_cost_adjustments'
        // before/after pattern but inline (single product per free_stock rebate).
        $avgBefore = \round((float) $product['moving_avg_cost'], 4);

        $rebateId = $this->repo->insert($base + [
            'free_product_id'      => $productId,
            'free_quantity'        => $quantity,
            'free_value_per_unit'  => $valuePerUnit,
            'free_stock_avg_before'=> $avgBefore,
        ], $userId);

        // Stock-in at the value-per-unit basis. We mark source='other' so
        // latest_purchase_landed_cost stays untouched (this isn't a fresh purchase).
        $this->inventory->applyStockIn($productId, $quantity, $valuePerUnit, [
            'source'          => 'other',
            'source_ref_type' => 'brand_rebate',
            'source_ref_id'   => $rebateId,
            'notes'           => 'Free stock from brand rebate ' . $base['rebate_number'],
            'user_id'         => $userId,
        ]);

        // Read the moving-avg AFTER the stock-in and store it for void's drift detection.
        $afterRow = $this->products->find($productId);
        $avgAfter = \round((float) $afterRow['moving_avg_cost'], 4);
        $this->repo->setFreeStockAvgAfter($rebateId, $avgAfter);

        return $rebateId;
    }

    /* ---------- void ---------- */

    public function void(int $rebateId, string $reason, int $userId): void
    {
        Db::transaction(function () use ($rebateId, $reason, $userId): void {
            $r = $this->repo->find($rebateId);
            if ($r === null) {
                throw new \RuntimeException('Rebate not found.');
            }
            if ($r['status'] !== 'posted') {
                throw new \RuntimeException("Rebate is already {$r['status']}.");
            }
            switch ($r['mode']) {
                case 'cash_back':
                    // Debit the receiving account back.
                    $this->applyAccountDelta(
                        (string) $r['received_into_kind'],
                        (int)    $r['received_into_id'],
                        -((float) $r['amount'])
                    );
                    break;

                case 'reduce_payable':
                    // Refuse void if any of the linked advance has been applied to a bill.
                    $advId = $r['linked_advance_id'] !== null ? (int) $r['linked_advance_id'] : null;
                    if ($advId === null) {
                        throw new \RuntimeException('Rebate has no linked advance — data inconsistent.');
                    }
                    $adv = $this->advances->findForUpdate($advId);
                    if ($adv === null) {
                        throw new \RuntimeException('Linked advance row vanished.');
                    }
                    if ((float) $adv['balance'] + 0.001 < (float) $adv['amount']) {
                        throw new \RuntimeException(
                            'Cannot void: the advance has been partly applied to a bill. ' .
                            'Reverse the apply first by deleting the consuming purchase-payment row(s).'
                        );
                    }
                    if ($adv['status'] === 'refunded') {
                        throw new \RuntimeException('Linked advance is already refunded.');
                    }
                    // Mark the non-cash advance refunded (no cash leg to credit back).
                    $this->advances->markRefunded($advId, 'Brand rebate voided', $userId);
                    break;

                case 'cost_reduction':
                    $adjustments = $this->repo->costAdjustmentsForRebate($rebateId);
                    $this->inventory->reverseRebateCostReduction($adjustments);
                    break;

                case 'free_stock':
                    $productId = (int)   $r['free_product_id'];
                    $qty       = (float) $r['free_quantity'];
                    // Stock-out at the same value basis. Refuse if quantity_on_hand has dropped
                    // below the free qty (some units already sold).
                    $cur = $this->products->find($productId);
                    if ($cur === null) {
                        throw new \RuntimeException('Free product not found while voiding.');
                    }
                    if ((float) $cur['quantity_on_hand'] + 0.001 < $qty) {
                        throw new \RuntimeException(\sprintf(
                            'Cannot void: only %s units of "%s" on hand, rebate added %s. Some have been sold.',
                            $cur['quantity_on_hand'], $cur['name'], $qty
                        ));
                    }
                    // Drift check FIRST: if moving-avg has shifted since post (a subsequent
                    // purchase or production reshaped the cost basis), refuse void cleanly
                    // before doing any inventory mutation. Mirrors reverseRebateCostReduction.
                    // NULL snapshots = pre-fix data; fall back to legacy stockOut-only path
                    // (avg stays shifted; documented as known limitation in bugs.md).
                    $hasSnapshot = $r['free_stock_avg_before'] !== null
                                 && $r['free_stock_avg_after'] !== null;
                    if ($hasSnapshot) {
                        $expectedAfter = (float) $r['free_stock_avg_after'];
                        $current       = (float) $cur['moving_avg_cost'];
                        if (\abs($current - $expectedAfter) > 0.0001) {
                            throw new \RuntimeException(\sprintf(
                                'Cannot void: moving-avg cost on "%s" has drifted (now ₹%s, expected ₹%s). ' .
                                'A subsequent purchase or production shifted the cost basis. Post a manual correction instead.',
                                $cur['name'], $current, $expectedAfter
                            ));
                        }
                    }
                    $this->inventory->applyStockOut($productId, $qty, false, ['source' => 'rebate_void']);
                    if ($hasSnapshot) {
                        // Safe to restore — no drift detected.
                        $upd = \App\Support\Db::pdo()->prepare(
                            'UPDATE products SET moving_avg_cost = ? WHERE id = ?'
                        );
                        $upd->execute([(float) $r['free_stock_avg_before'], $productId]);
                    }
                    break;
            }
            $this->repo->markVoided($rebateId, $reason, $userId);
            ActivityLog::log('void', 'brand_rebate', $rebateId, [
                'label' => $r['rebate_number'] ?? null,
                'notes' => 'reason=' . $reason . ', mode=' . $r['mode'],
            ]);
        });
    }

    /* ---------- helpers ---------- */

    private function resolveCostReductionProducts(int $brandId, int $supplierId, ?int $targetInvoiceId): array
    {
        if ($targetInvoiceId !== null) {
            $inv = $this->invoices->find($targetInvoiceId);
            if ($inv === null) {
                throw new \RuntimeException("Target invoice #{$targetInvoiceId} not found.");
            }
            if ((int) $inv['brand_id'] !== $brandId) {
                throw new \RuntimeException('Target invoice does not belong to the selected brand.');
            }
            if ((int) $inv['supplier_id'] !== $supplierId) {
                throw new \RuntimeException('Target invoice does not belong to the selected supplier.');
            }
            $lines = $this->lines->byInvoice($targetInvoiceId);
            $ids = [];
            foreach ($lines as $ln) { $ids[(int) $ln['product_id']] = true; }
            return \array_keys($ids);
        }
        // No target invoice — all products under this brand with positive on-hand stock.
        $stmt = Db::pdo()->prepare(
            "SELECT id FROM products WHERE brand_id = ? AND quantity_on_hand > 0 ORDER BY id"
        );
        $stmt->execute([$brandId]);
        return \array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function resolveAccount(string $kind, int $id): ?array
    {
        return $kind === 'bank_account' ? $this->banks->find($id) : $this->drawers->find($id);
    }

    private function applyAccountDelta(string $kind, int $id, float $delta): void
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
