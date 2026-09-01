<?php /** @var array $brands */ /** @var array $categories */ /** @var array $subcategoriesByCategory */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Product</h1>
    <a href="<?= url('/products') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/products') ?>" novalidate>
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
                                   class="form-control" value="<?= e(old('name')) ?>" required autofocus>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="brand_id">Brand <span class="text-danger">*</span></label>
                            <select id="brand_id" name="brand_id" class="form-select" required>
                                <option value="">— Select brand —</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"
                                        <?= (int) old('brand_id') === (int) $b['id'] ? 'selected' : '' ?>>
                                        <?= e($b['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="unit_id">Stock Unit <span class="text-danger">*</span></label>
                            <select id="unit_id" name="unit_id" class="form-select" required>
                                <?php $selUnit = (int) old('unit_id', '0'); ?>
                                <?php foreach (($unitsList ?? []) as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"
                                        <?= ($selUnit === (int) $u['id'] || ($selUnit === 0 && $u['code'] === 'PCS')) ? 'selected' : '' ?>>
                                        <?= e($u['code']) ?> — <?= e($u['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                The unit stock is tracked in. Manage the list under
                                <a href="<?= url('/units') ?>">Units</a>; add buy/sell packs per product after saving.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">— Select category —</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"
                                        <?= (int) old('category_id') === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="subcategory_id">Sub-Category <span class="text-danger">*</span></label>
                            <select id="subcategory_id" name="subcategory_id" class="form-select" required>
                                <option value="">— Pick a category first —</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" maxlength="500" rows="2"
                                      class="form-control"><?= e(old('description')) ?></textarea>
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
                                   class="form-control" value="<?= e(old('hsn_code')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tax_rate">Tax Rate (%)</label>
                            <input type="number" id="tax_rate" name="tax_rate" min="0" max="100" step="0.01"
                                   class="form-control" value="<?= e(old('tax_rate', '0')) ?>">
                            <div class="form-text">GST applicable to this item.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="default_selling_price">Default Selling Price (₹)</label>
                            <input type="number" id="default_selling_price" name="default_selling_price"
                                   min="0" step="0.01" class="form-control"
                                   value="<?= e(old('default_selling_price', '0')) ?>">
                            <div class="form-text">Suggested only — can be overridden per sale.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Opening Stock</strong></div>
                <div class="card-body">
                    <p class="small text-muted">
                        Set the quantity and per-unit cost the business is starting with.
                        After creation, stock and cost are maintained automatically by purchases, sales, and returns.
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="opening_quantity">Opening Quantity</label>
                        <input type="number" id="opening_quantity" name="opening_quantity" min="0"
                               class="form-control" value="<?= e(old('opening_quantity', '0')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="opening_avg_cost">
                            Opening Per-Unit Cost (₹)
                            <i class="bi bi-info-circle text-muted" title="Average cost basis of the opening stock. Used as the starting moving-average."></i>
                        </label>
                        <input type="number" id="opening_avg_cost" name="opening_avg_cost" min="0" step="0.0001"
                               class="form-control" value="<?= e(old('opening_avg_cost', '0')) ?>">
                        <div class="form-text">Required if opening qty &gt; 0.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="opening_latest_cost">
                            Latest Purchase Cost (₹)
                            <i class="bi bi-info-circle text-muted" title="Most recent known per-unit landed cost — used for current market-value calculations and composite raw-pricing. Leave blank to default to the opening cost above."></i>
                        </label>
                        <input type="number" id="opening_latest_cost" name="opening_latest_cost" min="0" step="0.0001"
                               class="form-control" value="<?= e(old('opening_latest_cost', '')) ?>"
                               placeholder="Blank = same as opening cost">
                        <div class="form-text">
                            Will be auto-updated on every future purchase finalize (D65).
                            Useful when the latest known price differs from the opening-stock's average cost.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Alerts</strong></div>
                <div class="card-body">
                    <label class="form-label" for="low_stock_threshold">Low Stock Threshold</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0"
                           class="form-control" value="<?= e(old('low_stock_threshold', '0')) ?>">
                    <div class="form-text">0 = no alert. Otherwise, item is flagged when stock reaches this number.</div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Composite (BOM)</strong></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="is_composite" name="is_composite" value="1">
                        <label class="form-check-label" for="is_composite">
                            This product is a composite (built from a recipe)
                            <i class="bi bi-info-circle text-muted" title="Tick to define the product's recipe inline below."></i>
                        </label>
                    </div>
                    <div id="composite_subflags" style="display:none">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="is_irreversible" name="is_irreversible" value="1">
                            <label class="form-check-label" for="is_irreversible">
                                Irreversible (physical — needs Production Orders, has own stock)
                                <i class="bi bi-info-circle text-muted" title="ON = physical composite (paint mix) — components consumed at production, composite tracked as its own stock. OFF = virtual (assembled computer) — sale deducts components; no own stock."></i>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="expose_components" name="expose_components" value="1">
                            <label class="form-check-label" for="expose_components">
                                Print component list on the bill
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============== INLINE RECIPE EDITOR (only for composites) ============== -->
    <div id="recipe_section" class="mt-3" style="display:none">
        <div class="card shadow-sm border-info">
            <div class="card-header bg-info-subtle d-flex justify-content-between">
                <strong><i class="bi bi-diagram-3"></i> Recipe</strong>
                <span class="small text-muted">
                    Quantities are per <strong>1</strong> unit of the composite. Back-calculated costs update live below.
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div id="recipe_rows_wrapper">
                            <table class="table table-sm align-middle" id="recipe_rows_table" style="display:none">
                                <thead>
                                    <tr>
                                        <th>Component</th>
                                        <th class="text-end">Qty (per 1 unit)</th>
                                        <th class="text-end">Avg Cost</th>
                                        <th class="text-end">Latest Cost</th>
                                        <th class="text-end">Line Cost (avg)</th>
                                        <th class="text-end">Line Cost (latest)</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="recipe_rows_body"></tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th colspan="4" class="text-end">Per-unit cost</th>
                                        <th class="text-end fw-bold" id="cost_avg_total">₹0.00</th>
                                        <th class="text-end fw-bold text-info" id="cost_latest_total">₹0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                            <p id="recipe_empty" class="text-muted text-center py-3 mb-0">
                                No components yet. Pick one below to start the recipe.
                            </p>
                        </div>

                        <div class="row g-2 align-items-end mt-2 p-2 bg-light rounded">
                            <div class="col-md-6">
                                <label class="form-label small">Add component</label>
                                <select id="recipe_picker" class="form-select form-select-sm">
                                    <option value="">— pick —</option>
                                    <?php foreach ($componentCandidates as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"
                                                data-name="<?= e($p['name']) ?>"
                                                data-unit="<?= e($p['unit']) ?>"
                                                data-brand="<?= e($p['brand_name']) ?>"
                                                data-avg="<?= e($p['moving_avg_cost']) ?>"
                                                data-latest="<?= e($p['latest_purchase_landed_cost']) ?>"
                                                data-stock="<?= e($p['quantity_on_hand']) ?>"
                                                data-composite="<?= (int) $p['is_composite'] ?>">
                                            <?= e($p['name']) ?> — <?= e($p['brand_name']) ?>
                                            (<?= e($p['unit']) ?>, stock <?= (float) $p['quantity_on_hand'] ?>)<?= (int) $p['is_composite'] === 1 ? ' [composite]' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Qty (per 1 unit)</label>
                                <input type="number" id="recipe_qty" min="0.001" step="0.001" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="recipe_add_btn" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-plus-lg"></i> Add to recipe
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-info">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Back-calculated per-unit cost</h6>
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <th title="Cost if built today at each component's moving-average">Moving-avg total</th>
                                        <td class="text-end fw-bold" id="cost_avg_summary">₹0.00</td>
                                    </tr>
                                    <tr>
                                        <th title="Market/replacement price — what it would cost at today's latest prices">Latest-price total</th>
                                        <td class="text-end fw-bold text-info" id="cost_latest_summary">₹0.00</td>
                                    </tr>
                                </table>
                                <p class="small text-muted mt-2 mb-0">
                                    These update live as you add/remove components.
                                    Latest-price is the composite's <strong>market price</strong> per the raw-products rule.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create
        </button>
        <a href="<?= url('/products') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    window.subcategoriesByCategory = <?= json_encode($subcategoriesByCategory, JSON_UNESCAPED_UNICODE) ?>;
    window.preselectedSubcategory = <?= (int) old('subcategory_id') ?>;

    // Wait for DOMContentLoaded so jQuery (loaded at bottom of body) is available.
    document.addEventListener('DOMContentLoaded', function () {
        jQuery(function ($) {
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
            if ($cat.val()) refreshSubs();

            // ---- Composite toggle + recipe section ----
            const $comp     = $('#is_composite');
            const $subflags = $('#composite_subflags');
            const $recipe   = $('#recipe_section');
            $comp.on('change', function () {
                const on = $comp.is(':checked');
                $subflags.toggle(on);
                $recipe.toggle(on);
            });

            // ---- Inline recipe staging ----
            const $picker = $('#recipe_picker');
            const $qty    = $('#recipe_qty');
            const $addBtn = $('#recipe_add_btn');
            const $table  = $('#recipe_rows_table');
            const $body   = $('#recipe_rows_body');
            const $empty  = $('#recipe_empty');
            const $avg    = $('#cost_avg_total');
            const $latest = $('#cost_latest_total');
            const $avgSum = $('#cost_avg_summary');
            const $latSum = $('#cost_latest_summary');

            let rowIndex = 0;
            const staged = new Set(); // component_product_id

            function fmt(n)   { return '₹' + (n || 0).toFixed(2); }
            function esc(s)   { return $('<div>').text(s == null ? '' : s).html(); }

            function refreshTotals() {
                let totalAvg = 0, totalLatest = 0;
                $body.find('tr').each(function () {
                    const $r = $(this);
                    totalAvg    += parseFloat($r.data('lineAvg')    || 0);
                    totalLatest += parseFloat($r.data('lineLatest') || 0);
                });
                $avg.text(fmt(totalAvg));
                $latest.text(fmt(totalLatest));
                $avgSum.text(fmt(totalAvg));
                $latSum.text(fmt(totalLatest));

                if (staged.size === 0) {
                    $table.hide();
                    $empty.show();
                } else {
                    $table.show();
                    $empty.hide();
                }
            }

            $addBtn.on('click', function () {
                const cid = parseInt($picker.val() || '0');
                const q   = parseFloat($qty.val()    || '0');
                if (!cid) { alert('Pick a component.'); return; }
                if (!(q > 0)) { alert('Quantity must be > 0.'); return; }
                if (staged.has(cid)) { alert('That component is already in the recipe — remove to change qty.'); return; }

                const opt = $picker[0].options[$picker[0].selectedIndex];
                const name   = opt.dataset.name;
                const unit   = opt.dataset.unit;
                const brand  = opt.dataset.brand;
                const avg    = parseFloat(opt.dataset.avg    || '0');
                const latest = parseFloat(opt.dataset.latest || '0');
                const lineAvg    = q * avg;
                const lineLatest = q * latest;

                const i = rowIndex++;
                staged.add(cid);

                const $row = $(
                    '<tr data-line-avg="'    + lineAvg    + '" data-line-latest="' + lineLatest + '" data-pid="' + cid + '">'
                    + '<td><input type="hidden" name="recipe[' + i + '][component_product_id]" value="' + cid + '">'
                    +   '<input type="hidden" name="recipe[' + i + '][quantity]" value="' + q + '">'
                    +   '<strong>' + esc(name) + '</strong>'
                    +   '<div class="small text-muted">' + esc(brand) + ' · ' + esc(unit) + '</div></td>'
                    + '<td class="text-end">' + q.toFixed(3) + ' ' + esc(unit) + '</td>'
                    + '<td class="text-end">' + '₹' + avg.toFixed(4)    + '</td>'
                    + '<td class="text-end">' + '₹' + latest.toFixed(4) + '</td>'
                    + '<td class="text-end fw-bold">' + fmt(lineAvg)    + '</td>'
                    + '<td class="text-end fw-bold text-info">' + fmt(lineLatest) + '</td>'
                    + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger recipe-remove">'
                    +   '<i class="bi bi-trash"></i></button></td>'
                    + '</tr>'
                );
                $body.append($row);
                $picker.val('').trigger('change');
                $qty.val('');
                refreshTotals();
            });

            $body.on('click', '.recipe-remove', function () {
                const $row = $(this).closest('tr');
                staged.delete(parseInt($row.data('pid')));
                $row.remove();
                refreshTotals();
            });

            refreshTotals();
        });
    });
</script>
