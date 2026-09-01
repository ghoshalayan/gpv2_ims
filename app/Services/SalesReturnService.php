<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BankAccountRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashierDrawerRepository;
use App\Repositories\SalesInvoiceLineComponentRepository;
use App\Repositories\SalesInvoiceLineRepository;
use App\Repositories\SalesInvoiceRepository;
use App\Repositories\SalesReturnLineComponentRepository;
use App\Repositories\SalesReturnLineRepository;
use App\Repositories\SalesReturnRepository;
use App\Support\ActivityLog;
use App\Support\Db;
use App\Support\Settings;

/**
 * Finalize orchestrator for sales returns. One DB transaction:
 *
 *   1. Validate the return is in draft and has at least one line.
 *   2. For each return line: validate qty <= original − already returned (excluding this draft).
 *   3. Recompute line tax math mirroring the originating sale's bill_format / tax_treatment.
 *   4. Stock-IN at SNAPSHOTTED cogs_per_unit (D54) — composite lines reverse via per-component snapshots.
 *   5. Reverse commission on a per-line basis (D62).
 *   6. Pro-rate the original invoice's final_discount_share + extras_share + extras_tax_share
 *      by qty_returned / qty_original (sum across lines).
 *   7. Compute totals + round-off; persist.
 *   8. Reduce the original sales_invoice.due_amount by the return's grand_total
 *      (clamped at zero). For refund_method='cash', the surplus that can't net against
 *      an open receivable (grand_total − due, i.e. the already-paid portion) is refunded:
 *      the chosen bank/drawer is DEBITED and cash_refund_amount is recorded. credit_note
 *      mode keeps the surplus deferred (no cash moves).
 *   9. Mark the return finalized and assign a return number.
 */
class SalesReturnService
{
    public function __construct(
        private SalesReturnRepository                  $returns               = new SalesReturnRepository(),
        private SalesReturnLineRepository              $returnLines           = new SalesReturnLineRepository(),
        private SalesReturnLineComponentRepository     $returnLineComponents  = new SalesReturnLineComponentRepository(),
        private SalesInvoiceRepository                 $invoices              = new SalesInvoiceRepository(),
        private SalesInvoiceLineRepository             $invoiceLines          = new SalesInvoiceLineRepository(),
        private SalesInvoiceLineComponentRepository    $invoiceLineComponents = new SalesInvoiceLineComponentRepository(),
        private BusinessProfileRepository              $business              = new BusinessProfileRepository(),
        private PricingService                         $pricing               = new PricingService(),
        private InventoryService                       $inventory             = new InventoryService(),
        private BankAccountRepository                  $banks                 = new BankAccountRepository(),
        private CashierDrawerRepository                $drawers               = new CashierDrawerRepository(),
    ) {}

