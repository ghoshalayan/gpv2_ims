<?php /** @var array $products */ /** @var array $recipes */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Production Order</h1>
    <a href="<?= url('/production-orders') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/production-orders') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Setup</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="product_id">Composite Product <span class="text-danger">*</span></label>
                            <select id="product_id" name="product_id" class="form-select" required>
                                <option value="">— Select composite —</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"
                                            data-unit="<?= e($p['unit']) ?>">
                                        <?= e($p['name']) ?> — <?= e($p['brand_name']) ?>
                                        (stock: <?= number_format((float) $p['quantity_on_hand'], 3) ?> <?= e($p['unit']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only active Irreversible composites with at least one recipe component are shown.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="quantity_produced">Qty to Produce <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" id="quantity_produced" name="quantity_produced"
                                       min="0.001" step="0.001" class="form-control" required>
                                <span class="input-group-text" id="unit_suffix">—</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="produced_date">Date <span class="text-danger">*</span></label>
                            <input type="date" id="produced_date" name="produced_date"
                                   class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="500" class="form-control"></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3" id="submit_btn" disabled>
                        <i class="bi bi-gear-wide-connected"></i> Produce (consume components + add composite stock)
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between">
                    <strong>Preview</strong>
                    <span class="small text-muted">Recomputes live from the recipe + quantity.</span>
                </div>
                <div class="card-body">
                    <div id="preview_empty" class="text-center text-muted py-4">
                        Pick a composite product and enter a quantity to see the component breakdown.
                    </div>
                    <div id="preview_body" style="display:none">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Component</th>
                                        <th class="text-end">Per Unit</th>
                                        <th class="text-end">Needed</th>
                                        <th class="text-end">Stock</th>
                                        <th>Status</th>
                                        <th class="text-end">Avg Cost</th>
                                        <th class="text-end">Line Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="preview_rows"></tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th colspan="6" class="text-end">Total Component Cost</th>
                                        <th class="text-end" id="total_cost">₹0.00</th>
                                    </tr>
                                    <tr class="table-light">
                                        <th colspan="6" class="text-end">Landed Cost per Unit of Composite</th>
                                        <th class="text-end fw-bold" id="landed_cost">₹0.0000</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div id="stock_warning" class="alert alert-danger small d-none mb-0">
                            <i class="bi bi-exclamation-triangle"></i>
                            Insufficient stock for one or more components — production will be blocked.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    window.productionRecipes = <?= json_encode($recipes, JSON_UNESCAPED_UNICODE) ?>;

    document.addEventListener('DOMContentLoaded', function () {
    jQuery(function ($) {
        const $prod = $('#product_id');
        const $qty  = $('#quantity_produced');
        const $unit = $('#unit_suffix');
        const $rows = $('#preview_rows');
        const $empty = $('#preview_empty');
        const $body = $('#preview_body');
        const $total = $('#total_cost');
        const $landed = $('#landed_cost');
        const $warn  = $('#stock_warning');
        const $submit = $('#submit_btn');

        function refresh() {
            const pid = parseInt($prod.val());
            const qty = parseFloat($qty.val() || '0');
            const opt = $prod[0].options[$prod[0].selectedIndex];
            $unit.text(opt?.dataset?.unit || '—');

            if (!pid || qty <= 0 || !window.productionRecipes[pid]) {
                $empty.show(); $body.hide(); $submit.prop('disabled', true);
                return;
            }
            $empty.hide(); $body.show();

            const recipe = window.productionRecipes[pid];
            let total = 0;
            let anyShort = false;
            $rows.empty();

            recipe.forEach(r => {
                const needed = r.recipe_quantity * qty;
                const lineCost = needed * r.moving_avg_cost;
                total += lineCost;
                const short = r.component_stock < needed - 1e-9;
                const inactive = r.component_status !== 'active';
                if (short || inactive) anyShort = true;

                const statusBadge = inactive
                    ? '<span class="badge text-bg-warning">inactive</span>'
                    : (short
                        ? '<span class="badge text-bg-danger">short</span>'
                        : '<span class="badge text-bg-success">ok</span>');

                $rows.append(`
                    <tr class="${short || inactive ? 'table-danger' : ''}">
                        <td>${$('<div>').text(r.name).html()} <small class="text-muted">(${$('<div>').text(r.unit).html()})</small></td>
                        <td class="text-end">${r.recipe_quantity.toFixed(3)}</td>
                        <td class="text-end fw-bold">${needed.toFixed(3)}</td>
                        <td class="text-end">${r.component_stock.toFixed(3)}</td>
                        <td>${statusBadge}</td>
                        <td class="text-end">₹${r.moving_avg_cost.toFixed(4)}</td>
                        <td class="text-end">₹${lineCost.toFixed(2)}</td>
                    </tr>
                `);
            });

            $total.text('₹' + total.toFixed(2));
            $landed.text('₹' + (qty > 0 ? (total / qty).toFixed(4) : '0.0000'));
            $warn.toggleClass('d-none', !anyShort);
            $submit.prop('disabled', anyShort);
        }

        $prod.on('change', refresh);
        $qty.on('input', refresh);
        refresh();
    });
    });
</script>
