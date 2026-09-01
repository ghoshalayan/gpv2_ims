<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;

/**
 * Profit & Loss for a period. Derived from the existing tables.
 *
 * Revenue:
 *   = sales_invoices.subtotal (excludes tax — tax is a pass-through liability, not revenue)
 *     − sales_returns.grand_total (returns reduce revenue)
 *
 * COGS:
 *   = sales_invoice_lines.cogs_total
 *     − purchase_returns.<cost-out>  (TBD — purchase returns reverse landed cost; out of scope for v1)
 *
 * Other Income:
 *   = brand_rebates cash_back amounts
 *   + brand_rebates cost_reduction.income_spillover
 *   + brand_rebates free_stock value (rebate is realised at receipt per AS-2 + design)
 *
 * Operating Expenses:
 *   = operating_expenses.total_with_gst (posted)
 *   + stock_adjustments.total_value (out direction = write-off losses)
 *   + cost-mode commission settlements (cash_amount, posted)
 *
 * Net Profit = Revenue − COGS + Other Income − Other Expenses − Operating Expenses
 *
 * Tax-related amounts (input/output GST) are NOT in P&L — they're balance-sheet items
 * (liabilities/assets, settled via the GST register & GSTR-3B).
 */
class ProfitLossRepository
{
    public function compute(string $from, string $to): array
    {
        $revenue   = $this->revenue($from, $to);
        $cogs      = $this->cogs($from, $to);
        $grossProfit = \round($revenue['net'] - $cogs['net'], 2);

        $otherIncome   = $this->otherIncome($from, $to);
        $otherExpenses = $this->otherExpenses($from, $to);
        $opex          = $this->operatingExpenses($from, $to);

        $netProfit = \round($grossProfit + $otherIncome['total'] - $otherExpenses['total'] - $opex['total'], 2);

        return [
            'from' => $from,
            'to'   => $to,
            'revenue'           => $revenue,
            'cogs'              => $cogs,
            'gross_profit'      => $grossProfit,
            'other_income'      => $otherIncome,
            'other_expenses'    => $otherExpenses,
            'operating_expenses'=> $opex,
            'net_profit'        => $netProfit,
            'gross_margin_pct'  => $revenue['net'] > 0 ? \round($grossProfit / $revenue['net'] * 100, 2) : 0.0,
            'net_margin_pct'    => $revenue['net'] > 0 ? \round($netProfit / $revenue['net'] * 100, 2) : 0.0,
        ];
    }

    private function revenue(string $from, string $to): array
    {
        // Revenue excludes tax (tax is a pass-through liability, not earnings).
        // grand_total − total_tax is the cleanest way to get net-of-tax revenue.
        $grossLessTax = (float) $this->scalar(
            "SELECT COALESCE(SUM(grand_total - total_tax), 0) FROM sales_invoices
             WHERE status = 'finalized' AND invoice_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $taxOnSales = (float) $this->scalar(
            "SELECT COALESCE(SUM(total_tax), 0) FROM sales_invoices
             WHERE status = 'finalized' AND invoice_date BETWEEN ? AND ?",
            [$from, $to]
        );
        // Add the restocking charge back so the revenue reversal reflects only the goods
        // (the charge shrank grand_total; it is booked separately under Other Income, so
        // letting it reduce the return here would double-count it).
        $returns = (float) $this->scalar(
            "SELECT COALESCE(SUM(sr.grand_total - sr.total_tax
                                 + sr.return_charge_amount + sr.return_charge_tax_amount), 0)
             FROM sales_returns sr
             WHERE sr.status = 'finalized' AND sr.return_date BETWEEN ? AND ?",
            [$from, $to]
        );