    public function finalize(int $returnId, int $userId): void
    {
        Db::transaction(function () use ($returnId, $userId): void {
            $return = $this->returns->find($returnId);
            if ($return === null) {
                throw new \RuntimeException("Sales return #{$returnId} not found.");
            }
            if ($return['status'] !== 'draft') {
                throw new \RuntimeException("Return is not a draft (status = {$return['status']}).");
            }

            $lines = $this->returnLines->byReturn($returnId);
            if ($lines === []) {
                throw new \RuntimeException('Cannot finalize a sales return with no lines.');
            }

            $originalInvoice = $this->invoices->find((int) $return['original_sales_invoice_id']);
            if ($originalInvoice === null || $originalInvoice['status'] !== 'finalized') {
                throw new \RuntimeException('Original sales invoice missing or not finalized.');
            }

            $billFormat   = (string) $return['bill_format'];
            $taxTreatment = (string) $return['tax_treatment'];
            $isIntraState = (int) $return['is_intra_state'] === 1;
            $invoiceTaxRate = (float) $return['invoice_level_tax_rate'];

            // -------- Step 2 + 3: validate qtys, recompute per-line math --------
            $totalSubtotal     = 0.0;
            $totalTax          = 0.0;
            $totalCgst         = 0.0;
            $totalSgst         = 0.0;
            $totalIgst         = 0.0;
            $totalCogs         = 0.0;
            $totalCommission   = 0.0;
            $totalReturnedQty  = 0.0;
            $totalOriginalQty  = 0.0;

            $rebuiltLines = [];

            foreach ($lines as $rl) {
                $origLine = $this->invoiceLines->findLine(
                    (int) $rl['original_sales_line_id'],
                    (int) $return['original_sales_invoice_id']
                );
                if ($origLine === null) {
                    throw new \RuntimeException("Original sale line #{$rl['original_sales_line_id']} not found.");
                }
                $alreadyReturned = $this->returnLines->returnedQtyForOriginalLine(
                    (int) $rl['original_sales_line_id'],
                    $returnId
                );
                $remaining = (float) $origLine['quantity'] - $alreadyReturned;
                if ((float) $rl['quantity_returned'] > $remaining + 0.0001) {
                    throw new \RuntimeException(sprintf(
                        "Return qty %s for '%s' exceeds remaining %s (original %s, prior returns %s).",
                        \rtrim(\rtrim((string) $rl['quantity_returned'], '0'), '.'),
                        $rl['display_name'],
                        \rtrim(\rtrim((string) $remaining, '0'), '.'),
                        \rtrim(\rtrim((string) $origLine['quantity'], '0'), '.'),
                        \rtrim(\rtrim((string) $alreadyReturned, '0'), '.')
                    ));
                }

                // Recompute line subtotal/tax mirroring the original line's tax shape.
                $c = $this->pricing->computeLine(
                    (float) $rl['quantity_returned'],
                    (float) $rl['unit_price'],
                    (float) $rl['addsub_percent'],
                    (float) $rl['tax_rate'],
                    $billFormat,
                    $taxTreatment,
                    $isIntraState
                );

                $taxAmount = $c['tax_amount'];
                $cgst      = $c['cgst'];
                $sgst      = $c['sgst'];
                $igst      = $c['igst'];
                $subtotal  = $c['subtotal'];
                $lineTotal = $c['line_total'];

                // COGS reversal: snapshot cogs_per_unit × qty_returned (D54).
                $cogsPerUnit = (float) $origLine['cogs_per_unit'];
                $cogsTotal   = $this->pricing->money($cogsPerUnit * (float) $rl['quantity_returned']);

                // Commission reversal: snapshot rate × subtotal (mirrors finalize math).
                $commRate     = (float) $origLine['commission_rate'];
                $commReversed = $this->pricing->money($subtotal * $commRate / 100);

                $rebuiltLines[(int) $rl['id']] = [
                    'original_sales_line_id' => (int) $rl['original_sales_line_id'],
                    'is_composite_line'      => (int) $rl['is_composite_line'],
                    'product_id'             => $rl['product_id'] ? (int) $rl['product_id'] : null,
                    'display_name'           => (string) $rl['display_name'],
                    'quantity_returned'      => (float) $rl['quantity_returned'],
                    'orig_quantity'          => (float) $origLine['quantity'],
                    'subtotal'               => $subtotal,
                    'tax_amount'             => $taxAmount,
                    'cgst'                   => $cgst,
                    'sgst'                   => $sgst,
                    'igst'                   => $igst,
                    'line_total'             => $lineTotal,
                    'cogs_per_unit'          => $cogsPerUnit,
                    'cogs_total'             => $cogsTotal,
                    'commission_rate'        => $commRate,
                    'commission_reversed'    => $commReversed,
                ];

                $totalSubtotal    += $subtotal;
                $totalReturnedQty += (float) $rl['quantity_returned'];
                $totalOriginalQty += (float) $origLine['quantity'];
            }

            // -------- Step 3b: invoice-level tax allocation in Mode A --------
            $effectiveInvoiceTaxRate = $taxTreatment === 'no_tax' ? 0.0 : $invoiceTaxRate;
            if ($billFormat === 'discount_then_tax' && $effectiveInvoiceTaxRate > 0) {
                if ($taxTreatment === 'inclusive') {
                    $rateFactor = 1 + $effectiveInvoiceTaxRate / 100;
                    $invoiceNet = $this->pricing->money($totalSubtotal / $rateFactor);
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

            // -------- Step 4: stock-in (D54: snapshot cost) + components for composites --------
            foreach ($rebuiltLines as $lineId => $row) {
                if ($row['is_composite_line'] === 1) {
                    // Reverse each component at its snapshotted per-component cogs_per_unit.
                    $origComponents = $this->invoiceLineComponents->byLine($row['original_sales_line_id']);
                    if ($origComponents === []) {
                        throw new \RuntimeException(
                            "Composite original line for '{$row['display_name']}' has no components."
                        );
                    }
                    $ratio = $row['orig_quantity'] > 0
                        ? $row['quantity_returned'] / $row['orig_quantity']
                        : 0.0;
                    foreach ($origComponents as $oc) {
                        $compReturnQty = (float) $oc['quantity_consumed'] * $ratio;
                        $compCogsUnit  = (float) $oc['cogs_per_unit'];
                        $compCogsTotal = $this->pricing->money($compCogsUnit * $compReturnQty);
                        $this->inventory->applyStockIn(
                            (int) $oc['component_product_id'],
                            $compReturnQty,
                            $compCogsUnit,
                            [
                                'source'          => 'return',
                                'source_ref_type' => 'sales_return_line',
                                'source_ref_id'   => $lineId,
                                'user_id'         => $userId,
                            ]
                        );
                        $this->returnLineComponents->insert($lineId, [
                            'original_component_id' => (int) $oc['id'],
                            'component_product_id'  => (int) $oc['component_product_id'],
                            'component_name'        => (string) $oc['component_name'],
                            'component_unit'        => (string) $oc['component_unit'],
                            'quantity_returned'     => $compReturnQty,
                            'cogs_per_unit'         => $compCogsUnit,
                            'cogs_total'            => $compCogsTotal,
                        ]);
                    }
                } elseif ($row['product_id'] !== null) {
                    // Simple / Irreversible-composite line — restock the product itself.
                    $this->inventory->applyStockIn(
                        (int) $row['product_id'],
                        (float) $row['quantity_returned'],
                        (float) $row['cogs_per_unit'],
                        [
                            'source'          => 'return',
                            'source_ref_type' => 'sales_return_line',
                            'source_ref_id'   => $lineId,
                            'user_id'         => $userId,
                        ]
                    );
                }

                $this->returnLines->updateComputed($lineId, [
                    'subtotal'            => $row['subtotal'],
                    'tax_amount'          => $row['tax_amount'],
                    'cgst_amount'         => $row['cgst'],
                    'sgst_amount'         => $row['sgst'],
                    'igst_amount'         => $row['igst'],
                    'line_total'          => $row['line_total'],
                    'cogs_total'          => $row['cogs_total'],
                    'commission_reversed' => $row['commission_reversed'],
                ]);

                $totalTax        += $row['tax_amount'];
                $totalCgst       += $row['cgst'];
                $totalSgst       += $row['sgst'];
                $totalIgst       += $row['igst'];
                $totalCogs       += $row['cogs_total'];
                $totalCommission += $row['commission_reversed'];
            }

            // -------- Step 6: pro-rate final_discount + extras shares --------
            $origSubtotal       = (float) $originalInvoice['subtotal'];
            $origFinalDiscount  = (float) ($originalInvoice['final_discount_amount'] ?? 0);
            $origTotalExtras    = (float) ($originalInvoice['total_extras']      ?? 0);
            $origTotalExtrasTax = (float) ($originalInvoice['total_extras_tax']  ?? 0);

            // Discount allocates by VALUE relative to original subtotal so it's accurate.
            $discountShare = $origSubtotal > 0
                ? $this->pricing->money($origFinalDiscount * ($totalSubtotal / $origSubtotal))
                : 0.0;
            // Extras pro-rate by qty (no per-line link to extras, so qty is the cleanest proxy).
            $qtyRatio       = $totalOriginalQty > 0 ? ($totalReturnedQty / $totalOriginalQty) : 0.0;
            // Original extras (packing/freight/handling) are EXCLUDED from the refund unless
            // this return opts in. Stored as 0 when excluded so the document is self-consistent.
            $includeExtras  = (int) ($return['include_original_extras'] ?? 0) === 1;
            $extrasShare    = $includeExtras ? $this->pricing->money($origTotalExtras    * $qtyRatio) : 0.0;
            $extrasTaxShare = $includeExtras ? $this->pricing->money($origTotalExtrasTax * $qtyRatio) : 0.0;

            // -------- Step 7: seller's return charge/fine, then grand total + round-off --------
            $taxableLines = $totalSubtotal - $discountShare;
            $grossReturn  = $taxableLines + $totalTax + $extrasShare + $extrasTaxShare;
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
                'subtotal'                  => $this->pricing->money($totalSubtotal),
                'final_discount_share'      => $discountShare,
                'extras_share'              => $extrasShare,
                'extras_tax_share'          => $extrasTaxShare,
                'total_tax'                 => $this->pricing->money($totalTax),
                'total_cgst'                => $this->pricing->money($totalCgst),
                'total_sgst'                => $this->pricing->money($totalSgst),
                'total_igst'                => $this->pricing->money($totalIgst),
                'total_cogs'                => $this->pricing->money($totalCogs),
                'total_commission_reversed' => $this->pricing->money($totalCommission),
                'return_charge_amount'      => $this->pricing->money($chargeBase),
                'return_charge_tax_amount'  => $this->pricing->money($chargeTax),
                'round_off_amount'          => $roundOff,
                'grand_total'               => $grandTotal,
            ]);

            // -------- Step 8: net against receivable first, then cash-refund the excess --------
            // The return's grand_total first reduces what the customer still owes on the
            // invoice. Whatever is left over (they had already paid for those goods) is the
            // cash we refund them. credit_note mode keeps the surplus deferred (no cash).
            // Lock the invoice row and re-read its due UNDER the lock — a concurrent
            // payment on the same bill also locks this row, so we never compute the cash
            // refund from a stale due_amount.
            $lockDue = Db::pdo()->prepare(
                'SELECT due_amount FROM sales_invoices WHERE id = ? FOR UPDATE'
            );
            $lockDue->execute([(int) $originalInvoice['id']]);
            $currentDue        = (float) $lockDue->fetchColumn();
            $appliedToDue      = \min($grandTotal, $currentDue);
            $newDue            = \max(0.0, $currentDue - $grandTotal);
            $this->reduceInvoiceDue((int) $originalInvoice['id'], $newDue);

            $cashRefund = 0.0;
            if ((string) $return['refund_method'] === 'cash') {
                $cashRefund = \round($grandTotal - $appliedToDue, 2);   // = max(0, grand_total − due)
                if ($cashRefund > 0.001) {
                    $kind  = (string) ($return['refund_account_kind'] ?? '');
                    $accId = (int) ($return['refund_account_id'] ?? 0);
                    if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true) || $accId <= 0) {
                        throw new \RuntimeException(
                            'Cash refund needs a source account. Edit the return header and pick the bank/drawer to pay ₹'
                            . \number_format($cashRefund, 2) . ' from.'
                        );
                    }
                    $acc = $kind === 'bank_account' ? $this->banks->find($accId) : $this->drawers->find($accId);
                    if ($acc === null) {
                        throw new \RuntimeException('Refund account not found.');
                    }
                    if (($acc['status'] ?? 'active') !== 'active') {
                        throw new \RuntimeException("Refund account '{$acc['name']}' is inactive.");
                    }
                    if ((float) $acc['current_balance'] < $cashRefund - 0.001) {
                        throw new \RuntimeException(\sprintf(
                            "Refund account '%s' balance ₹%0.2f is short of the ₹%0.2f cash refund.",
                            $acc['name'], (float) $acc['current_balance'], $cashRefund
                        ));
                    }
                    // We pay the customer back → DEBIT (money out) of the chosen account.
                    if ($kind === 'bank_account') {
                        $this->banks->applyDelta($accId, -$cashRefund);
                    } else {
                        $this->drawers->applyDelta($accId, -$cashRefund);
                    }
                } else {
                    $cashRefund = 0.0;   // method=cash but the return fully netted an open receivable
                }
            }
            $this->returns->recordCashRefund($returnId, $cashRefund);

            // -------- Step 9: assign return number + finalize --------
            $fy = $this->fiscalYearLabel((string) $return['return_date']);
            $next = $this->returns->maxSequenceForFy("GP-RET/{$fy}") + 1;
            $returnNumber = \sprintf('GP-RET/%s/%05d', $fy, $next);
            $this->returns->markFinalized($returnId, $returnNumber, $userId);
            ActivityLog::log('finalize', 'sales_return', $returnId, ['label' => $returnNumber]);
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
            "UPDATE sales_invoices
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
