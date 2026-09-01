<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * CR-2 / CR-3 Due List — read-only outstanding-balance rollups.
 *
 * Two audiences, deliberately different shapes:
 *
 *   CUSTOMERS  — one direction only (they owe us). Split by due term (CR-3).
 *   SALESMEN   — two opposing streams that the business nets against each other:
 *                  Commission  = what WE owe THEM   (earned − reversed − settled)
 *                  Cash        = what THEY owe US   (dues on their mirror customer record)
 *                  Total       = Commission − Cash  (positive ⇒ we owe them)
 *                The mirror-customer link (salesmen.linked_customer_id) exists precisely
 *                so a salesman can buy on credit and have it settled against commission
 *                (D29/D38), so netting the two is the intended reading — not a shortcut.
 *
 * RECONCILIATION WITH THE BALANCE SHEET
 * The customer figures here are built from the same base as
 * BalanceSheetRepository::customerReceivables() + legacyReceivables('customer'):
 *   receivable = finalized invoice dues + legacy obligation dues
 * Customer advances are NOT netted off, because the balance sheet carries them as a
 * separate liability (customerAdvanceLiability). They are surfaced as an informational
 * credit column instead, so the headline total still ties to the balance sheet.
 *
 * The one intentional difference: this report reads the stored `sales_invoices.due_amount`
 * rather than recomputing as-of a date. The integrity check guarantees that column equals
 * grand_total − paid − finalized returns, so the two agree for "as of today" — which is
 * what a due list is for.
 *
 * TERM RESOLUTION (CR-3), used everywhere below:
 *   COALESCE(sales_invoices.due_term, customers.default_due_term)
 * NULL at both levels ⇒ the "untagged" bucket. Untagged dues still count toward Total;
 * they are simply not attributed to short or long.
 */