        $net = \round($grossLessTax - $returns, 2);
        return [
            'sales_gross'        => \round($grossLessTax, 2),
            'sales_returns'      => \round($returns, 2),
            'tax_collected_info' => \round($taxOnSales, 2),  // info-only, not in P&L
            'net'                => $net,
        ];
    }

    private function cogs(string $from, string $to): array
    {
        // Sum of cogs_total per finalized sales invoice line in the period.
        $costOfSales = (float) $this->scalar(
            "SELECT COALESCE(SUM(sil.cogs_total), 0)
             FROM sales_invoice_lines sil
             JOIN sales_invoices si ON si.id = sil.sales_invoice_id
             WHERE si.status = 'finalized' AND si.invoice_date BETWEEN ? AND ?",
            [$from, $to]
        );
        // Sales returns: cost re-enters at the snapshotted cogs_per_unit (D54), so the cost
        // associated with returned units should be subtracted from COGS for the period.
        $returnedCost = (float) $this->scalar(
            "SELECT COALESCE(SUM(sr.total_cogs), 0)
             FROM sales_returns sr
             WHERE sr.status = 'finalized' AND sr.return_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $net = \round($costOfSales - $returnedCost, 2);
        return [
            'cost_of_sales' => \round($costOfSales, 2),
            'return_cost'   => \round($returnedCost, 2),
            'net'           => $net,
        ];
    }

    private function otherIncome(string $from, string $to): array
    {
        $cashBack = (float) $this->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM brand_rebates
             WHERE mode = 'cash_back' AND status = 'posted'
               AND rebate_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $costReductionSpillover = (float) $this->scalar(
            "SELECT COALESCE(SUM(income_spillover), 0) FROM brand_rebates
             WHERE mode = 'cost_reduction' AND status = 'posted'
               AND rebate_date BETWEEN ? AND ?",
            [$from, $to]
        );
        // Free-stock rebate: the value-at-cost basis IS the implicit income recognised at receipt.
        $freeStockIncome = (float) $this->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM brand_rebates
             WHERE mode = 'free_stock' AND status = 'posted'
               AND rebate_date BETWEEN ? AND ?",
            [$from, $to]
        );
        // Reduce-payable rebates: income is recognised AT THE MOMENT the credit is applied
        // to a future bill (when the linked supplier_advance is drawn down by a purchase
        // payment). Lifetime total = rebate.amount; period attribution follows actual usage.
        // Linkage chain: purchase_payments.from_advance_id → supplier_advances.id →
        // brand_rebates.linked_advance_id (mode='reduce_payable', status='posted').
        $reducePayable = (float) $this->scalar(
            "SELECT COALESCE(SUM(pp.from_advance_amount), 0)
             FROM purchase_payments pp
             JOIN supplier_advances sa ON sa.id = pp.from_advance_id
             JOIN brand_rebates br ON br.linked_advance_id = sa.id
             WHERE br.mode = 'reduce_payable' AND br.status = 'posted'
               AND pp.payment_date BETWEEN ? AND ?",
            [$from, $to]
        );

        // Sales-return restocking fee / fine retained by us (ex its own GST) is Other Income.
        $salesReturnCharge = (float) $this->scalar(
            "SELECT COALESCE(SUM(return_charge_amount), 0) FROM sales_returns
             WHERE status = 'finalized' AND return_date BETWEEN ? AND ?",
            [$from, $to]
        );

        $total = \round($cashBack + $costReductionSpillover + $freeStockIncome + $reducePayable + $salesReturnCharge, 2);
        return [
            'brand_rebate_cash_back'         => \round($cashBack, 2),
            'brand_rebate_reduce_payable'    => \round($reducePayable, 2),
            'brand_rebate_cost_spillover'    => \round($costReductionSpillover, 2),
            'brand_rebate_free_stock'        => \round($freeStockIncome, 2),
            'sales_return_charge'            => \round($salesReturnCharge, 2),
            'total'                          => $total,
        ];
    }

    /**
     * Other Expenses — costs that aren't operating expenses or COGS. Currently the
     * supplier's deduction/fine on a purchase return (ex its own GST), which is a real
     * cost to us. Mirrors otherIncome() for symmetry.
     */
    private function otherExpenses(string $from, string $to): array
    {
        $purchaseReturnCharge = (float) $this->scalar(
            "SELECT COALESCE(SUM(return_charge_amount), 0) FROM purchase_returns
             WHERE status = 'finalized' AND return_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $total = \round($purchaseReturnCharge, 2);
        return [
            'purchase_return_charge' => \round($purchaseReturnCharge, 2),
            'total'                  => $total,
        ];
    }

    private function operatingExpenses(string $from, string $to): array
    {
        $opEx = (float) $this->scalar(
            "SELECT COALESCE(SUM(total_with_gst), 0) FROM operating_expenses
             WHERE status = 'posted' AND expense_date BETWEEN ? AND ?",
            [$from, $to]
        );
        // Stock write-offs (out direction): damage/expiry/theft are P&L losses.
        $stockLoss = (float) $this->scalar(
            "SELECT COALESCE(SUM(total_value), 0) FROM stock_adjustments
             WHERE direction = 'out' AND status = 'posted'
               AND adjustment_date BETWEEN ? AND ?",
            [$from, $to]
        );
        // Found-stock (in direction): negative loss = recovery (subtract).
        $stockRecovery = (float) $this->scalar(
            "SELECT COALESCE(SUM(total_value), 0) FROM stock_adjustments
             WHERE direction = 'in' AND status = 'posted'
               AND adjustment_date BETWEEN ? AND ?",
            [$from, $to]
        );
        // Salesman commission paid in cash → expense.
        $commissionCash = (float) $this->scalar(
            "SELECT COALESCE(SUM(cash_amount), 0) FROM salesman_commission_settlements
             WHERE payout_mode = 'cash' AND status = 'posted'
               AND settlement_date BETWEEN ? AND ?",
            [$from, $to]
        );
        // Salesman commission paid in goods → expense at the goods' cost basis.
        // Without this, the inventory leaves the books (stock-out via InventoryService)
        // but no offsetting expense is recorded — P&L would silently overstate profit.
        $commissionGoods = (float) $this->scalar(
            "SELECT COALESCE(SUM(goods_total_value), 0) FROM salesman_commission_settlements
             WHERE payout_mode = 'goods' AND status = 'posted'
               AND settlement_date BETWEEN ? AND ?",
            [$from, $to]
        );

        $total = \round(
            $opEx + $stockLoss - $stockRecovery + $commissionCash + $commissionGoods,
            2
        );
        return [
            'operating_expenses' => \round($opEx, 2),
            'stock_writeoff'     => \round($stockLoss, 2),
            'stock_recovery'     => \round($stockRecovery, 2),
            'commission_cash'    => \round($commissionCash, 2),
            'commission_goods'   => \round($commissionGoods, 2),
            'total'              => $total,
        ];
    }

    private function scalar(string $sql, array $params): float
    {
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}
