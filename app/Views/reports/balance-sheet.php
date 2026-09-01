<?php
/** @var string $asOf */
/** @var array  $sheet */
/** @var array  $business */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-clipboard-data"></i> Balance Sheet</h1>
    <div class="text-muted small">
        <strong><?= e($business['legal_name'] ?? '—') ?></strong><br>
        As at <strong><?= e($asOf) ?></strong>
    </div>
</div>

<div class="alert alert-<?= $sheet['balanced'] ? 'success' : 'danger' ?> py-2 small">
    <?php if ($sheet['balanced']): ?>
        <i class="bi bi-shield-check"></i> <strong>Balanced.</strong>
        Assets = Liabilities + Equity (₹<?= number_format($sheet['assets']['total'], 2) ?>)
    <?php else: ?>
        <i class="bi bi-exclamation-triangle"></i> <strong>Imbalance:</strong>
        Assets ₹<?= number_format($sheet['assets']['total'], 2) ?>
        ≠ Liab + Equity ₹<?= number_format($sheet['total_liabilities_and_equity'], 2) ?>
        — diff ₹<?= number_format($sheet['imbalance'], 2) ?>.
        Investigate via the
        <a href="<?= url('/system/integrity-check') ?>">integrity check</a>.
    <?php endif; ?>
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="as_of">As of date</label>
                <input type="date" id="as_of" name="as_of" class="form-control form-control-sm" value="<?= e($asOf) ?>">
            </div>
            <div class="col-md-9 d-flex gap-2 align-items-end">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/reports/balance-sheet') ?>">Today</a>
                <span class="text-muted small ms-2">
                    Caveat: stock value is the current snapshot (no historical stock-history table in v1).
                </span>
            </div>
        </div>
    </div>
</form>

<div class="row g-3">
    <!-- Assets -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 border-success">
            <div class="card-header bg-success text-white">
                <strong>Assets</strong>
                <span class="float-end">₹<?= number_format($sheet['assets']['total'], 2) ?></span>
            </div>
            <table class="table table-sm align-middle mb-0">
                <tbody>
                    <tr>
                        <td>
                            <strong>Cash &amp; bank</strong>
                            <details class="mt-1 small text-muted">
                                <summary>per-account breakdown</summary>
                                <table class="table table-sm mt-1 mb-0">
                                    <?php foreach ($sheet['assets']['cash_and_bank']['accounts'] as $a): ?>
                                        <tr>
                                            <td class="ps-3"><?= e($a['name']) ?>
                                                <small class="text-muted">(<?= e(str_replace('_', ' ', $a['kind'])) ?>)</small>
                                            </td>
                                            <td class="text-end">₹<?= number_format((float) $a['balance'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </details>
                        </td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['assets']['cash_and_bank']['total'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>
                            <strong>Stock value</strong>
                            <small class="text-muted">(current snapshot · qty × moving-avg cost)</small>
                            <details class="mt-1 small text-muted">
                                <summary>top items by value</summary>
                                <table class="table table-sm mt-1 mb-0">
                                    <?php foreach (\array_slice($sheet['assets']['stock_value']['rows'], 0, 10) as $r): ?>
                                        <tr>
                                            <td class="ps-3"><?= e($r['name']) ?>
                                                <small class="text-muted"><?= (int) $r['quantity_on_hand'] ?> × ₹<?= number_format((float) $r['moving_avg_cost'], 4) ?></small>
                                            </td>
                                            <td class="text-end">₹<?= number_format((float) $r['line_value'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </details>
                        </td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['assets']['stock_value']['total'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Customer receivables</strong>
                            <small class="text-muted">(unpaid sales invoices)</small></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['assets']['customer_receivables'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Supplier advances</strong>
                            <small class="text-muted">(prepaid to suppliers, applicable to future bills)</small></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['assets']['supplier_advances'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Legacy customer receivables</strong>
                            <small class="text-muted">(pre-v2 customer dues)</small></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['assets']['legacy_customer_recv'], 2) ?></td>
                    </tr>
                    <?php if ($sheet['assets']['gst_input_credit_excess'] > 0.001): ?>
                        <tr>
                            <td><strong>GST input credit (excess)</strong>
                                <small class="text-muted">(input GST &gt; output GST collected)</small></td>
                            <td class="text-end fw-bold">₹<?= number_format($sheet['assets']['gst_input_credit_excess'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th>Total assets</th>
                        <th class="text-end">₹<?= number_format($sheet['assets']['total'], 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Liabilities + Equity -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning text-dark">
                <strong>Liabilities</strong>
                <span class="float-end">₹<?= number_format($sheet['liabilities']['total'], 2) ?></span>
            </div>
            <table class="table table-sm align-middle mb-0">
                <tbody>
                    <tr>
                        <td><strong>Supplier payables</strong>
                            <small class="text-muted">(unpaid purchase bills)</small></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['liabilities']['supplier_payables'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Customer advances</strong>
                            <small class="text-muted">(received, not yet absorbed)</small></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['liabilities']['customer_advances'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Loans outstanding</strong></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['liabilities']['loans'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Legacy supplier payables</strong>
                            <small class="text-muted">(pre-v2 supplier dues)</small></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['liabilities']['legacy_supplier_pay'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Legacy salesman payables</strong>
                            <small class="text-muted">(pre-v2 commission owed)</small></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['liabilities']['legacy_salesman_pay'], 2) ?></td>
                    </tr>
                    <?php if ($sheet['liabilities']['gst_output_payable'] > 0.001): ?>
                        <tr>
                            <td><strong>GST output payable</strong>
                                <small class="text-muted">(output collected − input claimed)</small></td>
                            <td class="text-end fw-bold">₹<?= number_format($sheet['liabilities']['gst_output_payable'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th>Total liabilities</th>
                        <th class="text-end">₹<?= number_format($sheet['liabilities']['total'], 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card shadow-sm border-info mt-3">
            <div class="card-header bg-info text-white">
                <strong>Equity</strong>
                <span class="float-end">₹<?= number_format($sheet['equity']['total'], 2) ?></span>
            </div>
            <table class="table table-sm align-middle mb-0">
                <tbody>
                    <tr>
                        <td><strong>Opening balance equity</strong>
                            <small class="text-muted">(go-live cash + opening stock; auto-balancing)</small></td>
                        <td class="text-end fw-bold <?= $sheet['equity']['opening_equity'] < 0 ? 'text-danger' : '' ?>">
                            ₹<?= number_format($sheet['equity']['opening_equity'], 2) ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Owner capital (net)</strong>
                            <small class="text-muted">(injections − drawings posted in v2)</small></td>
                        <td class="text-end fw-bold">₹<?= number_format($sheet['equity']['capital_net'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Retained earnings</strong>
                            <small class="text-muted">(lifetime net P&amp;L)</small></td>
                        <td class="text-end fw-bold <?= $sheet['equity']['retained_earnings'] < 0 ? 'text-danger' : '' ?>">
                            ₹<?= number_format($sheet['equity']['retained_earnings'], 2) ?>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th>Total equity</th>
                        <th class="text-end">₹<?= number_format($sheet['equity']['total'], 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card shadow-sm bg-light mt-3">
            <div class="card-body py-2 text-center">
                <span class="text-muted">Liabilities + Equity</span>
                <span class="ms-3 fs-5 fw-bold">₹<?= number_format($sheet['total_liabilities_and_equity'], 2) ?></span>
            </div>
        </div>
    </div>
</div>
