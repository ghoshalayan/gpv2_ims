<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\PurchaseInvoiceLineRepository;
use App\Repositories\PurchaseInvoiceRepository;
use App\Repositories\PurchaseReturnLineRepository;
use App\Repositories\PurchaseReturnRepository;
use App\Support\ActivityLog;
use App\Support\Db;
use App\Support\Settings;

/**
 * Finalize orchestrator for purchase returns. One DB transaction:
 *
 *   1. Validate the return is in draft and has at least one line.
 *   2. For each return line: validate qty <= original − already returned (excluding this draft).
 *   3. Recompute line tax mirroring the originating purchase's bill_format / tax_treatment.
 *   4. Stock-OUT at CURRENT moving_avg (D10) — InventoryService::applyStockOut returns the
 *      cost flowing out (qty × moving_avg). Snapshot this on the return line.
 *   5. Pro-rate the original invoice's final_discount + expenses + expense_tax by
 *      qty_returned / qty_original (sum across lines).
 *   6. Compute totals + round-off; persist.
 *   7. Reduce the original purchase_invoice.due_amount by the return's grand_total
 *      (clamped at zero). For refund_method='cash', the surplus that can't net against
 *      an open payable (grand_total − due, i.e. the already-paid portion) is refunded:
 *      the chosen bank/drawer is CREDITED and cash_refund_amount is recorded. credit_note
 *      mode keeps the surplus deferred (no cash moves).
 *   8. Mark finalized + assign return number GP-PRET/{FY}/{NNNNN}.
 */
class PurchaseReturnService
{
    public function __construct(
        private PurchaseReturnRepository       $returns       = new PurchaseReturnRepository(),
        private PurchaseReturnLineRepository   $returnLines   = new PurchaseReturnLineRepository(),
        private PurchaseInvoiceRepository      $invoices      = new PurchaseInvoiceRepository(),
        private PurchaseInvoiceLineRepository  $invoiceLines  = new PurchaseInvoiceLineRepository(),
        private BusinessProfileRepository      $business      = new BusinessProfileRepository(),
        private PricingService                 $pricing       = new PricingService(),
        private InventoryService               $inventory     = new InventoryService(),
        private BankAccountRepository          $banks         = new BankAccountRepository(),
        private CashierDrawerRepository        $drawers       = new CashierDrawerRepository(),
    ) {}

