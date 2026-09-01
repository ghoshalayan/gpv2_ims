<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BusinessProfileRepository;
use App\Repositories\SalesInvoiceExtraRepository;
use App\Repositories\SalesInvoiceLineComponentRepository;
use App\Repositories\SalesInvoiceLineRepository;
use App\Repositories\SalesInvoiceRepository;
use App\Repositories\SalesmanRepository;
use App\Support\ActivityLog;
use App\Support\Db;
use App\Support\Settings;

/**
 * Finalize orchestrator for sales invoices. Inside one transaction:
 *
 * 1. Validate invoice is in draft and has at least one line.
 * 2. Recompute each line's amounts (defensive, line math identical to purchase per D1/D2/D52).
 * 3. Apply invoice-level tax in discount_then_tax mode (allocates to lines by value).
 * 4. For each line:
 *      - Block oversell (D6): line.quantity must be <= product.quantity_on_hand.
 *      - Apply stock-out via InventoryService (returns COGS based on current moving avg).
 *      - Snapshot cogs_per_unit + cogs_total per D54 (used by sales returns).
 *      - Snapshot commission_rate + commission_amount per D62 (used by ledger module).
 * 5. Persist computed values per line.
 * 6. Compute invoice totals + round-off; update header.
 * 7. Generate invoice_number (GP/{FY}/{NNNNN}); resolve bill_type from customer GSTIN (D17).
 * 8. Mark finalized.
 *
 * Composite products are NOT yet supported — they appear as normal products that
 * happen to have 0 stock (which will trigger the oversell block). Composite-aware
 * sales come in module #14.5.
 */
