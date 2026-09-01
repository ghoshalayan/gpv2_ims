<?php
/** @var array $row */
/** @var array $lines */
?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<?php
    $reasonColors = [
        'damage'           => 'danger',
        'expiry'           => 'warning',
        'theft'            => 'dark',
        'count_correction' => 'info',
        'other'            => 'secondary',
    ];
    $rc = $reasonColors[$row['reason']] ?? 'secondary';
    $stsColor = $row['status'] === 'posted' ? 'success' : 'secondary';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-clipboard-x"></i> <?= e($row['adjustment_number']) ?>
        <span class="badge text-bg-<?= $rc ?> ms-2"><?= e(str_replace('_', ' ', $row['reason'])) ?></span>
        <?php if ($row['direction'] === 'out'): ?>
            <span class="badge text-bg-danger ms-1">↓ Out</span>
        <?php else: ?>
            <span class="badge text-bg-success ms-1">↑ In</span>
        <?php endif; ?>
        <span class="badge text-bg-<?= $stsColor ?> ms-1"><?= e($row['status']) ?></span>
    </h1>
    <a href="<?= url('/stock-adjustments') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($row['status'] === 'voided'): ?>
    <div class="alert alert-warning small">
        <strong>Voided</strong> on <?= e($row['voided_at']) ?>.
        Reason: <?= e($row['void_reason'] ?? '—') ?>.
        Stock effects have been reversed.
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Details</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Date:</strong> <?= e($row['adjustment_date']) ?></div>
                    <div class="col-md-4"><strong>Reason:</strong>
                        <span class="badge text-bg-<?= $rc ?>"><?= e(str_replace('_', ' ', $row['reason'])) ?></span>
                    </div>
                    <div class="col-md-4"><strong>Direction:</strong>
                        <?= $row['direction'] === 'out' ? '<span class="text-danger">Out — stock left</span>' : '<span class="text-success">In — stock found</span>' ?>
                    </div>
                    <?php if ($row['remarks']): ?>
                        <div class="col-12"><strong>Remarks:</strong> <?= e($row['remarks']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Total</strong></div>
            <div class="card-body text-center">
                <div class="h3 mb-0 fw-bold <?= $row['direction'] === 'out' ? 'text-danger' : 'text-success' ?>">
                    ₹<?= number_format((float) $row['total_value'], 2) ?>
                </div>
                <div class="text-muted small">across <?= count($lines) ?> line(s)</div>
            </div>
        </div>

        <?php if ($isAdmin && $row['status'] === 'posted'): ?>
            <form method="post" action="<?= url('/stock-adjustments/' . $row['id'] . '/void') ?>" class="mt-3"
                  onsubmit="return confirm('Void this adjustment? Stock changes will be reversed.')">
                <?= \App\Support\Csrf::field() ?>
                <input type="text" name="void_reason" maxlength="255" class="form-control mb-2"
                       placeholder="Reason for void (required)" required>
                <button class="btn btn-outline-danger w-100">
                    <i class="bi bi-x-octagon"></i> Void Adjustment (admin)
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Lines</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">#</th>
                    <th>Product</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Cost / unit</th>
                    <th class="text-end">Line Value</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $l): ?>
                    <tr>
                        <td><?= (int) $l['line_no'] ?></td>
                        <td><?= e($l['product_name']) ?></td>
                        <td class="text-end"><?= rtrim(rtrim(number_format((float) $l['quantity'], 3, '.', ''), '0'), '.') ?>
                            <span class="text-muted small"><?= e($l['product_unit']) ?></span></td>
                        <td class="text-end">₹<?= number_format((float) $l['cost_at_adjustment'], 4) ?></td>
                        <td class="text-end fw-bold">₹<?= number_format((float) $l['line_value'], 2) ?></td>
                        <td class="text-muted small"><?= e($l['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <th colspan="4" class="text-end">Total</th>
                    <th class="text-end">₹<?= number_format((float) $row['total_value'], 2) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
