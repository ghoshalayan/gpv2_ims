<?php
/** @var string $kind */
/** @var array  $context */
/** @var array  $rows */
/** @var array  $totals */
/** @var string $from */
/** @var string $to */
/** @var float  $computedOpening */
/** @var int    $totalLifetimeRows */
?>
<?php
// Map source_type to a colored badge for the lines.
$srcBadge = [
    'sales_invoice'              => ['Sale',         'primary'],
    'sales_payment'              => ['Sale Payment', 'success'],
    'sales_return'               => ['Sale Return',  'warning'],
    'purchase_invoice'           => ['Purchase',     'primary'],
    'purchase_payment'           => ['Purch. Pay',   'success'],
    'purchase_return'            => ['Purch. Return','warning'],
    'inter_account_transfer_in'  => ['Transfer In',  'info'],
    'inter_account_transfer_out' => ['Transfer Out', 'info'],
    'operating_expense'          => ['Expense',      'danger'],
    'capital_movement'           => ['Capital/Drawing', 'dark'],
    'sales_invoice_line'         => ['Commission',   'success'],
    'sales_return_line'          => ['Comm. Reverse','warning'],
];
$invertBalance = !empty($context['invert_balance']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-text"></i> <?= e($context['title']) ?></h1>
    <div class="d-flex gap-2">
        <a href="<?= url($context['back_url']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <?= e($context['back_label']) ?>
        </a>
    </div>
</div>

<div class="alert alert-info py-2 small mb-3"><?= e($context['subtitle']) ?></div>

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
            <div class="col-md-6 d-flex gap-2 align-items-end">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= url('/passbook/' . e($kind) . '/' . (int) $context['id'] . '?from=' . date('Y-01-01') . '&to=' . date('Y-m-d')) ?>">
                    This year
                </a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= url('/passbook/' . e($kind) . '/' . (int) $context['id'] . '?from=2000-01-01&to=' . date('Y-m-d')) ?>">
                    Lifetime
                </a>
                <span class="text-muted small ms-auto">
                    Showing <?= count($rows) ?> of <?= number_format($totalLifetimeRows) ?> lifetime entries
                </span>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Opening (period start)</div>
                <div class="h5 mb-0">₹<?= number_format($computedOpening, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Total <?= e($context['debit_label']) ?></div>
                <div class="h5 mb-0 text-danger">₹<?= number_format((float) $totals['debit'], 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Total <?= e($context['credit_label']) ?></div>
                <div class="h5 mb-0 text-success">₹<?= number_format((float) $totals['credit'], 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100 bg-light">
            <div class="card-body text-center">
                <div class="text-muted small"><?= e($context['balance_label']) ?></div>
                <?php
                    // For payable (supplier) we present credit − debit as positive when we owe.
                    $finalBalance = $computedOpening;
                    foreach ($rows as $r) { $finalBalance += (float) $r['credit'] - (float) $r['debit']; }
                    $display = $invertBalance ? -$finalBalance : $finalBalance;
                    $color = $display > 0.0001 ? 'text-danger' : ($display < -0.0001 ? 'text-success' : 'text-muted');
                ?>
                <div class="h4 mb-0 fw-bold <?= $color ?>">
                    ₹<?= number_format($display, 2) ?>
                </div>
                <?php if (!empty($context['positive_means'])): ?>
                    <div class="text-muted small">+ = <?= e($context['positive_means']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($rows === []): ?>
            <div class="text-center text-muted py-4">
                No transactions yet for this entity.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-end"><?= e($context['debit_label']) ?></th>
                            <th class="text-end"><?= e($context['credit_label']) ?></th>
                            <th class="text-end"><?= e($context['balance_label']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening row -->
                        <tr class="table-secondary">
                            <td colspan="4"></td>
                            <td><em>Opening balance (as of <?= e($from) ?>)</em></td>
                            <td></td>
                            <td></td>
                            <td class="text-end fw-bold">₹<?= number_format($computedOpening, 2) ?></td>
                        </tr>
                        <?php foreach ($rows as $i => $r): ?>
                            <?php [$badgeLabel, $badgeColor] = $srcBadge[$r['source_type']] ?? ['?', 'secondary']; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="text-nowrap"><?= e($r['txn_date']) ?></td>
                                <td><span class="badge text-bg-<?= $badgeColor ?>"><?= e($badgeLabel) ?></span></td>
                                <td class="font-monospace small"><?= e($r['ref_number'] ?? '') ?></td>
                                <td class="small"><?= e($r['description'] ?? '') ?></td>
                                <td class="text-end <?= (float) $r['debit'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                    <?= (float) $r['debit'] > 0 ? '₹' . number_format((float) $r['debit'], 2) : '—' ?>
                                </td>
                                <td class="text-end <?= (float) $r['credit'] > 0 ? 'text-success' : 'text-muted' ?>">
                                    <?= (float) $r['credit'] > 0 ? '₹' . number_format((float) $r['credit'], 2) : '—' ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php $disp = $invertBalance ? -$r['running_balance'] : $r['running_balance']; ?>
                                    <span class="<?= $disp > 0 ? 'text-danger' : ($disp < 0 ? 'text-success' : '') ?>">
                                        ₹<?= number_format((float) $disp, 2) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
