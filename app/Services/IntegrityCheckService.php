<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;

/**
 * Read-only sanity-check report. Verifies cross-balance invariants that should hold
 * across the whole system. If any check returns rows, there's a discrepancy that
 * needs investigation — likely caused by a partial transaction, an out-of-band SQL
 * edit, or a bug in one of the services.
 *
 * Each check returns:
 *   ['title' => string, 'description' => string, 'mismatches' => array, 'total_checked' => int]
 *
 * Mismatch row shape (varies per check, but always includes 'description' + 'difference'):
 *   ['entity_id', 'entity_label', 'expected', 'actual', 'difference', 'description']
 *
 * Tolerance: 0.01 (₹1 paisa) — anything inside that is treated as floating-point noise.
 */
class IntegrityCheckService
{
    private const TOLERANCE = 0.01;

    public function runAll(): array
    {
        return [
            $this->checkSalesInvoiceTotals(),
            $this->checkPurchaseInvoiceTotals(),
            $this->checkInvoiceDueVsReturns(
                'Sales invoices: due_amount vs returns',
                'sales_invoices', 'sales_returns', 'original_sales_invoice_id'
            ),
            $this->checkInvoiceDueVsReturns(
                'Purchase invoices: due_amount vs returns',
                'purchase_invoices', 'purchase_returns', 'original_purchase_invoice_id'
            ),
            $this->checkCustomerAdvanceBalances(),
            $this->checkSupplierAdvanceBalances(),
            $this->checkBrandRebateCostReductions(),
            $this->checkStockAdjustmentTotals(),
            $this->checkStockLedger(),
            $this->checkLegacyObligationTotals(),
            $this->checkAccountBalances('bank_account', 'bank_accounts'),
            $this->checkAccountBalances('cashier_drawer', 'cashier_drawers'),
        ];
    }

