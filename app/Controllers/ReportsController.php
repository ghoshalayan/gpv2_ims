<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BalanceSheetRepository;
use App\Repositories\BusinessProfileRepository;
use App\Repositories\CashFlowRepository;
use App\Repositories\DueListRepository;
use App\Repositories\GstRegisterRepository;
use App\Repositories\GstrFilingRepository;
use App\Repositories\ProfitLossRepository;
use App\Repositories\ReturnedSalesmanRepository;

class ReportsController
{
    public function __construct(
        private CashFlowRepository       $cashFlow      = new CashFlowRepository(),
        private GstRegisterRepository    $gst           = new GstRegisterRepository(),
        private BusinessProfileRepository $business     = new BusinessProfileRepository(),
        private BalanceSheetRepository   $balanceSheet  = new BalanceSheetRepository(),
        private ProfitLossRepository     $profitLoss    = new ProfitLossRepository(),
        private GstrFilingRepository     $gstrFiling    = new GstrFilingRepository(),
        private DueListRepository        $dueList       = new DueListRepository(),
        private ReturnedSalesmanRepository $returnedSalesman = new ReturnedSalesmanRepository(),
    ) {}

    /**
     * CR-6 — Returned Salesman Account. Goods returned against sales a salesman was
     * credited for, plus the commission reversed with them. Read-only: it reports over
     * sales_return_lines, which the salesman passbook already nets, rather than keeping
     * a second copy of the same reversals.
     */
    public function returnedSalesmanAccount(): string
    {
        [$from, $to] = $this->resolveDateRange();
        $search = \trim((string) ($_GET['q'] ?? ''));
        $rows   = $this->returnedSalesman->summary($from, $to, $search);

        // Drill-down for one salesman, when asked for.
        $focusId    = (int) ($_GET['salesman_id'] ?? 0);
        $focusLines = [];
        $focusName  = '';
        $focusEarned = 0.0;
        if ($focusId > 0) {
            $focusLines  = $this->returnedSalesman->linesForSalesman($focusId, $from, $to);
            $focusEarned = $this->returnedSalesman->commissionEarnedInRange($focusId, $from, $to);
            foreach ($rows as $r) {
                if ((int) $r['id'] === $focusId) { $focusName = (string) $r['name']; break; }
            }
        }

        $totals = ['total_value_returned' => 0.0, 'total_cost_returned' => 0.0,
                   'total_commission_reversed' => 0.0, 'total_qty_returned' => 0.0];
        foreach ($rows as $r) {
            foreach ($totals as $k => $_) { $totals[$k] += (float) $r[$k]; }
        }

        return view('reports/returned-salesman', [
            'title'       => 'Returned Salesman Account',
            'from'        => $from,
            'to'          => $to,
            'search'      => $search,
            'rows'        => $rows,
            'totals'      => $totals,
            'focusId'     => $focusId,
            'focusName'   => $focusName,
            'focusLines'  => $focusLines,
            'focusEarned' => $focusEarned,
        ]);
    }

    /** Column sets the due-list footer totals are summed over. */
    private const CUSTOMER_TOTAL_COLS = [
        'invoice_due', 'legacy_due', 'advance_credit',
        'short_due', 'long_due', 'untagged_due', 'total_due',
    ];
    private const SALESMAN_TOTAL_COLS = [
        'commission_due', 'cash_due', 'total_due',
    ];

    /**
     * CR-2 / CR-3 Due List. One screen, two tabs (customer | salesman), with the
     * short/long/untagged split on the customer tab.
     */
    public function dueListReport(): string
    {
        $data = $this->buildDueList();
        return view('reports/due-list', $data);
    }

    /**
     * CR-11 — print view. Rendered through layouts/print.php, which the browser turns
     * into a PDF via "Save as PDF". No PDF library needed (the app has zero dependencies),
     * and it is the same mechanism the invoice A4 templates already use.
     */
    public function dueListPrint(): string
    {
        $data = $this->buildDueList();
        $data['business'] = $this->business->get();
        $data['printedAt'] = \date('d M Y, g:i a');
        return view('reports/due-list-print', $data, 'print');
    }

