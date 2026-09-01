<?php /** @var array $product */ /** @var array $composition */ /** @var array $items */ /** @var array $candidates */ /** @var array $rawCost */ ?>
<?php $isIrreversible = (int) $product['is_irreversible'] === 1; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-diagram-3"></i> Recipe —
        <a href="<?= url('/products/' . $product['id'] . '/edit') ?>" class="text-decoration-none">
            <?= e($product['name']) ?>
        </a>
        <?php if ($isIrreversible): ?>
            <span class="badge text-bg-warning ms-2">Irreversible (physical)</span>
        <?php else: ?>
            <span class="badge text-bg-info ms-2">Reversible (virtual)</span>
        <?php endif; ?>
    </h1>
    <a href="<?= url('/products') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Products
    </a>
</div>

<div class="alert alert-<?= $isIrreversible ? 'warning' : 'info' ?> small">
    <?php if ($isIrreversible): ?>
        <strong>Irreversible composite</strong> — components must be assembled via a <strong>Production Order</strong>
        before this product has stock. Sale of this product deducts its own stock; components are NOT touched at sale time.
        Returns restore composite stock; components are not recoverable (e.g. mixed paint).
    <?php else: ?>
        <strong>Reversible composite</strong> — no own stock is kept. Selling deducts components directly at sale time;
        returns restore components. (e.g. assembled computer — can be dismantled.)
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Components</strong>
                <span class="text-muted small">Quantities are per <strong>1 <?= e($product['unit']) ?></strong> of the composite.</span>
            </div>
            <div class="card-body">
                <?php if ($items === []): ?>
                    <p class="text-muted text-center py-2 mb-0">No components yet. Add the first one below.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Component</th>
                                    <th class="text-end">Qty (per 1 <?= e($product['unit']) ?>)</th>
                                    <th class="text-end">Avg Cost</th>
                                    <th class="text-end">Latest Cost</th>
                                    <th class="text-end">Line Cost (avg)</th>
                                    <th>Component Stock</th>
                                    <th>Notes</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $it): ?>
                                    <?php $lineCost = (float) $it['quantity'] * (float) $it['moving_avg_cost']; ?>
                                    <tr class="<?= $it['component_status'] !== 'active' ? 'table-warning' : '' ?>">
                                        <td>
                                            <strong><?= e($it['component_name']) ?></strong>
                                            <?php if ($it['component_status'] !== 'active'): ?>
                                                <span class="badge text-bg-warning ms-1">inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?= number_format((float) $it['quantity'], 3) ?>
                                            <small class="text-muted"><?= e($it['component_unit']) ?></small>
                                        </td>
                                        <td class="text-end">₹<?= number_format((float) $it['moving_avg_cost'], 4) ?></td>
                                        <td class="text-end">₹<?= number_format((float) $it['latest_purchase_landed_cost'], 4) ?></td>
                                        <td class="text-end fw-bold">₹<?= number_format($lineCost, 2) ?></td>
                                        <td class="text-end">
                                            <span class="text-muted small"><?= (float) $it['component_stock'] ?> <?= e($it['component_unit']) ?></span>
                                        </td>
                                        <td class="text-muted small"><?= e($it['notes'] ?? '') ?></td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-recipe-item"
                                                    data-item-id="<?= (int) $it['id'] ?>"
                                                    data-component-name="<?= e($it['component_name']) ?>"
                                                    data-component-unit="<?= e($it['component_unit']) ?>"
                                                    data-quantity="<?= e((string) $it['quantity']) ?>"
                                                    data-notes="<?= e((string) ($it['notes'] ?? '')) ?>"
                                                    title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="post" action="<?= url('/products/' . $product['id'] . '/recipe/items/' . $it['id'] . '/delete') ?>"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Remove this component from the recipe?')">
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

                <?php if ($candidates === []): ?>
                    <div class="alert alert-info mt-3 mb-0 small">
                        No more products available to add (all already in recipe, or only this product exists).
                    </div>
                <?php else: ?>
                    <form method="post" action="<?= url('/products/' . $product['id'] . '/recipe/items') ?>" class="mt-3 p-3 bg-light rounded">
                        <?= \App\Support\Csrf::field() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small">Component Product</label>
                                <select name="component_product_id" class="form-select form-select-sm" required>
                                    <option value="">— pick a component —</option>
                                    <?php foreach ($candidates as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>">
                                            <?= e($p['name']) ?> — <?= e($p['brand_name']) ?>
                                            (<?= e($p['unit']) ?>, stock: <?= (float) $p['quantity_on_hand'] ?>)<?= (int) $p['is_composite'] === 1 ? ' [composite]' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Quantity <span class="text-muted">(per 1 <?= e($product['unit']) ?>)</span></label>
                                <input type="number" name="quantity" min="0.001" step="0.001" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Notes</label>
                                <input type="text" name="notes" maxlength="255" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2 text-end">
                                <button class="btn btn-sm btn-primary w-100">
                                    <i class="bi bi-plus-lg"></i> Add Component
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Recipe Notes</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/products/' . $product['id'] . '/recipe/notes') ?>">
                    <?= \App\Support\Csrf::field() ?>
                    <textarea name="notes" rows="3" maxlength="500" class="form-control"
                              placeholder="Instructions, brand-specific mixing ratios, process notes…"><?= e($composition['notes'] ?? '') ?></textarea>
                    <button class="btn btn-sm btn-primary mt-2"><i class="bi bi-check-lg"></i> Save Notes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Raw-cost Derived</strong></div>
            <div class="card-body">
                <p class="small text-muted mb-2">Cost of 1 <?= e($product['unit']) ?> of <?= e($product['name']) ?> built from current components.</p>
                <table class="table table-sm mb-0">
                    <tr>
                        <th title="Based on each component's moving-average cost (recommended)">Moving-avg cost</th>
                        <td class="text-end fw-bold">₹<?= number_format((float) $rawCost['moving_avg'], 2) ?></td>
                    </tr>
                    <tr>
                        <th title="Based on each component's latest purchase landed cost (replacement cost)">Latest-price cost</th>
                        <td class="text-end">₹<?= number_format((float) $rawCost['latest'], 2) ?></td>
                    </tr>
                    <?php if ((float) $product['default_selling_price'] > 0): ?>
                        <tr>
                            <th>Default sell</th>
                            <td class="text-end">₹<?= number_format((float) $product['default_selling_price'], 2) ?></td>
                        </tr>
                        <?php $margin = (float) $product['default_selling_price'] - (float) $rawCost['moving_avg']; ?>
                        <tr class="table-light">
                            <th>Margin (vs avg)</th>
                            <td class="text-end <?= $margin >= 0 ? 'text-success' : 'text-danger' ?>">
                                ₹<?= number_format($margin, 2) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php if ($isIrreversible): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Production</strong></div>
                <div class="card-body small">
                    Creating stock of this product requires a Production Order that consumes components.
                    <div class="mt-2">
                        <span class="badge text-bg-secondary">coming next turn — #14.5 Part 2</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit recipe-item modal — populated from each row's pencil button -->
<div class="modal fade" id="editRecipeItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="editRecipeItemForm" method="post" action="" novalidate>
            <?= \App\Support\Csrf::field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i> Edit component — <span id="eri_component_name"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Component product is locked here. Use Delete + Add to swap to a different component.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="eri_quantity">
                                Quantity per 1 unit of composite
                                <span class="text-muted">(<span id="eri_unit"></span>)</span>
                            </label>
                            <input type="number" id="eri_quantity" name="quantity"
                                   step="0.001" min="0.001" required
                                   class="form-control text-end">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="eri_notes">Notes</label>
                            <input type="text" id="eri_notes" name="notes" maxlength="500"
                                   class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    document.querySelectorAll('.edit-recipe-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const d = btn.dataset;
            const form = document.getElementById('editRecipeItemForm');
            form.action = '<?= url('/products/' . (int) $product['id']) ?>/recipe/items/' + d.itemId + '/update';
            document.getElementById('eri_component_name').textContent = d.componentName;
            document.getElementById('eri_unit').textContent           = d.componentUnit;
            document.getElementById('eri_quantity').value             = d.quantity;
            document.getElementById('eri_notes').value                = d.notes;
            new bootstrap.Modal(document.getElementById('editRecipeItemModal')).show();
        });
    });
</script>
