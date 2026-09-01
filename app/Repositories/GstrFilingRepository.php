<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;

/**
 * GSTR-1 / GSTR-3B JSON generation. Field names follow the GSTN offline-tool schema as
 * closely as possible so the output can be plugged into the official utility for
 * validation and upload. Numbers are encoded as strings with 2 decimals to match the
 * portal's expected format.
 *
 * GSTR-1 sections covered (v1):
 *   gstin       — filer GSTIN (from business_profile)
 *   fp          — filing period MMYYYY
 *   b2b         — B2B invoices (customer has GSTIN)
 *   b2cs        — B2C summarised by rate × place-of-supply (no customer GSTIN)
 *   hsn         — HSN-wise outward supply summary
 *
 * GSTR-3B sections covered (v1):
 *   gstin, ret_period
 *   sup_details.osup_det      — outward taxable supplies (other than zero-rated/nil/exempt)
 *   inter_sup.unreg_details   — inter-state supplies to unregistered persons
 *   itc_elg.itc_avl[0]        — all-other ITC
 *   intr_ltfee                — left blank for v1
 *
 * Deferred (v2):
 *   B2CL (B2C invoices over threshold per state), CDNR (credit/debit notes), exports,
 *   nil-rated/exempt details, reverse charge, ineligible ITC sections.
 */
class GstrFilingRepository
{
    public function __construct(
        private BusinessProfileRepository $business = new BusinessProfileRepository(),
    ) {}

    /**
     * Build GSTR-1 JSON for the given month (1-12) and year (YYYY).
     */
    public function gstr1(int $month, int $year): array
    {
        [$from, $to] = $this->monthBounds($month, $year);
        $bp = $this->business->get();

        return [
            'gstin'   => $bp['gstin'] ?? '',
            'fp'      => \sprintf('%02d%04d', $month, $year),
            'gt'      => $this->fmt($this->scalar(
                "SELECT COALESCE(SUM(grand_total), 0) FROM sales_invoices
                 WHERE status = 'finalized' AND invoice_date BETWEEN ? AND ?",
                [$from, $to]
            )),
            'b2b'     => $this->b2b($from, $to),
            'b2cs'    => $this->b2cs($from, $to),
            'hsn'     => $this->hsnSummary($from, $to, /*outward*/ true),
        ];
    }