    /** sales_invoices.paid_amount should equal effectivePaidForInvoice for every finalized invoice. */
    private function checkSalesInvoiceTotals(): array
    {
        $rows = Db::pdo()->query(
            "SELECT si.id, si.invoice_number, si.grand_total, si.paid_amount, si.due_amount,
                    COALESCE((
                        SELECT SUM(sp.cash_amount + sp.online_amount + sp.from_advance_amount +
                                   CASE WHEN sp.cheque_status IN ('pending','cleared') THEN sp.cheque_amount ELSE 0 END)
                        FROM sales_payments sp
                        WHERE sp.sales_invoice_id = si.id
                    ), 0) AS expected_paid
             FROM sales_invoices si
             WHERE si.status = 'finalized'"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $diff = \round((float) $r['paid_amount'] - (float) $r['expected_paid'], 2);
            if (\abs($diff) <= self::TOLERANCE) { continue; }
            $mismatches[] = [
                'entity_id'    => (int) $r['id'],
                'entity_label' => $r['invoice_number'],
                'expected'     => (float) $r['expected_paid'],
                'actual'       => (float) $r['paid_amount'],
                'difference'   => $diff,
                'description'  => "stored paid_amount drifted from sum of payments",
            ];
        }
        return [
            'title'         => 'Sales invoices: paid_amount vs sum(payments)',
            'description'   => 'Each finalized invoice\'s paid_amount should equal the sum of cash + online + counted-cheque + from_advance across its payments.',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    /** Purchase invoice paid_amount vs sum of payments (cash + online + counted-cheque + from_advance). */
    private function checkPurchaseInvoiceTotals(): array
    {
        $rows = Db::pdo()->query(
            "SELECT pi.id, pi.invoice_number, pi.grand_total, pi.paid_amount, pi.due_amount,
                    COALESCE((
                        SELECT SUM(pp.cash_amount + pp.online_amount + pp.from_advance_amount +
                                   CASE WHEN pp.cheque_status IN ('pending','cleared') THEN pp.cheque_amount ELSE 0 END)
                        FROM purchase_payments pp
                        WHERE pp.purchase_invoice_id = pi.id
                    ), 0) AS expected_paid
             FROM purchase_invoices pi
             WHERE pi.status = 'finalized'"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $diff = \round((float) $r['paid_amount'] - (float) $r['expected_paid'], 2);
            if (\abs($diff) <= self::TOLERANCE) { continue; }
            $mismatches[] = [
                'entity_id'    => (int) $r['id'],
                'entity_label' => $r['invoice_number'],
                'expected'     => (float) $r['expected_paid'],
                'actual'       => (float) $r['paid_amount'],
                'difference'   => $diff,
                'description'  => 'stored paid_amount drifted from sum of payments',
            ];
        }
        return [
            'title'         => 'Purchase invoices: paid_amount vs sum(payments)',
            'description'   => 'Each finalized purchase invoice\'s paid_amount should equal the sum of cash + online + counted-cheque + from_advance across its payments.',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    /**
     * Invoice due_amount should equal max(0, grand_total − paid_amount − Σ finalized return grand_totals).
     *
     * Surfaces the known drift where recording or deleting a payment AFTER a return runs a
     * return-UNAWARE recompute (due = grand_total − paid), silently discarding the return's
     * due reduction. Read-only diagnostic — mutates nothing. Table/column names are fixed
     * constants supplied by runAll(), never user input.
     */
    private function checkInvoiceDueVsReturns(
        string $label, string $invTable, string $returnTable, string $returnFk
    ): array {
        $rows = Db::pdo()->query(
            "SELECT inv.id, inv.invoice_number, inv.grand_total, inv.paid_amount, inv.due_amount,
                    COALESCE((
                        SELECT SUM(r.grand_total) FROM {$returnTable} r
                        WHERE r.{$returnFk} = inv.id AND r.status = 'finalized'
                    ), 0) AS returned_total
             FROM {$invTable} inv
             WHERE inv.status = 'finalized'"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $expected = \round(\max(0.0,
                (float) $r['grand_total'] - (float) $r['paid_amount'] - (float) $r['returned_total']
            ), 2);
            $actual = \round((float) $r['due_amount'], 2);
            $diff   = \round($actual - $expected, 2);
            if (\abs($diff) <= self::TOLERANCE) { continue; }
            $mismatches[] = [
                'entity_id'    => (int) $r['id'],
                'entity_label' => $r['invoice_number'],
                'expected'     => $expected,
                'actual'       => $actual,
                'difference'   => $diff,
                'description'  => 'due_amount not net of finalized returns (likely a payment recorded/deleted after a return)',
            ];
        }
        return [
            'title'         => $label,
            'description'   => 'due_amount should equal max(0, grand_total − paid_amount − sum of finalized return grand_totals). A mismatch means a return-unaware payment recompute overwrote a return\'s due reduction.',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    /**
     * Customer advance: amount must equal balance + sum_of_applications + (refund_amount when refunded).
     * For an active/exhausted advance: amount == balance + applied
     * For a refunded advance:           amount == applied + refund_amount  (balance forced to 0 at refund)
     * In both, amount == balance + applied + (refunded_balance) where refunded_balance = (amount - applied) when status='refunded' else 0.
     */
    private function checkCustomerAdvanceBalances(): array
    {
        $rows = Db::pdo()->query(
            "SELECT ca.id, ca.advance_number, ca.amount, ca.balance, ca.status,
                    COALESCE((
                        SELECT SUM(sp.from_advance_amount)
                        FROM sales_payments sp
                        WHERE sp.from_advance_id = ca.id
                    ), 0) AS applied
             FROM customer_advances ca"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $amount  = (float) $r['amount'];
            $balance = (float) $r['balance'];
            $applied = (float) $r['applied'];
            $status  = (string) $r['status'];

            // Expected balance (what should currently sit in the row).
            $expectedBalance = match ($status) {
                'refunded'  => 0.0,
                default     => $amount - $applied,
            };
            $balanceDiff = \round($balance - $expectedBalance, 2);

            // Sanity: applied <= amount (refunded amount = amount - applied, must be >= 0).
            $appliedTooHigh = $applied > $amount + self::TOLERANCE;

            if (\abs($balanceDiff) > self::TOLERANCE || $appliedTooHigh) {
                $mismatches[] = [
                    'entity_id'    => (int) $r['id'],
                    'entity_label' => $r['advance_number'],
                    'expected'     => $expectedBalance,
                    'actual'       => $balance,
                    'difference'   => $balanceDiff,
                    'description'  => $appliedTooHigh
                        ? "applied (₹" . \number_format($applied, 2) . ') exceeds original amount — IMPOSSIBLE STATE'
                        : "balance drifted: amount ₹" . \number_format($amount, 2) .
                          ' − applied ₹' . \number_format($applied, 2) .
                          ' should be ₹' . \number_format($expectedBalance, 2) .
                          ", got ₹" . \number_format($balance, 2) . " (status={$status})",
                ];
            }
        }
        return [
            'title'         => 'Customer advances: balance vs amount − applied',
            'description'   => 'For each advance: balance should equal amount minus the sum of from_advance_amount applied via sales_payments. Refunded advances should have balance = 0.',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    /**
     * Supplier advance: same shape as customer advance, but with purchase_payments.from_advance_amount.
     * For active/exhausted: amount == balance + applied
     * For refunded:         balance forced to 0; amount − applied is the refunded amount
     */
    private function checkSupplierAdvanceBalances(): array
    {
        $rows = Db::pdo()->query(
            "SELECT sa.id, sa.advance_number, sa.amount, sa.balance, sa.status,
                    COALESCE((
                        SELECT SUM(pp.from_advance_amount)
                        FROM purchase_payments pp
                        WHERE pp.from_advance_id = sa.id
                    ), 0) AS applied
             FROM supplier_advances sa"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $amount  = (float) $r['amount'];
            $balance = (float) $r['balance'];
            $applied = (float) $r['applied'];
            $status  = (string) $r['status'];

            $expectedBalance = match ($status) {
                'refunded'  => 0.0,
                default     => $amount - $applied,
            };
            $balanceDiff = \round($balance - $expectedBalance, 2);
            $appliedTooHigh = $applied > $amount + self::TOLERANCE;

            if (\abs($balanceDiff) > self::TOLERANCE || $appliedTooHigh) {
                $mismatches[] = [
                    'entity_id'    => (int) $r['id'],
                    'entity_label' => $r['advance_number'],
                    'expected'     => $expectedBalance,
                    'actual'       => $balance,
                    'difference'   => $balanceDiff,
                    'description'  => $appliedTooHigh
                        ? "applied (₹" . \number_format($applied, 2) . ') exceeds original amount — IMPOSSIBLE STATE'
                        : "balance drifted: amount ₹" . \number_format($amount, 2) .
                          ' − applied ₹' . \number_format($applied, 2) .
                          ' should be ₹' . \number_format($expectedBalance, 2) .
                          ", got ₹" . \number_format($balance, 2) . " (status={$status})",
                ];
            }
        }
        return [
            'title'         => 'Supplier advances: balance vs amount − applied',
            'description'   => 'For each advance: balance should equal amount minus the sum of from_advance_amount applied via purchase_payments. Refunded advances should have balance = 0.',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    /**
     * Legacy obligations (across all party kinds):
     *   paid_amount  ==  opening_paid_amount + SUM(legacy_settlements.amount WHERE obligation_id = X)
     *   due_amount   ==  obligation_amount - paid_amount
     */
    private function checkLegacyObligationTotals(): array
    {
        $rows = Db::pdo()->query(
            "SELECT lo.id, lo.obligation_number,
                    CASE WHEN lo.customer_id IS NOT NULL THEN 'customer'
                         WHEN lo.supplier_id IS NOT NULL THEN 'supplier'
                         WHEN lo.salesman_id IS NOT NULL THEN 'salesman' ELSE 'unknown' END AS party_kind,
                    lo.obligation_amount, lo.opening_paid_amount, lo.paid_amount, lo.due_amount,
                    COALESCE((
                        SELECT SUM(amount) FROM legacy_settlements ls WHERE ls.obligation_id = lo.id
                    ), 0) AS sum_settlements
             FROM legacy_obligations lo"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $amount  = (float) $r['obligation_amount'];
            $opening = (float) $r['opening_paid_amount'];
            $paid    = (float) $r['paid_amount'];
            $due     = (float) $r['due_amount'];
            $sumSet  = (float) $r['sum_settlements'];

            $expectedPaid = \round($opening + $sumSet, 2);
            $expectedDue  = \round($amount - $paid, 2);
            $paidDiff     = \round($paid - $expectedPaid, 2);
            $dueDiff      = \round($due - $expectedDue, 2);

            if (\abs($paidDiff) > self::TOLERANCE || \abs($dueDiff) > self::TOLERANCE) {
                $mismatches[] = [
                    'entity_id'    => (int) $r['id'],
                    'entity_label' => $r['obligation_number'] . ' (' . $r['party_kind'] . ')',
                    'expected'     => $expectedPaid,
                    'actual'       => $paid,
                    'difference'   => $paidDiff,
                    'description'  => \abs($paidDiff) > self::TOLERANCE
                        ? 'paid_amount drifted: opening (₹' . \number_format($opening, 2)
                          . ') + sum(settlements) (₹' . \number_format($sumSet, 2)
                          . ') should equal paid_amount, got ₹' . \number_format($paid, 2)
                        : 'due_amount drifted: obligation_amount − paid_amount should equal due_amount',
                ];
            }
        }
        return [
            'title'         => 'Legacy obligations: paid = opening + sum(settlements); due = amount − paid',
            'description'   => 'For each legacy obligation (customer bill / supplier bill / salesman commission): paid_amount must equal opening_paid_amount plus the sum of allocated legacy_settlements; due_amount must equal obligation_amount minus paid_amount.',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    /**
     * Stock adjustments:
     *   per line:  quantity × cost_at_adjustment ≈ line_value
     *   header:    SUM(line_value) ≈ total_value
     */
    private function checkStockAdjustmentTotals(): array
    {
        $rows = Db::pdo()->query(
            "SELECT sa.id, sa.adjustment_number, sa.total_value,
                    COALESCE((
                        SELECT SUM(line_value) FROM stock_adjustment_lines sal
                        WHERE sal.stock_adjustment_id = sa.id
                    ), 0) AS sum_line_value,
                    (
                        SELECT COUNT(*) FROM stock_adjustment_lines sal2
                        WHERE sal2.stock_adjustment_id = sa.id
                          AND ABS(sal2.quantity * sal2.cost_at_adjustment - sal2.line_value) > 0.01
                    ) AS bad_lines
             FROM stock_adjustments sa"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $expected = (float) $r['sum_line_value'];
            $actual   = (float) $r['total_value'];
            $diff     = \round($actual - $expected, 2);
            $badLines = (int) $r['bad_lines'];
            if (\abs($diff) > self::TOLERANCE || $badLines > 0) {
                $mismatches[] = [
                    'entity_id'    => (int) $r['id'],
                    'entity_label' => $r['adjustment_number'],
                    'expected'     => $expected,
                    'actual'       => $actual,
                    'difference'   => $diff,
                    'description'  => $badLines > 0
                        ? "{$badLines} line(s) where qty × cost ≠ line_value"
                        : 'header total_value drifted from sum of lines',
                ];
            }
        }
        return [
            'title'         => 'Stock adjustments: header vs lines',
            'description'   => 'Each line: quantity × cost_at_adjustment must equal line_value. Header total_value must equal SUM(line_value).',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    /**
     * Brand rebates with mode='cost_reduction':
     *   amount  ==  SUM(adjustments.amount_applied)  +  income_spillover   (within tolerance)
     * Voided rebates skip — adjustments were reversed but rows are kept for audit.
     */
    private function checkBrandRebateCostReductions(): array
    {
        $rows = Db::pdo()->query(
            "SELECT br.id, br.rebate_number, br.amount, br.income_spillover, br.status,
                    COALESCE((
                        SELECT SUM(rca.amount_applied) FROM brand_rebate_cost_adjustments rca
                        WHERE rca.rebate_id = br.id
                    ), 0) AS sum_applied
             FROM brand_rebates br
             WHERE br.mode = 'cost_reduction' AND br.status = 'posted'"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $amount    = (float) $r['amount'];
            $spillover = (float) $r['income_spillover'];
            $applied   = (float) $r['sum_applied'];
            $expected  = $amount;
            $actual    = $applied + $spillover;
            $diff      = \round($actual - $expected, 2);
            if (\abs($diff) > self::TOLERANCE) {
                $mismatches[] = [
                    'entity_id'    => (int) $r['id'],
                    'entity_label' => $r['rebate_number'],
                    'expected'     => $expected,
                    'actual'       => $actual,
                    'difference'   => $diff,
                    'description'  => 'amount ≠ sum(adjustments.amount_applied) + income_spillover',
                ];
            }
        }
        return [
            'title'         => 'Brand rebates (cost_reduction): amount = applied + spillover',
            'description'   => 'For each posted cost_reduction rebate: rebate.amount must equal the sum of per-product cost adjustments plus the income_spillover crossed to "Other Income".',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    /**
     * Bank/drawer current_balance should equal opening_balance plus the net of every event:
     *
     *   + transfers in        − transfers out
     *   + sales-payment cash-effective (received_into = this account)
     *   − purchase-payment cash-effective (paid_from = this account)
     *   − operating expenses (paid_from, status='posted')
     *   ± capital movements  (in / out, status='posted')
     *   − cash-mode commission settlements (paid_from, status='posted')
     *   + customer advance receipts (received_into)
     *   − customer advance refund unused-balance (received_into, status='refunded')
     *   − supplier advance cash payouts (paid_from, paid_from_kind IS NOT NULL)
     *   + supplier advance refund unused-balance (paid_from, status='refunded')
     *   + brand rebate cash-back received_into (mode='cash_back', status='posted')
     *   ± legacy settlements (party.kind='customer' → +amount; supplier/salesman → −amount)
     *
     * Rebate-created supplier_advances (payment_mode='rebate', paid_from_kind IS NULL) are
     * intentionally excluded from the supplier-advance payout path — no cash moved.
     */
    /**
     * Perpetual-inventory reconciliation: each product's quantity_on_hand must equal the
     * sum of its signed stock-movement ledger rows (opening + ins − outs). Drift means a
     * stock mutation bypassed InventoryService, or a ledger write was lost.
     */
    private function checkStockLedger(): array
    {
        $rows = Db::pdo()->query(
            "SELECT p.id, p.name, p.quantity_on_hand,
                    COALESCE((
                        SELECT SUM(sm.quantity_delta) FROM stock_movements sm WHERE sm.product_id = p.id
                    ), 0) AS ledger_qty
             FROM products p"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($rows as $r) {
            $expected = \round((float) $r['ledger_qty'], 3);
            $actual   = \round((float) $r['quantity_on_hand'], 3);
            $diff     = \round($actual - $expected, 3);
            if (\abs($diff) <= self::TOLERANCE) { continue; }
            $mismatches[] = [
                'entity_id'    => (int) $r['id'],
                'entity_label' => $r['name'],
                'expected'     => $expected,
                'actual'       => $actual,
                'difference'   => $diff,
                'description'  => 'quantity_on_hand drifted from the stock-movement ledger',
            ];
        }
        return [
            'title'         => 'Stock: quantity_on_hand vs movement ledger',
            'description'   => 'Each product\'s quantity_on_hand must equal SUM(stock_movements.quantity_delta) — opening snapshot + ins − outs.',
            'mismatches'    => $mismatches,
            'total_checked' => \count($rows),
        ];
    }

    private function checkAccountBalances(string $kind, string $tableName): array
    {
        $accounts = Db::pdo()->query(
            "SELECT id, name, opening_balance, current_balance, status FROM {$tableName}"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $mismatches = [];
        foreach ($accounts as $a) {
            $accountId = (int) $a['id'];
            $expected  = (float) $a['opening_balance'];

            // Transfers IN
            $expected += $this->scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM inter_account_transfers
                 WHERE to_kind = ? AND to_id = ?",
                [$kind, $accountId]
            );
            // Transfers OUT
            $expected -= $this->scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM inter_account_transfers
                 WHERE from_kind = ? AND from_id = ?",
                [$kind, $accountId]
            );
            // Sales-payment cash-effective IN
            $expected += $this->scalar(
                "SELECT COALESCE(SUM(
                    cash_amount + online_amount +
                    CASE WHEN cheque_status IN ('pending','cleared') THEN cheque_amount ELSE 0 END
                 ), 0)
                 FROM sales_payments
                 WHERE received_into_kind = ? AND received_into_id = ?",
                [$kind, $accountId]
            );
            // Purchase-payment cash-effective OUT
            $expected -= $this->scalar(
                "SELECT COALESCE(SUM(
                    cash_amount + online_amount +
                    CASE WHEN cheque_status IN ('pending','cleared') THEN cheque_amount ELSE 0 END
                 ), 0)
                 FROM purchase_payments
                 WHERE paid_from_kind = ? AND paid_from_id = ?",
                [$kind, $accountId]
            );
            // Purchase-return cash refunds IN (supplier refunded the already-paid excess;
            // credited to this account at finalize).
            $expected += $this->scalar(
                "SELECT COALESCE(SUM(cash_refund_amount), 0) FROM purchase_returns
                 WHERE refund_account_kind = ? AND refund_account_id = ?
                   AND status = 'finalized' AND refund_method = 'cash'",
                [$kind, $accountId]
            );
            // Sales-return cash refunds OUT (we refunded the customer's already-paid excess;
            // debited from this account at finalize).
            $expected -= $this->scalar(
                "SELECT COALESCE(SUM(cash_refund_amount), 0) FROM sales_returns
                 WHERE refund_account_kind = ? AND refund_account_id = ?
                   AND status = 'finalized' AND refund_method = 'cash'",
                [$kind, $accountId]
            );
            // Operating expenses OUT (posted only — voided rows already had their delta reversed)
            $expected -= $this->scalar(
                "SELECT COALESCE(SUM(total_with_gst), 0) FROM operating_expenses
                 WHERE paid_from_kind = ? AND paid_from_id = ? AND status = 'posted'",
                [$kind, $accountId]
            );
            // Capital movements IN
            $expected += $this->scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM capital_movements
                 WHERE account_kind = ? AND account_id = ? AND direction = 'in' AND status = 'posted'",
                [$kind, $accountId]
            );
            // Capital movements OUT
            $expected -= $this->scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM capital_movements
                 WHERE account_kind = ? AND account_id = ? AND direction = 'out' AND status = 'posted'",
                [$kind, $accountId]
            );
            // Cash-mode commission settlements OUT (posted only)
            $expected -= $this->scalar(
                "SELECT COALESCE(SUM(cash_amount), 0) FROM salesman_commission_settlements
                 WHERE paid_from_kind = ? AND paid_from_id = ? AND payout_mode = 'cash' AND status = 'posted'",
                [$kind, $accountId]
            );
            // Customer advances received IN (all rows, including refunded — receipt was real cash)
            $expected += $this->scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM customer_advances
                 WHERE received_into_kind = ? AND received_into_id = ?",
                [$kind, $accountId]
            );
            // Customer advances refunded OUT (refund_amount = amount − applied, debited at refund)
            $expected -= $this->scalar(
                "SELECT COALESCE(SUM(
                    ca.amount - COALESCE((
                        SELECT SUM(sp.from_advance_amount) FROM sales_payments sp
                        WHERE sp.from_advance_id = ca.id
                    ), 0)
                 ), 0)
                 FROM customer_advances ca
                 WHERE ca.received_into_kind = ? AND ca.received_into_id = ? AND ca.status = 'refunded'",
                [$kind, $accountId]
            );
            // Supplier advances paid OUT (cash leg only — rebate-mode advances skip this).
            $expected -= $this->scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM supplier_advances
                 WHERE paid_from_kind = ? AND paid_from_id = ? AND payment_mode <> 'rebate'",
                [$kind, $accountId]
            );
            // Supplier advances refunded IN (refund_amount = amount − applied, credited at refund).
            // Rebate-mode advances cannot be refunded (blocked at service layer) so no path here.
            $expected += $this->scalar(
                "SELECT COALESCE(SUM(
                    sa.amount - COALESCE((
                        SELECT SUM(pp.from_advance_amount) FROM purchase_payments pp
                        WHERE pp.from_advance_id = sa.id
                    ), 0)
                 ), 0)
                 FROM supplier_advances sa
                 WHERE sa.paid_from_kind = ? AND sa.paid_from_id = ? AND sa.status = 'refunded'",
                [$kind, $accountId]
            );
            // Brand-rebate cash-back received IN (posted only — voided rows had effect reversed).
            $expected += $this->scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM brand_rebates
                 WHERE received_into_kind = ? AND received_into_id = ?
                   AND mode = 'cash_back' AND status = 'posted'",
                [$kind, $accountId]
            );
            // Legacy settlements: customer = +amount (we got money), supplier/salesman = −amount.
            $expected += $this->scalar(
                "SELECT COALESCE(SUM(CASE WHEN ls.customer_id IS NOT NULL THEN ls.amount ELSE -ls.amount END), 0)
                 FROM legacy_settlements ls
                 WHERE ls.account_kind = ? AND ls.account_id = ?",
                [$kind, $accountId]
            );

            $expected = \round($expected, 2);
            $actual   = \round((float) $a['current_balance'], 2);
            $diff     = \round($actual - $expected, 2);

            if (\abs($diff) > self::TOLERANCE) {
                $mismatches[] = [
                    'entity_id'    => $accountId,
                    'entity_label' => $a['name'],
                    'expected'     => $expected,
                    'actual'       => $actual,
                    'difference'   => $diff,
                    'description'  => 'current_balance drifted from net of all real movements',
                ];
            }
        }
        $human = $kind === 'bank_account' ? 'Bank accounts' : 'Cashier drawers';
        return [
            'title'         => "{$human}: current_balance vs net of movements",
            'description'   => "Per account: opening_balance + sum(in events) − sum(out events) should equal current_balance.",
            'mismatches'    => $mismatches,
            'total_checked' => \count($accounts),
        ];
    }

    private function scalar(string $sql, array $params): float
    {
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}
