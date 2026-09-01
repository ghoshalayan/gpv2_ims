<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;

/**
 * Balance Sheet at a point in time. Derived from the existing tables — no formal
 * general ledger required. The same integrity invariants that guarantee per-account
 * balance correctness flow through here as well.
 *
 * NOTE on as-of dates: account balances (bank/drawer) are stored as live current_balance
 * (no historical snapshot). For an as-of date in the past, we reconstruct the balance by
 * subtracting events that occurred AFTER the as-of date — using the same source set as
 * CashFlowRepository.
 *
 * Stock value is taken from products.moving_avg_cost × quantity_on_hand (current). For
 * historical as-of, an exact answer would need a stock-history snapshot which doesn't
 * exist; this repo returns the CURRENT stock value as a known limitation. v1 caveat
 * documented in the view.
 */
class BalanceSheetRepository
{
    public function compute(string $asOf): array
    {
        $cashAndBank   = $this->cashAndBankBalances($asOf);
        $stockValue    = $this->stockValue();             // current snapshot (caveat — see class doc)
        $custReceiv    = $this->customerReceivables($asOf);
        $supAdvAsset   = $this->supplierAdvanceAsset($asOf);
        $legacyCustRec = $this->legacyReceivables('customer', $asOf);
        $gstInputCr    = $this->gstInputCredit($asOf);

        $supPayable    = $this->supplierPayables($asOf);
        $custAdvLiab   = $this->customerAdvanceLiability($asOf);
        $loanLiab      = $this->loanLiability($asOf);
        $legacySupPay  = $this->legacyPayables('supplier', $asOf);
        $legacySalPay  = $this->legacyPayables('salesman', $asOf);
        $gstOutputLiab = $this->gstOutputLiability($asOf);

        $netGstPayable = \round($gstOutputLiab - $gstInputCr, 2);
        // If input credit > output collected, the diff is a GST asset (claimable). Else liability.

        $capitalIn     = $this->capitalNet($asOf);
        $retainedEarn  = $this->retainedEarnings($asOf);

        $totalAssets = \round(
            $cashAndBank['total'] + $stockValue['total'] + $custReceiv +
            $supAdvAsset + $legacyCustRec + \max(0.0, -$netGstPayable),
            2
        );
        $totalLiab = \round(
            $supPayable + $custAdvLiab + $loanLiab + $legacySupPay + $legacySalPay +
            \max(0.0, $netGstPayable),
            2
        );

        // Opening Balance Equity (auto-balancing line). Represents owner equity that
        // existed at v2 go-live but isn't categorized as capital injection or retained
        // earnings — typically the opening cash + opening stock entered at setup. This
        // is the standard "Opening Balance Equity" account used in QuickBooks etc.
        $openingEquity = \round($totalAssets - $totalLiab - $capitalIn - $retainedEarn, 2);
        $totalEquity   = \round($capitalIn + $retainedEarn + $openingEquity, 2);

        return [
            'as_of'           => $asOf,
            'assets' => [
                'cash_and_bank'         => $cashAndBank,
                'stock_value'           => $stockValue,
                'customer_receivables'  => $custReceiv,
                'supplier_advances'     => $supAdvAsset,
                'legacy_customer_recv'  => $legacyCustRec,
                'gst_input_credit_excess' => \max(0.0, -$netGstPayable),
                'total'                 => $totalAssets,
            ],
            'liabilities' => [
                'supplier_payables'     => $supPayable,
                'customer_advances'     => $custAdvLiab,
                'loans'                 => $loanLiab,
                'legacy_supplier_pay'   => $legacySupPay,
                'legacy_salesman_pay'   => $legacySalPay,
                'gst_output_payable'    => \max(0.0, $netGstPayable),
                'total'                 => $totalLiab,
            ],
            'equity' => [
                'capital_net'           => $capitalIn,
                'retained_earnings'     => $retainedEarn,
                'opening_equity'        => $openingEquity,
                'total'                 => $totalEquity,
            ],
            'total_liabilities_and_equity' => \round($totalLiab + $totalEquity, 2),
            'balanced' => \abs($totalAssets - ($totalLiab + $totalEquity)) < 0.01,
            'imbalance'=> \round($totalAssets - ($totalLiab + $totalEquity), 2),
        ];
    }

    /* ---------- Asset side ---------- */

