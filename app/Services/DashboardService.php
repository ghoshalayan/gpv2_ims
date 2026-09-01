<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProfitLossRepository;
use App\Support\Db;
use PDO;

/**
 * Computes dashboard KPIs per section. Each public method returns a flat assoc
 * array of named KPIs ready for JSON; the view turns each into a grid of KPI
 * cards. Queries are batched with SUM(CASE …) so a whole section is 1–3 cheap
 * aggregate reads over indexed columns (status, dates). Money figures count
 * finalized documents only.
 */
class DashboardService
{
    public function __construct(
        private ProfitLossRepository $pl = new ProfitLossRepository(),
    ) {}

    /* ---------------- date helpers ---------------- */

    /** @return array{from:string,to:string,label:string} */
    public function periodRange(string $period): array
    {
        $y = (int) \date('Y');
        $m = (int) \date('n');

        switch ($period) {
            case 'quarter':
                $q  = (int) \floor(($m - 1) / 3);
                $sm = $q * 3 + 1;
                $from = \sprintf('%04d-%02d-01', $y, $sm);
                $to   = \date('Y-m-t', \strtotime(\sprintf('%04d-%02d-01', $y, $sm + 2)));
                return ['from' => $from, 'to' => $to, 'label' => 'This Quarter'];
            case 'half':
                if ($m <= 6) { $from = "{$y}-01-01"; $to = "{$y}-06-30"; }
                else         { $from = "{$y}-07-01"; $to = "{$y}-12-31"; }
                return ['from' => $from, 'to' => $to, 'label' => 'This Half-Year'];
            case 'year':
                return $this->fyRange();
            case 'month':
            default:
                return ['from' => \date('Y-m-01'), 'to' => \date('Y-m-t'), 'label' => 'This Month'];
        }
    }

    /** Financial year per business_profile.financial_year_start_month (default Apr). */
    private function fyRange(): array
    {
        $y = (int) \date('Y');
        $m = (int) \date('n');
        $fyStart = (int) ($this->scalar('SELECT financial_year_start_month FROM business_profile WHERE id = 1', []) ?: 4);
        $startYear = $m >= $fyStart ? $y : $y - 1;
        $from = \sprintf('%04d-%02d-01', $startYear, $fyStart);
        $to   = \date('Y-m-d', \strtotime($from . ' +1 year -1 day'));
        return ['from' => $from, 'to' => $to, 'label' => 'This Financial Year'];
    }

    /* ---------------- alerts (urgent banner) ---------------- */

