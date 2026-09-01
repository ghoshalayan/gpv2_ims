<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PurchaseInvoiceExpenseRepository;
use App\Repositories\PurchaseInvoiceLineRepository;
use App\Repositories\PurchaseInvoiceRepository;
use App\Support\ActivityLog;
use App\Support\Db;
use App\Support\Settings;

/**
 * Finalize orchestrator for purchase invoices. Everything inside one DB transaction.
 *
 * 1. Validate invoice is in draft and has at least one line.
 * 2. Recompute each line's amounts (defensive — sanity checks draft data).
 * 3. Allocate every expense to lines (by_value / by_quantity / equal_split).
 * 4. Compute per-line landed cost (per D10/D11).
 *      landed_cost_per_unit = (subtotal
 *                              + (non-claimable line tax)
 *                              + allocated expense share
 *                              + allocated expense tax share (non-claimable only)
 *                             ) / quantity
 * 5. For each line: apply stock-in to products (qty + moving-avg update).
 * 6. Persist per-line computed values.
 * 7. Compute invoice totals + round-off; update header.
 * 8. Mark finalized.
 *
 * Edit-finalized / reverse is not implemented in v1 — finalized invoices are immutable.
 */
class PurchaseInvoiceService
{
    public function __construct(
        private PurchaseInvoiceRepository         $invoices  = new PurchaseInvoiceRepository(),
        private PurchaseInvoiceLineRepository     $lines     = new PurchaseInvoiceLineRepository(),
        private PurchaseInvoiceExpenseRepository  $expenses  = new PurchaseInvoiceExpenseRepository(),
        private PricingService                    $pricing   = new PricingService(),
        private InventoryService                  $inventory = new InventoryService(),
    ) {}