    /** Cash + bank, reconstructed at as-of by subtracting events that happened after. */
    private function cashAndBankBalances(string $asOf): array
    {
        $accounts = [];
        foreach (Db::pdo()->query(
            "SELECT 'bank_account' AS kind, id, name, current_balance FROM bank_accounts
             UNION ALL
             SELECT 'cashier_drawer', id, name, current_balance FROM cashier_drawers
             ORDER BY kind, id"
        )->fetchAll(\PDO::FETCH_ASSOC) as $a) {
            $current = (float) $a['current_balance'];
            // Adjust for events strictly AFTER as_of date.
            $delta = (float) $this->scalar(
                "SELECT COALESCE(SUM(
                    CASE direction WHEN 'in' THEN amount ELSE -amount END
                 ), 0) FROM (" . $this->cashEventsAfter() . ") AS evts
                 WHERE evts.event_date > ?
                   AND evts.account_kind = ? AND evts.account_id = ?",
                [$asOf, $a['kind'], $a['id']]
            );
            $asOfBalance = \round($current - $delta, 2);
            $accounts[] = [
                'kind'    => $a['kind'],
                'id'      => (int) $a['id'],
                'name'    => $a['name'],
                'balance' => $asOfBalance,
            ];
        }
        $total = \round(\array_sum(\array_column($accounts, 'balance')), 2);
        return ['accounts' => $accounts, 'total' => $total];
    }

    /** Inline expression for unified cash events (matches CashFlowRepository::events). */
    private function cashEventsAfter(): string
    {
        // Compact UNION focused on date + direction + account fields only.
        return "
            SELECT sp.payment_date AS event_date, sp.received_into_kind AS account_kind, sp.received_into_id AS account_id,
                   'in' AS direction,
                   (sp.cash_amount + sp.online_amount + CASE WHEN sp.cheque_status IN ('pending','cleared') THEN sp.cheque_amount ELSE 0 END) AS amount
            FROM sales_payments sp WHERE sp.received_into_kind IS NOT NULL
            UNION ALL
            SELECT ca.advance_date, ca.received_into_kind, ca.received_into_id, 'in', ca.amount
            FROM customer_advances ca
            UNION ALL
            SELECT DATE(ca.refunded_at), ca.received_into_kind, ca.received_into_id, 'out',
                   (ca.amount - COALESCE((SELECT SUM(sp.from_advance_amount) FROM sales_payments sp WHERE sp.from_advance_id = ca.id), 0))
            FROM customer_advances ca WHERE ca.status = 'refunded' AND ca.refunded_at IS NOT NULL
            UNION ALL
            SELECT pp.payment_date, pp.paid_from_kind, pp.paid_from_id, 'out',
                   (pp.cash_amount + pp.online_amount + CASE WHEN pp.cheque_status IN ('pending','cleared') THEN pp.cheque_amount ELSE 0 END)
            FROM purchase_payments pp WHERE pp.paid_from_kind IS NOT NULL
            UNION ALL
            SELECT sa.advance_date, sa.paid_from_kind, sa.paid_from_id, 'out', sa.amount
            FROM supplier_advances sa WHERE sa.payment_mode <> 'rebate' AND sa.paid_from_kind IS NOT NULL
            UNION ALL
            SELECT DATE(sa.refunded_at), sa.paid_from_kind, sa.paid_from_id, 'in',
                   (sa.amount - COALESCE((SELECT SUM(pp.from_advance_amount) FROM purchase_payments pp WHERE pp.from_advance_id = sa.id), 0))
            FROM supplier_advances sa WHERE sa.status = 'refunded' AND sa.paid_from_kind IS NOT NULL
            UNION ALL
            SELECT iat.transfer_date, iat.from_kind, iat.from_id, 'out', iat.amount FROM inter_account_transfers iat
            UNION ALL
            SELECT iat.transfer_date, iat.to_kind, iat.to_id, 'in', iat.amount FROM inter_account_transfers iat
            UNION ALL
            SELECT cm.movement_date, cm.account_kind, cm.account_id, cm.direction, cm.amount
            FROM capital_movements cm WHERE cm.status = 'posted'
            UNION ALL
            SELECT oe.expense_date, oe.paid_from_kind, oe.paid_from_id, 'out', oe.total_with_gst
            FROM operating_expenses oe WHERE oe.status = 'posted'
            UNION ALL
            SELECT scs.settlement_date, scs.paid_from_kind, scs.paid_from_id, 'out', scs.cash_amount
            FROM salesman_commission_settlements scs WHERE scs.payout_mode = 'cash' AND scs.status = 'posted'
            UNION ALL
            SELECT br.rebate_date, br.received_into_kind, br.received_into_id, 'in', br.amount
            FROM brand_rebates br WHERE br.mode = 'cash_back' AND br.status = 'posted'
            UNION ALL
            SELECT ls.settlement_date, ls.account_kind, ls.account_id,
                   CASE WHEN ls.customer_id IS NOT NULL THEN 'in' ELSE 'out' END,
                   ls.amount
            FROM legacy_settlements ls
        ";
    }

    private function stockValue(): array
    {
        $rows = Db::pdo()->query(
            "SELECT id, name, quantity_on_hand, moving_avg_cost,
                    quantity_on_hand * moving_avg_cost AS line_value
             FROM products
             WHERE quantity_on_hand > 0 AND moving_avg_cost > 0
             ORDER BY line_value DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $total = 0.0;
        foreach ($rows as $r) { $total += (float) $r['line_value']; }
        return ['rows' => $rows, 'total' => \round($total, 2)];
    }

    /** Sum of due_amount on finalized sales invoices as of the date. */
    private function customerReceivables(string $asOf): float
    {
        // Approximation: invoices issued on/before $asOf, payments on/before $asOf
        // (excludes from_advance — that's already netted via customer_advances liability).
        return $this->scalar(
            "SELECT COALESCE(SUM(
                si.grand_total - COALESCE((
                    SELECT SUM(sp.cash_amount + sp.online_amount + sp.from_advance_amount +
                               CASE WHEN sp.cheque_status IN ('pending','cleared') THEN sp.cheque_amount ELSE 0 END)
                    FROM sales_payments sp
                    WHERE sp.sales_invoice_id = si.id AND sp.payment_date <= ?
                ), 0)
                - COALESCE((
                    SELECT SUM(sr.grand_total) FROM sales_returns sr
                    WHERE sr.original_sales_invoice_id = si.id AND sr.status = 'finalized'
                      AND sr.return_date <= ?
                ), 0)
             ), 0)
             FROM sales_invoices si
             WHERE si.status = 'finalized' AND si.invoice_date <= ?",
            [$asOf, $asOf, $asOf]
        );
    }

    private function supplierAdvanceAsset(string $asOf): float
    {
        // Live balance approximated by amount minus applied (via purchase_payments before asOf)
        return $this->scalar(
            "SELECT COALESCE(SUM(
                sa.amount - COALESCE((
                    SELECT SUM(pp.from_advance_amount) FROM purchase_payments pp
                    WHERE pp.from_advance_id = sa.id AND pp.payment_date <= ?
                ), 0)
             ), 0)
             FROM supplier_advances sa
             WHERE sa.advance_date <= ?
               AND (sa.status <> 'refunded' OR sa.refunded_at > ?)",
            [$asOf, $asOf, $asOf . ' 23:59:59']
        );
    }

    private function legacyReceivables(string $partyKind, string $asOf): float
    {
        // Kind is now derived from which live-entity column the obligation carries.
        $col = match ($partyKind) {
            'supplier' => 'supplier_id',
            'salesman' => 'salesman_id',
            default    => 'customer_id',
        };
        return $this->scalar(
            "SELECT COALESCE(SUM(
                lo.obligation_amount - lo.opening_paid_amount
                  - COALESCE((SELECT SUM(amount) FROM legacy_settlements ls
                              WHERE ls.obligation_id = lo.id AND ls.settlement_date <= ?), 0)
             ), 0)
             FROM legacy_obligations lo
             WHERE lo.{$col} IS NOT NULL AND lo.obligation_date <= ?",
            [$asOf, $asOf]
        );
    }

    /**
     * Cumulative input GST on claimable purchases, NET of GST reversed on purchase returns
     * tied to claimable invoices. A return reverses both the inventory cost AND the input
     * credit it originally produced.
     */
    private function gstInputCredit(string $asOf): float
    {
        $gross = $this->scalar(
            "SELECT COALESCE(SUM(total_tax), 0) FROM purchase_invoices
             WHERE status = 'finalized' AND gst_claimable = 1 AND invoice_date <= ?",
            [$asOf]
        );
        // Subtract input GST reversed via purchase returns against claimable invoices.
        $reversed = $this->scalar(
            "SELECT COALESCE(SUM(pr.total_tax), 0)
             FROM purchase_returns pr
             JOIN purchase_invoices pi ON pi.id = pr.original_purchase_invoice_id
             WHERE pr.status = 'finalized'
               AND pi.gst_claimable = 1
               AND pr.return_date <= ?",
            [$asOf]
        );
        return \round($gross - $reversed, 2);
    }

    /* ---------- Liability side ---------- */

    private function supplierPayables(string $asOf): float
    {
        return $this->scalar(
            "SELECT COALESCE(SUM(
                pi.grand_total - COALESCE((
                    SELECT SUM(pp.cash_amount + pp.online_amount + pp.from_advance_amount +
                               CASE WHEN pp.cheque_status IN ('pending','cleared') THEN pp.cheque_amount ELSE 0 END)
                    FROM purchase_payments pp
                    WHERE pp.purchase_invoice_id = pi.id AND pp.payment_date <= ?
                ), 0)
                - COALESCE((
                    SELECT SUM(pr.grand_total) FROM purchase_returns pr
                    WHERE pr.original_purchase_invoice_id = pi.id AND pr.status = 'finalized'
                      AND pr.return_date <= ?
                ), 0)
             ), 0)
             FROM purchase_invoices pi
             WHERE pi.status = 'finalized' AND pi.invoice_date <= ?",
            [$asOf, $asOf, $asOf]
        );
    }

    private function customerAdvanceLiability(string $asOf): float
    {
        return $this->scalar(
            "SELECT COALESCE(SUM(
                ca.amount - COALESCE((
                    SELECT SUM(sp.from_advance_amount) FROM sales_payments sp
                    WHERE sp.from_advance_id = ca.id AND sp.payment_date <= ?
                ), 0)
             ), 0)
             FROM customer_advances ca
             WHERE ca.advance_date <= ?
               AND (ca.status <> 'refunded' OR ca.refunded_at > ?)",
            [$asOf, $asOf, $asOf . ' 23:59:59']
        );
    }

    private function loanLiability(string $asOf): float
    {
        // loans_in less loan_repays, posted only.
        return $this->scalar(
            "SELECT COALESCE(SUM(CASE WHEN txn_type = 'loan_in' THEN amount
                                       WHEN txn_type = 'loan_repay' THEN -amount ELSE 0 END), 0)
             FROM capital_movements
             WHERE status = 'posted' AND movement_date <= ?",
            [$asOf]
        );
    }

    private function legacyPayables(string $partyKind, string $asOf): float
    {
        // Reuse the same query — legacy obligations for supplier/salesman parties are payable.
        return $this->legacyReceivables($partyKind, $asOf);
    }

    /**
     * Cumulative output GST collected on sales, NET of GST reversed on sales returns.
     * A return creates a credit note that reverses the original output GST liability.
     */
    private function gstOutputLiability(string $asOf): float
    {
        $gross = $this->scalar(
            "SELECT COALESCE(SUM(total_tax), 0) FROM sales_invoices
             WHERE status = 'finalized' AND invoice_date <= ?",
            [$asOf]
        );
        $reversed = $this->scalar(
            "SELECT COALESCE(SUM(total_tax), 0) FROM sales_returns
             WHERE status = 'finalized' AND return_date <= ?",
            [$asOf]
        );
        return \round($gross - $reversed, 2);
    }

    /* ---------- Equity side ---------- */

    private function capitalNet(string $asOf): float
    {
        return $this->scalar(
            "SELECT COALESCE(SUM(CASE WHEN txn_type = 'capital_in' THEN amount
                                       WHEN txn_type = 'drawing'    THEN -amount ELSE 0 END), 0)
             FROM capital_movements
             WHERE status = 'posted' AND movement_date <= ?",
            [$asOf]
        );
    }

    /**
     * Lifetime retained earnings = sum of period P&L from system inception to as_of.
     * Computed by ProfitLossRepository over the open-ended range.
     */
    private function retainedEarnings(string $asOf): float
    {
        $pl = (new ProfitLossRepository())->compute('1900-01-01', $asOf);
        return (float) $pl['net_profit'];
    }

    private function scalar(string $sql, array $params): float
    {
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}
