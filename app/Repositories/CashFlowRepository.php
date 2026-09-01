<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * Unified cash-event view across every transactional source. Same set of sources used
 * by IntegrityCheckService::checkAccountBalances — by construction, summing all events
 * since account creation must equal current_balance − opening_balance for that account.
 *
 * If integrity passes, this report is guaranteed balanced. Any discrepancy here surfaces
 * as a non-zero "Diff" on the reconciliation panel and signals a bug somewhere upstream.
 *
 * Each row shape:
 *   {
 *     event_date, source_type, source_id, source_number, party_name,
 *     account_kind, account_id, account_name,
 *     category, direction ('in'|'out'), amount, narration
 *   }
 *
 * Categories (used for grouping in the UI):
 *   sales, customer_advance, purchase, supplier_advance, transfer,
 *   capital, expense, commission, rebate, legacy
 */
class CashFlowRepository
{
    /**
     * Full unified event list. Sums of inflows − outflows per account, summed over the
     * entire history, must equal current_balance − opening_balance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function events(?string $from = null, ?string $to = null): array
    {
        $sql = $this->buildEventsSql();
        $where = [];
        $params = [];
        if ($from !== null && $from !== '') { $where[] = 'event_date >= ?'; $params[] = $from; }
        if ($to   !== null && $to   !== '') { $where[] = 'event_date <= ?'; $params[] = $to; }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);
        $stmt = Db::pdo()->prepare(
            "SELECT * FROM ({$sql}) AS t {$whereSql} ORDER BY event_date ASC, source_type, source_id"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The big UNION query producing one row per cash event. Shared by events(),
     * paginateEvents(), aggregateByCategory(), and reconciliation().
     */
    private function buildEventsSql(): string
    {
        return "
            -- 1. Sales payments → CREDIT account (cash + online + counted-cheque only).
            SELECT sp.payment_date AS event_date,
                   'sales_payment' AS source_type,
                   sp.id AS source_id,
                   CONCAT('PAY-', sp.id) AS source_number,
                   c.name AS party_name,
                   sp.received_into_kind AS account_kind,
                   sp.received_into_id AS account_id,
                   CASE sp.received_into_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts WHERE id = sp.received_into_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = sp.received_into_id)
                   END AS account_name,
                   'sales' AS category,
                   'in' AS direction,
                   (sp.cash_amount + sp.online_amount +
                       CASE WHEN sp.cheque_status IN ('pending','cleared') THEN sp.cheque_amount ELSE 0 END) AS amount,
                   CONCAT('Sales receipt · inv ', si.invoice_number) AS narration
            FROM sales_payments sp
            JOIN sales_invoices si ON si.id = sp.sales_invoice_id
            JOIN customers c       ON c.id = si.customer_id
            WHERE sp.received_into_kind IS NOT NULL
              AND (sp.cash_amount + sp.online_amount +
                   CASE WHEN sp.cheque_status IN ('pending','cleared') THEN sp.cheque_amount ELSE 0 END) > 0

            UNION ALL

