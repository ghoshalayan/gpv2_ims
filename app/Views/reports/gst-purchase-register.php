<?php
/** @var string $from */
/** @var string $to */
/** @var array  $rows */
/** @var array  $aggregate */
/** @var array  $rateSummary */
/** @var array  $business */
/** @var array  $pg */
?>
<?php
    $totalTaxable = (float) ($aggregate['taxable_value'] ?? 0);
    $totalCgst    = (float) ($aggregate['cgst']          ?? 0);
    $totalSgst    = (float) ($aggregate['sgst']          ?? 0);
    $totalIgst    = (float) ($aggregate['igst']          ?? 0);
    $totalTax     = (float) ($aggregate['total_tax']     ?? 0);
    $totalGrand   = (float) ($aggregate['grand_total']   ?? 0);
    $intra        = (int)   ($aggregate['intra_count']   ?? 0);
    $inter        = (int)   ($aggregate['inter_count']   ?? 0);
    $invoiceCount = (int)   ($aggregate['invoice_count'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-file-earmark-spreadsheet"></i> GST Purchase Register</h1>
    <a href="<?= url('/reports/gst-purchase-register/csv?from=' . urlencode($from) . '&to=' . urlencode($to)) ?>"
       class="btn btn-outline-success">
        <i class="bi bi-filetype-csv"></i> Export CSV
    </a>
</div>

<div class="alert alert-info py-2 small">
    Input-credit eligible purchases only (gst_claimable = 1). Non-claimable purchases still
    affect inventory cost (D12) but don't appear here. For input-credit claim under GSTR-2/3B.
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="from">From</label>
                <input type="date" id="from" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="to">To</label>
                <input type="date" id="to" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/reports/gst-purchase-register') ?>">Reset (FY)</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Invoices</div>
        <div class="h4 mb-0"><?= number_format($invoiceCount) ?></div>
        <div class="small text-muted"><?= $intra ?> intra · <?= $inter ?> inter</div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Taxable Value</div>
        <div class="h4 mb-0">₹<?= number_format($totalTaxable, 2) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Total GST (input)</div>
        <div class="h4 mb-0">₹<?= number_format($totalTax, 2) ?></div>
        <div class="small text-muted">CGST ₹<?= number_format($totalCgst, 2) ?> · SGST ₹<?= number_format($totalSgst, 2) ?> · IGST ₹<?= number_format($totalIgst, 2) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100 bg-light"><div class="card-body text-center">
        <div class="text-muted small">Grand Total</div>
        <div class="h4 mb-0 fw-bold">₹<?= number_format($totalGrand, 2) ?></div>
    </div></div></div>
</div>

<?php if ($rateSummary !== []): ?>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Summary by tax rate</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Rate</th>
                    <th class="text-end">Invoices</th>
                    <th class="text-end">Taxable Value</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end">IGST</th>
                    <th class="text-end">Total Tax</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rateSummary as $s): ?>
                    <tr>
                        <td><strong><?= number_format((float) $s['tax_rate'], 2) ?>%</strong></td>
                        <td class="text-end"><?= (int) $s['invoice_count'] ?></td>
                        <td class="text-end">₹<?= number_format((float) $s['taxable_value'], 2) ?></td>
                        <td class="text-end">₹<?= number_format((float) $s['cgst'], 2) ?></td>
                        <td class="text-end">₹<?= number_format((float) $s['sgst'], 2) ?></td>
                        <td class="text-end">₹<?= number_format((float) $s['igst'], 2) ?></td>
                        <td class="text-end fw-bold">₹<?= number_format((float) $s['total_tax'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Invoices</strong></div>
    <div class="card-body">
        <?php $searchPlaceholder = 'Search invoice #, supplier name, or GSTIN…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($rows === []): ?>
            <p class="text-center text-muted py-3 mb-0">
                <?= $pg['search'] !== '' ? 'No matches for "' . e($pg['search']) . '".' : 'No claimable purchase invoices in this period.' ?>
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>GSTIN</th>
                            <th>State</th>
                            <th>POS</th>
                            <th class="text-end">Taxable</th>
                            <th class="text-end">CGST</th>
                            <th class="text-end">SGST</th>
                            <th class="text-end">IGST</th>
                            <th class="text-end">Total Tax</th>
                            <th class="text-end">Grand Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php $taxable = (float) $r['grand_total'] - (float) $r['total_tax']; ?>
                            <tr>
                                <td>
                                    <a href="<?= url('/purchase-invoices/' . $r['id']) ?>">
                                        <strong><?= e($r['invoice_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e($r['invoice_date']) ?></td>
                                <td><?= e($r['supplier_name']) ?></td>
                                <td class="font-monospace small"><?= e($r['supplier_gstin'] ?? '') ?></td>
                                <td class="small"><?= e($r['supplier_state'] ?? '') ?></td>
                                <td class="small">
                                    <?= ((int) $r['is_intra_state'] === 1) ? 'Intra' : 'Inter' ?>
                                </td>
                                <td class="text-end">₹<?= number_format($taxable, 2) ?></td>
                                <td class="text-end">₹<?= number_format((float) $r['total_cgst'], 2) ?></td>
                                <td class="text-end">₹<?= number_format((float) $r['total_sgst'], 2) ?></td>
                                <td class="text-end">₹<?= number_format((float) $r['total_igst'], 2) ?></td>
                                <td class="text-end">₹<?= number_format((float) $r['total_tax'], 2) ?></td>
                                <td class="text-end fw-bold">₹<?= number_format((float) $r['grand_total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="6" class="text-end">Period totals (all <?= number_format($invoiceCount) ?> invoices)</th>
                            <th class="text-end">₹<?= number_format($totalTaxable, 2) ?></th>
                            <th class="text-end">₹<?= number_format($totalCgst, 2) ?></th>
                            <th class="text-end">₹<?= number_format($totalSgst, 2) ?></th>
                            <th class="text-end">₹<?= number_format($totalIgst, 2) ?></th>
                            <th class="text-end">₹<?= number_format($totalTax, 2) ?></th>
                            <th class="text-end">₹<?= number_format($totalGrand, 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>