    /** CSV export, matching the GST registers' export convention. */
    public function dueListCsv(): string
    {
        $d    = $this->buildDueList();
        $tab  = $d['tab'];
        $date = \date('Y-m-d');

        if ($tab === 'salesman') {
            return $this->emitCsv("due-list-salesman_{$date}.csv", [
                ['Salesman', 'Phone', 'Status', 'Commission Due (₹)', 'Cash Due (₹)', 'Net Total (₹)', 'Direction'],
                ...\array_map(static fn($r) => [
                    $r['name'], $r['phone'], $r['status'],
                    \number_format((float) $r['commission_due'], 2, '.', ''),
                    \number_format((float) $r['cash_due'], 2, '.', ''),
                    \number_format((float) $r['total_due'], 2, '.', ''),
                    ((float) $r['total_due']) >= 0 ? 'We owe salesman' : 'Salesman owes us',
                ], $d['rows']),
            ]);
        }

        return $this->emitCsv("due-list-customer_{$date}.csv", [
            ['Customer', 'Phone', 'Status', 'Open Invoices', 'Invoice Due (₹)', 'Legacy Due (₹)',
             'Short-term (₹)', 'Long-term (₹)', 'Untagged (₹)', 'Total Due (₹)',
             'Unapplied Advance (₹)', 'Oldest Due Date'],
            ...\array_map(static fn($r) => [
                $r['name'], $r['phone'], $r['status'], $r['invoice_count'],
                \number_format((float) $r['invoice_due'], 2, '.', ''),
                \number_format((float) $r['legacy_due'], 2, '.', ''),
                \number_format((float) $r['short_due'], 2, '.', ''),
                \number_format((float) $r['long_due'], 2, '.', ''),
                \number_format((float) $r['untagged_due'], 2, '.', ''),
                \number_format((float) $r['total_due'], 2, '.', ''),
                \number_format((float) $r['advance_credit'], 2, '.', ''),
                $r['oldest_due_date'] ?? '',
            ], $d['rows']),
        ]);
    }

