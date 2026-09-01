<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * CR-6 (second half) — "Returned Salesman Account": goods returned against sales a
 * salesman was credited for, and the commission reversed with them.
 *
 * Deliberately a REPORT over existing data, not a new ledger. Everything it needs is
 * already recorded on sales_return_lines (salesman_id, quantity_returned,
 * commission_reversed, cogs_total), and PassbookRepository::salesmanLedger() already
 * nets those reversals against earned commission. Adding a parallel account table would
 * mean the same reversal was represented twice and could drift out of agreement with
 * the passbook — so this reads, and never writes.
 */
class ReturnedSalesmanRepository
{
    /**
     * Per-salesman rollup of returned goods over a date range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summary(string $from, string $to, string $search = ''): array
    {
        $params = [$from, $to];
        $where  = ["sr.status = 'finalized'", 'sr.return_date BETWEEN ? AND ?'];

        if ($search !== '') {
            $where[]  = '(s.name LIKE ? OR s.phone LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $stmt = Db::pdo()->prepare(
            'SELECT s.id,
                    s.name,
                    s.phone,
                    s.status,
                    COUNT(DISTINCT sr.id)                    AS return_count,
                    COUNT(srl.id)                            AS line_count,
                    COALESCE(SUM(srl.quantity_returned), 0)  AS total_qty_returned,
                    COALESCE(SUM(srl.line_total), 0)         AS total_value_returned,
                    COALESCE(SUM(srl.cogs_total), 0)         AS total_cost_returned,
                    COALESCE(SUM(srl.commission_reversed), 0) AS total_commission_reversed,
                    MAX(sr.return_date)                      AS last_return_date
             FROM sales_return_lines srl
             JOIN sales_returns sr ON sr.id = srl.sales_return_id
             JOIN salesmen s       ON s.id = srl.salesman_id
             WHERE ' . \implode(' AND ', $where) . '
             GROUP BY s.id, s.name, s.phone, s.status
             ORDER BY total_value_returned DESC, s.name ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The individual returned lines behind one salesman's figures — the drill-down.
     *
     * @return array<int, array<string, mixed>>
     */
    public function linesForSalesman(int $salesmanId, string $from, string $to): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT sr.return_number,
                    sr.return_date,
                    sr.reason,
                    si.invoice_number,
                    c.name AS customer_name,
                    srl.display_name,
                    srl.quantity_returned,
                    srl.unit_price,
                    srl.line_total,
                    srl.cogs_total,
                    srl.commission_rate,
                    srl.commission_reversed
             FROM sales_return_lines srl
             JOIN sales_returns sr  ON sr.id = srl.sales_return_id
             JOIN sales_invoices si ON si.id = sr.original_sales_invoice_id
             JOIN customers c       ON c.id = sr.customer_id
             WHERE srl.salesman_id = ?
               AND sr.status = 'finalized'
               AND sr.return_date BETWEEN ? AND ?
             ORDER BY sr.return_date DESC, sr.id DESC"
        );
        $stmt->execute([$salesmanId, $from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cross-check figure: total commission a salesman earned in the same window, so the
     * report can show reversals in proportion rather than as a bare number.
     */
    public function commissionEarnedInRange(int $salesmanId, string $from, string $to): float
    {
        $stmt = Db::pdo()->prepare(
            "SELECT COALESCE(SUM(sil.commission_amount), 0)
             FROM sales_invoice_lines sil
             JOIN sales_invoices si ON si.id = sil.sales_invoice_id
             WHERE sil.salesman_id = ? AND si.status = 'finalized'
               AND si.invoice_date BETWEEN ? AND ?"
        );
        $stmt->execute([$salesmanId, $from, $to]);
        return (float) $stmt->fetchColumn();
    }
}
