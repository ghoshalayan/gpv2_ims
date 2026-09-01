<?php
/** @var array $products */
/** @var array $brands */
/** @var array $categories */
/** @var array $filters */
/** @var array $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-tag"></i> Products</h1>
    <a href="<?= url('/products/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Product
    </a>
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="f_brand">Brand</label>
                <select id="f_brand" name="brand_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= ($filters['brand_id'] ?? null) === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_category">Category</label>
                <select id="f_category" name="category_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= ($filters['category_id'] ?? null) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_stock">Stock</label>
                <select id="f_stock" name="stock_state" class="form-select form-select-sm">
                    <option value="">Any</option>
                    <option value="in_stock"     <?= ($filters['stock_state'] ?? '') === 'in_stock'     ? 'selected' : '' ?>>In stock</option>
                    <option value="low_stock"    <?= ($filters['stock_state'] ?? '') === 'low_stock'    ? 'selected' : '' ?>>Low stock</option>
                    <option value="out_of_stock" <?= ($filters['stock_state'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_status">Status</label>
                <select id="f_status" name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active"   <?= ($filters['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_price_min">Selling price from</label>
                <input type="number" id="f_price_min" name="price_min" step="0.01" min="0"
                       class="form-control form-control-sm" placeholder="min"
                       value="<?= $filters['price_min'] !== null ? e((string) $filters['price_min']) : '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="f_price_max">to</label>
                <input type="number" id="f_price_max" name="price_max" step="0.01" min="0"
                       class="form-control form-control-sm" placeholder="max"
                       value="<?= $filters['price_max'] !== null ? e((string) $filters['price_max']) : '' ?>">
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/products') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search name, HSN, description, brand, category, or sub-category…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($products === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== '' || array_filter($filters)): ?>
                    No matches for the current filters.
                <?php else: ?>
                    No products yet. <a href="<?= url('/products/create') ?>">Create the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Brand</th>
                            <th>Sub-Category</th>
                            <th>Unit</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end" title="Weighted moving average — historic cost basis">Avg Cost</th>
                            <th class="text-end" title="Most recent purchase landed cost — current replacement price">Latest Cost</th>
                            <th class="text-end" title="Stock × Avg Cost — invested value">Invested</th>
                            <th class="text-end" title="Stock × Latest Cost — current market value">Market</th>
                            <th class="text-end">Sell Price</th>
                            <th>Tax%</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $i => $p): ?>
                            <?php
                                $low = (int) $p['quantity_on_hand'] <= (int) $p['low_stock_threshold']
                                    && (int) $p['low_stock_threshold'] > 0;
                                $parentInactive = $p['brand_status'] !== 'active'
                                    || $p['subcategory_status'] !== 'active'
                                    || $p['category_status'] !== 'active';
                            ?>
                            <tr>
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <strong><?= e($p['name']) ?></strong>
                                    <?php if ((int) ($p['is_composite'] ?? 0) === 1): ?>
                                        <?php $irr = (int) ($p['is_irreversible'] ?? 0) === 1; ?>
                                        <span class="badge text-bg-<?= $irr ? 'warning' : 'info' ?> ms-1"
                                              title="<?= $irr ? 'Irreversible (physical) — needs Production Order' : 'Reversible (virtual) — sale deducts components' ?>">
                                            <i class="bi bi-diagram-3"></i> <?= $irr ? 'composite-P' : 'composite-V' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($p['hsn_code']): ?>
                                        <div class="small text-muted">HSN <?= e($p['hsn_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($p['brand_name']) ?></td>
                                <td>
                                    <?= e($p['category_name']) ?> &rsaquo; <?= e($p['subcategory_name']) ?>
                                    <?php if ($parentInactive): ?>
                                        <span class="badge text-bg-warning ms-1" title="Brand or category chain is inactive">parent inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($p['unit']) ?></td>
                                <td class="text-end <?= $low ? 'text-danger fw-bold' : '' ?>">
                                    <?= (int) $p['quantity_on_hand'] ?>
                                    <?php if ($low): ?><i class="bi bi-exclamation-triangle-fill" title="At or below low-stock threshold"></i><?php endif; ?>
                                </td>
                                <?php
                                    // For composites, use derived-from-recipe values (the "market price" rule).
                                    $isComp     = (int) ($p['is_composite'] ?? 0) === 1;
                                    $avgCost    = $isComp ? (float) $p['derived_avg_cost']    : (float) $p['moving_avg_cost'];
                                    $latestCost = $isComp ? (float) $p['derived_market_price'] : (float) $p['latest_purchase_landed_cost'];
                                ?>
                                <td class="text-end">
                                    <?= number_format($avgCost, 2) ?>
                                    <?php if ($isComp): ?><span class="small text-info d-block" title="Derived from components' moving-avg">derived</span><?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?= number_format($latestCost, 2) ?>
                                    <?php if ($isComp): ?>
                                        <span class="small text-info d-block" title="Derived from components' latest prices">derived</span>
                                    <?php elseif ($p['latest_purchase_at']): ?>
                                        <div class="small text-muted" title="Last purchase recorded at"><?= e(substr((string) $p['latest_purchase_at'], 0, 10)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php $qty = (float) $p['quantity_on_hand']; ?>
                                <td class="text-end">₹<?= number_format($qty * $avgCost, 2) ?></td>
                                <td class="text-end">₹<?= number_format($qty * $latestCost, 2) ?></td>
                                <td class="text-end"><?= number_format((float) $p['default_selling_price'], 2) ?></td>
                                <td><?= number_format((float) $p['tax_rate'], 2) ?></td>
                                <td>
                                    <?php if ($p['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/products/' . $p['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="<?= url('/products/' . $p['id'] . '/toggle') ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('Toggle status of <?= e($p['name']) ?>?')">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <?= $p['status'] === 'active' ? 'Disable' : 'Activate' ?>
                                        </button>
                                    </form>
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
