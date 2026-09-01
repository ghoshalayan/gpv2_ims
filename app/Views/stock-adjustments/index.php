<?php
/** @var array  $adjustments */
/** @var string $reasonFilter */
/** @var string $directionFilter */
/** @var string $statusFilter */
/** @var array  $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-clipboard-x"></i> Stock Adjustments</h1>
    <a href="<?= url('/stock-adjustments/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Post Adjustment
    </a>
</div>

<div class="alert alert-info py-2 small">
    Write-offs (damage / expiry / theft) and physical-count corrections.
    <strong>Out</strong> adjustments stamp current moving-avg cost as the COGS basis;
    <strong>In</strong> adjustments (count_correction → found stock) require admin-entered cost.
    Voiding an adjustment reverses its stock effect.
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="f_reason">Reason</label>
                <select id="f_reason" name="reason" class="form-select form-select-sm">
                    <option value="">All reasons</option>
                    <?php foreach (['damage', 'expiry', 'theft', 'count_correction', 'other'] as $r): ?>
                        <option value="<?= $r ?>" <?= $reasonFilter === $r ? 'selected' : '' ?>>
                            <?= e(ucwords(str_replace('_', ' ', $r))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_dir">Direction</label>
                <select id="f_dir" name="direction" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="out" <?= $directionFilter === 'out' ? 'selected' : '' ?>>Out (write-off)</option>
                    <option value="in"  <?= $directionFilter === 'in'  ? 'selected' : '' ?>>In (found stock)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_sts">Status</label>
                <select id="f_sts" name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="posted" <?= $statusFilter === 'posted' ? 'selected' : '' ?>>Posted</option>
                    <option value="voided" <?= $statusFilter === 'voided' ? 'selected' : '' ?>>Voided</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/stock-adjustments') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search adjustment #…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($adjustments === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No matches for "<?= e($pg['search']) ?>".
                <?php elseif ($reasonFilter || $directionFilter || $statusFilter): ?>
                    No adjustments match the current filters.
                <?php else: ?>
                    No adjustments yet. <a href="<?= url('/stock-adjustments/create') ?>">Post the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Number</th>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Direction</th>
                            <th class="text-end">Lines</th>
                            <th class="text-end">Total Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $reasonColors = [
                                'damage'           => 'danger',
                                'expiry'           => 'warning',
                                'theft'            => 'dark',
                                'count_correction' => 'info',
                                'other'            => 'secondary',
                            ];
                        ?>
                        <?php foreach ($adjustments as $i => $a): ?>
                            <?php $rc = $reasonColors[$a['reason']] ?? 'secondary'; ?>
                            <tr class="<?= $a['status'] !== 'posted' ? 'text-muted' : '' ?>">
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <a href="<?= url('/stock-adjustments/' . $a['id']) ?>">
                                        <strong><?= e($a['adjustment_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e($a['adjustment_date']) ?></td>
                                <td><span class="badge text-bg-<?= $rc ?>"><?= e(str_replace('_', ' ', $a['reason'])) ?></span></td>
                                <td>
                                    <?php if ($a['direction'] === 'out'): ?>
                                        <span class="badge text-bg-danger">↓ Out</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">↑ In</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= (int) $a['line_count'] ?></td>
                                <td class="text-end fw-bold">₹<?= number_format((float) $a['total_value'], 2) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $a['status'] === 'posted' ? 'success' : 'secondary' ?>">
                                        <?= e($a['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>
