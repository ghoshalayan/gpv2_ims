<?php
/** @var string $from */
/** @var string $to */
/** @var string $search */
/** @var array  $rows */
/** @var array  $totals */
/** @var int    $focusId */
/** @var string $focusName */
/** @var array  $focusLines */
/** @var float  $focusEarned */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-arrow-return-left"></i> Returned Salesman Account</h1>
</div>

<div class="alert alert-info py-2 small">
    Goods returned against sales each salesman was credited for, and the commission
    reversed with them. These reversals are already netted in the salesman's passbook —
    this view breaks them out so returns can be reviewed per salesman.
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
            <div class="col-md-3">
                <label class="form-label small" for="q">Salesman</label>
                <input type="text" id="q" name="q" class="form-control form-control-sm"
                       value="<?= e($search) ?>" placeholder="Name or phone…">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/reports/returned-salesman') ?>">Reset (FY)</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Salesmen with returns</div>
        <div class="h4 mb-0"><?= count($rows) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Value returned</div>
        <div class="h4 mb-0">₹<?= number_format((float) $totals['total_value_returned'], 2) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center">
        <div class="text-muted small">Cost back into stock</div>
        <div class="h4 mb-0">₹<?= number_format((float) $totals['total_cost_returned'], 2) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100 bg-light"><div class="card-body text-center">
        <div class="text-muted small">Commission reversed</div>
        <div class="h4 mb-0 fw-bold text-danger">₹<?= number_format((float) $totals['total_commission_reversed'], 2) ?></div>
    </div></div></div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>By salesman</strong></div>
    <div class="card-body">
        <?php if ($rows === []): ?>
            <p class="text-center text-muted py-3 mb-0">
                No salesman-attributed returns in this period.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Salesman</th>
                            <th class="text-end">Returns</th>
                            <th class="text-end">Lines</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Value</th>
                            <th class="text-end">Cost</th>
                            <th class="text-end">Commission reversed</th>
                            <th>Last return</th>
                            <th class="text-end">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr class="<?= $focusId === (int) $r['id'] ? 'table-active' : '' ?>">
                                <td>
                                    <strong><?= e($r['name']) ?></strong>
                                    <?php if ($r['status'] !== 'active'): ?>
                                        <span class="badge bg-secondary">inactive</span>
                                    <?php endif; ?>
                                    <div class="small text-muted"><?= e($r['phone']) ?></div>
                                </td>
                                <td class="text-end"><?= (int) $r['return_count'] ?></td>
                                <td class="text-end"><?= (int) $r['line_count'] ?></td>
                                <td class="text-end"><?= rtrim(rtrim(number_format((float) $r['total_qty_returned'], 3, '.', ''), '0'), '.') ?></td>
                                <td class="text-end">₹<?= number_format((float) $r['total_value_returned'], 2) ?></td>
                                <td class="text-end">₹<?= number_format((float) $r['total_cost_returned'], 2) ?></td>
                                <td class="text-end text-danger">₹<?= number_format((float) $r['total_commission_reversed'], 2) ?></td>
                                <td class="small text-nowrap"><?= e($r['last_return_date']) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="<?= url('/reports/returned-salesman?from=' . urlencode($from) . '&to=' . urlencode($to) . '&salesman_id=' . (int) $r['id']) ?>">
                                        <i class="bi bi-list-ul"></i>
                                    </a>
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="<?= url('/passbook/salesman/' . (int) $r['id']) ?>"
                                       title="Salesman passbook">
                                        <i class="bi bi-journal-text"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th class="text-end">Totals</th>
                            <th colspan="3"></th>
                            <th class="text-end">₹<?= number_format((float) $totals['total_value_returned'], 2) ?></th>
                            <th class="text-end">₹<?= number_format((float) $totals['total_cost_returned'], 2) ?></th>
                            <th class="text-end">₹<?= number_format((float) $totals['total_commission_reversed'], 2) ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($focusId > 0): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Returned lines — <?= e($focusName !== '' ? $focusName : 'salesman #' . $focusId) ?></strong>
            <?php if ($focusEarned > 0): ?>
                <?php
                    $reversed = 0.0;
                    foreach ($rows as $r) {
                        if ((int) $r['id'] === $focusId) { $reversed = (float) $r['total_commission_reversed']; break; }
                    }
                ?>
                <span class="small text-muted">
                    Reversed ₹<?= number_format($reversed, 2) ?> of ₹<?= number_format($focusEarned, 2) ?>
                    earned in this period (<?= number_format(($reversed / $focusEarned) * 100, 1) ?>%)
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($focusLines === []): ?>
                <p class="text-center text-muted py-3 mb-0">No returned lines in this period.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Return #</th>
                                <th>Date</th>
                                <th>Original invoice</th>
                                <th>Customer</th>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Value</th>
                                <th class="text-end">Comm %</th>
                                <th class="text-end">Comm reversed</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($focusLines as $l): ?>
                                <tr>
                                    <td class="font-monospace small"><?= e($l['return_number'] ?? '') ?></td>
                                    <td class="small text-nowrap"><?= e($l['return_date']) ?></td>
                                    <td class="font-monospace small"><?= e($l['invoice_number']) ?></td>
                                    <td class="small"><?= e($l['customer_name']) ?></td>
                                    <td><?= e($l['display_name']) ?></td>
                                    <td class="text-end"><?= rtrim(rtrim(number_format((float) $l['quantity_returned'], 3, '.', ''), '0'), '.') ?></td>
                                    <td class="text-end">₹<?= number_format((float) $l['line_total'], 2) ?></td>
                                    <td class="text-end small"><?= number_format((float) $l['commission_rate'], 2) ?>%</td>
                                    <td class="text-end text-danger">₹<?= number_format((float) $l['commission_reversed'], 2) ?></td>
                                    <td class="small text-muted"><?= e($l['reason'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