class SalesInvoiceService
{
    public function __construct(
        private SalesInvoiceRepository               $invoices       = new SalesInvoiceRepository(),
        private SalesInvoiceLineRepository           $lines          = new SalesInvoiceLineRepository(),
        private SalesInvoiceLineComponentRepository  $lineComponents = new SalesInvoiceLineComponentRepository(),
        private SalesInvoiceExtraRepository          $extras         = new SalesInvoiceExtraRepository(),
        private SalesmanRepository                   $salesmen       = new SalesmanRepository(),
        private BusinessProfileRepository            $business       = new BusinessProfileRepository(),
        private PricingService                       $pricing        = new PricingService(),
        private InventoryService                     $inventory      = new InventoryService(),
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

            $billFormat     = $invoice['bill_format'];
            $taxTreatment   = (string) $invoice['tax_treatment'];
            $isIntraState   = (int) $invoice['is_intra_state'] === 1;
            $invoiceTaxRate = (float) $invoice['invoice_level_tax_rate'];

            // -------- Step 2: recompute line amounts --------
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
                    'id'                 => (int) $line['id'],
                    'product_id'         => $line['product_id'] ? (int) $line['product_id'] : null,
                    'is_composite_line'  => (int) ($line['is_composite_line'] ?? 0),
                    'display_name'       => (string) ($line['display_name'] ?? ''),
                    'salesman_id'        => $line['salesman_id'] ? (int) $line['salesman_id'] : null,
                    // CR-6: carried through so the commission snapshot below can prefer it
                    // over the salesman master rate. NULL = use the master rate, as before.
                    'commission_rate_override' => $line['commission_rate_override'] ?? null,
                    'quantity'           => (float) $line['quantity'],
                    'subtotal'           => $c['subtotal'],
                    'tax_amount'         => $c['tax_amount'],
                    'cgst' => $c['cgst'], 'sgst' => $c['sgst'], 'igst' => $c['igst'],
                    'line_total'         => $c['line_total'],
                ];
            }

            // -------- Step 3: invoice-level tax (discount_then_tax mode) --------
            // no_tax zeroes the effective rate. inclusive needs back-out from gross subtotals.
            $effectiveInvoiceTaxRate = $taxTreatment === 'no_tax' ? 0.0 : $invoiceTaxRate;
            if ($billFormat === 'discount_then_tax' && $effectiveInvoiceTaxRate > 0) {
                $invoiceSubtotal = \array_sum(\array_column($lineTable, 'subtotal'));
                if ($taxTreatment === 'inclusive') {
                    $rateFactor     = 1 + $effectiveInvoiceTaxRate / 100;
                    $invoiceNet     = $this->pricing->money($invoiceSubtotal / $rateFactor);
                    $invoiceLevelTax = $this->pricing->money($invoiceSubtotal - $invoiceNet);
                    // Re-base each line subtotal to net proportionally.
                    $adjFactor = $invoiceSubtotal > 0 ? ($invoiceNet / $invoiceSubtotal) : 1.0;
                    foreach ($lineTable as $lid => $r) {
                        $lineTable[$lid]['subtotal'] = $this->pricing->money($r['subtotal'] * $adjFactor);
                    }
                } else {
                    $invoiceLevelTax = $this->pricing->money($invoiceSubtotal * $effectiveInvoiceTaxRate / 100);
                }
                $shares = $this->pricing->allocateInvoiceLevelTax($invoiceLevelTax, array_values($lineTable));
                foreach ($shares as $lineId => $share) {
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

            // -------- Step 4: per-line stock-out, COGS + commission snapshot --------
            $totalSubtotal   = 0.0;
            $totalTax        = 0.0;
            $totalCgst       = 0.0;
            $totalSgst       = 0.0;
            $totalIgst       = 0.0;
            $totalCogs       = 0.0;
            $totalCommission = 0.0;

            $commissionRateCache = []; // salesman_id => rate

            foreach ($lineTable as $lineId => $row) {
                if ($row['is_composite_line'] === 1) {
                    // ----- Composite line: stock-out each component, not the composite itself -----
                    $cogsTotal = 0.0;
                    $components = $this->lineComponents->byLine($lineId);
                    if ($components === []) {
                        throw new \RuntimeException(
                            "Composite line '{$row['display_name']}' has no components."
                        );
                    }
                    // Pre-check all components first (all-or-nothing on the line).
                    foreach ($components as $comp) {
                        $stmt = Db::pdo()->prepare(
                            'SELECT name, unit, quantity_on_hand, status FROM products WHERE id = ? FOR UPDATE'
                        );
                        $stmt->execute([$comp['component_product_id']]);
                        $cp = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($cp === false) {
                            throw new \RuntimeException("Component #{$comp['component_product_id']} not found.");
                        }
                        if ($cp['status'] !== 'active') {
                            throw new \RuntimeException("Component '{$cp['name']}' is inactive — cannot sell composite line '{$row['display_name']}'.");
                        }
                        if ((float) $cp['quantity_on_hand'] < (float) $comp['quantity_consumed'] - 0.0001) {
                            throw new \RuntimeException(sprintf(
                                "Insufficient '%s' for composite line '%s'. Need %s %s, have %s.",
                                $cp['name'], $row['display_name'],
                                \rtrim(\rtrim((string) $comp['quantity_consumed'], '0'), '.'),
                                $cp['unit'],
                                \rtrim(\rtrim((string) $cp['quantity_on_hand'], '0'), '.')
                            ));
                        }
                    }
                    // Stock-out each component; record COGS snapshot on the component row.
                    foreach ($components as $comp) {
                        $consumed = (float) $comp['quantity_consumed'];
                        $cogsForComp = $this->inventory->applyStockOut(
                            (int) $comp['component_product_id'],
                            $consumed,
                            false,
                            ['source' => 'sale']
                        );
                        $perUnit = $consumed > 0 ? \round($cogsForComp / $consumed, 4) : 0.0;
                        $this->lineComponents->updateCogs((int) $comp['id'], $perUnit, $cogsForComp);
                        $cogsTotal += $cogsForComp;
                    }
                    $cogsPerUnit = $row['quantity'] > 0
                        ? \round($cogsTotal / $row['quantity'], 4)
                        : 0.0;
                } else {
                    // ----- Simple / Irreversible-composite line: stock-out from its own product -----
                    if ($row['product_id'] === null) {
                        throw new \RuntimeException("Line '{$row['display_name']}' has no product — mark it composite or pick a product.");
                    }
                    $stockCheckStmt = Db::pdo()->prepare(
                        'SELECT name, quantity_on_hand FROM products WHERE id = ? FOR UPDATE'
                    );
                    $stockCheckStmt->execute([$row['product_id']]);
                    $stockRow = $stockCheckStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($stockRow === false) {
                        throw new \RuntimeException("Product #{$row['product_id']} not found during finalize.");
                    }
                    if ((float) $stockRow['quantity_on_hand'] < $row['quantity']) {
                        throw new \RuntimeException(sprintf(
                            "Insufficient stock for '%s'. Available: %s, requested: %s.",
                            $stockRow['name'],
                            \rtrim(\rtrim((string) $stockRow['quantity_on_hand'], '0'), '.'),
                            \rtrim(\rtrim((string) $row['quantity'], '0'), '.')
                        ));
                    }
                    $cogsTotal = $this->inventory->applyStockOut($row['product_id'], $row['quantity'], false, ['source' => 'sale']);
                    $cogsPerUnit = $row['quantity'] > 0
                        ? \round($cogsTotal / $row['quantity'], 4)
                        : 0.0;
                }

                // Commission snapshot (per line). CR-6: a per-line override wins over the
                // salesman master rate; NULL (the default) keeps the original behaviour.
                // The resolved rate is what gets snapshotted, so sales returns — which
                // reverse using the sale line's snapshotted rate — stay consistent with
                // whatever was actually charged.
                $commRate = 0.0;
                $commAmount = 0.0;
                if ($row['salesman_id'] !== null) {
                    $override = $row['commission_rate_override'] ?? null;
                    if ($override !== null && $override !== '') {
                        $commRate = (float) $override;
                    } else {
                        if (!isset($commissionRateCache[$row['salesman_id']])) {
                            $sm = $this->salesmen->find($row['salesman_id']);
                            $commissionRateCache[$row['salesman_id']] = $sm
                                ? (float) $sm['commission_rate']
                                : 0.0;
                        }
                        $commRate = $commissionRateCache[$row['salesman_id']];
                    }
                    $commAmount = $this->pricing->money($row['subtotal'] * $commRate / 100);
                }

                $this->lines->updateLineComputed($lineId, [
                    'subtotal'          => $row['subtotal'],
                    'tax_amount'        => $row['tax_amount'],
                    'cgst_amount'       => $row['cgst'],
                    'sgst_amount'       => $row['sgst'],
                    'igst_amount'       => $row['igst'],
                    'line_total'        => $row['line_total'],
                    'cogs_per_unit'     => $cogsPerUnit,
                    'cogs_total'        => $cogsTotal,
                    'commission_rate'   => $commRate,
                    'commission_amount' => $commAmount,
                ]);

                $totalSubtotal   += $row['subtotal'];
                $totalTax        += $row['tax_amount'];
                $totalCgst       += $row['cgst'];
                $totalSgst       += $row['sgst'];
                $totalIgst       += $row['igst'];
                $totalCogs       += $cogsTotal;
                $totalCommission += $commAmount;
            }

            // -------- Step 5: extras (footer-editable, unified across both modes) --------
            // Every extra row carries its own tax_rate. An extra with rate=0 is non-taxable.
            // Extras are independent of the line-level tax in both modes:
            //   - Their tax contributes to total_tax / CGST / SGST / IGST aggregates.
            //   - In Mode A, extras do NOT join the final-discount base, and the invoice-level
            //     tax rate applies only to the lines (not the extras).
            //   - In Mode B, extras are simply added to the line totals.
            $extraRows       = $this->extras->byInvoice($invoiceId);
            $totalExtras     = 0.0;
            $totalExtrasTax  = 0.0;
            foreach ($extraRows as $ex) {
                $amt  = (float) $ex['amount'];
                $rate = $taxTreatment === 'no_tax' ? 0.0 : (float) $ex['tax_rate'];
                $exTax = $this->pricing->money($amt * $rate / 100);
                $totalExtras    += $amt;
                $totalExtrasTax += $exTax;
                $totalTax       += $exTax;
                [$cgst, $sgst, $igst] = $this->pricing->splitTax($exTax, $isIntraState);
                $totalCgst += $cgst;
                $totalSgst += $sgst;
                $totalIgst += $igst;
                $this->extras->updateComputed((int) $ex['id'], $exTax);
            }

            // -------- Step 6: invoice totals + final discount (Mode A only) + round-off --------
            // Final discount applies in Mode A only and reduces the lines' taxable base
            // (NOT the extras). The invoice-level tax rate is re-applied on (lineSubtotal − discount)
            // and overrides the per-line tax allocation done in Step 3 for totals purposes.
            $finalDiscount = 0.0;
            $scaledTax  = $this->pricing->money($totalTax);
            $scaledCgst = $this->pricing->money($totalCgst);
            $scaledSgst = $this->pricing->money($totalSgst);
            $scaledIgst = $this->pricing->money($totalIgst);

            if ($billFormat === 'discount_then_tax') {
                $finalDiscount = \max(0.0, (float) ($invoice['final_discount_amount'] ?? 0));
                if ($finalDiscount > $totalSubtotal) { $finalDiscount = $totalSubtotal; }

                $effRate      = $taxTreatment === 'no_tax' ? 0.0 : (float) $invoice['invoice_level_tax_rate'];
                $taxableLines = $this->pricing->money($totalSubtotal - $finalDiscount);

                if ($taxTreatment === 'inclusive' && $effRate > 0) {
                    // Lines were re-based to net in Step 3; tax = net × rate / 100
                    $linesTax = $this->pricing->money($taxableLines * $effRate / 100);
                } else {
                    $linesTax = $this->pricing->money($taxableLines * $effRate / 100);
                }

                $scaledTax = $this->pricing->money($linesTax + $totalExtrasTax);
                [$lCgst, $lSgst, $lIgst] = $this->pricing->splitTax($linesTax, $isIntraState);
                $extrasSplit = $this->pricing->splitTax($totalExtrasTax, $isIntraState);
                $scaledCgst = $this->pricing->money($lCgst + $extrasSplit[0]);
                $scaledSgst = $this->pricing->money($lSgst + $extrasSplit[1]);
                $scaledIgst = $this->pricing->money($lIgst + $extrasSplit[2]);
            }

            // Grand total (both modes unified):
            //   lineSubtotal − discount + lineTax + extras + extrasTax
            $linesTaxPortion = $this->pricing->money($scaledTax - $totalExtrasTax);
            $preRound = $totalSubtotal - $finalDiscount + $linesTaxPortion + $totalExtras + $totalExtrasTax;

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
                'total_tax'            => $scaledTax,
                'total_cgst'           => $scaledCgst,
                'total_sgst'           => $scaledSgst,
                'total_igst'           => $scaledIgst,
                'total_cogs'           => $this->pricing->money($totalCogs),
                'total_commission'     => $this->pricing->money($totalCommission),
                'total_extras'         => $this->pricing->money($totalExtras),
                'total_extras_tax'     => $this->pricing->money($totalExtrasTax),
                'final_discount_amount'=> $this->pricing->money($finalDiscount),
                'round_off_amount'     => $roundOff,
                'grand_total'          => $grandTotal,
            ]);

            // -------- Step 7: invoice number + bill type --------
            $fy = $this->fiscalYearLabel($invoice['invoice_date']);
            $next = $this->invoices->maxSequenceForFy("GP/{$fy}") + 1;
            $invoiceNumber = \sprintf('GP/%s/%05d', $fy, $next);

            $billType = !empty($invoice['customer_gstin']) ? 'tax_invoice' : 'bill_of_supply';

            // -------- Step 8: mark finalized --------
            $this->invoices->markFinalized($invoiceId, $invoiceNumber, $billType, $userId);
            ActivityLog::log('finalize', 'sales_invoice', $invoiceId, ['label' => $invoiceNumber]);
        });
    }

    /** Compute fiscal-year label like "26-27" for an invoice date and the configured FY start month. */
    private function fiscalYearLabel(string $invoiceDate): string
    {
        $bp = $this->business->get();
        $fyStartMonth = (int) ($bp['financial_year_start_month'] ?? 4);
        $ts = \strtotime($invoiceDate) ?: \time();
        $year  = (int) \date('Y', $ts);
        $month = (int) \date('n', $ts);
        if ($month >= $fyStartMonth) {
            return sprintf('%02d-%02d', $year % 100, ($year + 1) % 100);
        }
        return sprintf('%02d-%02d', ($year - 1) % 100, $year % 100);
    }
}