    public function finalize(int $returnId, int $userId): void
    {
        Db::transaction(function () use ($returnId, $userId): void {
            $return = $this->returns->find($returnId);
            if ($return === null) {
                throw new \RuntimeException("Purchase return #{$returnId} not found.");
            }
            if ($return['status'] !== 'draft') {
                throw new \RuntimeException("Return is not a draft (status = {$return['status']}).");
            }

            $lines = $this->returnLines->byReturn($returnId);
            if ($lines === []) {
                throw new \RuntimeException('Cannot finalize a purchase return with no lines.');
            }

            $originalInvoice = $this->invoices->find((int) $return['original_purchase_invoice_id']);
            if ($originalInvoice === null || $originalInvoice['status'] !== 'finalized') {
                throw new \RuntimeException('Original purchase invoice missing or not finalized.');
            }

            $billFormat     = (string) $return['bill_format'];
            $taxTreatment   = (string) $return['tax_treatment'];
            $isIntraState   = (int) $return['is_intra_state'] === 1;
            $invoiceTaxRate = (float) $return['invoice_level_tax_rate'];

            // -------- Steps 2 + 3: validate qtys, recompute per-line math --------
            $totalSubtotal       = 0.0;
            $totalTax            = 0.0;
            $totalCgst           = 0.0;
            $totalSgst           = 0.0;
            $totalIgst           = 0.0;
            $totalStockOutValue  = 0.0;
            $totalReturnedQty    = 0.0;
            $totalOriginalQty    = 0.0;

            $rebuiltLines = [];

            foreach ($lines as $rl) {
                $origLine = $this->invoiceLines->findLine(
                    (int) $rl['original_purchase_line_id'],
                    (int) $return['original_purchase_invoice_id']
                );
                if ($origLine === null) {
                    throw new \RuntimeException("Original purchase line #{$rl['original_purchase_line_id']} not found.");
                }
                $alreadyReturned = $this->returnLines->returnedQtyForOriginalLine(
                    (int) $rl['original_purchase_line_id'],
                    $returnId
                );
                $remaining = (float) $origLine['quantity'] - $alreadyReturned;
                if ((float) $rl['quantity_returned'] > $remaining + 0.0001) {
                    throw new \RuntimeException(\sprintf(
                        "Return qty %s for '%s' exceeds remaining %s (original %s, prior returns %s).",
                        \rtrim(\rtrim((string) $rl['quantity_returned'], '0'), '.'),
                        $origLine['product_name'],
                        \rtrim(\rtrim((string) $remaining, '0'), '.'),
                        \rtrim(\rtrim((string) $origLine['quantity'], '0'), '.'),
                        \rtrim(\rtrim((string) $alreadyReturned, '0'), '.')
                    ));
                }

                $c = $this->pricing->computeLine(
                    (float) $rl['quantity_returned'],
                    (float) $rl['unit_price'],
                    (float) $rl['addsub_percent'],
                    (float) $rl['tax_rate'],
                    $billFormat,
                    $taxTreatment,
                    $isIntraState
                );

                $rebuiltLines[(int) $rl['id']] = [
                    'product_id'              => (int) $rl['product_id'],
                    'product_name'            => (string) $origLine['product_name'],
                    'quantity_returned'       => (float) $rl['quantity_returned'],
                    'orig_quantity'           => (float) $origLine['quantity'],
                    'subtotal'                => $c['subtotal'],
                    'tax_amount'              => $c['tax_amount'],
                    'cgst'                    => $c['cgst'],
                    'sgst'                    => $c['sgst'],
                    'igst'                    => $c['igst'],
                    'line_total'              => $c['line_total'],
                ];

                $totalSubtotal    += $c['subtotal'];
                $totalReturnedQty += (float) $rl['quantity_returned'];
                $totalOriginalQty += (float) $origLine['quantity'];
            }

            // -------- Step 3b: invoice-level tax allocation in Mode A --------
            $effectiveInvoiceTaxRate = $taxTreatment === 'no_tax' ? 0.0 : $invoiceTaxRate;
            if ($billFormat === 'discount_then_tax' && $effectiveInvoiceTaxRate > 0) {
                if ($taxTreatment === 'inclusive') {
                    $rateFactor      = 1 + $effectiveInvoiceTaxRate / 100;
                    $invoiceNet      = $this->pricing->money($totalSubtotal / $rateFactor);
                    $invoiceLevelTax = $this->pricing->money($totalSubtotal - $invoiceNet);
                    $adjFactor = $totalSubtotal > 0 ? ($invoiceNet / $totalSubtotal) : 1.0;
                    foreach ($rebuiltLines as $lid => $r) {
                        $rebuiltLines[$lid]['subtotal'] = $this->pricing->money($r['subtotal'] * $adjFactor);
                    }
                    $totalSubtotal = \array_sum(\array_column($rebuiltLines, 'subtotal'));
                } else {
                    $invoiceLevelTax = $this->pricing->money($totalSubtotal * $effectiveInvoiceTaxRate / 100);
                }
                $linesForAlloc = [];
                foreach ($rebuiltLines as $lid => $r) {
                    $linesForAlloc[] = ['id' => $lid, 'subtotal' => $r['subtotal']];
                }
                $alloc = $this->pricing->allocateInvoiceLevelTax($invoiceLevelTax, $linesForAlloc);
                foreach ($alloc as $lineId => $share) {
                    $split = $this->pricing->splitTax($share, $isIntraState);
                    $rebuiltLines[$lineId]['tax_amount'] = $share;
                    $rebuiltLines[$lineId]['cgst']       = $split[0];
                    $rebuiltLines[$lineId]['sgst']       = $split[1];
                    $rebuiltLines[$lineId]['igst']       = $split[2];
                    $rebuiltLines[$lineId]['line_total'] = $this->pricing->money(
                        $rebuiltLines[$lineId]['subtotal'] + $share
                    );
                }
            }

            // -------- Step 4: stock-OUT at CURRENT moving_avg (D10) --------
            foreach ($rebuiltLines as $lineId => $row) {
                // FOR UPDATE lock + sufficient-stock check
                $stmt = Db::pdo()->prepare(
                    'SELECT name, quantity_on_hand, moving_avg_cost FROM products WHERE id = ? FOR UPDATE'
                );
                $stmt->execute([$row['product_id']]);
                $productRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($productRow === false) {
                    throw new \RuntimeException("Product #{$row['product_id']} not found during finalize.");
                }
                if ((float) $productRow['quantity_on_hand'] < $row['quantity_returned'] - 0.0001) {
                    throw new \RuntimeException(\sprintf(
                        "Insufficient stock to return '%s'. Available: %s, returning: %s.",
                        $productRow['name'],
                        \rtrim(\rtrim((string) $productRow['quantity_on_hand'], '0'), '.'),
                        \rtrim(\rtrim((string) $row['quantity_returned'], '0'), '.')
                    ));
                }
                $costPerUnit = (float) $productRow['moving_avg_cost'];
                $stockOutCost = $this->inventory->applyStockOut(
                    (int) $row['product_id'],
                    (float) $row['quantity_returned'],
                    false,
                    ['source' => 'purchase_return', 'source_ref_type' => 'purchase_return', 'source_ref_id' => $returnId, 'user_id' => $userId]
                );

                $this->returnLines->updateComputed($lineId, [
                    'subtotal'                => $row['subtotal'],
                    'tax_amount'              => $row['tax_amount'],
                    'cgst_amount'             => $row['cgst'],
                    'sgst_amount'             => $row['sgst'],
                    'igst_amount'             => $row['igst'],
                    'line_total'              => $row['line_total'],
                    'stock_out_cost_per_unit' => \round($costPerUnit, 4),
                    'stock_out_cost_total'    => $this->pricing->money($stockOutCost),
                ]);

                $totalTax           += $row['tax_amount'];
                $totalCgst          += $row['cgst'];
                $totalSgst          += $row['sgst'];
                $totalIgst          += $row['igst'];
                $totalStockOutValue += $stockOutCost;
            }

            // -------- Step 5: pro-rate final_discount + expenses --------
            $origSubtotal       = (float) $originalInvoice['subtotal'];
            $origFinalDiscount  = (float) ($originalInvoice['final_discount_amount'] ?? 0);
            $origTotalExpenses  = (float) ($originalInvoice['total_expenses']     ?? 0);
            $origExpenseTax     = (float) ($originalInvoice['total_expense_tax']  ?? 0);

            $discountShare = $origSubtotal > 0
                ? $this->pricing->money($origFinalDiscount * ($totalSubtotal / $origSubtotal))
                : 0.0;
            $qtyRatio       = $totalOriginalQty > 0 ? ($totalReturnedQty / $totalOriginalQty) : 0.0;
            // Original extras (loading/unloading/freight) are EXCLUDED from the refund unless
            // this return opts in. Stored as 0 when excluded so the document is self-consistent.
            $includeExtras   = (int) ($return['include_original_extras'] ?? 0) === 1;
            $expensesShare   = $includeExtras ? $this->pricing->money($origTotalExpenses * $qtyRatio) : 0.0;
            $expenseTaxShare = $includeExtras ? $this->pricing->money($origExpenseTax    * $qtyRatio) : 0.0;

            // -------- Step 6: seller's return charge/fine, then grand total + round-off --------
            $taxableLines = $totalSubtotal - $discountShare;
            $grossReturn  = $taxableLines + $totalTax + $expensesShare + $expenseTaxShare;
            [$chargeBase, $chargeTax] = $this->computeReturnCharge($return, $taxableLines);
            if ($chargeBase + $chargeTax > $grossReturn + 0.01) {
                throw new \RuntimeException(\sprintf(
                    'Return charge ₹%0.2f exceeds the return value ₹%0.2f. Lower the charge.',
                    $chargeBase + $chargeTax, $grossReturn
                ));
            }
            $preRound = $grossReturn - $chargeBase - $chargeTax;
            if ($preRound < 0) { $preRound = 0; }

            if (Settings::bool('enable_round_off', true)) {
                $rounded    = $this->pricing->roundOff($preRound);
                $grandTotal = $rounded['rounded_total'];
                $roundOff   = $rounded['round_off'];
            } else {
                $grandTotal = $this->pricing->money($preRound);
                $roundOff   = 0.0;
            }

            $this->returns->updateTotals($returnId, [
                'subtotal'              => $this->pricing->money($totalSubtotal),
                'final_discount_share'  => $discountShare,
                'expenses_share'        => $expensesShare,
                'expenses_tax_share'    => $expenseTaxShare,
                'total_tax'             => $this->pricing->money($totalTax),
                'total_cgst'            => $this->pricing->money($totalCgst),
                'total_sgst'            => $this->pricing->money($totalSgst),
                'total_igst'            => $this->pricing->money($totalIgst),
                'total_stock_out_value' => $this->pricing->money($totalStockOutValue),
                'return_charge_amount'     => $this->pricing->money($chargeBase),
                'return_charge_tax_amount' => $this->pricing->money($chargeTax),
                'round_off_amount'      => $roundOff,
                'grand_total'           => $grandTotal,
            ]);

            // -------- Step 7: net against payable first, then cash-refund the excess --------
            // The return's grand_total first reduces what we still owe on the invoice.
            // Whatever is left over (we'd already paid for those goods) is the cash the
            // supplier refunds us. credit_note mode keeps the surplus deferred (no cash).
            // Lock the invoice row and re-read its due UNDER the lock — a concurrent
            // payment on the same bill also locks this row, so we never compute the cash
            // refund from a stale due_amount.
            $lockDue = Db::pdo()->prepare(
                'SELECT due_amount FROM purchase_invoices WHERE id = ? FOR UPDATE'
            );
            $lockDue->execute([(int) $originalInvoice['id']]);
            $currentDue       = (float) $lockDue->fetchColumn();
            $appliedToPayable = \min($grandTotal, $currentDue);
            $newDue           = \max(0.0, $currentDue - $grandTotal);
            $this->reduceInvoiceDue((int) $originalInvoice['id'], $newDue);

            $cashRefund = 0.0;
            if ((string) $return['refund_method'] === 'cash') {
                $cashRefund = \round($grandTotal - $appliedToPayable, 2);   // = max(0, grand_total − due)
                if ($cashRefund > 0.001) {
                    $kind  = (string) ($return['refund_account_kind'] ?? '');
                    $accId = (int) ($return['refund_account_id'] ?? 0);
                    if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true) || $accId <= 0) {
                        throw new \RuntimeException(
                            'Cash refund needs a destination account. Edit the return header and pick the bank/drawer to receive ₹'
                            . \number_format($cashRefund, 2) . '.'
                        );
                    }
                    $acc = $kind === 'bank_account' ? $this->banks->find($accId) : $this->drawers->find($accId);
                    if ($acc === null) {
                        throw new \RuntimeException('Refund account not found.');
                    }
                    if (($acc['status'] ?? 'active') !== 'active') {
                        throw new \RuntimeException("Refund account '{$acc['name']}' is inactive.");
                    }
                    // Supplier refunds us → CREDIT (money in) to the chosen account.
                    if ($kind === 'bank_account') {
                        $this->banks->applyDelta($accId, $cashRefund);
                    } else {
                        $this->drawers->applyDelta($accId, $cashRefund);
                    }
                } else {
                    $cashRefund = 0.0;   // method=cash but the return fully netted an open payable
                }
            }
            $this->returns->recordCashRefund($returnId, $cashRefund);

            // -------- Step 8: assign return number + finalize --------
            $fy = $this->fiscalYearLabel((string) $return['return_date']);
            $next = $this->returns->maxSequenceForFy("GP-PRET/{$fy}") + 1;
            $returnNumber = \sprintf('GP-PRET/%s/%05d', $fy, $next);
            $this->returns->markFinalized($returnId, $returnNumber, $userId);
            ActivityLog::log('finalize', 'purchase_return', $returnId, ['label' => $returnNumber]);
        });
    }

    /**
     * Seller's return charge / fine / restocking deduction (reduces the refund).
     * Flat ₹ or a percentage of the returned goods value; GST added on top when taxable.
     *
     * @return array{0: float, 1: float} [charge base (ex-GST), charge GST]
     */
    private function computeReturnCharge(array $return, float $goodsValue): array
    {
        $mode = (string) ($return['return_charge_mode'] ?? 'none');
        $value = (float) ($return['return_charge_value'] ?? 0);
        if ($mode === 'none' || $value <= 0) {
            return [0.0, 0.0];
        }
        $base = $mode === 'percent'
            ? $this->pricing->money(\max(0.0, $goodsValue) * $value / 100)
            : $this->pricing->money($value);

        $tax = 0.0;
        if ((int) ($return['return_charge_taxable'] ?? 0) === 1) {
            $rate = (float) ($return['return_charge_tax_rate'] ?? 0);
            $tax = $this->pricing->money($base * $rate / 100);
        }
        return [$base, $tax];
    }

    private function reduceInvoiceDue(int $invoiceId, float $newDue): void
    {
        $stmt = Db::pdo()->prepare(
            "UPDATE purchase_invoices
             SET due_amount = ?,
                 payment_status = CASE
                    WHEN ? <= 0 THEN 'paid'
                    WHEN paid_amount > 0 THEN 'partial'
                    ELSE 'unpaid'
                 END
             WHERE id = ?"
        );
        $stmt->execute([$newDue, $newDue, $invoiceId]);
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