    public function alerts(): array
    {
        $lowStock = Db::pdo()->query(
            "SELECT id, name, unit, quantity_on_hand, low_stock_threshold
             FROM products
             WHERE status='active' AND low_stock_threshold > 0 AND quantity_on_hand <= low_stock_threshold
             ORDER BY (quantity_on_hand - low_stock_threshold) ASC LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        $outOfStock = (int) $this->scalar(
            "SELECT COUNT(*) FROM products WHERE status='active' AND quantity_on_hand <= 0", []
        );

        // NOTE: reference the aggregate expression directly (not the SELECT alias)
        // in HAVING/ORDER BY — stricter MySQL/MariaDB builds reject an aggregate
        // alias used inside an ORDER BY expression (error 1247).
        $overLimit = Db::pdo()->query(
            "SELECT c.id, c.name, c.credit_limit, COALESCE(SUM(si.due_amount),0) AS outstanding
             FROM customers c
             JOIN sales_invoices si ON si.customer_id = c.id AND si.status='finalized'
             WHERE c.credit_limit > 0
             GROUP BY c.id, c.name, c.credit_limit
             HAVING COALESCE(SUM(si.due_amount),0) > c.credit_limit
             ORDER BY (COALESCE(SUM(si.due_amount),0) - c.credit_limit) DESC LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        return [
            'low_stock'           => $lowStock,
            'low_stock_count'     => \count($lowStock),
            'out_of_stock'        => $outOfStock,
            'credit_breach'       => $overLimit,
            'credit_breach_count' => \count($overLimit),
            'total'               => \count($lowStock) + \count($overLimit),
        ];
    }

    /* ---------------- Sales ---------------- */

    public function sales(): array
    {
        $today = \date('Y-m-d');
        $mFrom = \date('Y-m-01');
        $lmF   = \date('Y-m-01', \strtotime('first day of last month'));
        $lmT   = \date('Y-m-t',  \strtotime('last day of last month'));
        $fy    = $this->fyRange();

        $row = $this->row(
            "SELECT
                COALESCE(SUM(CASE WHEN invoice_date = ? THEN grand_total END),0)        AS today_amt,
                SUM(CASE WHEN invoice_date = ? THEN 1 ELSE 0 END)                        AS today_cnt,
                COALESCE(SUM(CASE WHEN invoice_date >= ? THEN grand_total END),0)        AS month_amt,
                SUM(CASE WHEN invoice_date >= ? THEN 1 ELSE 0 END)                       AS month_cnt,
                COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN grand_total END),0) AS last_amt,
                SUM(CASE WHEN invoice_date >= ? AND payment_status <> 'paid' THEN 1 ELSE 0 END) AS month_unpaid_cnt,
                COALESCE(SUM(grand_total),0)                                             AS fy_amt
             FROM sales_invoices
             WHERE status='finalized' AND invoice_date BETWEEN ? AND ?",
            [$today, $today, $mFrom, $mFrom, $lmF, $lmT, $mFrom, $fy['from'], $fy['to']]
        );
        $receivable = (float) $this->scalar("SELECT COALESCE(SUM(due_amount),0) FROM sales_invoices WHERE status='finalized'", []);
        $returnsMonth = (float) $this->scalar(
            "SELECT COALESCE(SUM(grand_total),0) FROM sales_returns WHERE status='finalized' AND return_date >= ?", [$mFrom]
        );
        $top = $this->row(
            "SELECT c.name, COALESCE(SUM(si.grand_total),0) AS amt
             FROM sales_invoices si JOIN customers c ON c.id = si.customer_id
             WHERE si.status='finalized' AND si.invoice_date >= ?
             GROUP BY c.id, c.name ORDER BY amt DESC LIMIT 1", [$mFrom]
        );
        $monthAmt = (float) $row['month_amt']; $lastAmt = (float) $row['last_amt']; $monthCnt = (int) $row['month_cnt'];

        return [
            'today_amount'   => \round((float) $row['today_amt'], 2),
            'today_count'    => (int) $row['today_cnt'],
            'month_amount'   => \round($monthAmt, 2),
            'month_count'    => $monthCnt,
            'last_month'     => \round($lastAmt, 2),
            'mom_pct'        => $lastAmt > 0 ? \round(($monthAmt - $lastAmt) / $lastAmt * 100, 1) : null,
            'avg_invoice'    => $monthCnt > 0 ? \round($monthAmt / $monthCnt, 2) : 0.0,
            'fy_amount'      => \round((float) $row['fy_amt'], 2),
            'month_unpaid'   => (int) $row['month_unpaid_cnt'],
            'returns_month'  => \round($returnsMonth, 2),
            'receivable'     => \round($receivable, 2),
            'top_customer'   => $top ? ['name' => $top['name'], 'amount' => \round((float) $top['amt'], 2)] : null,
            // chart + table data
            'trend'          => $this->salesPurchaseTrend(),
            'payment_split'  => $this->paymentSplit(),
            'top_customers'  => $this->topCustomers(),
        ];
    }

    /* ---------------- Purchase ---------------- */