    /**
     * Shared between the screen, the print view and the CSV so all three can never
     * show different numbers for the same filters.
     *
     * @return array<string, mixed>
     */
    private function buildDueList(): array
    {
        $tab    = ($_GET['tab'] ?? 'customer') === 'salesman' ? 'salesman' : 'customer';
        $search = \trim((string) ($_GET['q'] ?? ''));

        $term = (string) ($_GET['term'] ?? '');
        if (!\in_array($term, ['short', 'long', 'untagged'], true)) {
            $term = '';
        }

        if ($tab === 'salesman') {
            $rows   = $this->dueList->salesmanDues($search);
            $totals = $this->dueList->totals($rows, self::SALESMAN_TOTAL_COLS);
        } else {
            $rows   = $this->dueList->customerDues($search, $term);
            $totals = $this->dueList->totals($rows, self::CUSTOMER_TOTAL_COLS);
        }

        return [
            'title'  => 'Due List',
            'tab'    => $tab,
            'term'   => $term,
            'search' => $search,
            'rows'   => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Centralized cash-flow sheet:
     *   - reconciliation panel (lifetime, opening + inflows − outflows = computed closing)
     *   - period inflows / outflows side-by-side, grouped by category
     *   - full event list, paginated/filterable
     */
    public function cashFlow(): string
    {
        [$from, $to] = $this->resolveDateRange();
        $accountKind = (string) ($_GET['account_kind'] ?? '');
        $accountId   = (int)    ($_GET['account_id']   ?? 0);
        $category    = (string) ($_GET['category'] ?? '');
        $accountIdParam = $accountId > 0 ? $accountId : null;
        $accountKindParam = $accountKind !== '' ? $accountKind : null;

        $reconciles  = $this->cashFlow->reconciliation();
        $hasMismatch = false;
        foreach ($reconciles as $r) {
            if (\abs((float) $r['diff']) > 0.01) { $hasMismatch = true; break; }
        }

        // Period totals from a SQL aggregation — independent of the paginated display.
        $agg = $this->cashFlow->aggregateByCategory($from, $to, $accountKindParam, $accountIdParam);

        // Paginated event list for the table at the bottom.
        $p = \App\Support\Paginator::fromQuery();
        $result = $this->cashFlow->paginateEvents(
            $from, $to, $p->page, $p->perPage, $p->search,
            $accountKindParam, $accountIdParam,
            $category !== '' ? $category : null
        );

        $extraQuery = [
            'from'         => $from,
            'to'           => $to,
            'account_kind' => $accountKind,
            'account_id'   => $accountId,
            'category'     => $category,
            'q'            => $p->search,
        ];

        return view('reports/cash-flow', [
            'title'        => 'Cash Inflow / Outflow',
            'from'         => $from,
            'to'           => $to,
            'accountKind'  => $accountKind,
            'accountId'    => $accountId,
            'category'     => $category,
            'reconciles'   => $reconciles,
            'hasMismatch'  => $hasMismatch,
            'events'       => $result['rows'],
            'inByCat'      => $agg['in'],
            'outByCat'     => $agg['out'],
            'inTotal'      => $agg['in_total'],
            'outTotal'     => $agg['out_total'],
            'periodNet'    => $agg['in_total'] - $agg['out_total'],
            'pg'           => $p->buildContext($result['total'], url('/reports/cash-flow'), $extraQuery),
        ]);
    }

    public function gstSalesRegister(): string
    {
        [$from, $to] = $this->resolveDateRange();
        $p = \App\Support\Paginator::fromQuery();
        $result = $this->gst->paginateSalesRegister($from, $to, $p->page, $p->perPage, $p->search);
        return view('reports/gst-sales-register', [
            'title'       => 'GST Sales Register',
            'from'        => $from,
            'to'          => $to,
            'rows'        => $result['rows'],
            'aggregate'   => $this->gst->salesAggregate($from, $to),
            'rateSummary' => $this->gst->salesSummaryByRate($from, $to),
            'business'    => $this->business->get(),
            'pg'          => $p->buildContext($result['total'], url('/reports/gst-sales-register'),
                                ['from' => $from, 'to' => $to, 'q' => $p->search]),
        ]);
    }

    public function gstSalesRegisterCsv(): string
    {
        [$from, $to] = $this->resolveDateRange();
        $rows = $this->gst->salesRegister($from, $to);
        $filename = "gst-sales-register_{$from}_to_{$to}.csv";
        return $this->emitCsv($filename, [
            ['Invoice #', 'Date', 'Customer', 'GSTIN', 'State', 'Place of Supply',
             'Bill Type', 'Tax Treatment', 'Taxable Value (₹)',
             'CGST (₹)', 'SGST (₹)', 'IGST (₹)', 'Total Tax (₹)', 'Grand Total (₹)',
             'Payment Status'],
            ...\array_map(static fn($r) => [
                $r['invoice_number'], $r['invoice_date'], $r['customer_name'],
                $r['customer_gstin'] ?? '', $r['customer_state'] ?? '',
                ((int) $r['is_intra_state'] === 1) ? 'Intra-state' : 'Inter-state',
                $r['bill_type'], $r['tax_treatment'],
                \number_format((float) $r['subtotal'], 2, '.', ''),
                \number_format((float) $r['total_cgst'], 2, '.', ''),
                \number_format((float) $r['total_sgst'], 2, '.', ''),
                \number_format((float) $r['total_igst'], 2, '.', ''),
                \number_format((float) $r['total_tax'], 2, '.', ''),
                \number_format((float) $r['grand_total'], 2, '.', ''),
                $r['payment_status'],
            ], $rows),
        ]);
    }

    public function gstPurchaseRegister(): string
    {
        [$from, $to] = $this->resolveDateRange();
        $p = \App\Support\Paginator::fromQuery();
        $result = $this->gst->paginatePurchaseRegister($from, $to, $p->page, $p->perPage, $p->search);
        return view('reports/gst-purchase-register', [
            'title'       => 'GST Purchase Register',
            'from'        => $from,
            'to'          => $to,
            'rows'        => $result['rows'],
            'aggregate'   => $this->gst->purchaseAggregate($from, $to),
            'rateSummary' => $this->gst->purchaseSummaryByRate($from, $to),
            'business'    => $this->business->get(),
            'pg'          => $p->buildContext($result['total'], url('/reports/gst-purchase-register'),
                                ['from' => $from, 'to' => $to, 'q' => $p->search]),
        ]);
    }

    public function gstPurchaseRegisterCsv(): string
    {
        [$from, $to] = $this->resolveDateRange();
        $rows = $this->gst->purchaseRegister($from, $to);
        $filename = "gst-purchase-register_{$from}_to_{$to}.csv";
        return $this->emitCsv($filename, [
            ['Invoice #', 'Date', 'Supplier', 'GSTIN', 'State', 'Place of Supply',
             'Tax Treatment', 'Taxable Value (₹)',
             'CGST (₹)', 'SGST (₹)', 'IGST (₹)', 'Total Tax (₹)', 'Grand Total (₹)',
             'Payment Status'],
            ...\array_map(static fn($r) => [
                $r['invoice_number'], $r['invoice_date'], $r['supplier_name'],
                $r['supplier_gstin'] ?? '', $r['supplier_state'] ?? '',
                ((int) $r['is_intra_state'] === 1) ? 'Intra-state' : 'Inter-state',
                $r['tax_treatment'],
                \number_format((float) ($r['grand_total'] - $r['total_tax']), 2, '.', ''),
                \number_format((float) $r['total_cgst'], 2, '.', ''),
                \number_format((float) $r['total_sgst'], 2, '.', ''),
                \number_format((float) $r['total_igst'], 2, '.', ''),
                \number_format((float) $r['total_tax'], 2, '.', ''),
                \number_format((float) $r['grand_total'], 2, '.', ''),
                $r['payment_status'],
            ], $rows),
        ]);
    }

    public function balanceSheet(): string
    {
        $asOf = (string) ($_GET['as_of'] ?? \date('Y-m-d'));
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
            $asOf = \date('Y-m-d');
        }
        return view('reports/balance-sheet', [
            'title' => 'Balance Sheet',
            'asOf'  => $asOf,
            'sheet' => $this->balanceSheet->compute($asOf),
            'business' => $this->business->get(),
        ]);
    }

    public function profitLoss(): string
    {
        [$from, $to] = $this->resolveDateRange();
        return view('reports/profit-loss', [
            'title'    => 'Profit & Loss',
            'from'     => $from,
            'to'       => $to,
            'pl'       => $this->profitLoss->compute($from, $to),
            'business' => $this->business->get(),
        ]);
    }

    public function gstr1Json(): string
    {
        [$month, $year] = $this->resolveMonth();
        $payload = $this->gstrFiling->gstr1($month, $year);
        return $this->emitJson(\sprintf('GSTR-1_%02d%04d.json', $month, $year), $payload);
    }

    public function gstr3bJson(): string
    {
        [$month, $year] = $this->resolveMonth();
        $payload = $this->gstrFiling->gstr3b($month, $year);
        return $this->emitJson(\sprintf('GSTR-3B_%02d%04d.json', $month, $year), $payload);
    }

    /** Resolve ?month= (1-12) and ?year= (YYYY); default = previous calendar month. */
    private function resolveMonth(): array
    {
        $month = (int) ($_GET['month'] ?? 0);
        $year  = (int) ($_GET['year']  ?? 0);
        if ($month < 1 || $month > 12 || $year < 2000) {
            $ts    = \strtotime('first day of last month') ?: \time();
            $month = (int) \date('n', $ts);
            $year  = (int) \date('Y', $ts);
        }
        return [$month, $year];
    }

    private function emitJson(string $filename, array $payload): string
    {
        \header('Content-Type: application/json; charset=utf-8');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');
        \header('Cache-Control: no-store');
        echo \json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return '';
    }

    /** Default = current FY; override via ?from=&to= */
    private function resolveDateRange(): array
    {
        $bp           = $this->business->get();
        $fyStartMonth = (int) ($bp['financial_year_start_month'] ?? 4);
        $today        = \date('Y-m-d');
        $year         = (int) \date('Y');
        $month        = (int) \date('n');
        $fyYear       = $month >= $fyStartMonth ? $year : ($year - 1);
        $defaultFrom  = \sprintf('%04d-%02d-01', $fyYear, $fyStartMonth);
        $defaultTo    = $today;

        $from = (string) ($_GET['from'] ?? $defaultFrom);
        $to   = (string) ($_GET['to']   ?? $defaultTo);
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = $defaultFrom; }
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = $defaultTo; }
        return [$from, $to];
    }

    private function emitCsv(string $filename, array $rows): string
    {
        \header('Content-Type: text/csv; charset=utf-8');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');
        \header('Cache-Control: no-store');
        $out = \fopen('php://output', 'w');
        // BOM so Excel reads UTF-8 (₹ symbol etc.) correctly.
        \fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            \fputcsv($out, $row);
        }
        \fclose($out);
        return '';
    }
}