class DueListRepository
{
    /**
     * Per-customer outstanding, split by due term.
     *
     * @param string $search  matches customer name or phone
     * @param string $term    '', 'short', 'long' or 'untagged' — filters to customers
     *                        carrying a due in that bucket
     * @return array<int, array<string, mixed>>
     */
    public function customerDues(string $search = '', string $term = ''): array
    {
        $params = [];
        $where  = ['(inv.total_due > 0 OR leg.total_due > 0)'];

        if ($search !== '') {
            $where[]  = '(c.name LIKE ? OR c.phone LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        // Legacy dues inherit the customer's default term (a legacy obligation has no
        // per-bill tag of its own), so the bucket filter must consider both streams.
        $where[] = match ($term) {
            'short'    => "(inv.short_due > 0 OR (c.default_due_term = 'short' AND leg.total_due > 0))",
            'long'     => "(inv.long_due  > 0 OR (c.default_due_term = 'long'  AND leg.total_due > 0))",
            'untagged' => '(inv.untagged_due > 0 OR (c.default_due_term IS NULL AND leg.total_due > 0))',
            default    => '1 = 1',
        };

        $sql = "
            SELECT c.id,
                   c.name,
                   c.phone,
                   c.status,
                   c.default_due_term,
                   COALESCE(inv.total_due, 0)     AS invoice_due,
                   COALESCE(inv.short_due, 0)     AS invoice_short,
                   COALESCE(inv.long_due, 0)      AS invoice_long,
                   COALESCE(inv.untagged_due, 0)  AS invoice_untagged,
                   COALESCE(inv.invoice_count, 0) AS invoice_count,
                   inv.oldest_due_date,
                   COALESCE(leg.total_due, 0)     AS legacy_due,
                   COALESCE(leg.bill_count, 0)    AS legacy_count,
                   COALESCE(adv.credit, 0)        AS advance_credit,
                   COALESCE(inv.total_due, 0) + COALESCE(leg.total_due, 0) AS total_due,
                   -- Legacy dues follow the customer-level default term.
                   COALESCE(inv.short_due, 0)
                     + CASE WHEN c.default_due_term = 'short' THEN COALESCE(leg.total_due, 0) ELSE 0 END AS short_due,
                   COALESCE(inv.long_due, 0)
                     + CASE WHEN c.default_due_term = 'long'  THEN COALESCE(leg.total_due, 0) ELSE 0 END AS long_due,
                   COALESCE(inv.untagged_due, 0)
                     + CASE WHEN c.default_due_term IS NULL   THEN COALESCE(leg.total_due, 0) ELSE 0 END AS untagged_due
            FROM customers c
            LEFT JOIN (
                SELECT si.customer_id,
                       SUM(si.due_amount) AS total_due,
                       SUM(CASE WHEN COALESCE(si.due_term, c2.default_due_term) = 'short'
                                THEN si.due_amount ELSE 0 END) AS short_due,
                       SUM(CASE WHEN COALESCE(si.due_term, c2.default_due_term) = 'long'
                                THEN si.due_amount ELSE 0 END) AS long_due,
                       SUM(CASE WHEN COALESCE(si.due_term, c2.default_due_term) IS NULL
                                THEN si.due_amount ELSE 0 END) AS untagged_due,
                       COUNT(*)           AS invoice_count,
                       MIN(si.invoice_date) AS oldest_due_date
                FROM sales_invoices si
                JOIN customers c2 ON c2.id = si.customer_id
                WHERE si.status = 'finalized' AND si.due_amount > 0
                GROUP BY si.customer_id
            ) inv ON inv.customer_id = c.id
            LEFT JOIN (
                SELECT lo.customer_id,
                       SUM(lo.due_amount) AS total_due,
                       COUNT(*)           AS bill_count
                FROM legacy_obligations lo
                WHERE lo.customer_id IS NOT NULL AND lo.due_amount > 0
                GROUP BY lo.customer_id
            ) leg ON leg.customer_id = c.id
            LEFT JOIN (
                SELECT ca.customer_id, SUM(ca.balance) AS credit
                FROM customer_advances ca
                WHERE ca.status = 'active' AND ca.balance > 0
                GROUP BY ca.customer_id
            ) adv ON adv.customer_id = c.id
            WHERE " . \implode(' AND ', $where) . "
            ORDER BY total_due DESC, c.name ASC
        ";

        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Per-salesman outstanding: commission we owe, cash they owe, and the net.
     *
     * The commission arithmetic mirrors PassbookRepository::salesmanLedger() term for
     * term, so this report and the salesman passbook can never disagree:
     *   + sales_invoice_lines.commission_amount     (finalized invoices)
     *   − sales_return_lines.commission_reversed    (finalized returns)
     *   − salesman_commission_settlements           (posted)
     *   + legacy_obligations                        (obligation − opening_paid)
     *   − legacy_settlements
     *
     * @return array<int, array<string, mixed>>
     */
    public function salesmanDues(string $search = ''): array
    {
        $params = [];
        $where  = [];

        if ($search !== '') {
            $where[]  = '(s.name LIKE ? OR s.phone LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        // Only show salesmen who actually carry a balance on either stream.
        $where[] = '(COALESCE(earned.amt, 0) - COALESCE(reversed.amt, 0) - COALESCE(settled.amt, 0)
                     + COALESCE(legcomm.amt, 0) - COALESCE(legpaid.amt, 0)) <> 0
                    OR COALESCE(cashinv.amt, 0) > 0 OR COALESCE(cashleg.amt, 0) > 0';

        $sql = "
            SELECT s.id,
                   s.name,
                   s.phone,
                   s.status,
                   s.commission_rate,
                   s.linked_customer_id,
                   COALESCE(earned.amt, 0)   AS commission_earned,
                   COALESCE(reversed.amt, 0) AS commission_reversed,
                   COALESCE(settled.amt, 0)  AS commission_settled,
                   COALESCE(legcomm.amt, 0)  AS legacy_commission,
                   COALESCE(legpaid.amt, 0)  AS legacy_paid,
                   (COALESCE(earned.amt, 0) - COALESCE(reversed.amt, 0) - COALESCE(settled.amt, 0)
                    + COALESCE(legcomm.amt, 0) - COALESCE(legpaid.amt, 0)) AS commission_due,
                   COALESCE(cashinv.amt, 0) AS cash_invoice_due,
                   COALESCE(cashleg.amt, 0) AS cash_legacy_due,
                   (COALESCE(cashinv.amt, 0) + COALESCE(cashleg.amt, 0)) AS cash_due,
                   (COALESCE(earned.amt, 0) - COALESCE(reversed.amt, 0) - COALESCE(settled.amt, 0)
                    + COALESCE(legcomm.amt, 0) - COALESCE(legpaid.amt, 0))
                   - (COALESCE(cashinv.amt, 0) + COALESCE(cashleg.amt, 0)) AS total_due
            FROM salesmen s
            LEFT JOIN (
                SELECT sil.salesman_id, SUM(sil.commission_amount) AS amt
                FROM sales_invoice_lines sil
                JOIN sales_invoices si ON si.id = sil.sales_invoice_id
                WHERE si.status = 'finalized' AND sil.commission_amount > 0
                GROUP BY sil.salesman_id
            ) earned ON earned.salesman_id = s.id
            LEFT JOIN (
                SELECT srl.salesman_id, SUM(srl.commission_reversed) AS amt
                FROM sales_return_lines srl
                JOIN sales_returns sr ON sr.id = srl.sales_return_id
                WHERE sr.status = 'finalized' AND srl.commission_reversed > 0
                GROUP BY srl.salesman_id
            ) reversed ON reversed.salesman_id = s.id
            LEFT JOIN (
                SELECT scs.salesman_id, SUM(scs.total_settled_amount) AS amt
                FROM salesman_commission_settlements scs
                WHERE scs.status = 'posted'
                GROUP BY scs.salesman_id
            ) settled ON settled.salesman_id = s.id
            LEFT JOIN (
                SELECT lo.salesman_id,
                       SUM(GREATEST(lo.obligation_amount - lo.opening_paid_amount, 0)) AS amt
                FROM legacy_obligations lo
                WHERE lo.salesman_id IS NOT NULL
                GROUP BY lo.salesman_id
            ) legcomm ON legcomm.salesman_id = s.id
            LEFT JOIN (
                SELECT ls.salesman_id, SUM(ls.amount) AS amt
                FROM legacy_settlements ls
                WHERE ls.salesman_id IS NOT NULL
                GROUP BY ls.salesman_id
            ) legpaid ON legpaid.salesman_id = s.id
            LEFT JOIN (
                SELECT si.customer_id, SUM(si.due_amount) AS amt
                FROM sales_invoices si
                WHERE si.status = 'finalized' AND si.due_amount > 0
                GROUP BY si.customer_id
            ) cashinv ON cashinv.customer_id = s.linked_customer_id
            LEFT JOIN (
                SELECT lo.customer_id, SUM(lo.due_amount) AS amt
                FROM legacy_obligations lo
                WHERE lo.customer_id IS NOT NULL AND lo.due_amount > 0
                GROUP BY lo.customer_id
            ) cashleg ON cashleg.customer_id = s.linked_customer_id
            WHERE " . \implode(' AND ', $where) . "
            ORDER BY ABS(
                (COALESCE(earned.amt, 0) - COALESCE(reversed.amt, 0) - COALESCE(settled.amt, 0)
                 + COALESCE(legcomm.amt, 0) - COALESCE(legpaid.amt, 0))
                - (COALESCE(cashinv.amt, 0) + COALESCE(cashleg.amt, 0))
            ) DESC, s.name ASC
        ";

        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The open invoices behind one customer's due figure — the drill-down, and the
     * screen where per-invoice due_term is tagged (CR-3).
     *
     * @return array<int, array<string, mixed>>
     */
    public function customerOpenInvoices(int $customerId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT si.id,
                    si.invoice_number,
                    si.invoice_date,
                    si.grand_total,
                    si.paid_amount,
                    si.due_amount,
                    si.payment_status,
                    si.due_term,
                    c.default_due_term,
                    COALESCE(si.due_term, c.default_due_term) AS effective_due_term,
                    DATEDIFF(CURDATE(), si.invoice_date)      AS age_days
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id
             WHERE si.customer_id = ? AND si.status = 'finalized' AND si.due_amount > 0
             ORDER BY si.invoice_date ASC, si.id ASC"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CR-3 tag writes. These are the only non-read methods on this repository; they live
     * here so the whole due-term feature is contained in one repository + one controller
     * and can be removed in one step.
     *
     * due_term never participates in any financial computation, so writing it to a
     * finalized invoice does not violate finalize-immutability.
     *
     * @param string|null $term 'short', 'long', or null to clear back to untagged
     */
    public function setInvoiceDueTerm(int $invoiceId, ?string $term): void
    {
        $stmt = Db::pdo()->prepare('UPDATE sales_invoices SET due_term = ? WHERE id = ?');
        $stmt->execute([$term, $invoiceId]);
    }

    public function setCustomerDefaultDueTerm(int $customerId, ?string $term): void
    {
        $stmt = Db::pdo()->prepare('UPDATE customers SET default_due_term = ? WHERE id = ?');
        $stmt->execute([$term, $customerId]);
    }

    /** Current tag values, read before a write so the audit log can record the change. */
    public function invoiceDueTerm(int $invoiceId): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, invoice_number, due_term FROM sales_invoices WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function customerDueTerm(int $customerId): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, default_due_term FROM customers WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Column-wise totals for a set of rows already fetched. Kept in PHP rather than a
     * second aggregate query so the footer can never disagree with the rows above it.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string>               $columns
     * @return array<string, float>
     */
    public function totals(array $rows, array $columns): array
    {
        $out = \array_fill_keys($columns, 0.0);
        foreach ($rows as $r) {
            foreach ($columns as $col) {
                $out[$col] += (float) ($r[$col] ?? 0);
            }
        }
        foreach ($out as $k => $v) {
            $out[$k] = \round($v, 2);
        }
        return $out;
    }
}
