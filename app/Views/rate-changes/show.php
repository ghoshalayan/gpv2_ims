<?php
/** @var array $batch */
/** @var array $lines */
?>
<?php
    $scope = array_filter([
        $batch['category_name'] ?? null,
        $batch['subcategory_name'] ?? null,
        $batch['brand_name'] ?? null,
    ]);
    $modeLabel = match ($batch['mode']) {
        'percent'     => (float) $batch['value'] . '%',
        'flat_amount' => '₹' . number_format((float) $batch['value'], 2),
        'set_price'   => 'set to ₹' . number_format((float) $batch['value'], 2),
        default       => (string) $batch['value'],
    };
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-currency-rupee"></i> <?= e($batch['batch_number']) ?>
        <?php if ($batch['status'] === 'reverted'): ?>
            <span class="badge bg-secondary align-middle">reverted</span>
        <?php else: ?>
            <span class="badge bg-success align-middle">applied</span>
        <?php endif; ?>
    </h1>
    <a href="<?= url('/rate-changes') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Rate Management
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Date</div>
                <div><?= e($batch['change_date']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Selection</div>
                <div><?= $scope === [] ? '<em class="text-muted">All products</em>' : e(implode(' / ', $scope)) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Change</div>
                <div><?= e($modeLabel) ?><?= (int) $batch['round_to_rupee'] === 1 ? ' (rounded to rupee)' : '' ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Products affected</div>
                <div><?= (int) $batch['products_affected'] ?></div>
            </div>
            <?php if (!empty($batch['remarks'])): ?>
                <div class="col-12">
                    <div class="text-muted small">Remarks</div>
                    <div><?= e($batch['remarks']) ?></div>
                </div>
            <?php endif; ?>
            <?php if ($batch['status'] === 'reverted'): ?>
                <div class="col-12">
                    <div class="alert alert-secondary py-2 small mb-0">
                        Reverted on <?= e((string) $batch['reverted_at']) ?> — every product below
                        was restored to its "Before" price.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Products in this batch</strong></div>
    <div class="card-body">
        <?php if ($lines === []): ?>
            <p class="text-center text-muted py-3 mb-0">No lines recorded.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-end">Before</th>
                            <th class="text-end">After</th>
                            <th class="text-end">Change</th>
                            <th class="text-end">Price now</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $i => $l): ?>
                            <?php
                                $old = (float) $l['old_price'];
                                $new = (float) $l['new_price'];
                                $now = (float) $l['current_price'];
                                $delta = $new - $old;
                                // Flag anything re-priced since — those block a revert.
                                $expected = $batch['status'] === 'reverted' ? $old : $new;
                                $drifted  = abs($now - $expected) > 0.005;
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($l['product_name']) ?></td>
                                <td class="text-end">₹<?= number_format($old, 2) ?></td>
                                <td class="text-end">₹<?= number_format($new, 2) ?></td>
                                <td class="text-end <?= $delta < 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= $delta >= 0 ? '+' : '−' ?>₹<?= number_format(abs($delta), 2) ?>
                                </td>
                                <td class="text-end <?= $drifted ? 'fw-bold text-warning' : '' ?>">
                                    ₹<?= number_format($now, 2) ?>
                                    <?php if ($drifted): ?>
                                        <div class="small text-muted">changed since</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
