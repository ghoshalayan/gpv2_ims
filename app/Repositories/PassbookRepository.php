<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * Read-only ledger views per entity (D40 / D50 / #24 Universal Passbook).
 *
 * Each method returns a list of normalized rows:
 *   {
 *     txn_date, ref_number, source_type, source_id,
 *     description, debit, credit, status,
 *   }
 * sorted oldest-first so the caller can compute a running balance.
 *
 * No new persistent table — these are query-time UNIONs across the existing source
 * tables (sales/purchase invoices, payments, returns, transfers, expenses, capital
 * movements). When the formal `ledger_entries` table arrives in a future phase,
 * this repo can be swapped for a single-table SELECT without touching callers.
 *
 * Sign convention by entity kind:
 *   - Customer  : debit  = customer owes us (sale)            credit = we received / customer credit (payment, return)
 *   - Supplier  : credit = we owe supplier (purchase)         debit  = we paid / supplier credit  (payment, return)
 *   - Account   : debit  = money OUT of the account           credit = money IN  to the account
 *   - Salesman  : credit = commission earned                  debit  = commission paid / reversed (returns)
 *
 * "Running balance" interpretation is defined by the caller per entity kind.
 */
class PassbookRepository
{
    /**
     * Customer ledger: invoices in, payments out, returns out.
     *   - Finalized sales_invoice  → DEBIT  (grand_total)
     *   - sales_payment            → CREDIT (effective amount: cash + online + cheque-if-counted)
     *   - Finalized sales_return   → CREDIT (grand_total)
     */
    public function customerLedger(int $customerId): array
    {
        // Sign convention recap (customer):
        //   Sale invoice            → DEBIT  (customer owes us)
        //   Sales payment (cash)    → CREDIT (cash/online/cheque only — excludes from_advance)
        //   Sales return            → CREDIT (we owe customer back)
        //   Customer advance recv'd → CREDIT (prepaid; we owe them this much)
        //   Customer advance refund → DEBIT  (we returned the unused balance)
        //
        // Why payment credit excludes from_advance: the advance was already credited at
        // receipt time. An apply is just re-allocating that existing credit to absorb an
        // invoice — net effect on customer's outstanding is zero, so showing a credit on
        // BOTH the receipt AND the application would double-count.
        $sql = "
            SELECT si.invoice_date  AS txn_date,
                   si.invoice_number AS ref_number,
                   'sales_invoice'   AS source_type,
                   si.id             AS source_id,
                   CONCAT('Sales invoice', IFNULL(CONCAT(' (', NULLIF(si.bill_type, ''), ')'), '')) AS description,
                   si.grand_total    AS debit,
                   0                 AS credit,
                   si.status         AS status
            FROM sales_invoices si
            WHERE si.customer_id = ? AND si.status = 'finalized'

            UNION ALL

            SELECT sp.payment_date,
                   CONCAT('PAY-', sp.id),
                   'sales_payment',
                   sp.id,
                   CONCAT('Payment',
                          IFNULL(CONCAT(' (', NULLIF(sp.cheque_status, ''), ' cheque)'), ''),
                          IFNULL(CONCAT(' · inv ', si2.invoice_number), ''),
                          CASE WHEN sp.from_advance_amount > 0
                               THEN CONCAT(' · used ₹',
                                           FORMAT(sp.from_advance_amount, 2),
                                           ' from advance')
                               ELSE '' END),
                   0,
                   sp.cash_amount + sp.online_amount +
                       CASE WHEN sp.cheque_status IN ('pending','cleared') THEN sp.cheque_amount ELSE 0 END,
                   sp.cheque_status
            FROM sales_payments sp
            JOIN sales_invoices si2 ON si2.id = sp.sales_invoice_id
            WHERE si2.customer_id = ?

            UNION ALL

            SELECT sr.return_date,
                   sr.return_number,
                   'sales_return',
                   sr.id,
                   CONCAT('Sales return / credit note',
                          IFNULL(CONCAT(' · inv ', si3.invoice_number), '')),
                   0,
                   sr.grand_total,
                   sr.status
            FROM sales_returns sr
            JOIN sales_invoices si3 ON si3.id = sr.original_sales_invoice_id
            WHERE sr.customer_id = ? AND sr.status = 'finalized'

            UNION ALL

            -- Customer advance received
            SELECT ca.advance_date,
                   ca.advance_number,
                   'customer_advance',
                   ca.id,
                   CONCAT('Advance received',
                          IFNULL(CONCAT(' · ', NULLIF(ca.payment_mode, '')), '')),
                   0,
                   ca.amount,
                   ca.status
            FROM customer_advances ca
            WHERE ca.customer_id = ?

            UNION ALL

            -- Customer advance refunded — debit the unused balance returned at refund time
            SELECT DATE(ca.refunded_at),
                   CONCAT(ca.advance_number, '/RFND'),
                   'customer_advance_refund',
                   ca.id,
                   CONCAT('Advance refunded',
                          IFNULL(CONCAT(' · ', NULLIF(ca.refund_reason, '')), '')),
                   ca.amount - COALESCE((
                       SELECT SUM(sp4.from_advance_amount) FROM sales_payments sp4
                       WHERE sp4.from_advance_id = ca.id
                   ), 0),
                   0,
                   'refunded'
            FROM customer_advances ca
            WHERE ca.customer_id = ? AND ca.status = 'refunded' AND ca.refunded_at IS NOT NULL

            UNION ALL

            -- Carry-forward from old bills attached to this customer: pre-v2 unpaid
            -- bills become a single DEBIT per obligation on the obligation_date,
            -- valued at (obligation_amount − opening_paid_amount).
            SELECT lo.obligation_date,
                   lo.obligation_number,
                   'legacy_obligation',
                   lo.id,
                   CONCAT('Old bill',
                          IFNULL(CONCAT(' · ', NULLIF(lo.remarks, '')), '')),
                   GREATEST(lo.obligation_amount - lo.opening_paid_amount, 0),
                   0,
                   lo.payment_status
            FROM legacy_obligations lo
            WHERE lo.customer_id = ?

            UNION ALL

            -- Old payments received from this customer → CREDIT.
            SELECT ls.settlement_date,
                   CONCAT('LS-', ls.id),
                   'legacy_settlement',
                   ls.id,
                   CONCAT('Old payment',
                          IFNULL(CONCAT(' · ', lo2.obligation_number), ' · unallocated')),
                   0,
                   ls.amount,
                   ls.payment_mode
            FROM legacy_settlements ls
            LEFT JOIN legacy_obligations lo2 ON lo2.id = ls.obligation_id
            WHERE ls.customer_id = ?

            ORDER BY txn_date ASC, source_id ASC
        ";
        // Positional placeholders — 7 customer_id occurrences (5 base + 2 legacy).
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([
            $customerId, $customerId, $customerId, $customerId, $customerId,
            $customerId, $customerId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supplier ledger: invoices CREDIT, payments DEBIT, returns DEBIT.
     *
     * Sign convention recap (supplier):
     *   Purchase invoice         → CREDIT (we owe supplier)
     *   Purchase payment (cash)  → DEBIT  (cash/online/cheque only — excludes from_advance)
     *   Purchase return          → DEBIT  (supplier owes us back)
     *   Supplier advance paid    → DEBIT  (prepayment; supplier owes goods/credit)
     *   Supplier advance refund  → CREDIT (they returned the unused balance)
     *
     * Why payment debit excludes from_advance: the advance was already debited at
     * payment-out time. An apply is just re-allocating that existing credit to absorb a
     * bill — net effect on supplier's outstanding is zero, so showing a debit on
     * BOTH the issuance AND the application would double-count.
     */
    public function supplierLedger(int $supplierId): array
    {
        $sql = "
            SELECT pi.invoice_date AS txn_date,
                   pi.invoice_number AS ref_number,
                   'purchase_invoice' AS source_type,
                   pi.id AS source_id,
                   'Purchase invoice' AS description,
                   0 AS debit,
                   pi.grand_total AS credit,
                   pi.status AS status
            FROM purchase_invoices pi
            WHERE pi.supplier_id = ? AND pi.status = 'finalized'

            UNION ALL

            SELECT pp.payment_date,
                   CONCAT('PAY-', pp.id),
                   'purchase_payment',
                   pp.id,
                   CONCAT('Payment to supplier',
                          IFNULL(CONCAT(' (', NULLIF(pp.cheque_status, ''), ' cheque)'), ''),
                          IFNULL(CONCAT(' · inv ', pi2.invoice_number), ''),
                          CASE WHEN pp.from_advance_amount > 0
                               THEN CONCAT(' · used ₹',
                                           FORMAT(pp.from_advance_amount, 2),
                                           ' from advance')
                               ELSE '' END),
                   pp.cash_amount + pp.online_amount +
                       CASE WHEN pp.cheque_status IN ('pending','cleared') THEN pp.cheque_amount ELSE 0 END,
                   0,
                   pp.cheque_status
            FROM purchase_payments pp
            JOIN purchase_invoices pi2 ON pi2.id = pp.purchase_invoice_id
            WHERE pi2.supplier_id = ?

            UNION ALL

            SELECT pr.return_date,
                   pr.return_number,
                   'purchase_return',
                   pr.id,
                   CONCAT('Purchase return / debit note',
                          IFNULL(CONCAT(' · inv ', pi3.invoice_number), '')),
                   pr.grand_total,
                   0,
                   pr.status
            FROM purchase_returns pr
            JOIN purchase_invoices pi3 ON pi3.id = pr.original_purchase_invoice_id
            WHERE pr.supplier_id = ? AND pr.status = 'finalized'

            UNION ALL

            -- Supplier advance paid out (cash) OR brand-rebate-created (no cash leg).
            -- Rebate-created rows have payment_mode='rebate' and a NULL paid_from_kind;
            -- distinguished narratively below.
            SELECT sa.advance_date,
                   sa.advance_number,
                   'supplier_advance',
                   sa.id,
                   CASE WHEN sa.payment_mode = 'rebate'
                        THEN CONCAT('Brand rebate credit (reduce_payable)',
                                    IFNULL(CONCAT(' · CN ', NULLIF(sa.payment_reference, '')), ''))
                        ELSE CONCAT('Advance paid to supplier',
                                    IFNULL(CONCAT(' · ', NULLIF(sa.payment_mode, '')), ''))
                   END,
                   sa.amount,
                   0,
                   sa.status
            FROM supplier_advances sa
            WHERE sa.supplier_id = ?

            UNION ALL

            -- Supplier advance refunded — credit the unused balance returned at refund time
            SELECT DATE(sa.refunded_at),
                   CONCAT(sa.advance_number, '/RFND'),
                   'supplier_advance_refund',
                   sa.id,
                   CONCAT('Advance refunded by supplier',
                          IFNULL(CONCAT(' · ', NULLIF(sa.refund_reason, '')), '')),
                   0,
                   sa.amount - COALESCE((
                       SELECT SUM(pp4.from_advance_amount) FROM purchase_payments pp4
                       WHERE pp4.from_advance_id = sa.id
                   ), 0),
                   'refunded'
            FROM supplier_advances sa
            WHERE sa.supplier_id = ? AND sa.status = 'refunded' AND sa.refunded_at IS NOT NULL

            UNION ALL

            -- Brand rebate cash-back received from this supplier — DEBIT (supplier credit).
            -- Reduce-payable rebates already surface via the supplier_advance branch above.
            SELECT br.rebate_date,
                   br.rebate_number,
                   'brand_rebate',
                   br.id,
                   CONCAT('Brand rebate cash-back · CN ', br.credit_note_number),
                   br.amount,
                   0,
                   br.status
            FROM brand_rebates br
            WHERE br.supplier_id = ? AND br.mode = 'cash_back' AND br.status = 'posted'

            UNION ALL

            -- Old bills owed to this supplier → CREDIT (we owe).
            SELECT lo.obligation_date,
                   lo.obligation_number,
                   'legacy_obligation',
                   lo.id,
                   CONCAT('Old bill',
                          IFNULL(CONCAT(' · ', NULLIF(lo.remarks, '')), '')),
                   0,
                   GREATEST(lo.obligation_amount - lo.opening_paid_amount, 0),
                   lo.payment_status
            FROM legacy_obligations lo
            WHERE lo.supplier_id = ?

            UNION ALL

            -- Old payments to this supplier → DEBIT.
            SELECT ls.settlement_date,
                   CONCAT('LS-', ls.id),
                   'legacy_settlement',
                   ls.id,
                   CONCAT('Old payment',
                          IFNULL(CONCAT(' · ', lo2.obligation_number), ' · unallocated')),
                   ls.amount,
                   0,
                   ls.payment_mode
            FROM legacy_settlements ls
            LEFT JOIN legacy_obligations lo2 ON lo2.id = ls.obligation_id
            WHERE ls.supplier_id = ?

            ORDER BY txn_date ASC, source_id ASC
        ";
        // Positional placeholders — 8 supplier_id occurrences (6 base + 2 legacy).
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([
            $supplierId, $supplierId, $supplierId, $supplierId, $supplierId, $supplierId,
            $supplierId, $supplierId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Account ledger (bank or drawer). Sign convention:
     *   debit  = money OUT (account decreases)
     *   credit = money IN  (account increases)
     *
     * Sources: inter-account transfers, sales/purchase payments, operating expenses,
     * capital movements. Recurring expenses surface via their spawned operating_expenses.
     */
    public function accountLedger(string $kind, int $accountId): array
    {
        if (!\in_array($kind, ['bank_account', 'cashier_drawer'], true)) {
            throw new \InvalidArgumentException("Unknown account kind: {$kind}");
        }

        $sql = "
            -- Inter-account transfers IN
            SELECT iat.transfer_date AS txn_date,
                   iat.transfer_number AS ref_number,
                   'inter_account_transfer_in' AS source_type,
                   iat.id AS source_id,
                   CONCAT('Transfer from ',
                          CASE iat.from_kind
                            WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = iat.from_id)
                            WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = iat.from_id)
                          END) AS description,
                   0 AS debit,
                   iat.amount AS credit,
                   iat.mode AS status
            FROM inter_account_transfers iat
            WHERE iat.to_kind = ? AND iat.to_id = ?

            UNION ALL

            -- Inter-account transfers OUT
            SELECT iat.transfer_date,
                   iat.transfer_number,
                   'inter_account_transfer_out',
                   iat.id,
                   CONCAT('Transfer to ',
                          CASE iat.to_kind
                            WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = iat.to_id)
                            WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = iat.to_id)
                          END),
                   iat.amount,
                   0,
                   iat.mode
            FROM inter_account_transfers iat
            WHERE iat.from_kind = ? AND iat.from_id = ?

            UNION ALL

            -- Sales payments received into this account
            SELECT sp.payment_date,
                   CONCAT('SP-', sp.id),
                   'sales_payment',
                   sp.id,
                   CONCAT('Sales payment',
                          IFNULL(CONCAT(' (', NULLIF(sp.cheque_status, ''), ' cheque)'), ''),
                          ' · inv ', si.invoice_number,
                          ' · ', c.name),
                   0,
                   sp.cash_amount + sp.online_amount +
                       CASE WHEN sp.cheque_status IN ('pending','cleared') THEN sp.cheque_amount ELSE 0 END,
                   sp.cheque_status
            FROM sales_payments sp
            JOIN sales_invoices si ON si.id = sp.sales_invoice_id
            JOIN customers c       ON c.id  = si.customer_id
            WHERE sp.received_into_kind = ? AND sp.received_into_id = ?

            UNION ALL

            -- Purchase payments paid from this account
            SELECT pp.payment_date,
                   CONCAT('PP-', pp.id),
                   'purchase_payment',
                   pp.id,
                   CONCAT('Purchase payment',
                          IFNULL(CONCAT(' (', NULLIF(pp.cheque_status, ''), ' cheque)'), ''),
                          ' · inv ', pi.invoice_number,
                          ' · ', s.name),
                   pp.cash_amount + pp.online_amount +
                       CASE WHEN pp.cheque_status IN ('pending','cleared') THEN pp.cheque_amount ELSE 0 END,
                   0,
                   pp.cheque_status
            FROM purchase_payments pp
            JOIN purchase_invoices pi ON pi.id = pp.purchase_invoice_id
            JOIN suppliers s          ON s.id  = pi.supplier_id
            WHERE pp.paid_from_kind = ? AND pp.paid_from_id = ?

            UNION ALL

            -- Operating expenses paid from this account
            SELECT oe.expense_date,
                   oe.expense_number,
                   'operating_expense',
                   oe.id,
                   CONCAT('Expense — ', cat.name,
                          IFNULL(CONCAT(' · ', NULLIF(oe.vendor_name, '')), '')),
                   oe.total_with_gst,
                   0,
                   oe.status
            FROM operating_expenses oe
            JOIN operating_expense_categories cat ON cat.id = oe.category_id
            WHERE oe.paid_from_kind = ? AND oe.paid_from_id = ? AND oe.status = 'posted'

            UNION ALL

            -- Capital movements on this account
            SELECT cm.movement_date,
                   cm.movement_number,
                   'capital_movement',
                   cm.id,
                   CONCAT(
                       CASE cm.txn_type
                           WHEN 'capital_in' THEN 'Capital injection'
                           WHEN 'drawing'    THEN 'Owner drawing'
                           WHEN 'loan_in'    THEN 'Loan received'
                           WHEN 'loan_repay' THEN 'Loan repayment'
                           ELSE 'Other capital movement'
                       END,
                       ' · ', cm.counterparty_name),
                   CASE WHEN cm.direction = 'out' THEN cm.amount ELSE 0 END,
                   CASE WHEN cm.direction = 'in'  THEN cm.amount ELSE 0 END,
                   cm.status
            FROM capital_movements cm
            WHERE cm.account_kind = ? AND cm.account_id = ? AND cm.status = 'posted'

            UNION ALL

            -- Cash-mode commission settlements paid from this account
            SELECT scs.settlement_date,
                   scs.settlement_number,
                   'commission_settlement',
                   scs.id,
                   CONCAT('Commission paid · ', sm.name),
                   scs.cash_amount,
                   0,
                   scs.status
            FROM salesman_commission_settlements scs
            JOIN salesmen sm ON sm.id = scs.salesman_id
            WHERE scs.payout_mode = 'cash'
              AND scs.paid_from_kind = ? AND scs.paid_from_id = ?
              AND scs.status = 'posted'

            UNION ALL

            -- Customer advance received into this account
            SELECT ca.advance_date,
                   ca.advance_number,
                   'customer_advance',
                   ca.id,
                   CONCAT('Customer advance · ', cu.name),
                   0,
                   ca.amount,
                   ca.status
            FROM customer_advances ca
            JOIN customers cu ON cu.id = ca.customer_id
            WHERE ca.received_into_kind = ? AND ca.received_into_id = ?

            UNION ALL

            -- Customer advance refunded from this account
            SELECT DATE(ca2.refunded_at),
                   CONCAT(ca2.advance_number, '/RFND'),
                   'customer_advance_refund',
                   ca2.id,
                   CONCAT('Advance refund to ', cu2.name),
                   ca2.amount - COALESCE((
                       SELECT SUM(sp5.from_advance_amount) FROM sales_payments sp5
                       WHERE sp5.from_advance_id = ca2.id
                   ), 0),
                   0,
                   'refunded'
            FROM customer_advances ca2
            JOIN customers cu2 ON cu2.id = ca2.customer_id
            WHERE ca2.received_into_kind = ? AND ca2.received_into_id = ?
              AND ca2.status = 'refunded' AND ca2.refunded_at IS NOT NULL

            UNION ALL

            -- Supplier advance paid out from this account
            SELECT sa.advance_date,
                   sa.advance_number,
                   'supplier_advance',
                   sa.id,
                   CONCAT('Supplier advance · ', su.name),
                   sa.amount,
                   0,
                   sa.status
            FROM supplier_advances sa
            JOIN suppliers su ON su.id = sa.supplier_id
            WHERE sa.paid_from_kind = ? AND sa.paid_from_id = ?

            UNION ALL

            -- Supplier advance refunded into this account
            SELECT DATE(sa2.refunded_at),
                   CONCAT(sa2.advance_number, '/RFND'),
                   'supplier_advance_refund',
                   sa2.id,
                   CONCAT('Supplier advance refund from ', su2.name),
                   0,
                   sa2.amount - COALESCE((
                       SELECT SUM(pp5.from_advance_amount) FROM purchase_payments pp5
                       WHERE pp5.from_advance_id = sa2.id
                   ), 0),
                   'refunded'
            FROM supplier_advances sa2
            JOIN suppliers su2 ON su2.id = sa2.supplier_id
            WHERE sa2.paid_from_kind = ? AND sa2.paid_from_id = ?
              AND sa2.status = 'refunded' AND sa2.refunded_at IS NOT NULL

            UNION ALL

            -- Brand rebate cash-back received into this account (CREDIT, money in)
            SELECT br.rebate_date,
                   br.rebate_number,
                   'brand_rebate',
                   br.id,
                   CONCAT('Brand rebate · ', br_b.name, ' · ', br_s.name,
                          ' · CN ', br.credit_note_number),
                   0,
                   br.amount,
                   br.status
            FROM brand_rebates br
            JOIN brands    br_b ON br_b.id = br.brand_id
            JOIN suppliers br_s ON br_s.id = br.supplier_id
            WHERE br.received_into_kind = ? AND br.received_into_id = ?
              AND br.mode = 'cash_back' AND br.status = 'posted'

            UNION ALL

            -- Old settlements directly attached to a customer/supplier/salesman.
            -- Direction by which entity column is set: customer = CREDIT (money in),
            -- supplier/salesman = DEBIT (money out). Resolves the entity name via a
            -- COALESCE across the three live tables.
            SELECT ls.settlement_date,
                   CONCAT('LS-', ls.id),
                   CASE
                     WHEN ls.customer_id IS NOT NULL THEN 'legacy_customer_settlement'
                     WHEN ls.supplier_id IS NOT NULL THEN 'legacy_supplier_settlement'
                     WHEN ls.salesman_id IS NOT NULL THEN 'legacy_salesman_settlement'
                     ELSE 'legacy_settlement'
                   END,
                   ls.id,
                   CONCAT('Old settlement · ',
                          COALESCE(
                              (SELECT name FROM customers WHERE id = ls.customer_id),
                              (SELECT name FROM suppliers WHERE id = ls.supplier_id),
                              (SELECT name FROM salesmen  WHERE id = ls.salesman_id),
                              '?'
                          ),
                          IFNULL(CONCAT(' · ', lo.obligation_number), ' · unallocated')),
                   CASE WHEN ls.customer_id IS NOT NULL THEN 0          ELSE ls.amount END,
                   CASE WHEN ls.customer_id IS NOT NULL THEN ls.amount  ELSE 0          END,
                   ls.payment_mode
            FROM legacy_settlements ls
            LEFT JOIN legacy_obligations lo ON lo.id = ls.obligation_id
            WHERE ls.account_kind = ? AND ls.account_id = ?

            ORDER BY txn_date ASC, source_id ASC
        ";
        // Positional placeholders — same kind/aid pair repeats per UNION branch.
        // 13 branches × 2 placeholders = 26 values.
        $stmt = Db::pdo()->prepare($sql);
        $params = [];
        for ($i = 0; $i < 13; $i++) {
            $params[] = $kind;
            $params[] = $accountId;
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salesman ledger:
     *   - Sales line              → CREDIT (commission earned)
     *   - Sales return line       → DEBIT  (commission reversed)
     *   - Commission settlement   → DEBIT  (paid out as cash or goods)
     */
    public function salesmanLedger(int $salesmanId): array
    {
        $sql = "
            SELECT si.invoice_date AS txn_date,
                   si.invoice_number AS ref_number,
                   'sales_invoice_line' AS source_type,
                   sil.id AS source_id,
                   CONCAT('Commission earned · inv ', si.invoice_number,
                          ' · ', sil.display_name,
                          ' @ ', sil.commission_rate, '%') AS description,
                   0 AS debit,
                   sil.commission_amount AS credit,
                   si.status AS status
            FROM sales_invoice_lines sil
            JOIN sales_invoices si ON si.id = sil.sales_invoice_id
            WHERE sil.salesman_id = ? AND si.status = 'finalized'
              AND sil.commission_amount > 0

            UNION ALL

            SELECT sr.return_date,
                   sr.return_number,
                   'sales_return_line',
                   srl.id,
                   CONCAT('Commission reversed (return) · ', sr.return_number,
                          ' · ', srl.display_name),
                   srl.commission_reversed,
                   0,
                   sr.status
            FROM sales_return_lines srl
            JOIN sales_returns sr ON sr.id = srl.sales_return_id
            WHERE srl.salesman_id = ? AND sr.status = 'finalized'
              AND srl.commission_reversed > 0

            UNION ALL

            SELECT scs.settlement_date,
                   scs.settlement_number,
                   'commission_settlement',
                   scs.id,
                   CASE scs.payout_mode
                       WHEN 'cash'  THEN CONCAT('Settled in cash from ',
                                                 CASE scs.paid_from_kind
                                                     WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = scs.paid_from_id)
                                                     WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = scs.paid_from_id)
                                                 END)
                       WHEN 'goods' THEN CONCAT('Settled in goods · ',
                                                 (SELECT name FROM products WHERE id = scs.goods_product_id),
                                                 ' × ', scs.goods_quantity)
                   END,
                   scs.total_settled_amount,
                   0,
                   scs.status
            FROM salesman_commission_settlements scs
            WHERE scs.salesman_id = ? AND scs.status = 'posted'

            UNION ALL

            -- Old unsettled commissions for this salesman → CREDIT (we owe).
            SELECT lo.obligation_date,
                   lo.obligation_number,
                   'legacy_obligation',
                   lo.id,
                   CONCAT('Old commission',
                          IFNULL(CONCAT(' · ', NULLIF(lo.remarks, '')), '')),
                   0,
                   GREATEST(lo.obligation_amount - lo.opening_paid_amount, 0),
                   lo.payment_status
            FROM legacy_obligations lo
            WHERE lo.salesman_id = ?

            UNION ALL

            -- Old payouts to this salesman → DEBIT.
            SELECT ls.settlement_date,
                   CONCAT('LS-', ls.id),
                   'legacy_settlement',
                   ls.id,
                   CONCAT('Old payout',
                          IFNULL(CONCAT(' · ', lo2.obligation_number), ' · unallocated')),
                   ls.amount,
                   0,
                   ls.payment_mode
            FROM legacy_settlements ls
            LEFT JOIN legacy_obligations lo2 ON lo2.id = ls.obligation_id
            WHERE ls.salesman_id = ?

            ORDER BY txn_date ASC, source_id ASC
        ";
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([$salesmanId, $salesmanId, $salesmanId, $salesmanId, $salesmanId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
