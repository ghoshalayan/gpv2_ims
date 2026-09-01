<?php /** @var array $product */ /** @var array $brands */ /** @var array $categories */ /** @var array $subcategoriesByCategory */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Product</h1>
    <a href="<?= url('/products') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/products/' . $product['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Product Master</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" maxlength="150"
                                   class="form-control"
                                   value="<?= e(old('name', $product['name'])) ?>" required autofocus>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="brand_id">Brand <span class="text-danger">*</span></label>
                            <select id="brand_id" name="brand_id" class="form-select" required>
                                <?php $selectedBrand = (int) old('brand_id', (string) $product['brand_id']); ?>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"
                                        <?= (int) $b['id'] === $selectedBrand ? 'selected' : '' ?>>
                                        <?= e($b['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="unit_id">Stock Unit <span class="text-danger">*</span></label>
                            <select id="unit_id" name="unit_id" class="form-select" required>
                                <?php $selUnit = (int) old('unit_id', (string) ($product['unit_id'] ?? 0)); ?>
                                <?php foreach (($unitsList ?? []) as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"
                                        <?= $selUnit === (int) $u['id'] ? 'selected' : '' ?>>
                                        <?= e($u['code']) ?> — <?= e($u['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Changing this re-bases stock reporting. Buy/sell packs are configured below.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <?php $selectedCat = (int) old('category_id', (string) $product['category_id']); ?>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"
                                            <?= (int) $c['id'] === $selectedCat ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?><?= $c['status'] !== 'active' ? ' (inactive)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="subcategory_id">Sub-Category <span class="text-danger">*</span></label>
                            <select id="subcategory_id" name="subcategory_id" class="form-select" required>
                                <option value="">— Select sub-category —</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" maxlength="500" rows="2"
                                      class="form-control"><?= e(old('description', $product['description'] ?? '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Pricing &amp; Tax</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="hsn_code">HSN Code</label>
                            <input type="text" id="hsn_code" name="hsn_code" maxlength="20"
                                   class="form-control"
                                   value="<?= e(old('hsn_code', $product['hsn_code'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tax_rate">Tax Rate (%)</label>
                            <input type="number" id="tax_rate" name="tax_rate" min="0" max="100" step="0.01"
                                   class="form-control"
                                   value="<?= e(old('tax_rate', (string) $product['tax_rate'])) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="default_selling_price">Default Selling Price (₹)</label>
                            <input type="number" id="default_selling_price" name="default_selling_price"
                                   min="0" step="0.01" class="form-control"
                                   value="<?= e(old('default_selling_price', (string) $product['default_selling_price'])) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Live Stock &amp; Cost</strong></div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        Maintained automatically by purchases, sales, returns, and write-offs. Read-only here.
                    </p>
                    <table class="table table-sm mb-0">
                        <tr>
                            <th class="w-50">On hand</th>
                            <td class="text-end fw-bold"><?= (int) $product['quantity_on_hand'] ?> <?= e($product['unit']) ?></td>
                        </tr>
                        <tr>
                            <th title="Weighted moving average — historic cost basis">Moving Avg Cost</th>
                            <td class="text-end">₹<?= number_format((float) $product['moving_avg_cost'], 4) ?></td>
                        </tr>
                        <tr>
                            <th title="Latest purchase landed cost — current replacement price">Latest Cost</th>
                            <td class="text-end">
                                ₹<?= number_format((float) ($product['latest_purchase_landed_cost'] ?? 0), 4) ?>
                                <?php if (!empty($product['latest_purchase_at'])): ?>
                                    <div class="small text-muted">as of <?= e(substr((string) $product['latest_purchase_at'], 0, 10)) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Invested Value <small class="text-muted">(qty × avg)</small></th>
                            <td class="text-end">₹<?= number_format((float) $product['quantity_on_hand'] * (float) $product['moving_avg_cost'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Market Value <small class="text-muted">(qty × latest)</small></th>
                            <td class="text-end">₹<?= number_format((float) $product['quantity_on_hand'] * (float) ($product['latest_purchase_landed_cost'] ?? 0), 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if ($derivedCosts !== null): ?>
                <div class="card shadow-sm border-info mt-3">
                    <div class="card-header bg-info-subtle">
                        <strong><i class="bi bi-diagram-3"></i> Derived From Recipe</strong>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            Back-calculated from current component prices (<?= (int) $derivedCosts['component_count'] ?> components).
                            <strong>Market price = derived from raw products' latest prices.</strong>
                        </p>
                        <table class="table table-sm mb-0">
                            <tr>
                                <th class="w-50" title="Cost to build one at components' moving-average">Per-unit Avg Cost</th>
                                <td class="text-end">₹<?= number_format((float) $derivedCosts['moving_avg'], 2) ?></td>
                            </tr>
                            <tr>
                                <th title="Market / replacement price per unit, from current component latest prices">Per-unit Market Price</th>
                                <td class="text-end fw-bold text-info">₹<?= number_format((float) $derivedCosts['latest'], 2) ?></td>
                            </tr>
                            <?php if ((float) $product['quantity_on_hand'] > 0): ?>
                                <tr class="table-light">
                                    <th>Derived Market Value <small class="text-muted">(qty × derived)</small></th>
                                    <td class="text-end fw-bold">₹<?= number_format((float) $product['quantity_on_hand'] * (float) $derivedCosts['latest'], 2) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ((float) $product['default_selling_price'] > 0): ?>
                                <?php $margin = (float) $product['default_selling_price'] - (float) $derivedCosts['moving_avg']; ?>
                                <tr>
                                    <th>Margin <small class="text-muted">(sell − avg)</small></th>
                                    <td class="text-end <?= $margin >= 0 ? 'text-success' : 'text-danger' ?>">
                                        ₹<?= number_format($margin, 2) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                        <a href="<?= url('/products/' . $product['id'] . '/recipe') ?>" class="btn btn-sm btn-outline-primary mt-3">
                            <i class="bi bi-diagram-3"></i> Edit Recipe
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Alerts</strong></div>
                <div class="card-body">
                    <label class="form-label" for="low_stock_threshold">Low Stock Threshold</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0"
                           class="form-control"
                           value="<?= e(old('low_stock_threshold', (string) $product['low_stock_threshold'])) ?>">
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Composite (BOM)</strong></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <?php $isComp = (int) $product['is_composite'] === 1; ?>
                        <input class="form-check-input" type="checkbox" id="is_composite" name="is_composite" value="1"
                               <?= $isComp ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_composite">
                            This product is a composite
                            <i class="bi bi-info-circle text-muted" title="Built from other products via a recipe. Use for assembled or mixed items."></i>
                        </label>
                    </div>
                    <div id="composite_subflags" style="<?= $isComp ? '' : 'display:none' ?>">
                        <div class="form-check form-switch mb-2">
                            <?php $isIrr = (int) $product['is_irreversible'] === 1; ?>
                            <input class="form-check-input" type="checkbox" id="is_irreversible" name="is_irreversible" value="1"
                                   <?= $isIrr ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_irreversible">
                                Irreversible (needs Production Orders, has own stock)
                                <i class="bi bi-info-circle text-muted" title="ON = physical composite (paint mix, food mix) — components consumed by production, composite tracked as its own stock. OFF = virtual (assembled computer) — sale deducts components; no own stock."></i>
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            <?php $expose = (int) $product['expose_components'] === 1; ?>
                            <input class="form-check-input" type="checkbox" id="expose_components" name="expose_components" value="1"
                                   <?= $expose ? 'checked' : '' ?>>
                            <label class="form-check-label" for="expose_components">
                                Print component list on the bill
                            </label>
                        </div>
                        <div class="mt-3">
                            <a href="<?= url('/products/' . $product['id'] . '/recipe') ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-diagram-3"></i> Edit Recipe
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Save changes
        </button>
        <a href="<?= url('/products') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<!-- ============== LATEST COST UPDATE + HISTORY ============== -->
<div class="row g-3 mt-1">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Update Latest Cost</strong></div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Use this when the latest known per-unit landed cost changes but no purchase has happened yet
                    (e.g. supplier announced a new rate). Updates only the latest-cost value and adds a history entry;
                    does <strong>not</strong> touch stock or moving-average cost.
                </p>
                <form method="post" action="<?= url('/products/' . $product['id'] . '/update-latest-cost') ?>">
                    <?= \App\Support\Csrf::field() ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small" for="latest_cost">New Latest Cost (₹)</label>
                            <input type="number" id="latest_cost" name="latest_cost"
                                   min="0" step="0.0001" class="form-control"
                                   value="<?= e((string) ($product['latest_purchase_landed_cost'] ?? 0)) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="cost_notes">Reason / Notes</label>
                            <input type="text" id="cost_notes" name="notes" maxlength="500"
                                   class="form-control"
                                   placeholder="e.g. Supplier rate revised 10%">
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary mt-3">
                        <i class="bi bi-check-lg"></i> Update Latest Cost
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Price &amp; Moving-Average History</strong>
                <span class="small text-muted">
                    <?= number_format((int) ($historyPg['total'] ?? 0)) ?> events
                </span>
            </div>
            <div class="card-body">
                <?php $pg = $historyPg; $searchPlaceholder = 'Search source / notes…'; ?>
                <?php require APP_ROOT . '/app/Views/partials/pagination.php'; ?>

                <?php if ($priceHistory === []): ?>
                    <p class="text-muted text-center py-3 mb-0">No price history yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>When</th>
                                    <th>Source</th>
                                    <th class="text-end small">Prior</th>
                                    <th class="text-end small">Event</th>
                                    <th class="text-end small">After</th>
                                    <th class="text-end">New Avg</th>
                                    <th>Math</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($priceHistory as $h): ?>
                                    <?php
                                        $srcColor = [
                                            'opening'    => 'secondary',
                                            'purchase'   => 'success',
                                            'production' => 'info',
                                            'return'     => 'warning',
                                            'manual'     => 'primary',
                                            'other'      => 'dark',
                                        ][$h['source']] ?? 'dark';
                                        $hasMath = $h['prior_qty'] !== null && $h['new_qty'] !== null;
                                        $priorQty = (float) ($h['prior_qty']        ?? 0);
                                        $priorAvg = (float) ($h['prior_avg']        ?? 0);
                                        $eventQty = (float) ($h['event_qty']        ?? 0);
                                        $eventCst = (float) ($h['event_unit_cost']  ?? 0);
                                        $newQty   = (float) ($h['new_qty']          ?? 0);
                                        $newAvg   = (float) ($h['new_avg']          ?? $h['landed_cost']);
                                        $delta    = $newAvg - $priorAvg;
                                    ?>
                                    <tr>
                                        <td class="text-nowrap small"><?= e($h['effective_at']) ?></td>
                                        <td>
                                            <span class="badge text-bg-<?= $srcColor ?>"><?= e($h['source']) ?></span>
                                            <?php if (!empty($h['source_ref_type'])): ?>
                                                <div class="small text-muted font-monospace">
                                                    <?= e($h['source_ref_type']) ?>#<?= (int) $h['source_ref_id'] ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($hasMath): ?>
                                            <td class="text-end small text-nowrap">
                                                <?= number_format($priorQty, 3) ?><br>
                                                <span class="text-muted">@ ₹<?= number_format($priorAvg, 4) ?></span>
                                            </td>
                                            <td class="text-end small text-nowrap">
                                                <?php if ($eventQty != 0.0): ?>
                                                    <?= ($eventQty > 0 ? '+' : '') . number_format($eventQty, 3) ?><br>
                                                    <span class="text-muted">@ ₹<?= number_format($eventCst, 4) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end small text-nowrap">
                                                <?= number_format($newQty, 3) ?><br>
                                                <span class="text-muted">@ ₹<?= number_format($newAvg, 4) ?></span>
                                            </td>
                                        <?php else: ?>
                                            <td colspan="3" class="text-center small text-muted">— no snapshot —</td>
                                        <?php endif; ?>
                                        <td class="text-end fw-bold">₹<?= number_format((float) $h['landed_cost'], 4) ?></td>
                                        <td class="small">
                                            <?php if ($hasMath): ?>
                                                <details>
                                                    <summary class="text-primary" style="cursor:pointer">show</summary>
                                                    <div class="mt-1 font-monospace small">
                                                        <?php if ($eventQty > 0 && $priorQty + $eventQty != 0): ?>
                                                            <?= number_format($priorQty, 3) ?>×₹<?= number_format($priorAvg, 4) ?>
                                                            + <?= number_format($eventQty, 3) ?>×₹<?= number_format($eventCst, 4) ?>
                                                            <br>÷ <?= number_format($priorQty + $eventQty, 3) ?>
                                                            = <strong>₹<?= number_format($newAvg, 4) ?></strong>
                                                        <?php elseif ($eventQty < 0): ?>
                                                            stock out <?= number_format($eventQty, 3) ?>;
                                                            avg unchanged at ₹<?= number_format($newAvg, 4) ?>
                                                        <?php elseif ($eventQty == 0.0): ?>
                                                            <?php if (\abs($delta) > 0.0001): ?>
                                                                cost-only adjustment Δ ₹<?= number_format($delta, 4) ?>
                                                                → new avg ₹<?= number_format($newAvg, 4) ?>
                                                            <?php else: ?>
                                                                manual landed-cost set;
                                                                stock + moving-avg unchanged
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($h['notes'])): ?>
                                                        <div class="text-muted small mt-1"><?= e($h['notes']) ?></div>
                                                    <?php endif; ?>
                                                </details>
                                            <?php else: ?>
                                                <span class="text-muted"><?= e($h['notes'] ?? '') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= e($h['created_by_name'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php require APP_ROOT . '/app/Views/partials/pagination.php'; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Per-product unit overrides (case=12 pcs etc.) -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>
            <i class="bi bi-rulers"></i> Buy / Sell Units &amp; Conversions
        </strong>
        <span class="small text-muted">
            Stock unit:
            <strong><?= e((string) ($product['unit'] ?? '')) ?></strong>
            <?php if (!empty($product['unit_id'])): ?>
                <span class="text-muted">(unit_id #<?= (int) $product['unit_id'] ?>)</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            The product is stocked in its <strong>base unit</strong>. Add other units here to allow buying / selling
            in different packaging (e.g. <code>1 case = 12 pcs</code>). The factor below answers:
            <em>"how many of the stock unit is 1 of this unit?"</em>
        </p>

        <?php if (empty($productUnits)): ?>
            <div class="text-muted small mb-3">No alternate units yet — purchase &amp; sales lines use the stock unit.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Unit</th>
                            <th class="text-end">1 of unit = ? stock units</th>
                            <th class="text-center">Purchase</th>
                            <th class="text-center">Sale</th>
                            <th class="text-center">Default Buy</th>
                            <th class="text-center">Default Sell</th>
                            <th>Notes</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productUnits as $pu): ?>
                            <tr>
                                <form method="post" action="<?= url('/products/' . (int) $product['id'] . '/units/' . (int) $pu['id'] . '/update') ?>">
                                    <?= \App\Support\Csrf::field() ?>
                                    <td>
                                        <strong class="font-monospace"><?= e($pu['unit_code']) ?></strong>
                                        <span class="text-muted small d-block"><?= e($pu['unit_name']) ?></span>
                                    </td>
                                    <td>
                                        <input type="number" step="0.00000001" min="0.00000001"
                                               name="factor_to_product_base"
                                               value="<?= e((string) $pu['factor_to_product_base']) ?>"
                                               class="form-control form-control-sm text-end font-monospace">
                                    </td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="is_purchase" value="1" <?= (int) $pu['is_purchase'] === 1 ? 'checked' : '' ?>>
                                    </td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="is_sale" value="1" <?= (int) $pu['is_sale'] === 1 ? 'checked' : '' ?>>
                                    </td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="is_default_purchase" value="1" <?= (int) $pu['is_default_purchase'] === 1 ? 'checked' : '' ?>>
                                    </td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="is_default_sale" value="1" <?= (int) $pu['is_default_sale'] === 1 ? 'checked' : '' ?>>
                                    </td>
                                    <td>
                                        <input type="text" name="notes" maxlength="255"
                                               value="<?= e((string) ($pu['notes'] ?? '')) ?>"
                                               class="form-control form-control-sm">
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button type="submit" class="btn btn-sm btn-success" title="Save"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="post" action="<?= url('/products/' . (int) $product['id'] . '/units/' . (int) $pu['id'] . '/delete') ?>"
                                      class="d-inline"
                                      onsubmit="return confirm('Remove this unit from the product?')">
                                    <?= \App\Support\Csrf::field() ?>
                                    <button class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-trash"></i></button>
                                </form>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Add new unit override -->
        <form method="post" action="<?= url('/products/' . (int) $product['id'] . '/units') ?>"
              class="border-top pt-3 mt-2" novalidate>
            <?= \App\Support\Csrf::field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small" for="pu_unit_id">Unit</label>
                    <select name="unit_id" id="pu_unit_id" class="form-select form-select-sm" required>
                        <option value="">— pick a unit —</option>
                        <?php foreach ($unitsList as $u): ?>
                            <?php if ((int) $u['id'] === (int) ($product['unit_id'] ?? 0)) continue; ?>
                            <option value="<?= (int) $u['id'] ?>">
                                <?= e($u['code']) ?> — <?= e($u['name']) ?> (<?= e($u['family']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="pu_factor">1 of unit = ? stock units</label>
                    <input type="number" id="pu_factor" name="factor_to_product_base"
                           step="0.00000001" min="0.00000001" required
                           class="form-control form-control-sm text-end font-monospace"
                           placeholder="e.g. 12">
                </div>
                <div class="col-md-1 text-center">
                    <label class="form-label small d-block">Buy</label>
                    <input class="form-check-input" type="checkbox" name="is_purchase" value="1" checked>
                </div>
                <div class="col-md-1 text-center">
                    <label class="form-label small d-block">Sell</label>
                    <input class="form-check-input" type="checkbox" name="is_sale" value="1" checked>
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="pu_notes">Notes</label>
                    <input type="text" id="pu_notes" name="notes" maxlength="255"
                           class="form-control form-control-sm" placeholder="optional">
                </div>
                <div class="col-md-1 text-end">
                    <button class="btn btn-sm btn-primary" type="submit">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    window.subcategoriesByCategory = <?= json_encode($subcategoriesByCategory, JSON_UNESCAPED_UNICODE) ?>;
    window.preselectedSubcategory = <?= (int) old('subcategory_id', (string) $product['subcategory_id']) ?>;

    // Wait for DOMContentLoaded so jQuery (loaded at bottom of body) is available.
    document.addEventListener('DOMContentLoaded', function () {
        jQuery(function ($) {
            // Toggle composite sub-flags visibility
            const $comp = $('#is_composite');
            const $subflags = $('#composite_subflags');
            $comp.on('change', () => $subflags.toggle($comp.is(':checked')));

            const $cat = $('#category_id');
            const $sub = $('#subcategory_id');

            function refreshSubs() {
                const subs = window.subcategoriesByCategory[$cat.val()] || [];
                $sub.empty().append(new Option('— Select sub-category —', ''));
                subs.forEach(s => {
                    const isPre = s.id === window.preselectedSubcategory;
                    $sub.append(new Option(s.name, s.id, false, isPre));
                });
                window.preselectedSubcategory = 0;
                $sub.trigger('change');   // refresh Select2 display
            }

            $cat.on('change', refreshSubs);
            refreshSubs();
        });
    });
</script>