    public function finalize(int $invoiceId, int $userId): void
    {
        Db::transaction(function () use ($invoiceId, $userId): void {
            $invoice = $this->invoices->find($invoiceId);
            if ($invoice === null) {
                throw new \RuntimeException("Invoice #{$invoiceId} not found.");
            }
            if ($invoice['status'] !== 'draft') {
                throw new \RuntimeException("Invoice is not a draft (status = {$invoice['status']}).");
            }

            $lines = $this->lines->byInvoice($invoiceId);
            if ($lines === []) {
                throw new \RuntimeException('Cannot finalize an invoice with no lines.');
            }

            $expenses = $this->expenses->byInvoice($invoiceId);

            $billFormat     = $invoice['bill_format'];
            $taxTreatment   = (string) $invoice['tax_treatment'];
            $isIntraState   = (int) $invoice['is_intra_state'] === 1;
            $gstClaimable   = (int) $invoice['gst_claimable'] === 1;
            $invoiceTaxRate = (float) $invoice['invoice_level_tax_rate'];
            $finalDiscount  = $billFormat === 'discount_then_tax'
                ? \max(0.0, (float) ($invoice['final_discount_amount'] ?? 0))
                : 0.0;

            // -------- Step 2: recompute line amounts (defensive) --------
            $lineTable = [];
            foreach ($lines as $line) {
                $c = $this->pricing->computeLine(
                    (float) $line['quantity'],
                    (float) $line['unit_price'],
                    (float) $line['addsub_percent'],
                    (float) $line['tax_rate'],
                    $billFormat,
                    $taxTreatment,
                    $isIntraState
                );
                $lineTable[(int) $line['id']] = [
                    'id'       => (int) $line['id'],
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (float) $line['quantity'],
                    'subtotal' => $c['subtotal'],
                    'tax_amount' => $c['tax_amount'],
                    'cgst' => $c['cgst'], 'sgst' => $c['sgst'], 'igst' => $c['igst'],
                    'line_total' => $c['line_total'],
                    'expense_share'     => 0.0,
                    'expense_tax_share' => 0.0,
                    'invoice_tax_share' => 0.0,
                    'discount_share'    => 0.0,
                ];
            }

            // -------- Step 3: invoice-level tax in discount_then_tax mode --------
            // no_tax treatment zeroes the invoice-level rate regardless of what's stored.
            // Final discount (Mode A only) reduces the taxable base BEFORE invoice-level tax
            // and is allocated per line by value so landed cost stays accurate.
            $effectiveInvoiceTaxRate = $taxTreatment === 'no_tax' ? 0.0 : $invoiceTaxRate;
            $invoiceLevelTax = 0.0;
            $invoiceSubtotal = \array_sum(\array_column($lineTable, 'subtotal'));
            if ($finalDiscount > $invoiceSubtotal) { $finalDiscount = $invoiceSubtotal; }

            if ($billFormat === 'discount_then_tax' && $effectiveInvoiceTaxRate > 0) {
                $taxableBase = \max(0.0, $invoiceSubtotal - $finalDiscount);
                if ($taxTreatment === 'inclusive') {
                    // Line subtotals are GROSS; back out invoice-level tax on the discounted base.
                    $rateFactor = 1 + $effectiveInvoiceTaxRate / 100;
                    $invoiceNet = $this->pricing->money($taxableBase / $rateFactor);
                    $invoiceLevelTax = $this->pricing->money($taxableBase - $invoiceNet);
                    // Re-base each line subtotal to net proportionally relative to the GROSS subtotal.
                    $adjFactor = $invoiceSubtotal > 0 ? (($invoiceNet + $finalDiscount) / $invoiceSubtotal) : 1.0;
                    foreach ($lineTable as $lid => $r) {
                        $lineTable[$lid]['subtotal'] = $this->pricing->money($r['subtotal'] * $adjFactor);
                    }
                    // Refresh $invoiceSubtotal after rebase so discount allocation below is proportional.
                    $invoiceSubtotal = \array_sum(\array_column($lineTable, 'subtotal'));
                } else {
                    $invoiceLevelTax = $this->pricing->money($taxableBase * $effectiveInvoiceTaxRate / 100);
                }
                $shares = $this->pricing->allocateInvoiceLevelTax(
                    $invoiceLevelTax,
                    array_values($lineTable)
                );
                foreach ($shares as $lineId => $share) {
                    $lineTable[$lineId]['invoice_tax_share'] = $share;
                    $split = $this->pricing->splitTax($share, $isIntraState);
                    $lineTable[$lineId]['tax_amount'] = $share;
                    $lineTable[$lineId]['cgst']       = $split[0];
                    $lineTable[$lineId]['sgst']       = $split[1];
                    $lineTable[$lineId]['igst']       = $split[2];
                    $lineTable[$lineId]['line_total'] = $this->pricing->money(
                        $lineTable[$lineId]['subtotal'] + $share
                    );
                }
            }

            // Allocate the final discount per line by value so landed cost absorbs its share.
            if ($finalDiscount > 0 && $invoiceSubtotal > 0) {
                $discountShares = $this->pricing->allocateExpense(
                    $finalDiscount,
                    'by_value',
                    array_values($lineTable)
                );
                foreach ($discountShares as $lineId => $share) {
                    $lineTable[$lineId]['discount_share'] = $share;
                }
            }

            // -------- Step 4: allocate expenses across lines --------
            $totalExpenses     = 0.0;
            $totalExpenseTax   = 0.0;
            foreach ($expenses as $exp) {
                $baseShares = $this->pricing->allocateExpense(
                    (float) $exp['amount'],
                    $exp['allocation_method'],
                    array_values($lineTable)
                );
                $taxShares = $this->pricing->allocateExpense(
                    (float) $exp['tax_amount'],
                    $exp['allocation_method'],
                    array_values($lineTable)
                );
                foreach ($baseShares as $lineId => $share) {
                    $lineTable[$lineId]['expense_share']     += $share;
                    $lineTable[$lineId]['expense_tax_share'] += $taxShares[$lineId];
                }
                $totalExpenses   += (float) $exp['amount'];
                $totalExpenseTax += (float) $exp['tax_amount'];
            }

            // -------- Step 5+6: per-line landed cost + stock-in + persist --------
            $totalSubtotal = 0.0;
            $totalTax      = 0.0;
            $totalCgst     = 0.0;
            $totalSgst     = 0.0;
            $totalIgst     = 0.0;

            foreach ($lineTable as $lineId => $row) {
                // Non-claimable line tax goes into cost; claimable doesn't.
                $nonClaimableLineTax    = $gstClaimable ? 0.0 : $row['tax_amount'];
                $nonClaimableExpenseTax = $gstClaimable ? 0.0 : $row['expense_tax_share'];

                // Final discount reduces the line's cost basis (its share of the bottom-line discount).
                $landedBase = $row['subtotal']
                            - $row['discount_share']
                            + $nonClaimableLineTax
                            + $row['expense_share']
                            + $nonClaimableExpenseTax;

                $landedCostPerUnit = $row['quantity'] > 0
                    ? \round($landedBase / $row['quantity'], 4)
                    : 0.0;

                $this->inventory->applyStockIn(
                    $row['product_id'],
                    $row['quantity'],
                    $landedCostPerUnit,
                    [
                        'source'          => 'purchase',
                        'source_ref_type' => 'purchase_invoice_line',
                        'source_ref_id'   => $lineId,
                        'user_id'         => $userId,
                    ]
                );

                $this->lines->updateLineComputed($lineId, [
                    'subtotal'                    => $row['subtotal'],
                    'tax_amount'                  => $row['tax_amount'],
                    'cgst_amount'                 => $row['cgst'],
                    'sgst_amount'                 => $row['sgst'],
                    'igst_amount'                 => $row['igst'],
                    'line_total'                  => $row['line_total'],
                    'allocated_expense_share'     => $row['expense_share'],
                    'allocated_expense_tax_share' => $row['expense_tax_share'],
                    'landed_cost_per_unit'        => $landedCostPerUnit,
                ]);

                $totalSubtotal += $row['subtotal'];
                $totalTax      += $row['tax_amount'];
                $totalCgst     += $row['cgst'];
                $totalSgst     += $row['sgst'];
                $totalIgst     += $row['igst'];
            }

            // -------- Step 7: invoice totals + round-off --------
            // Grand total includes final discount deducted (Mode A only).
            $preRound = $totalSubtotal - $finalDiscount + $totalTax + $totalExpenses + $totalExpenseTax;
            if (Settings::bool('enable_round_off', true)) {
                $rounded = $this->pricing->roundOff($preRound);
                $grandTotal = $rounded['rounded_total'];
                $roundOff   = $rounded['round_off'];
            } else {
                $grandTotal = $this->pricing->money($preRound);
                $roundOff   = 0.0;
            }

            $this->invoices->updateTotals($invoiceId, [
                'subtotal'             => $this->pricing->money($totalSubtotal),
                'total_tax'            => $this->pricing->money($totalTax),
                'total_cgst'           => $this->pricing->money($totalCgst),
                'total_sgst'           => $this->pricing->money($totalSgst),
                'total_igst'           => $this->pricing->money($totalIgst),
                'total_expenses'       => $this->pricing->money($totalExpenses),
                'total_expense_tax'    => $this->pricing->money($totalExpenseTax),
                'final_discount_amount'=> $this->pricing->money($finalDiscount),
                'round_off_amount'     => $roundOff,
                'grand_total'          => $grandTotal,
            ]);

            // -------- Step 8: mark finalized --------
            $this->invoices->markFinalized($invoiceId, $userId);
            $finalized = $this->invoices->find($invoiceId);
            ActivityLog::log('finalize', 'purchase_invoice', $invoiceId, [
                'label' => $finalized['invoice_number'] ?? null,
            ]);
        });
    }
}