    public function purchase(): array
    {
        $mFrom = \date('Y-m-01');
        $lmF   = \date('Y-m-01', \strtotime('first day of last month'));
        $lmT   = \date('Y-m-t',  \strtotime('last day of last month'));
        $fy    = $this->fyRange();

        $row = $this->row(
            "SELECT
                COALESCE(SUM(CASE WHEN invoice_date >= ? THEN grand_total END),0)        AS month_amt,
                SUM(CASE WHEN invoice_date >= ? THEN 1 ELSE 0 END)                       AS month_cnt,
                COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN grand_total END),0) AS last_amt,
                SUM(CASE WHEN invoice_date >= ? AND payment_status <> 'paid' THEN 1 ELSE 0 END) AS month_unpaid_cnt,
                COALESCE(SUM(grand_total),0)                                             AS fy_amt
             FROM purchase_invoices
             WHERE status='finalized' AND invoice_date BETWEEN ? AND ?",
            [$mFrom, $mFrom, $lmF, $lmT, $mFrom, $fy['from'], $fy['to']]
        );
        $payable = (float) $this->scalar("SELECT COALESCE(SUM(due_amount),0) FROM purchase_invoices WHERE status='finalized'", []);
        $returnsMonth = (float) $this->scalar(
            "SELECT COALESCE(SUM(grand_total),0) FROM purchase_returns WHERE status='finalized' AND return_date >= ?", [$mFrom]
        );
        $top = $this->row(
            "SELECT s.name, COALESCE(SUM(pi.grand_total),0) AS amt
             FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id
             WHERE pi.status='finalized' AND pi.invoice_date >= ?
             GROUP BY s.id, s.name ORDER BY amt DESC LIMIT 1", [$mFrom]
        );
        $monthAmt = (float) $row['month_amt']; $lastAmt = (float) $row['last_amt']; $monthCnt = (int) $row['month_cnt'];

        return [
            'month_amount'  => \round($monthAmt, 2),
            'month_count'   => $monthCnt,
            'last_month'    => \round($lastAmt, 2),
            'mom_pct'       => $lastAmt > 0 ? \round(($monthAmt - $lastAmt) / $lastAmt * 100, 1) : null,
            'avg_bill'      => $monthCnt > 0 ? \round($monthAmt / $monthCnt, 2) : 0.0,
            'fy_amount'     => \round((float) $row['fy_amt'], 2),
            'month_unpaid'  => (int) $row['month_unpaid_cnt'],
            'returns_month' => \round($returnsMonth, 2),
            'payable'       => \round($payable, 2),
            'top_supplier'  => $top ? ['name' => $top['name'], 'amount' => \round((float) $top['amt'], 2)] : null,
            'top_suppliers' => $this->topSuppliers(),
        ];
    }

    /* ---------------- Profit & Loss ---------------- */

    public function profitLoss(string $period): array
    {
        $r = $this->periodRange($period);
        $pl = $this->pl->compute($r['from'], $r['to']);
        return [
            'period'           => $period,
            'period_label'     => $r['label'],
            'from'             => $r['from'],
            'to'               => $r['to'],
            'revenue'          => $pl['revenue']['net'],
            'cogs'             => $pl['cogs']['net'],
            'gross_profit'     => $pl['gross_profit'],
            'other_income'     => $pl['other_income']['total'],
            'opex'             => $pl['operating_expenses']['total'],
            'net_profit'       => $pl['net_profit'],
            'gross_margin_pct' => $pl['gross_margin_pct'],
            'net_margin_pct'   => $pl['net_margin_pct'],
        ];
    }

    /* ---------------- Customers ---------------- */

    public function customers(): array
    {
        $mFrom = \date('Y-m-01');
        $base = $this->row(
            "SELECT
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END)   AS active,
                SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END)   AS new_month
             FROM customers", [$mFrom . ' 00:00:00']
        );
        $due = $this->row(
            "SELECT COUNT(*) AS debtors, COALESCE(SUM(due),0) AS total_due, COALESCE(AVG(due),0) AS avg_due
             FROM (
                SELECT c.id, SUM(si.due_amount) AS due
                FROM customers c JOIN sales_invoices si ON si.customer_id=c.id AND si.status='finalized'
                GROUP BY c.id HAVING due > 0
             ) x", []
        );
        $overLimit = (int) $this->scalar(
            "SELECT COUNT(*) FROM (
                SELECT c.id FROM customers c JOIN sales_invoices si ON si.customer_id=c.id AND si.status='finalized'
                WHERE c.credit_limit > 0 GROUP BY c.id, c.credit_limit HAVING SUM(si.due_amount) > c.credit_limit
             ) y", []
        );
        $top = $this->row(
            "SELECT c.name, COALESCE(SUM(si.due_amount),0) AS due
             FROM customers c JOIN sales_invoices si ON si.customer_id=c.id AND si.status='finalized'
             GROUP BY c.id, c.name HAVING due > 0 ORDER BY due DESC LIMIT 1", []
        );
        return [
            'total_active'    => (int) ($base['active'] ?? 0),
            'total_inactive'  => (int) ($base['inactive'] ?? 0),
            'new_month'       => (int) ($base['new_month'] ?? 0),
            'over_limit'      => $overLimit,
            'with_dues'       => (int) ($due['debtors'] ?? 0),
            'receivable'      => \round((float) ($due['total_due'] ?? 0), 2),
            'avg_outstanding' => \round((float) ($due['avg_due'] ?? 0), 2),
            'top_debtor'      => $top ? ['name' => $top['name'], 'amount' => \round((float) $top['due'], 2)] : null,
            'top_debtors'     => $this->topDebtors(),
        ];
    }

    /* ---------------- Salesmen ---------------- */

    public function salesmen(): array
    {
        $mFrom = \date('Y-m-01');
        $fy    = $this->fyRange();
        $active = (int) $this->scalar("SELECT COUNT(*) FROM salesmen WHERE status='active'", []);
        $accruedAll = (float) $this->scalar("SELECT COALESCE(SUM(total_commission),0) FROM sales_invoices WHERE status='finalized'", []);
        $accruedFy  = (float) $this->scalar(
            "SELECT COALESCE(SUM(total_commission),0) FROM sales_invoices WHERE status='finalized' AND invoice_date BETWEEN ? AND ?",
            [$fy['from'], $fy['to']]
        );
        $settledAll = (float) $this->scalar("SELECT COALESCE(SUM(total_settled_amount),0) FROM salesman_commission_settlements WHERE status='posted'", []);
        $settledFy  = (float) $this->scalar(
            "SELECT COALESCE(SUM(total_settled_amount),0) FROM salesman_commission_settlements WHERE status='posted' AND settlement_date BETWEEN ? AND ?",
            [$fy['from'], $fy['to']]
        );
        $withSales = (int) $this->scalar(
            "SELECT COUNT(DISTINCT header_salesman_id) FROM sales_invoices WHERE status='finalized' AND invoice_date >= ? AND header_salesman_id IS NOT NULL",
            [$mFrom]
        );
        $top = $this->row(
            "SELECT sm.name, COALESCE(SUM(si.grand_total),0) AS sales
             FROM salesmen sm JOIN sales_invoices si ON si.header_salesman_id=sm.id AND si.status='finalized' AND si.invoice_date >= ?
             GROUP BY sm.id, sm.name ORDER BY sales DESC LIMIT 1", [$mFrom]
        );
        return [
            'total_active'           => $active,
            'commission_outstanding' => \round(\max(0, $accruedAll - $settledAll), 2),
            'commission_accrued_fy'  => \round($accruedFy, 2),
            'commission_settled_fy'  => \round($settledFy, 2),
            'with_sales_month'       => $withSales,
            'top'                    => $top ? ['name' => $top['name'], 'amount' => \round((float) $top['sales'], 2)] : null,
            'top_list'               => $this->topSalesmenList(),
        ];
    }

    /* ---------------- Inventory ---------------- */

    public function inventory(): array
    {
        $mFrom = \date('Y-m-01');
        $row = $this->row(
            "SELECT
                COALESCE(SUM(quantity_on_hand * moving_avg_cost),0) AS stock_value,
                COUNT(*) AS sku_count,
                SUM(CASE WHEN quantity_on_hand <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN low_stock_threshold > 0 AND quantity_on_hand <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN is_composite = 1 THEN 1 ELSE 0 END) AS composite_count
             FROM products WHERE status='active'", []
        );
        $adjMonth = (int) $this->scalar(
            "SELECT COUNT(*) FROM stock_adjustments WHERE status='posted' AND adjustment_date >= ?", [$mFrom]
        );
        $top = $this->row(
            "SELECT name, ROUND(quantity_on_hand * moving_avg_cost, 2) AS val
             FROM products WHERE status='active' ORDER BY val DESC LIMIT 1", []
        );
        return [
            'stock_value'     => \round((float) ($row['stock_value'] ?? 0), 2),
            'sku_count'       => (int) ($row['sku_count'] ?? 0),
            'low_stock'       => (int) ($row['low_stock'] ?? 0),
            'out_of_stock'    => (int) ($row['out_of_stock'] ?? 0),
            'composite_count' => (int) ($row['composite_count'] ?? 0),
            'adj_month'       => $adjMonth,
            'top_value'       => $top ? ['name' => $top['name'], 'amount' => \round((float) $top['val'], 2)] : null,
            'by_brand'        => $this->stockByBrand(),
            'top_products'    => $this->topProductsByValue(),
        ];
    }

    /* ---------------- Cash & Bank ---------------- */

    public function cashBank(): array
    {
        $mFrom = \date('Y-m-01');
        $bankRow = $this->row("SELECT COALESCE(SUM(current_balance),0) AS bal, COUNT(*) AS n FROM bank_accounts WHERE status='active'", []);
        $cashRow = $this->row("SELECT COALESCE(SUM(current_balance),0) AS bal, COUNT(*) AS n FROM cashier_drawers WHERE status='active'", []);

        $inflow = (float) $this->scalar(
            "SELECT COALESCE(SUM(cash_amount + online_amount + CASE WHEN cheque_status IN ('pending','cleared') THEN cheque_amount ELSE 0 END),0)
             FROM sales_payments WHERE payment_date >= ?", [$mFrom]
        );
        $outPay = (float) $this->scalar(
            "SELECT COALESCE(SUM(cash_amount + online_amount + CASE WHEN cheque_status IN ('pending','cleared') THEN cheque_amount ELSE 0 END),0)
             FROM purchase_payments WHERE payment_date >= ?", [$mFrom]
        );
        $outExp = (float) $this->scalar(
            "SELECT COALESCE(SUM(total_with_gst),0) FROM operating_expenses WHERE status='posted' AND expense_date >= ?", [$mFrom]
        );
        $bank = (float) $bankRow['bal']; $cash = (float) $cashRow['bal'];
        return [
            'total'         => \round($bank + $cash, 2),
            'bank'          => \round($bank, 2),
            'cash'          => \round($cash, 2),
            'bank_accounts' => (int) $bankRow['n'],
            'drawers'       => (int) $cashRow['n'],
            'inflow_month'  => \round($inflow, 2),
            'outflow_month' => \round($outPay + $outExp, 2),
        ];
    }

    /* ---------------- trend / charts / tables ---------------- */

    /** Last 12 months sales + purchase totals (finalized) for a combined bar chart. */
    public function salesPurchaseTrend(): array
    {
        $from = \date('Y-m-01', \strtotime('-11 months'));
        $sales = $this->keyedTotals(
            "SELECT DATE_FORMAT(invoice_date,'%Y-%m') ym, COALESCE(SUM(grand_total),0) amt
             FROM sales_invoices WHERE status='finalized' AND invoice_date >= ? GROUP BY ym", [$from]
        );
        $purch = $this->keyedTotals(
            "SELECT DATE_FORMAT(invoice_date,'%Y-%m') ym, COALESCE(SUM(grand_total),0) amt
             FROM purchase_invoices WHERE status='finalized' AND invoice_date >= ? GROUP BY ym", [$from]
        );
        $labels = []; $salesS = []; $purchS = [];
        for ($i = 11; $i >= 0; $i--) {
            $ym = \date('Y-m', \strtotime("-{$i} months"));
            $labels[] = \date('M y', \strtotime($ym . '-01'));
            $salesS[] = \round((float) ($sales[$ym] ?? 0), 2);
            $purchS[] = \round((float) ($purch[$ym] ?? 0), 2);
        }
        return ['labels' => $labels, 'sales' => $salesS, 'purchase' => $purchS];
    }

    /** Collected vs outstanding on finalized sales (doughnut). */
    public function paymentSplit(): array
    {
        $r = $this->row(
            "SELECT COALESCE(SUM(paid_amount),0) AS collected, COALESCE(SUM(due_amount),0) AS outstanding
             FROM sales_invoices WHERE status='finalized'", []
        );
        return [
            'collected'   => \round((float) ($r['collected'] ?? 0), 2),
            'outstanding' => \round((float) ($r['outstanding'] ?? 0), 2),
        ];
    }

    /** Top 5 customers by FY sales, with their outstanding. */
    public function topCustomers(): array
    {
        $fy = $this->fyRange();
        return $this->all(
            "SELECT c.name, COALESCE(SUM(si.grand_total),0) AS sales, COALESCE(SUM(si.due_amount),0) AS due
             FROM sales_invoices si JOIN customers c ON c.id = si.customer_id
             WHERE si.status='finalized' AND si.invoice_date BETWEEN ? AND ?
             GROUP BY c.id, c.name ORDER BY sales DESC LIMIT 5", [$fy['from'], $fy['to']]
        );
    }

    /** Top 5 suppliers by FY purchases. */
    public function topSuppliers(): array
    {
        $fy = $this->fyRange();
        return $this->all(
            "SELECT s.name, COALESCE(SUM(pi.grand_total),0) AS purchases, COALESCE(SUM(pi.due_amount),0) AS due
             FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id
             WHERE pi.status='finalized' AND pi.invoice_date BETWEEN ? AND ?
             GROUP BY s.id, s.name ORDER BY purchases DESC LIMIT 5", [$fy['from'], $fy['to']]
        );
    }

    /** Top 5 salesmen by this-month sales. */
    public function topSalesmenList(): array
    {
        $mFrom = \date('Y-m-01');
        return $this->all(
            "SELECT sm.name, COALESCE(SUM(si.grand_total),0) AS sales, COALESCE(SUM(si.total_commission),0) AS commission
             FROM salesmen sm JOIN sales_invoices si ON si.header_salesman_id = sm.id
                  AND si.status='finalized' AND si.invoice_date >= ?
             GROUP BY sm.id, sm.name ORDER BY sales DESC LIMIT 5", [$mFrom]
        );
    }

    /** Top 5 debtors by outstanding (all-time finalized). */
    public function topDebtors(): array
    {
        return $this->all(
            "SELECT c.name, COALESCE(SUM(si.due_amount),0) AS due
             FROM customers c JOIN sales_invoices si ON si.customer_id = c.id AND si.status='finalized'
             GROUP BY c.id, c.name HAVING due > 0 ORDER BY due DESC LIMIT 5", []
        );
    }

    /** Top 5 products by on-hand stock value. */
    public function topProductsByValue(): array
    {
        return $this->all(
            "SELECT name, unit, quantity_on_hand AS qty, ROUND(quantity_on_hand * moving_avg_cost, 2) AS value
             FROM products WHERE status='active' AND quantity_on_hand > 0
             ORDER BY value DESC LIMIT 5", []
        );
    }

    /** Stock value by brand (top 6) for a doughnut. */
    public function stockByBrand(): array
    {
        return $this->all(
            "SELECT b.name, ROUND(SUM(p.quantity_on_hand * p.moving_avg_cost), 2) AS value
             FROM products p JOIN brands b ON b.id = p.brand_id
             WHERE p.status='active'
             GROUP BY b.id, b.name HAVING value > 0 ORDER BY value DESC LIMIT 6", []
        );
    }

    /* ---------------- helpers ---------------- */

    private function scalar(string $sql, array $params): mixed
    {
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function row(string $sql, array $params): array
    {
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r === false ? [] : $r;
    }

    private function all(string $sql, array $params): array
    {
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,float> keyed by the first column. */
    private function keyedTotals(string $sql, array $params): array
    {
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) { $out[(string) $r[0]] = (float) $r[1]; }
        return $out;
    }
}