    /**
     * Build GSTR-3B JSON for the given month (1-12) and year (YYYY).
     */
    public function gstr3b(int $month, int $year): array
    {
        [$from, $to] = $this->monthBounds($month, $year);
        $bp = $this->business->get();

        // 3.1 Outward supplies (taxable, non-zero rate, non-exempt).
        // Aggregate from sales_invoices of any tax_treatment except 'no_tax'.
        $row = Db::pdo()->prepare(
            "SELECT COALESCE(SUM(subtotal),    0) AS taxable,
                    COALESCE(SUM(total_cgst),  0) AS cgst,
                    COALESCE(SUM(total_sgst),  0) AS sgst,
                    COALESCE(SUM(total_igst),  0) AS igst
             FROM sales_invoices
             WHERE status = 'finalized'
               AND tax_treatment <> 'no_tax'
               AND invoice_date BETWEEN ? AND ?"
        );
        $row->execute([$from, $to]);
        $supplies = $row->fetch(\PDO::FETCH_ASSOC) ?: ['taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0];

        // 3.2 inter-state to unregistered persons (no GSTIN, inter-state).
        $unreg = $this->b2cInterStateByState($from, $to);

        // 4 Eligible ITC — all-other from claimable purchases.
        $itc = Db::pdo()->prepare(
            "SELECT COALESCE(SUM(total_cgst), 0) AS cgst,
                    COALESCE(SUM(total_sgst), 0) AS sgst,
                    COALESCE(SUM(total_igst), 0) AS igst
             FROM purchase_invoices
             WHERE status = 'finalized' AND gst_claimable = 1
               AND invoice_date BETWEEN ? AND ?"
        );
        $itc->execute([$from, $to]);
        $itcRow = $itc->fetch(\PDO::FETCH_ASSOC) ?: ['cgst'=>0,'sgst'=>0,'igst'=>0];

        return [
            'gstin'      => $bp['gstin'] ?? '',
            'ret_period' => \sprintf('%02d%04d', $month, $year),
            'sup_details' => [
                'osup_det' => [
                    'txval' => $this->fmt($supplies['taxable']),
                    'iamt'  => $this->fmt($supplies['igst']),
                    'camt'  => $this->fmt($supplies['cgst']),
                    'samt'  => $this->fmt($supplies['sgst']),
                    'csamt' => '0.00',
                ],
                // The remaining osup_zero / osup_nil_exmp / osup_nongst / isup_rev are
                // deferred to v2 — populated as zero shells for schema completeness.
                'osup_zero'    => ['txval'=>'0.00','iamt'=>'0.00','csamt'=>'0.00'],
                'osup_nil_exmp'=> ['txval'=>'0.00'],
                'isup_rev'     => ['txval'=>'0.00','iamt'=>'0.00','camt'=>'0.00','samt'=>'0.00','csamt'=>'0.00'],
                'osup_nongst'  => ['txval'=>'0.00'],
            ],
            'inter_sup' => [
                'unreg_details' => $unreg,
                'comp_details'  => [],
                'uin_details'   => [],
            ],
            'itc_elg' => [
                'itc_avl' => [
                    [
                        'ty'    => 'OTH',                  // all other ITC
                        'iamt'  => $this->fmt($itcRow['igst']),
                        'camt'  => $this->fmt($itcRow['cgst']),
                        'samt'  => $this->fmt($itcRow['sgst']),
                        'csamt' => '0.00',
                    ],
                ],
                'itc_rev'   => [],
                'itc_net'   => [
                    'iamt'  => $this->fmt($itcRow['igst']),
                    'camt'  => $this->fmt($itcRow['cgst']),
                    'samt'  => $this->fmt($itcRow['sgst']),
                    'csamt' => '0.00',
                ],
                'itc_inelg' => [],
            ],
            'inward_sup' => [
                'isup_details' => [
                    ['ty'=>'GST',   'inter'=>'0.00', 'intra'=>'0.00'],
                    ['ty'=>'NONGST','inter'=>'0.00', 'intra'=>'0.00'],
                ],
            ],
        ];
    }

    /* ---------- GSTR-1 sections ---------- */

    private function b2b(string $from, string $to): array
    {
        // GSTIN-based grouping. Each customer with GSTIN → one cust block; their invoices
        // are nested. Invoice-level totals are split by rate.
        $custMap = [];
        $stmt = Db::pdo()->prepare(
            "SELECT si.id, si.invoice_number, si.invoice_date, si.is_intra_state,
                    si.grand_total,
                    c.gstin AS cust_gstin, c.name AS cust_name, c.state AS cust_state
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id
             WHERE si.status = 'finalized'
               AND si.tax_treatment <> 'no_tax'
               AND c.gstin IS NOT NULL AND c.gstin <> ''
               AND si.invoice_date BETWEEN ? AND ?
             ORDER BY si.invoice_date, si.id"
        );
        $stmt->execute([$from, $to]);
        $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Pull per-line tax breakup once.
        $linesByInvoice = $this->linesByInvoice(\array_column($invoices, 'id'), 'sales');

        foreach ($invoices as $inv) {
            $gstin = $inv['cust_gstin'];
            if (!isset($custMap[$gstin])) {
                $custMap[$gstin] = [
                    'ctin' => $gstin,
                    'inv'  => [],
                ];
            }
            $stateCode = $this->stateCode($inv['cust_state'] ?? '');
            $itms = $this->groupItemsByRate($linesByInvoice[$inv['id']] ?? []);
            $custMap[$gstin]['inv'][] = [
                'inum'    => $inv['invoice_number'],
                'idt'     => $this->fmtDate($inv['invoice_date']),
                'val'     => $this->fmt($inv['grand_total']),
                'pos'     => $stateCode,
                'rchrg'   => 'N',
                'inv_typ' => 'R',
                'itms'    => $itms,
            ];
        }
        return \array_values($custMap);
    }

    /** B2C summarised by rate × place-of-supply (state). */
    private function b2cs(string $from, string $to): array
    {
        // Effective rate: line tax_rate when set, else fall back to invoice_level_tax_rate
        // (discount-then-tax mode allocates GST at invoice level — line.tax_rate stays 0).
        $stmt = Db::pdo()->prepare(
            "SELECT c.state                AS pos,
                    si.is_intra_state,
                    CASE WHEN sil.tax_rate > 0 THEN sil.tax_rate
                         ELSE si.invoice_level_tax_rate END AS rt,
                    SUM(sil.subtotal)      AS txval,
                    SUM(sil.cgst_amount)   AS camt,
                    SUM(sil.sgst_amount)   AS samt,
                    SUM(sil.igst_amount)   AS iamt
             FROM sales_invoice_lines sil
             JOIN sales_invoices si ON si.id = sil.sales_invoice_id
             JOIN customers c       ON c.id = si.customer_id
             WHERE si.status = 'finalized'
               AND si.tax_treatment <> 'no_tax'
               AND (c.gstin IS NULL OR c.gstin = '')
               AND si.invoice_date BETWEEN ? AND ?
             GROUP BY c.state, si.is_intra_state,
                      CASE WHEN sil.tax_rate > 0 THEN sil.tax_rate ELSE si.invoice_level_tax_rate END
             ORDER BY c.state, rt"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'sply_ty' => ((int) $r['is_intra_state'] === 1) ? 'INTRA' : 'INTER',
                'pos'     => $this->stateCode($r['pos'] ?? ''),
                'typ'     => 'OE',                                  // OE = Other than e-commerce
                'rt'      => $this->fmt($r['rt']),
                'txval'   => $this->fmt($r['txval']),
                'iamt'    => $this->fmt($r['iamt']),
                'camt'    => $this->fmt($r['camt']),
                'samt'    => $this->fmt($r['samt']),
                'csamt'   => '0.00',
            ];
        }
        return $out;
    }

