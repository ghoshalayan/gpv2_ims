<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Pure per-line and per-invoice math. No DB, no side effects. Safe to unit-test.
 *
 * Line subtotal: qty × unit_price × (1 + addsub_percent/100)   — per D1
 * Line tax:      subtotal × tax_rate / 100                      — per_line_tax mode only
 * Line total:    subtotal + tax_amount
 *
 * In discount_then_tax mode, per-line tax is always 0. Invoice-level tax is computed
 * on the invoice subtotal (sum of line subtotals) and allocated back to lines by value
 * when landed cost is computed.
 *
 * CGST/SGST/IGST split (per D52): intra-state = CGST+SGST (half each); inter-state = IGST.
 */
class PricingService
{
    /**
     * Compute a single line's amounts, honouring bill format AND tax treatment.
     *
     * $taxTreatment = 'exclusive' | 'inclusive' | 'no_tax'
     *   - exclusive: the entered unit price does NOT include tax; tax is added on top.
     *   - inclusive: the entered unit price INCLUDES tax; we back it out for accounting.
     *   - no_tax:    no GST on this invoice regardless of the rate entered.
     *
     * In discount_then_tax mode, tax is computed at invoice level (not per line);
     * line-level tax is zero and the service allocates invoice tax back after computeLine.
     * For inclusive + discount_then_tax, line subtotals are gross here and get net-adjusted
     * by the service when it allocates back.
     *
     * @return array{subtotal:float, tax_amount:float, cgst:float, sgst:float, igst:float, line_total:float}
     */
    public function computeLine(
        float  $quantity,
        float  $unitPrice,
        float  $addsubPercent,
        float  $taxRate,
        string $billFormat,
        string $taxTreatment,
        bool   $isIntraState
    ): array {
        $gross = $quantity * $unitPrice * (1 + $addsubPercent / 100);

        // no_tax overrides everything
        if ($taxTreatment === 'no_tax') {
            $subtotal = $this->money($gross);
            return [
                'subtotal'   => $subtotal,
                'tax_amount' => 0.0,
                'cgst'       => 0.0,
                'sgst'       => 0.0,
                'igst'       => 0.0,
                'line_total' => $subtotal,
            ];
        }

        // For discount_then_tax, the line carries no tax — invoice applies it later.
        if ($billFormat === 'discount_then_tax') {
            $subtotal = $this->money($gross);
            return [
                'subtotal'   => $subtotal,
                'tax_amount' => 0.0,
                'cgst'       => 0.0,
                'sgst'       => 0.0,
                'igst'       => 0.0,
                'line_total' => $subtotal,
            ];
        }

        // per_line_tax mode
        if ($taxTreatment === 'inclusive') {
            // Entered price includes tax. Back out to find net subtotal.
            $rateFactor = 1 + $taxRate / 100;
            $subtotal   = $rateFactor > 0 ? $this->money($gross / $rateFactor) : $this->money($gross);
            $taxAmount  = $this->money($gross - $subtotal);
            $lineTotal  = $this->money($gross);
        } else {
            // exclusive — tax added on top
            $subtotal   = $this->money($gross);
            $taxAmount  = $this->money($subtotal * $taxRate / 100);
            $lineTotal  = $this->money($subtotal + $taxAmount);
        }

        [$cgst, $sgst, $igst] = $this->splitTax($taxAmount, $isIntraState);

        return [
            'subtotal'   => $subtotal,
            'tax_amount' => $taxAmount,
            'cgst'       => $cgst,
            'sgst'       => $sgst,
            'igst'       => $igst,
            'line_total' => $lineTotal,
        ];
    }

    /** Split a tax amount into CGST/SGST/IGST based on intra/inter-state. */
    public function splitTax(float $taxAmount, bool $isIntraState): array
    {
        if ($taxAmount <= 0) {
            return [0.0, 0.0, 0.0];
        }
        if ($isIntraState) {
            $half = $this->money($taxAmount / 2);
            // Absorb any rounding drift into CGST so CGST+SGST == taxAmount exactly
            return [$this->money($taxAmount - $half), $half, 0.0];
        }
        return [0.0, 0.0, $taxAmount];
    }

    /**
     * Compute expense-level tax and total. Expense amount is tax-exclusive.
     * @return array{tax_amount:float, total_with_tax:float}
     */
    public function computeExpense(float $amount, float $taxRate): array
    {
        $tax = $this->money($amount * $taxRate / 100);
        return [
            'tax_amount'     => $tax,
            'total_with_tax' => $this->money($amount + $tax),
        ];
    }

    /**
     * Allocate an expense amount across lines.
     *
     * @param array<int, array{id:int, quantity:float, subtotal:float}> $lines
     * @return array<int, float>  keyed by line id → allocated share (sums to $amountToAllocate)
     */
    public function allocateExpense(float $amountToAllocate, string $method, array $lines): array
    {
        $result = [];
        if ($lines === [] || $amountToAllocate <= 0) {
            foreach ($lines as $l) { $result[$l['id']] = 0.0; }
            return $result;
        }

        if ($method === 'equal_split') {
            $share = $amountToAllocate / \count($lines);
            foreach ($lines as $l) { $result[$l['id']] = $this->money($share); }
            return $this->fixRoundingDrift($result, $amountToAllocate);
        }

        if ($method === 'by_quantity') {
            $totalQty = \array_sum(\array_column($lines, 'quantity'));
            if ($totalQty <= 0) {
                foreach ($lines as $l) { $result[$l['id']] = 0.0; }
                return $result;
            }
            foreach ($lines as $l) {
                $result[$l['id']] = $this->money($amountToAllocate * ($l['quantity'] / $totalQty));
            }
            return $this->fixRoundingDrift($result, $amountToAllocate);
        }

        // Default: by_value (by line subtotal)
        $totalValue = \array_sum(\array_column($lines, 'subtotal'));
        if ($totalValue <= 0) {
            foreach ($lines as $l) { $result[$l['id']] = 0.0; }
            return $result;
        }
        foreach ($lines as $l) {
            $result[$l['id']] = $this->money($amountToAllocate * ($l['subtotal'] / $totalValue));
        }
        return $this->fixRoundingDrift($result, $amountToAllocate);
    }

    /**
     * Invoice-level tax in discount_then_tax mode. Distribute back to lines by value.
     *
     * @param array<int, array{id:int, subtotal:float}> $lines
     * @return array<int, float>
     */
    public function allocateInvoiceLevelTax(float $invoiceLevelTax, array $lines): array
    {
        return $this->allocateExpense($invoiceLevelTax, 'by_value', array_map(
            fn($l) => ['id' => $l['id'], 'quantity' => 0, 'subtotal' => $l['subtotal']],
            $lines
        ));
    }

    public function roundOff(float $grandTotal): array
    {
        $rounded = \round($grandTotal);
        return [
            'rounded_total' => $rounded,
            'round_off'     => $this->money($rounded - $grandTotal),
        ];
    }

    /** Round to 2 decimals the way money should be rounded. */
    public function money(float $amount): float
    {
        return \round($amount, 2, PHP_ROUND_HALF_UP);
    }

    /** Adjust the largest share to absorb rounding drift so the set sums to $expectedTotal. */
    private function fixRoundingDrift(array $shares, float $expectedTotal): array
    {
        $actual = \array_sum($shares);
        $diff   = $this->money($expectedTotal - $actual);
        if ($diff === 0.0 || $shares === []) {
            return $shares;
        }
        $largestKey = \array_keys($shares, \max($shares))[0];
        $shares[$largestKey] = $this->money($shares[$largestKey] + $diff);
        return $shares;
    }
}
