<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Db;
use PDO;

/**
 * GST Sales / Purchase Register data — invoice-level rows for GSTR-1 and input-credit
 * filing. CGST/SGST/IGST split is per-invoice (D52). Tax_treatment in ('exclusive',
 * 'inclusive') counts as taxable; 'no_tax' invoices don't appear in these registers.
 */
class GstRegisterRepository
{
    /**
     * Sales register: every finalized sales_invoice in the date range, with totals.
     * Cancelled invoices are excluded.
     */
    public function salesRegister(string $from, string $to): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT si.id,
                    si.invoice_number,
                    si.invoice_date,
                    si.bill_type,
                    c.name        AS customer_name,
                    c.gstin       AS customer_gstin,
                    c.state       AS customer_state,
                    si.is_intra_state,
                    si.tax_treatment,
                    si.subtotal,
                    si.total_cgst,
                    si.total_sgst,
                    si.total_igst,
                    si.total_tax,
                    si.grand_total,
                    si.payment_status
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id
             WHERE si.status = 'finalized'
               AND si.invoice_date BETWEEN ? AND ?
             ORDER BY si.invoice_date ASC, si.id ASC"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Purchase register: only gst_claimable=1 finalized purchase_invoices count for input
     * credit. Non-claimable purchases still affect inventory cost (D12) but don't show here.
     */
    public function purchaseRegister(string $from, string $to): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT pi.id,
                    pi.invoice_number,
                    pi.invoice_date,
                    s.name        AS supplier_name,
                    s.gstin       AS supplier_gstin,
                    s.state       AS supplier_state,
                    pi.is_intra_state,
                    pi.tax_treatment,
                    pi.gst_claimable,
                    pi.total_cgst,
                    pi.total_sgst,
                    pi.total_igst,
                    pi.total_tax,
                    pi.grand_total,
                    pi.payment_status
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             WHERE pi.status = 'finalized'
               AND pi.gst_claimable = 1
               AND pi.invoice_date BETWEEN ? AND ?
             ORDER BY pi.invoice_date ASC, pi.id ASC"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Per-rate summary for the sales register. Effective rate = line.tax_rate when set,
     * else invoice_level_tax_rate (the discount-then-tax bill format applies GST at the
     * invoice level — line.tax_rate stays 0 even though CGST/SGST/IGST are populated).
     */
    public function salesSummaryByRate(string $from, string $to): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT CASE WHEN sil.tax_rate > 0 THEN sil.tax_rate
                         ELSE si.invoice_level_tax_rate END AS tax_rate,
                    COUNT(DISTINCT si.id) AS invoice_count,
                    SUM(sil.subtotal)     AS taxable_value,
                    SUM(sil.cgst_amount)  AS cgst,
                    SUM(sil.sgst_amount)  AS sgst,
                    SUM(sil.igst_amount)  AS igst,
                    SUM(sil.tax_amount)   AS total_tax
             FROM sales_invoice_lines sil
             JOIN sales_invoices si ON si.id = sil.sales_invoice_id
             WHERE si.status = 'finalized'
               AND si.invoice_date BETWEEN ? AND ?
             GROUP BY CASE WHEN sil.tax_rate > 0 THEN sil.tax_rate ELSE si.invoice_level_tax_rate END
             ORDER BY tax_rate"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Same shape for purchase side, with the same effective-rate fallback. */
    public function purchaseSummaryByRate(string $from, string $to): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT CASE WHEN pil.tax_rate > 0 THEN pil.tax_rate
                         ELSE pi.invoice_level_tax_rate END AS tax_rate,
                    COUNT(DISTINCT pi.id) AS invoice_count,
                    SUM(pil.subtotal)     AS taxable_value,
                    SUM(pil.cgst_amount)  AS cgst,
                    SUM(pil.sgst_amount)  AS sgst,
                    SUM(pil.igst_amount)  AS igst,
                    SUM(pil.tax_amount)   AS total_tax
             FROM purchase_invoice_lines pil
             JOIN purchase_invoices pi ON pi.id = pil.purchase_invoice_id
             WHERE pi.status = 'finalized'
               AND pi.gst_claimable = 1
               AND pi.invoice_date BETWEEN ? AND ?
             GROUP BY CASE WHEN pil.tax_rate > 0 THEN pil.tax_rate ELSE pi.invoice_level_tax_rate END
             ORDER BY tax_rate"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paginated sales register with optional search across invoice number / customer name.
     * Uses the same date-range + finalized-only filter as salesRegister().
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginateSalesRegister(string $from, string $to, int $page, int $perPage, string $search = ''): array
    {
        $where  = ["si.status = 'finalized'", "si.invoice_date BETWEEN ? AND ?"];
        $params = [$from, $to];
        if ($search !== '') {
            $where[] = '(si.invoice_number LIKE ? OR c.name LIKE ? OR c.gstin LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM sales_invoices si JOIN customers c ON c.id = si.customer_id {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT si.id, si.invoice_number, si.invoice_date, si.bill_type,
                    c.name AS customer_name, c.gstin AS customer_gstin, c.state AS customer_state,
                    si.is_intra_state, si.tax_treatment, si.subtotal,
                    si.total_cgst, si.total_sgst, si.total_igst, si.total_tax,
                    si.grand_total, si.payment_status
             FROM sales_invoices si
             JOIN customers c ON c.id = si.customer_id
             {$whereSql}
             ORDER BY si.invoice_date ASC, si.id ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Period-level aggregates for the summary cards at the top of the sales register.
     * Independent of pagination — reflects ALL invoices in the date range.
     */
    public function salesAggregate(string $from, string $to): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT COUNT(*) AS invoice_count,
                    COALESCE(SUM(subtotal), 0)    AS taxable_value,
                    COALESCE(SUM(total_cgst), 0)  AS cgst,
                    COALESCE(SUM(total_sgst), 0)  AS sgst,
                    COALESCE(SUM(total_igst), 0)  AS igst,
                    COALESCE(SUM(total_tax), 0)   AS total_tax,
                    COALESCE(SUM(grand_total), 0) AS grand_total,
                    SUM(CASE WHEN is_intra_state = 1 THEN 1 ELSE 0 END) AS intra_count,
                    SUM(CASE WHEN is_intra_state = 0 THEN 1 ELSE 0 END) AS inter_count
             FROM sales_invoices
             WHERE status = 'finalized' AND invoice_date BETWEEN ? AND ?"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Same shape for purchase side. */
    public function purchaseAggregate(string $from, string $to): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT COUNT(*) AS invoice_count,
                    COALESCE(SUM(grand_total - total_tax), 0) AS taxable_value,
                    COALESCE(SUM(total_cgst), 0)  AS cgst,
                    COALESCE(SUM(total_sgst), 0)  AS sgst,
                    COALESCE(SUM(total_igst), 0)  AS igst,
                    COALESCE(SUM(total_tax), 0)   AS total_tax,
                    COALESCE(SUM(grand_total), 0) AS grand_total,
                    SUM(CASE WHEN is_intra_state = 1 THEN 1 ELSE 0 END) AS intra_count,
                    SUM(CASE WHEN is_intra_state = 0 THEN 1 ELSE 0 END) AS inter_count
             FROM purchase_invoices
             WHERE status = 'finalized' AND gst_claimable = 1 AND invoice_date BETWEEN ? AND ?"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginatePurchaseRegister(string $from, string $to, int $page, int $perPage, string $search = ''): array
    {
        $where  = ["pi.status = 'finalized'", "pi.gst_claimable = 1", "pi.invoice_date BETWEEN ? AND ?"];
        $params = [$from, $to];
        if ($search !== '') {
            $where[] = '(pi.invoice_number LIKE ? OR s.name LIKE ? OR s.gstin LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = ' WHERE ' . \implode(' AND ', $where);

        $count = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset  = \max(0, ($page - 1) * $perPage);
        $perPage = \max(1, $perPage);
        $stmt = Db::pdo()->prepare(
            "SELECT pi.id, pi.invoice_number, pi.invoice_date,
                    s.name AS supplier_name, s.gstin AS supplier_gstin, s.state AS supplier_state,
                    pi.is_intra_state, pi.tax_treatment, pi.gst_claimable,
                    pi.total_cgst, pi.total_sgst, pi.total_igst, pi.total_tax,
                    pi.grand_total, pi.payment_status
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             {$whereSql}
             ORDER BY pi.invoice_date ASC, pi.id ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }
}