    private function hsnSummary(string $from, string $to, bool $outward): array
    {
        $sql = $outward
            ? "SELECT p.hsn_code, p.unit, sil.tax_rate,
                      SUM(sil.quantity) AS qty,
                      SUM(sil.subtotal) AS txval,
                      SUM(sil.cgst_amount) AS camt,
                      SUM(sil.sgst_amount) AS samt,
                      SUM(sil.igst_amount) AS iamt,
                      SUM(sil.tax_amount)  AS tax_total
               FROM sales_invoice_lines sil
               JOIN sales_invoices si ON si.id = sil.sales_invoice_id
               JOIN products p        ON p.id = sil.product_id
               WHERE si.status = 'finalized' AND si.invoice_date BETWEEN ? AND ?
               GROUP BY p.hsn_code, p.unit, sil.tax_rate
               ORDER BY p.hsn_code, sil.tax_rate"
            : '';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $data = [];
        $num = 1;
        foreach ($rows as $r) {
            $data[] = [
                'num'    => $num++,
                'hsn_sc' => (string) ($r['hsn_code'] ?: ''),
                'uqc'    => $this->uqcCode((string) ($r['unit'] ?? '')),
                'qty'    => $this->fmtQty($r['qty']),
                'rt'     => $this->fmt($r['tax_rate']),
                'txval'  => $this->fmt($r['txval']),
                'iamt'   => $this->fmt($r['iamt']),
                'camt'   => $this->fmt($r['camt']),
                'samt'   => $this->fmt($r['samt']),
                'csamt'  => '0.00',
            ];
        }
        return ['data' => $data];
    }

    /* ---------- GSTR-3B helpers ---------- */