            -- 2. Customer advance receipts → CREDIT account.
            SELECT ca.advance_date,
                   'customer_advance', ca.id, ca.advance_number,
                   c.name,
                   ca.received_into_kind, ca.received_into_id,
                   CASE ca.received_into_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = ca.received_into_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = ca.received_into_id)
                   END,
                   'customer_advance', 'in', ca.amount,
                   CONCAT('Customer advance receipt')
            FROM customer_advances ca
            JOIN customers c ON c.id = ca.customer_id

            UNION ALL

            -- 3. Customer advance refunds → DEBIT account (we paid back).
            SELECT DATE(ca.refunded_at),
                   'customer_advance_refund', ca.id, CONCAT(ca.advance_number, '/RFND'),
                   c.name,
                   ca.received_into_kind, ca.received_into_id,
                   CASE ca.received_into_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = ca.received_into_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = ca.received_into_id)
                   END,
                   'customer_advance', 'out',
                   (ca.amount - COALESCE((SELECT SUM(sp.from_advance_amount) FROM sales_payments sp WHERE sp.from_advance_id = ca.id), 0)),
                   'Customer advance refund'
            FROM customer_advances ca
            JOIN customers c ON c.id = ca.customer_id
            WHERE ca.status = 'refunded' AND ca.refunded_at IS NOT NULL

            UNION ALL

            -- 4. Purchase payments → DEBIT account.
            SELECT pp.payment_date,
                   'purchase_payment', pp.id, CONCAT('PAY-', pp.id),
                   s.name,
                   pp.paid_from_kind, pp.paid_from_id,
                   CASE pp.paid_from_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = pp.paid_from_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = pp.paid_from_id)
                   END,
                   'purchase', 'out',
                   (pp.cash_amount + pp.online_amount +
                       CASE WHEN pp.cheque_status IN ('pending','cleared') THEN pp.cheque_amount ELSE 0 END),
                   CONCAT('Purchase payment · inv ', pi.invoice_number)
            FROM purchase_payments pp
            JOIN purchase_invoices pi ON pi.id = pp.purchase_invoice_id
            JOIN suppliers s          ON s.id  = pi.supplier_id
            WHERE pp.paid_from_kind IS NOT NULL
              AND (pp.cash_amount + pp.online_amount +
                   CASE WHEN pp.cheque_status IN ('pending','cleared') THEN pp.cheque_amount ELSE 0 END) > 0

            UNION ALL

            -- 5. Supplier advance payouts → DEBIT account (rebate-mode advances skipped — no cash).
            SELECT sa.advance_date,
                   'supplier_advance', sa.id, sa.advance_number,
                   s.name,
                   sa.paid_from_kind, sa.paid_from_id,
                   CASE sa.paid_from_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = sa.paid_from_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = sa.paid_from_id)
                   END,
                   'supplier_advance', 'out', sa.amount,
                   'Supplier advance paid'
            FROM supplier_advances sa
            JOIN suppliers s ON s.id = sa.supplier_id
            WHERE sa.payment_mode <> 'rebate' AND sa.paid_from_kind IS NOT NULL

            UNION ALL

            -- 6. Supplier advance refunds → CREDIT account (we got back).
            SELECT DATE(sa.refunded_at),
                   'supplier_advance_refund', sa.id, CONCAT(sa.advance_number, '/RFND'),
                   s.name,
                   sa.paid_from_kind, sa.paid_from_id,
                   CASE sa.paid_from_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = sa.paid_from_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = sa.paid_from_id)
                   END,
                   'supplier_advance', 'in',
                   (sa.amount - COALESCE((SELECT SUM(pp.from_advance_amount) FROM purchase_payments pp WHERE pp.from_advance_id = sa.id), 0)),
                   'Supplier advance refund'
            FROM supplier_advances sa
            JOIN suppliers s ON s.id = sa.supplier_id
            WHERE sa.status = 'refunded' AND sa.refunded_at IS NOT NULL AND sa.paid_from_kind IS NOT NULL

            UNION ALL

            -- 7. Inter-account transfers OUT → DEBIT source.
            SELECT iat.transfer_date,
                   'inter_account_transfer', iat.id, iat.transfer_number,
                   CASE iat.to_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = iat.to_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = iat.to_id)
                   END,
                   iat.from_kind, iat.from_id,
                   CASE iat.from_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = iat.from_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = iat.from_id)
                   END,
                   'transfer', 'out', iat.amount,
                   'Transfer (out)'
            FROM inter_account_transfers iat

            UNION ALL

            -- 8. Inter-account transfers IN → CREDIT destination.
            SELECT iat.transfer_date,
                   'inter_account_transfer', iat.id, iat.transfer_number,
                   CASE iat.from_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = iat.from_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = iat.from_id)
                   END,
                   iat.to_kind, iat.to_id,
                   CASE iat.to_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = iat.to_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = iat.to_id)
                   END,
                   'transfer', 'in', iat.amount,
                   'Transfer (in)'
            FROM inter_account_transfers iat

            UNION ALL

            -- 9. Capital movements → direction in (CREDIT) or out (DEBIT) as recorded.
            SELECT cm.movement_date,
                   'capital_movement', cm.id, cm.movement_number,
                   cm.counterparty_name,
                   cm.account_kind, cm.account_id,
                   CASE cm.account_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = cm.account_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = cm.account_id)
                   END,
                   'capital', cm.direction, cm.amount,
                   CONCAT(
                       CASE cm.txn_type
                           WHEN 'capital_in' THEN 'Capital injection'
                           WHEN 'drawing'    THEN 'Owner drawing'
                           WHEN 'loan_in'    THEN 'Loan received'
                           WHEN 'loan_repay' THEN 'Loan repayment'
                           ELSE 'Other capital movement'
                       END
                   )
            FROM capital_movements cm
            WHERE cm.status = 'posted'

            UNION ALL

            -- 10. Operating expenses → DEBIT.
            SELECT oe.expense_date,
                   'operating_expense', oe.id, oe.expense_number,
                   COALESCE(NULLIF(oe.vendor_name, ''), cat.name),
                   oe.paid_from_kind, oe.paid_from_id,
                   CASE oe.paid_from_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = oe.paid_from_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = oe.paid_from_id)
                   END,
                   'expense', 'out', oe.total_with_gst,
                   CONCAT('Expense — ', cat.name)
            FROM operating_expenses oe
            JOIN operating_expense_categories cat ON cat.id = oe.category_id
            WHERE oe.status = 'posted'

            UNION ALL

            -- 11. Cash-mode commission settlements → DEBIT.
            SELECT scs.settlement_date,
                   'commission_settlement', scs.id, scs.settlement_number,
                   sm.name,
                   scs.paid_from_kind, scs.paid_from_id,
                   CASE scs.paid_from_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = scs.paid_from_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = scs.paid_from_id)
                   END,
                   'commission', 'out', scs.cash_amount,
                   CONCAT('Commission paid · ', sm.name)
            FROM salesman_commission_settlements scs
            JOIN salesmen sm ON sm.id = scs.salesman_id
            WHERE scs.payout_mode = 'cash' AND scs.status = 'posted'

            UNION ALL

            -- 12. Brand rebate cash-back → CREDIT.
            SELECT br.rebate_date,
                   'brand_rebate', br.id, br.rebate_number,
                   CONCAT(b.name, ' / ', s.name),
                   br.received_into_kind, br.received_into_id,
                   CASE br.received_into_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = br.received_into_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = br.received_into_id)
                   END,
                   'rebate', 'in', br.amount,
                   CONCAT('Brand rebate · CN ', br.credit_note_number)
            FROM brand_rebates br
            JOIN brands    b ON b.id = br.brand_id
            JOIN suppliers s ON s.id = br.supplier_id
            WHERE br.mode = 'cash_back' AND br.status = 'posted'

            UNION ALL

            -- 13. Legacy (pre-v2) settlements attached directly to a customer/supplier/salesman
            --     (customer = CREDIT money in; supplier/salesman = DEBIT money out).
            SELECT ls.settlement_date,
                   CASE
                       WHEN ls.customer_id IS NOT NULL THEN 'legacy_customer_settlement'
                       WHEN ls.supplier_id IS NOT NULL THEN 'legacy_supplier_settlement'
                       WHEN ls.salesman_id IS NOT NULL THEN 'legacy_salesman_settlement'
                       ELSE 'legacy_settlement'
                   END, ls.id, CONCAT('LS-', ls.id),
                   COALESCE(
                       (SELECT name FROM customers WHERE id = ls.customer_id),
                       (SELECT name FROM suppliers WHERE id = ls.supplier_id),
                       (SELECT name FROM salesmen  WHERE id = ls.salesman_id), '?'),
                   ls.account_kind, ls.account_id,
                   CASE ls.account_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = ls.account_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = ls.account_id)
                   END,
                   'legacy',
                   CASE WHEN ls.customer_id IS NOT NULL THEN 'in' ELSE 'out' END,
                   ls.amount,
                   CONCAT('Legacy settlement · ',
                          COALESCE(
                              (SELECT name FROM customers WHERE id = ls.customer_id),
                              (SELECT name FROM suppliers WHERE id = ls.supplier_id),
                              (SELECT name FROM salesmen  WHERE id = ls.salesman_id), '?'),
                          IFNULL(CONCAT(' · ', lo.obligation_number), ' · unallocated'))
            FROM legacy_settlements ls
            LEFT JOIN legacy_obligations lo  ON lo.id = ls.obligation_id

            UNION ALL

            -- 14. Purchase-return cash refunds → CREDIT account (supplier refunded us in cash).
            SELECT pr.return_date AS event_date,
                   'purchase_return_refund' AS source_type,
                   pr.id AS source_id,
                   pr.return_number AS source_number,
                   s.name AS party_name,
                   pr.refund_account_kind AS account_kind,
                   pr.refund_account_id AS account_id,
                   CASE pr.refund_account_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = pr.refund_account_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = pr.refund_account_id)
                   END AS account_name,
                   'purchase' AS category,
                   'in' AS direction,
                   pr.cash_refund_amount AS amount,
                   CONCAT('Purchase return refund · ', pr.return_number) AS narration
            FROM purchase_returns pr
            JOIN suppliers s ON s.id = pr.supplier_id
            WHERE pr.status = 'finalized'
              AND pr.refund_method = 'cash'
              AND pr.refund_account_kind IS NOT NULL
              AND pr.cash_refund_amount > 0

            UNION ALL

            -- 15. Sales-return cash refunds → DEBIT account (we refunded the customer in cash).
            SELECT sr.return_date AS event_date,
                   'sales_return_refund' AS source_type,
                   sr.id AS source_id,
                   sr.return_number AS source_number,
                   c.name AS party_name,
                   sr.refund_account_kind AS account_kind,
                   sr.refund_account_id AS account_id,
                   CASE sr.refund_account_kind
                       WHEN 'bank_account'   THEN (SELECT name FROM bank_accounts   WHERE id = sr.refund_account_id)
                       WHEN 'cashier_drawer' THEN (SELECT name FROM cashier_drawers WHERE id = sr.refund_account_id)
                   END AS account_name,
                   'sales' AS category,
                   'out' AS direction,
                   sr.cash_refund_amount AS amount,
                   CONCAT('Sales return refund · ', sr.return_number) AS narration
            FROM sales_returns sr
            JOIN customers c ON c.id = sr.customer_id
            WHERE sr.status = 'finalized'
              AND sr.refund_method = 'cash'
              AND sr.refund_account_kind IS NOT NULL
              AND sr.cash_refund_amount > 0
        ";
    }

    /**
     * Paginated event list. Period totals (sum of inflows/outflows by category) are
     * NOT in this result — caller fetches them separately via aggregateByCategory().
     * That way the display table can show 50 rows per page without losing the totals.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginateEvents(
        ?string $from, ?string $to,
        int $page, int $perPage, string $search = '',
        ?string $accountKind = null, ?int $accountId = null,
        ?string $category = null
    ): array {
        $inner   = $this->buildEventsSql();
        $where   = [];
        $params  = [];
        if ($from !== null && $from !== '') { $where[] = 'event_date >= ?'; $params[] = $from; }
        if ($to   !== null && $to   !== '') { $where[] = 'event_date <= ?'; $params[] = $to; }
        if ($accountKind !== null && $accountKind !== '') {
            $where[] = 'account_kind = ?';
            $params[] = $accountKind;
            if ($accountId !== null && $accountId > 0) { $where[] = 'account_id = ?'; $params[] = $accountId; }
        }
        if ($category !== null && $category !== '') { $where[] = 'category = ?'; $params[] = $category; }
        if ($search !== '') {
            $where[]  = '(source_number LIKE ? OR party_name LIKE ? OR narration LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare("SELECT COUNT(*) FROM ({$inner}) AS t {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT * FROM ({$inner}) AS t {$whereSql}
             ORDER BY event_date ASC, source_type, source_id
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Sum of in-flows and out-flows grouped by category (and direction) over a period.
     * Used by the cash-flow page summary cards — independent of the paginated display.
     *
     * @return array{in: array<string,float>, out: array<string,float>, in_total: float, out_total: float}
     */
    public function aggregateByCategory(
        ?string $from, ?string $to,
        ?string $accountKind = null, ?int $accountId = null
    ): array {
        $inner  = $this->buildEventsSql();
        $where  = [];
        $params = [];
        if ($from !== null && $from !== '') { $where[] = 'event_date >= ?'; $params[] = $from; }
        if ($to   !== null && $to   !== '') { $where[] = 'event_date <= ?'; $params[] = $to; }
        if ($accountKind !== null && $accountKind !== '') {
            $where[] = 'account_kind = ?';
            $params[] = $accountKind;
            if ($accountId !== null && $accountId > 0) { $where[] = 'account_id = ?'; $params[] = $accountId; }
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . \implode(' AND ', $where);

        $sql = "SELECT category, direction, SUM(amount) AS total
                FROM ({$inner}) AS t
                {$whereSql}
                GROUP BY category, direction";
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);

        $in = []; $out = []; $inTotal = 0.0; $outTotal = 0.0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cat = (string) $r['category'];
            $amt = (float) $r['total'];
            if ($r['direction'] === 'in') { $in[$cat]  = $amt; $inTotal  += $amt; }
            else                          { $out[$cat] = $amt; $outTotal += $amt; }
        }
        \ksort($in); \ksort($out);
        return ['in' => $in, 'out' => $out, 'in_total' => $inTotal, 'out_total' => $outTotal];
    }

    /**
     * Lifetime reconciliation per account: opening + lifetime inflows − lifetime outflows
     * should equal stored current_balance. The diff column should always be ₹0.00.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reconciliation(): array
    {
        $accounts = [];
        foreach (Db::pdo()->query(
            "SELECT 'bank_account' AS kind, id, name, opening_balance, current_balance, status FROM bank_accounts
             UNION ALL
             SELECT 'cashier_drawer', id, name, opening_balance, current_balance, status FROM cashier_drawers
             ORDER BY kind, id"
        )->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $accounts[] = $a;
        }

        // Pull lifetime events once (no date filter), then aggregate in PHP.
        $events = $this->events();
        $totalsByAccount = [];
        foreach ($events as $e) {
            $key = $e['account_kind'] . ':' . $e['account_id'];
            if (!isset($totalsByAccount[$key])) {
                $totalsByAccount[$key] = ['in' => 0.0, 'out' => 0.0];
            }
            $totalsByAccount[$key][$e['direction']] += (float) $e['amount'];
        }

        $rows = [];
        foreach ($accounts as $a) {
            $key      = $a['kind'] . ':' . $a['id'];
            $inflow   = $totalsByAccount[$key]['in']  ?? 0.0;
            $outflow  = $totalsByAccount[$key]['out'] ?? 0.0;
            $opening  = (float) $a['opening_balance'];
            $current  = (float) $a['current_balance'];
            $expected = \round($opening + $inflow - $outflow, 2);
            $diff     = \round($current - $expected, 2);

            $rows[] = [
                'account_kind' => $a['kind'],
                'account_id'   => (int) $a['id'],
                'account_name' => $a['name'],
                'status'       => $a['status'],
                'opening'      => $opening,
                'inflow'       => \round($inflow,  2),
                'outflow'      => \round($outflow, 2),
                'expected'     => $expected,
                'actual'       => $current,
                'diff'         => $diff,
            ];
        }
        return $rows;
    }
}
