<?php
/** @var array  $checks */
/** @var int    $totalMismatches */
/** @var string $ranAt */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-shield-check"></i> System Integrity Check
        <?php if ($totalMismatches === 0): ?>
            <span class="badge text-bg-success ms-2">All clean</span>
        <?php else: ?>
            <span class="badge text-bg-danger ms-2"><?= $totalMismatches ?> issue(s)</span>
        <?php endif; ?>
    </h1>
    <div class="text-muted small">Ran at <?= e($ranAt) ?></div>
</div>

<div class="alert alert-<?= $totalMismatches === 0 ? 'success' : 'danger' ?> py-2 small">
    <?php if ($totalMismatches === 0): ?>
        <i class="bi bi-check-circle"></i>
        All cross-balance invariants hold. Stored totals match the sum of underlying source rows for every entity.
    <?php else: ?>
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Discrepancies detected.</strong>
        Each row below shows where a stored aggregate (e.g. <code>current_balance</code>, <code>paid_amount</code>)
        diverged from what the underlying source events would compute. Likely causes: an out-of-band SQL edit,
        a partial transaction that didn't roll back, or an unfixed bug. Investigate before relying on reports.
    <?php endif; ?>
</div>

<?php foreach ($checks as $c): ?>
    <?php $clean = $c['mismatches'] === []; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <strong><?= e($c['title']) ?></strong>
                <span class="text-muted small ms-2"><?= e($c['description']) ?></span>
            </div>
            <?php if ($clean): ?>
                <span class="badge text-bg-success">
                    <i class="bi bi-check"></i>
                    OK · <?= (int) $c['total_checked'] ?> checked
                </span>
            <?php else: ?>
                <span class="badge text-bg-danger">
                    <i class="bi bi-x"></i>
                    <?= count($c['mismatches']) ?> mismatch(es) of <?= (int) $c['total_checked'] ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if (!$clean): ?>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Entity</th>
                            <th class="text-end">Expected</th>
                            <th class="text-end">Actual (stored)</th>
                            <th class="text-end">Difference</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($c['mismatches'] as $m): ?>
                            <tr class="table-warning">
                                <td>
                                    <strong><?= e($m['entity_label']) ?></strong>
                                    <span class="text-muted small">#<?= (int) $m['entity_id'] ?></span>
                                </td>
                                <td class="text-end font-monospace">₹<?= number_format((float) $m['expected'], 2) ?></td>
                                <td class="text-end font-monospace">₹<?= number_format((float) $m['actual'], 2) ?></td>
                                <td class="text-end fw-bold font-monospace <?= (float) $m['difference'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= ((float) $m['difference'] > 0 ? '+' : '') . number_format((float) $m['difference'], 2) ?>
                                </td>
                                <td class="small text-muted"><?= e($m['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="card shadow-sm border-info">
    <div class="card-body small">
        <strong>What this checks:</strong>
        <ul class="mb-0 mt-2">
            <li><strong>Sales/purchase invoices:</strong> stored <code>paid_amount</code> matches the live sum of payments (cash + online + counted-cheque + advance applications).</li>
            <li><strong>Customer advances:</strong> for each row, <code>balance</code> equals <code>amount − applied</code> (or 0 when refunded). Applied amount must never exceed the original.</li>
            <li><strong>Bank accounts &amp; cashier drawers:</strong> <code>current_balance</code> equals <code>opening_balance</code> plus the net of every cash event touching that account — transfers, sales/purchase payments, expenses, capital movements, commission settlements, customer advances.</li>
        </ul>
        <p class="mt-2 mb-0">
            Tolerance: ₹0.01 (rounding noise is ignored). Anything larger is a real discrepancy.
        </p>
    </div>
</div>
