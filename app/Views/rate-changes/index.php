<?php
/** @var array  $categories */
/** @var array  $subcategories */
/** @var array  $brands */
/** @var array  $filters */
/** @var string $mode */
/** @var string $value */
/** @var bool   $roundToRupee */
/** @var array|null $preview */
/** @var string|null $previewError */
/** @var string $remarks */
/** @var array  $batches */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-currency-rupee"></i> Rate Management</h1>
</div>

<div class="alert alert-info py-2 small">
    Changes the <strong>selling price</strong> of every active product in the selection.
    Cost, stock and existing invoices are never touched. Nothing is written until you
    review the preview and press Apply — and any applied batch can be reverted.
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="category_id">Category</label>
                <select id="category_id" name="category_id" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="subcategory_id">Sub-category</label>
                <select id="subcategory_id" name="subcategory_id" class="form-select form-select-sm">
                    <option value="">All sub-categories</option>
                    <?php foreach ($subcategories as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"
                                data-category="<?= (int) $s['category_id'] ?>"
                                <?= $filters['subcategory_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="brand_id">Brand</label>
                <select id="brand_id" name="brand_id" class="form-select form-select-sm">
                    <option value="">All brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= $filters['brand_id'] === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="mode">Change type</label>
                <select id="mode" name="mode" class="form-select form-select-sm" required>
                    <option value="">— select —</option>
                    <option value="percent"     <?= $mode === 'percent'     ? 'selected' : '' ?>>By percentage (%)</option>
                    <option value="flat_amount" <?= $mode === 'flat_amount' ? 'selected' : '' ?>>By flat amount (₹)</option>
                    <option value="set_price"   <?= $mode === 'set_price'   ? 'selected' : '' ?>>Set to fixed price (₹)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="value">Value</label>
                <input type="number" id="value" name="value" step="0.01" required
                       class="form-control form-control-sm" value="<?= e($value) ?>"
                       placeholder="e.g. 5 or -5">
                <div class="form-text">Negative reduces the price.</div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="round_to_rupee"
                           name="round_to_rupee" value="1" <?= $roundToRupee ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="round_to_rupee">
                        Round to nearest rupee
                    </label>
                </div>
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-eye"></i> Preview changes
                </button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/rate-changes') ?>">Reset</a>
            </div>
        </div>
    </div>
</form>

<?php if ($previewError !== null): ?>
    <div class="alert alert-danger py-2"><?= e($previewError) ?></div>
<?php endif; ?>

<?php if ($preview !== null): ?>
    <?php $rows = $preview['rows']; $skipped = $preview['skipped']; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Preview — <?= count($rows) ?> product(s) would change</strong>
            <?php if ($skipped !== []): ?>
                <span class="small text-muted"><?= count($skipped) ?> skipped</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($rows === []): ?>
                <p class="text-center text-muted py-3 mb-0">
                    Nothing would change with these settings.
                </p>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 26rem; overflow-y: auto;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0;">
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Brand</th>
                                <th class="text-end">Current</th>
                                <th class="text-end">New</th>
                                <th class="text-end">Change</th>
                                <th class="text-end" title="New price vs moving-average cost">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                    $delta = (float) $r['delta'];
                                    $cost  = (float) $r['moving_avg_cost'];
                                    $new   = (float) $r['new_price'];
                                ?>
                                <tr>
                                    <td><?= e($r['name']) ?></td>
                                    <td class="small text-muted"><?= e($r['category_name']) ?> / <?= e($r['subcategory_name']) ?></td>
                                    <td class="small text-muted"><?= e($r['brand_name']) ?></td>
                                    <td class="text-end">₹<?= number_format((float) $r['default_selling_price'], 2) ?></td>
                                    <td class="text-end fw-bold">₹<?= number_format($new, 2) ?></td>
                                    <td class="text-end <?= $delta < 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= $delta >= 0 ? '+' : '−' ?>₹<?= number_format(abs($delta), 2) ?>
                                    </td>
                                    <td class="text-end small">
                                        <?php if ($cost > 0): ?>
                                            <span class="<?= $new < $cost ? 'text-danger fw-bold' : 'text-muted' ?>">
                                                <?= number_format((($new - $cost) / $cost) * 100, 1) ?>%
                                                <?= $new < $cost ? ' (below cost)' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="post" action="<?= url('/rate-changes/apply') ?>" class="mt-3"
                      onsubmit="return confirm('Apply this price change to <?= count($rows) ?> product(s)?');">
                    <?= \App\Support\Csrf::field() ?>
                    <input type="hidden" name="category_id"    value="<?= (int) $filters['category_id'] ?>">
                    <input type="hidden" name="subcategory_id" value="<?= (int) $filters['subcategory_id'] ?>">
                    <input type="hidden" name="brand_id"       value="<?= (int) $filters['brand_id'] ?>">
                    <input type="hidden" name="mode"           value="<?= e($mode) ?>">
                    <input type="hidden" name="value"          value="<?= e($value) ?>">
                    <?php if ($roundToRupee): ?>
                        <input type="hidden" name="round_to_rupee" value="1">
                    <?php endif; ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label small" for="remarks">Remarks (optional)</label>
                            <input type="text" id="remarks" name="remarks" maxlength="500"
                                   class="form-control form-control-sm" value="<?= e($remarks) ?>"
                                   placeholder="e.g. April price revision from Berger">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100">
                                <i class="bi bi-check2-circle"></i> Apply to <?= count($rows) ?> product(s)
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($skipped !== []): ?>
                <details class="mt-3">
                    <summary class="small text-muted">
                        <?= count($skipped) ?> product(s) skipped — click to see why
                    </summary>
                    <ul class="small text-muted mt-2 mb-0">
                        <?php foreach ($skipped as $s): ?>
                            <li><?= e($s['name']) ?> — <?= e($s['reason']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Recent rate changes</strong></div>
    <div class="card-body">
        <?php if ($batches === []): ?>
            <p class="text-center text-muted py-3 mb-0">No rate changes applied yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Batch</th>
                            <th>Date</th>
                            <th>Selection</th>
                            <th>Change</th>
                            <th class="text-end">Products</th>
                            <th>By</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $b): ?>
                            <?php
                                $scope = array_filter([
                                    $b['category_name'] ?? null,
                                    $b['subcategory_name'] ?? null,
                                    $b['brand_name'] ?? null,
                                ]);
                                $label = match ($b['mode']) {
                                    'percent'     => (float) $b['value'] . '%',
                                    'flat_amount' => '₹' . number_format((float) $b['value'], 2),
                                    'set_price'   => 'set ₹' . number_format((float) $b['value'], 2),
                                    default       => (string) $b['value'],
                                };
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= url('/rate-changes/' . (int) $b['id']) ?>">
                                        <strong><?= e($b['batch_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="small text-nowrap"><?= e($b['change_date']) ?></td>
                                <td class="small"><?= $scope === [] ? '<em class="text-muted">All products</em>' : e(implode(' / ', $scope)) ?></td>
                                <td class="small"><?= e($label) ?><?= (int) $b['round_to_rupee'] === 1 ? ' (rounded)' : '' ?></td>
                                <td class="text-end"><?= (int) $b['products_affected'] ?></td>
                                <td class="small text-muted"><?= e($b['created_by_name'] ?? '—') ?></td>
                                <td>
                                    <?php if ($b['status'] === 'reverted'): ?>
                                        <span class="badge bg-secondary">reverted</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">applied</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($b['status'] !== 'reverted'): ?>
                                        <form method="post" action="<?= url('/rate-changes/' . (int) $b['id'] . '/revert') ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Restore the previous price on <?= (int) $b['products_affected'] ?> product(s)?');">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-arrow-counterclockwise"></i> Revert
                                            </button>
                                        </form>
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

<script>
// Narrow the sub-category list to the chosen category so the two filters can't
// contradict each other.
(function () {
    var cat = document.getElementById('category_id');
    var sub = document.getElementById('subcategory_id');
    if (!cat || !sub) { return; }
    function sync() {
        var want = cat.value;
        Array.prototype.forEach.call(sub.options, function (opt) {
            if (!opt.value) { return; }
            var show = !want || opt.getAttribute('data-category') === want;
            opt.hidden = !show;
            if (!show && opt.selected) { sub.value = ''; }
        });
    }
    cat.addEventListener('change', sync);
    sync();
})();
</script>
