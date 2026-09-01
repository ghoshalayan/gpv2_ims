<?php
/** @var array $brand */
/** @var array $slabs */
/** @var array $products */
/** @var bool  $enabled */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-bar-chart-steps"></i> Purchase Rate Slabs · <?= e($brand['name']) ?>
    </h1>
    <a href="<?= url('/brands') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Brands
    </a>
</div>

<?php if (!$enabled): ?>
    <div class="alert alert-warning py-2">
        <strong>Slab rates are switched off.</strong>
        You can set them up here, but purchase entry will not suggest them until
        <em>Slab purchase rates</em> is enabled in
        <a href="<?= url('/settings') ?>">Settings</a>.
    </div>
<?php else: ?>
    <div class="alert alert-info py-2 small">
        When a purchase line for this brand reaches a slab's quantity, the rate below is
        <strong>suggested</strong> in the price field. It stays editable — nothing is locked,
        and already-saved invoices are never changed.
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Quantity slabs</strong></div>
    <div class="card-body">
        <?php if ($slabs === []): ?>
            <p class="text-muted small">
                No slabs yet. A typical setup is two: <em>Under 50</em> (min 0, max 50) and
                <em>50 and above</em> (min 50, max blank).
            </p>
        <?php else: ?>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Label</th>
                            <th class="text-end">From qty</th>
                            <th class="text-end">Up to (excl.)</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slabs as $s): ?>
                            <tr>
                                <form method="post" action="<?= url('/brand-slabs/' . (int) $s['id'] . '/update') ?>">
                                    <?= \App\Support\Csrf::field() ?>
                                    <td>
                                        <input type="text" name="label" maxlength="100" required
                                               class="form-control form-control-sm"
                                               value="<?= e($s['label']) ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="min_qty" step="0.001" min="0" required
                                               class="form-control form-control-sm text-end"
                                               value="<?= e(rtrim(rtrim((string) $s['min_qty'], '0'), '.')) ?: '0' ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="max_qty" step="0.001" min="0"
                                               class="form-control form-control-sm text-end"
                                               placeholder="and above"
                                               value="<?= $s['max_qty'] === null ? '' : e(rtrim(rtrim((string) $s['max_qty'], '0'), '.')) ?>">
                                    </td>
                                    <td>
                                        <select name="status" class="form-select form-select-sm no-select2">
                                            <option value="active"   <?= $s['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $s['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                                        <form method="post" action="<?= url('/brand-slabs/' . (int) $s['id'] . '/delete') ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete this slab and every product rate recorded under it?');">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('/brands/' . (int) $brand['id'] . '/slabs') ?>">
            <?= \App\Support\Csrf::field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small" for="label">New slab label</label>
                    <input type="text" id="label" name="label" maxlength="100" required
                           class="form-control form-control-sm" placeholder="e.g. Under 50">
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="min_qty">From quantity</label>
                    <input type="number" id="min_qty" name="min_qty" step="0.001" min="0" required
                           class="form-control form-control-sm" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="max_qty">Up to (exclusive)</label>
                    <input type="number" id="max_qty" name="max_qty" step="0.001" min="0"
                           class="form-control form-control-sm" placeholder="blank = and above">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-plus-lg"></i> Add slab
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($slabs !== []): ?>
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Rates per product</strong>
        <span class="small text-muted">Leave a cell blank for no suggestion at that quantity.</span>
    </div>
    <div class="card-body">
        <?php if ($products === []): ?>
            <p class="text-center text-muted py-3 mb-0">
                This brand has no active products yet.
            </p>
        <?php else: ?>
            <form method="post" action="<?= url('/brands/' . (int) $brand['id'] . '/slabs/rates') ?>">
                <?= \App\Support\Csrf::field() ?>
                <div class="table-responsive" style="max-height: 30rem; overflow-y: auto;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0;">
                            <tr>
                                <th>Product</th>
                                <th class="text-end" title="Weighted moving average cost">Avg cost</th>
                                <?php foreach ($slabs as $s): ?>
                                    <th class="text-end" style="min-width: 8rem;">
                                        <?= e($s['label']) ?>
                                        <?php if ($s['status'] !== 'active'): ?>
                                            <span class="badge bg-secondary">off</span>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <?= e($p['name']) ?>
                                        <div class="small text-muted"><?= e($p['unit']) ?></div>
                                    </td>
                                    <td class="text-end small text-muted">
                                        ₹<?= number_format((float) $p['moving_avg_cost'], 2) ?>
                                    </td>
                                    <?php foreach ($slabs as $s): ?>
                                        <?php $val = $p['rates'][(int) $s['id']] ?? null; ?>
                                        <td>
                                            <input type="number" step="0.0001" min="0"
                                                   class="form-control form-control-sm text-end"
                                                   name="rates[<?= (int) $p['id'] ?>][<?= (int) $s['id'] ?>]"
                                                   value="<?= $val === null ? '' : e(rtrim(rtrim(number_format($val, 4, '.', ''), '0'), '.')) ?>"
                                                   placeholder="—">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Save rates
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