    private function b2cInterStateByState(string $from, string $to): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT c.state                AS pos,
                    SUM(sil.subtotal)      AS txval,
                    SUM(sil.igst_amount)   AS iamt
             FROM sales_invoice_lines sil
             JOIN sales_invoices si ON si.id = sil.sales_invoice_id
             JOIN customers c       ON c.id = si.customer_id
             WHERE si.status = 'finalized'
               AND si.tax_treatment <> 'no_tax'
               AND si.is_intra_state = 0
               AND (c.gstin IS NULL OR c.gstin = '')
               AND si.invoice_date BETWEEN ? AND ?
             GROUP BY c.state
             ORDER BY c.state"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'pos'   => $this->stateCode($r['pos'] ?? ''),
                'txval' => $this->fmt($r['txval']),
                'iamt'  => $this->fmt($r['iamt']),
            ];
        }
        return $out;
    }

    /* ---------- shared helpers ---------- */

    private function linesByInvoice(array $invoiceIds, string $kind): array
    {
        if ($invoiceIds === []) { return []; }
        $placeholders = \implode(',', \array_fill(0, \count($invoiceIds), '?'));
        $table = $kind === 'sales' ? 'sales_invoice_lines' : 'purchase_invoice_lines';
        $fk    = $kind === 'sales' ? 'sales_invoice_id'    : 'purchase_invoice_id';
        $stmt = Db::pdo()->prepare(
            "SELECT {$fk} AS invoice_id, tax_rate, subtotal, cgst_amount, sgst_amount, igst_amount, tax_amount
             FROM {$table}
             WHERE {$fk} IN ({$placeholders})"
        );
        $stmt->execute($invoiceIds);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['invoice_id']][] = $r;
        }
        return $out;
    }

    private function groupItemsByRate(array $lines): array
    {
        $byRate = [];
        foreach ($lines as $l) {
            $rate = (string) (float) $l['tax_rate'];
            if (!isset($byRate[$rate])) {
                $byRate[$rate] = ['rt'=>(float)$rate, 'txval'=>0.0, 'cgst'=>0.0, 'sgst'=>0.0, 'igst'=>0.0];
            }
            $byRate[$rate]['txval'] += (float) $l['subtotal'];
            $byRate[$rate]['cgst']  += (float) $l['cgst_amount'];
            $byRate[$rate]['sgst']  += (float) $l['sgst_amount'];
            $byRate[$rate]['igst']  += (float) $l['igst_amount'];
        }
        $items = [];
        $num = 1;
        foreach ($byRate as $g) {
            $items[] = [
                'num' => $num++,
                'itm_det' => [
                    'rt'    => $this->fmt($g['rt']),
                    'txval' => $this->fmt($g['txval']),
                    'iamt'  => $this->fmt($g['igst']),
                    'camt'  => $this->fmt($g['cgst']),
                    'samt'  => $this->fmt($g['sgst']),
                    'csamt' => '0.00',
                ],
            ];
        }
        return $items;
    }

    private function fmt(mixed $v): string
    {
        return \number_format((float) $v, 2, '.', '');
    }

    private function fmtQty(mixed $v): string
    {
        return \number_format((float) $v, 3, '.', '');
    }

    private function fmtDate(string $iso): string
    {
        // YYYY-MM-DD → DD-MM-YYYY (GSTN format)
        $ts = \strtotime($iso) ?: \time();
        return \date('d-m-Y', $ts);
    }

    private function stateCode(string $stateName): string
    {
        // GSTN state-code map. Falls back to '' if not recognized.
        static $map = null;
        if ($map === null) {
            $map = [
                'jammu and kashmir'=>'01','himachal pradesh'=>'02','punjab'=>'03',
                'chandigarh'=>'04','uttarakhand'=>'05','haryana'=>'06','delhi'=>'07',
                'rajasthan'=>'08','uttar pradesh'=>'09','bihar'=>'10','sikkim'=>'11',
                'arunachal pradesh'=>'12','nagaland'=>'13','manipur'=>'14','mizoram'=>'15',
                'tripura'=>'16','meghalaya'=>'17','assam'=>'18','west bengal'=>'19',
                'jharkhand'=>'20','odisha'=>'21','chhattisgarh'=>'22','madhya pradesh'=>'23',
                'gujarat'=>'24','daman and diu'=>'25','dadra and nagar haveli'=>'26',
                'maharashtra'=>'27','andhra pradesh'=>'28','karnataka'=>'29','goa'=>'30',
                'lakshadweep'=>'31','kerala'=>'32','tamil nadu'=>'33','puducherry'=>'34',
                'andaman and nicobar islands'=>'35','telangana'=>'36','ladakh'=>'38',
            ];
        }
        $key = \strtolower(\trim($stateName));
        return $map[$key] ?? '';
    }

    private function uqcCode(string $unit): string
    {
        // Map of common product units to GSTN UQC codes.
        static $map = [
            'pcs'=>'PCS-PIECES','piece'=>'PCS-PIECES','no'=>'NOS-NUMBERS','nos'=>'NOS-NUMBERS',
            'kg'=>'KGS-KILOGRAMS','kgs'=>'KGS-KILOGRAMS','gms'=>'GMS-GRAMMES',
            'ltr'=>'LTR-LITRES','litre'=>'LTR-LITRES','ml'=>'MLT-MILILITRE',
            'mtr'=>'MTR-METERS','metre'=>'MTR-METERS','box'=>'BOX-BOX','set'=>'SET-SET',
            'pkt'=>'PAC-PACKS','pack'=>'PAC-PACKS','bag'=>'BAG-BAGS',
            'bottle'=>'BTL-BOTTLES','can'=>'CAN-CANS','tin'=>'TIN-TINS',
            'roll'=>'ROL-ROLLS','sheet'=>'SHT-SHEETS',
        ];
        $k = \strtolower(\trim($unit));
        return $map[$k] ?? 'OTH-OTHERS';
    }

    private function monthBounds(int $month, int $year): array
    {
        $month = \max(1, \min(12, $month));
        $from  = \sprintf('%04d-%02d-01', $year, $month);
        $to    = \date('Y-m-t', \strtotime($from) ?: \time());
        return [$from, $to];
    }

    private function scalar(string $sql, array $params): float
    {
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}
