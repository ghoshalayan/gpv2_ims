<?php /** @var array $invoice */ /** @var array $lines */ /** @var array $expenses */ /** @var array $brandProducts */ /** @var array $preview */ ?>
<?php $hasLines = $lines !== []; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-pencil"></i> Edit Purchase Invoice
        <span class="badge text-bg-secondary ms-2">Draft</span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/purchase-invoices') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/cancel') ?>"
              onsubmit="return confirm('Cancel this draft? It will be kept with status=cancelled for audit history.')">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-outline-warning">
                <i class="bi bi-x-circle"></i> Cancel Draft
            </button>
        </form>
        <?php if (\App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN)): ?>
            <form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/delete') ?>"
                  onsubmit="return confirm('PERMANENTLY DELETE this draft? Lines and expenses will be removed. This cannot be undone.')">
                <?= \App\Support\Csrf::field() ?>
                <button class="btn btn-outline-danger">
                    <i class="bi bi-trash"></i> Delete Draft
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ============== HEADER ============== -->
<form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/update-header') ?>" class="mb-3">
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between">
            <strong>Header</strong>
            <?php if ($hasLines): ?>
                <span class="small text-muted">Supplier, brand, bill format, and GST flag are locked (lines exist).</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Supplier's Invoice #</label>
                    <input type="text" name="invoice_number" maxlength="100"
                           class="form-control" value="<?= e($invoice['invoice_number']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" name="invoice_date" class="form-control"
                           value="<?= e($invoice['invoice_date']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Supplier</label>
                    <input type="text" class="form-control" value="<?= e($invoice['supplier_name']) ?>" readonly>
                    <input type="hidden" name="supplier_id" value="<?= (int) $invoice['supplier_id'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Brand</label>
                    <input type="text" class="form-control" value="<?= e($invoice['brand_name']) ?>" readonly>
                    <input type="hidden" name="brand_id" value="<?= (int) $invoice['brand_id'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Intra-state?</label>
                    <input type="text" class="form-control"
                           value="<?= (int) $invoice['is_intra_state'] === 1 ? 'Yes (CGST+SGST)' : 'No (IGST)' ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        Bill Format
                        <i class="bi bi-info-circle text-muted" title="Per-line tax: each line has its own rate. Discount then GST: single rate on full bill subtotal after line discounts."></i>
                    </label>
                    <?php if ($hasLines): ?>
                        <input type="text" class="form-control" value="<?= e($invoice['bill_format']) ?>" readonly>
                        <input type="hidden" name="bill_format" id="bill_format_sel" value="<?= e($invoice['bill_format']) ?>">
                    <?php else: ?>
                        <select name="bill_format" class="form-select" id="bill_format_sel">
                            <option value="per_line_tax"      <?= $invoice['bill_format'] === 'per_line_tax'      ? 'selected' : '' ?>
                                    title="Standard GST invoice — each line carries its own rate.">Per-line tax</option>
                            <option value="discount_then_tax" <?= $invoice['bill_format'] === 'discount_then_tax' ? 'selected' : '' ?>
                                    title="Single rate on full bill subtotal after line discounts.">Discount then GST</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label">
                        Tax Treatment
                        <i class="bi bi-info-circle text-muted" title="Exclusive: price excludes tax. Inclusive: price includes tax. No tax: no GST on this bill."></i>
                    </label>
                    <select name="tax_treatment" class="form-select" id="tax_treatment_sel">
                        <option value="exclusive" <?= $invoice['tax_treatment'] === 'exclusive' ? 'selected' : '' ?>
                                title="Price excludes tax — added on top.">exclusive</option>
                        <option value="inclusive" <?= $invoice['tax_treatment'] === 'inclusive' ? 'selected' : '' ?>
                                title="Price already includes tax.">inclusive</option>
                        <option value="no_tax"    <?= $invoice['tax_treatment'] === 'no_tax'    ? 'selected' : '' ?>
                                title="No GST anywhere on this invoice.">no_tax</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">
                        GST Claimable
                        <i class="bi bi-info-circle text-muted" title="Yes: tax goes to GST Input Register (not cost). No: tax folds into landed cost."></i>
                    </label>
                    <?php $claimable = (int) $invoice['gst_claimable'] === 1; ?>
                    <select name="gst_claimable" class="form-select" id="gst_claimable_sel" <?= $hasLines ? 'disabled' : '' ?>>
                        <option value="1" <?= $claimable ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= !$claimable ? 'selected' : '' ?>>No</option>
                    </select>
                    <?php if ($hasLines): ?>
                        <input type="hidden" name="gst_claimable" value="<?= (int) $invoice['gst_claimable'] ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" maxlength="500" class="form-control"
                           value="<?= e($invoice['remarks'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div id="dispute_warnings" class="alert alert-warning small d-none mb-2">
                        <strong><i class="bi bi-exclamation-triangle"></i> Check your selections:</strong>
                        <ul class="mb-0 mt-1" id="dispute_warnings_list"></ul>
                    </div>
                    <button class="btn btn-sm btn-primary">
                        <i class="bi bi-check-lg"></i> Save Header
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- ============== LINES ============== -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Lines</strong> <span class="text-muted small ms-2">(products in this brand only)</span></div>
    <div class="card-body">
        <?php $hideLineTax = $invoice['bill_format'] === 'discount_then_tax'; ?>
        <?php if ($lines !== []): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">AddSub %</th>
                            <th class="text-end">Subtotal</th>
                            <?php if (!$hideLineTax): ?>
                                <th class="text-end">Tax %</th>
                                <th class="text-end">Tax</th>
                            <?php endif; ?>
                            <th class="text-end">Line Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $l): ?>
                            <tr>
                                <td><?= (int) $l['line_no'] ?></td>
                                <td><?= e($l['product_name']) ?> <span class="text-muted small">(<?= e($l['product_unit']) ?>)</span></td>
                                <td class="text-end">
                                    <?php if (!empty($l['entry_unit_code'])): ?>
                                        <?= rtrim(rtrim(number_format((float) $l['entry_quantity'], 3), '0'), '.') ?>
                                        <span class="text-muted small"><?= e($l['entry_unit_code']) ?></span>
                                        <div class="text-muted" style="font-size:.72rem">
                                            = <?= rtrim(rtrim(number_format((float) $l['quantity'], 3), '0'), '.') ?> <?= e($l['product_unit']) ?>
                                        </div>
                                    <?php else: ?>
                                        <?= number_format((float) $l['quantity'], 3) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= number_format((float) $l['unit_price'], 2) ?></td>
                                <td class="text-end <?= (float) $l['addsub_percent'] < 0 ? 'text-success' : '' ?>">
                                    <?= number_format((float) $l['addsub_percent'], 2) ?>%
                                </td>
                                <td class="text-end"><?= number_format((float) $l['subtotal'], 2) ?></td>
                                <?php if (!$hideLineTax): ?>
                                    <td class="text-end"><?= number_format((float) $l['tax_rate'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float) $l['tax_amount'], 2) ?></td>
                                <?php endif; ?>
                                <td class="text-end fw-bold"><?= number_format((float) $l['line_total'], 2) ?></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-line-btn"
                                            data-line-id="<?= (int) $l['id'] ?>"
                                            data-product-name="<?= e($l['product_name']) ?>"
                                            data-quantity="<?= e((string) $l['quantity']) ?>"
                                            data-unit-price="<?= e((string) $l['unit_price']) ?>"
                                            data-addsub-percent="<?= e((string) $l['addsub_percent']) ?>"
                                            data-tax-rate="<?= e((string) $l['tax_rate']) ?>"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/lines/' . $l['id'] . '/delete') ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('Remove this line?')">
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
        <?php else: ?>
            <p class="text-muted text-center py-2 mb-0">No lines yet. Add the first one below.</p>
        <?php endif; ?>

        <!-- Add-line form -->
        <?php if ($brandProducts === []): ?>
            <div class="alert alert-warning mt-3 mb-0 small">
                No active products exist for brand <strong><?= e($invoice['brand_name']) ?></strong>.
                <a href="<?= url('/products/create') ?>">Create one</a> first.
            </div>
        <?php else: ?>
            <form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/lines') ?>" class="mt-3 p-3 bg-light rounded">
                <?= \App\Support\Csrf::field() ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Product</label>
                        <select name="product_id" class="form-select form-select-sm" required data-products='<?= e(json_encode($brandProducts)) ?>'>
                            <option value="">— pick —</option>
                            <?php foreach ($brandProducts as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"
                                        data-price="<?= e($p['default_selling_price']) ?>"
                                        data-tax="<?= e($p['tax_rate']) ?>">
                                    <?= e($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">Qty</label>
                        <input type="number" name="quantity" min="0.001" step="0.001" class="form-control form-control-sm" id="add_quantity" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Unit</label>
                        <select name="entry_unit_id" id="add_entry_unit" class="form-select form-select-sm no-select2"></select>
                        <div class="form-text small" id="add_unit_hint"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Unit Price <span class="text-muted">/ stock unit</span></label>
                        <input type="number" name="unit_price" min="0" step="0.01" class="form-control form-control-sm" id="add_unit_price" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">AddSub % <span class="text-muted">(− = discount)</span></label>
                        <input type="number" name="addsub_percent" min="-100" max="100" step="0.01" class="form-control form-control-sm" value="0">
                    </div>
                    <?php if ($invoice['bill_format'] === 'per_line_tax'): ?>
                        <div class="col-md-2">
                            <label class="form-label small">Tax %</label>
                            <input type="number" name="tax_rate" min="0" max="100" step="0.01" class="form-control form-control-sm" id="add_tax_rate" value="0">
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="tax_rate" value="0">
                    <?php endif; ?>
                    <div class="col-md-2 text-end">
                        <button class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-plus-lg"></i> Add Line
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ============== EXPENSES ============== -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Expenses (loading, transport, fees…)</strong></div>
    <div class="card-body">
        <?php if ($expenses !== []): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Tax %</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end">Total</th>
                            <th>Allocation</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $ex): ?>
                            <tr>
                                <td><?= e($ex['expense_type']) ?></td>
                                <td class="text-end"><?= number_format((float) $ex['amount'], 2) ?></td>
                                <td class="text-end"><?= number_format((float) $ex['tax_rate'], 2) ?></td>
                                <td class="text-end"><?= number_format((float) $ex['tax_amount'], 2) ?></td>
                                <td class="text-end fw-bold"><?= number_format((float) $ex['total_with_tax'], 2) ?></td>
                                <td><span class="badge text-bg-info"><?= e(str_replace('_', ' ', $ex['allocation_method'])) ?></span></td>
                                <td class="text-muted small"><?= e($ex['remarks'] ?? '') ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/expenses/' . $ex['id'] . '/delete') ?>"
                                          onsubmit="return confirm('Remove this expense?')">
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
        <?php else: ?>
            <p class="text-muted text-center py-2 mb-0">No expenses yet.</p>
        <?php endif; ?>

        <form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/expenses') ?>" class="mt-3 p-3 bg-light rounded">
            <?= \App\Support\Csrf::field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Type</label>
                    <input type="text" name="expense_type" maxlength="100" class="form-control form-control-sm" placeholder="Transport, Loading…" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Amount</label>
                    <input type="number" name="amount" min="0" step="0.01" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label small">Tax %</label>
                    <input type="number" name="tax_rate" min="0" max="100" step="0.01" class="form-control form-control-sm" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Allocate by</label>
                    <select name="allocation_method" class="form-select form-select-sm">
                        <option value="by_value">By Value (default)</option>
                        <option value="by_quantity">By Quantity</option>
                        <option value="equal_split">Equal Split</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Remarks</label>
                    <input type="text" name="remarks" maxlength="255" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 text-end">
                    <button class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-plus-lg"></i> Add Expense
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ============== PREVIEW TOTALS ============== -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body small">
                <strong>Note:</strong> Preview totals are live but not yet persisted.
                Landed-cost per line is computed at finalize time (D10/D11).
                Clicking Finalize runs it all in one transaction, updates stock &amp; moving-avg,
                and locks the invoice.
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php $isModeA = $invoice['bill_format'] === 'discount_then_tax'; ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Totals Preview</strong></div>
            <div class="card-body">
                <?php if ($isModeA): ?>
                    <form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/update-footer') ?>" class="mb-2">
                        <?= \App\Support\Csrf::field() ?>
                        <table class="table table-sm mb-0">
                            <tr>
                                <th>Subtotal <small class="text-muted">(lines)</small></th>
                                <td class="text-end">₹<?= number_format((float) $preview['subtotal'], 2) ?></td>
                            </tr>
                            <tr>
                                <th class="text-success">− Final Discount (₹)</th>
                                <td class="text-end">
                                    <input type="number" name="final_discount_amount" min="0" step="0.01"
                                           class="form-control form-control-sm text-end"
                                           value="<?= e((string) ($invoice['final_discount_amount'] ?? 0)) ?>">
                                </td>
                            </tr>
                            <tr>
                                <th>= Taxable</th>
                                <td class="text-end">₹<?= number_format((float) $preview['taxable_subtotal'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>+ Tax @ (%)</th>
                                <td class="text-end">
                                    <input type="number" name="invoice_level_tax_rate" min="0" max="100" step="0.01"
                                           class="form-control form-control-sm text-end"
                                           value="<?= e((string) $invoice['invoice_level_tax_rate']) ?>">
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted small">Tax on lines</th>
                                <td class="text-end text-muted">₹<?= number_format((float) $preview['lines_tax'], 2) ?></td>
                            </tr>
                            <?php if ((float) $preview['expenses'] > 0): ?>
                                <tr><th>+ Expenses</th>
                                    <td class="text-end">₹<?= number_format((float) $preview['expenses'], 2) ?></td></tr>
                                <?php if ((float) $preview['expense_tax'] > 0): ?>
                                    <tr><th>+ Expense Tax</th>
                                        <td class="text-end">₹<?= number_format((float) $preview['expense_tax'], 2) ?></td></tr>
                                <?php endif; ?>
                            <?php endif; ?>
                            <tr class="table-light"><th>Grand Total</th>
                                <td class="text-end fw-bold">₹<?= number_format((float) $preview['grand_total'], 2) ?></td></tr>
                        </table>
                        <button class="btn btn-sm btn-outline-primary w-100 mt-2">
                            <i class="bi bi-arrow-repeat"></i> Update Discount &amp; Tax Rate
                        </button>
                    </form>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <tr><th>Subtotal <small class="text-muted">(pre-tax)</small></th>
                            <td class="text-end">₹<?= number_format((float) $preview['subtotal'], 2) ?></td></tr>
                        <tr><th>+ Line Taxes</th>
                            <td class="text-end">₹<?= number_format((float) $preview['line_tax'], 2) ?></td></tr>
                        <?php if ((float) $preview['expenses'] > 0): ?>
                            <tr><th>+ Expenses</th>
                                <td class="text-end">₹<?= number_format((float) $preview['expenses'], 2) ?></td></tr>
                            <?php if ((float) $preview['expense_tax'] > 0): ?>
                                <tr><th>+ Expense Tax</th>
                                    <td class="text-end">₹<?= number_format((float) $preview['expense_tax'], 2) ?></td></tr>
                            <?php endif; ?>
                        <?php endif; ?>
                        <tr class="table-light"><th>Grand Total</th>
                            <td class="text-end fw-bold">₹<?= number_format((float) $preview['grand_total'], 2) ?></td></tr>
                    </table>
                    <div class="form-text small mt-2">
                        No Final Discount in per-line-tax mode. Switch to "Discount then GST" to use one.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="<?= url('/purchase-invoices/' . $invoice['id'] . '/finalize') ?>" class="mt-3"
              onsubmit="return confirm('Finalize this invoice? Stock and costs will be updated. This cannot be undone in v1.')">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-success w-100" <?= $lines === [] ? 'disabled' : '' ?>>
                <i class="bi bi-lock-fill"></i> Finalize Invoice
            </button>
            <?php if ($lines === []): ?>
                <div class="form-text">Add at least one line to enable.</div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    // Auto-prefill unit price and tax rate when a product is selected.
    (function () {
        const sel   = document.querySelector('select[name="product_id"]');
        const price = document.getElementById('add_unit_price');
        const tax   = document.getElementById('add_tax_rate');
        const unitSel = document.getElementById('add_entry_unit');
        const qty   = document.getElementById('add_quantity');
        const hint  = document.getElementById('add_unit_hint');
        const unitMap = <?= json_encode($unitMap ?? [], JSON_UNESCAPED_UNICODE) ?>;

        function currentUnit() {
            if (!unitSel || !unitSel.selectedOptions.length) return null;
            const o = unitSel.selectedOptions[0];
            return { factor: parseFloat(o.dataset.factor || '1'), base: o.dataset.base || '', isBase: o.dataset.isBase === '1' };
        }
        function refreshHint() {
            if (!hint) return;
            const u = currentUnit();
            const q = parseFloat(qty && qty.value || '0');
            if (!u || u.isBase || !q) { hint.textContent = ''; return; }
            hint.textContent = '= ' + (+(q * u.factor).toFixed(3)) + ' ' + u.base;
        }
        function populateUnits(productId) {
            if (!unitSel) return;
            unitSel.innerHTML = '';
            const units = unitMap[productId] || [];
            units.forEach(function (u) {
                const opt = document.createElement('option');
                opt.value = u.unit_id;
                opt.textContent = u.code + (u.is_base ? ' (stock)' : ' ×' + u.factor);
                opt.dataset.factor = u.factor;
                opt.dataset.base = (units[0] && units[0].code) || u.code;
                opt.dataset.isBase = u.is_base ? '1' : '0';
                if (u.is_default) opt.selected = true;
                unitSel.appendChild(opt);
            });
            refreshHint();
        }
        if (sel) {
            sel.addEventListener('change', function () {
                const opt = sel.options[sel.selectedIndex];
                if (!opt || !opt.value) { if (unitSel) unitSel.innerHTML = ''; if (hint) hint.textContent=''; return; }
                if (price && !price.value) price.value = opt.dataset.price || '';
                if (tax   && !tax.value)   tax.value   = opt.dataset.tax   || '';
                populateUnits(opt.value);
            });
        }
        if (unitSel) unitSel.addEventListener('change', refreshHint);
        if (qty)     qty.addEventListener('input', refreshHint);
    })();

    // CR-1b — brand quantity-slab rate suggestion.
    //
    // Strictly a SUGGESTION: it fills the price field and says which slab it used, and
    // it stops suggesting the moment the user types their own price. Nothing here is
    // enforced on save — the server stores whatever is in the field, exactly as before.
    // slabMap is {} when the feature is off, so this block then does nothing at all.
    (function () {
        const slabMap = <?= json_encode($slabMap ?? [], JSON_UNESCAPED_UNICODE) ?>;
        if (!slabMap || Object.keys(slabMap).length === 0) { return; }

        const sel   = document.querySelector('select[name="product_id"]');
        const price = document.getElementById('add_unit_price');
        const qty   = document.getElementById('add_quantity');
        if (!sel || !price || !qty) { return; }

        // Once the user edits the price themselves, stop overwriting it.
        let userEdited = false;
        price.addEventListener('input', function () { userEdited = true; });

        let note = document.getElementById('slab_rate_note');
        if (!note) {
            note = document.createElement('div');
            note.id = 'slab_rate_note';
            note.className = 'form-text';
            price.parentNode.appendChild(note);
        }

        // Half-open [min, max); null max is unbounded — mirrors SlabRateService::covers().
        function bandFor(productId, q) {
            const bands = slabMap[productId];
            if (!bands || !(q > 0)) { return null; }
            for (let i = 0; i < bands.length; i++) {
                const b = bands[i];
                if (q >= b.min && (b.max === null || q < b.max)) { return b; }
            }
            return null;
        }

        function apply() {
            const productId = sel.value;
            const q = parseFloat(qty.value || '0');
            const band = bandFor(productId, q);

            if (!band) { note.textContent = ''; return; }

            if (userEdited) {
                // Respect the typed price, but still say what the slab would have been.
                note.textContent = 'Slab "' + band.label + '" rate is ' + band.rate;
                return;
            }
            price.value = band.rate;
            note.textContent = 'Auto-filled from slab "' + band.label + '" — editable.';
        }

        sel.addEventListener('change', function () { userEdited = false; apply(); });
        qty.addEventListener('input', apply);
        apply();
    })();

    // Header dispute warnings. Invoice-level rate lives in the footer now.
    (function () {
        const bf     = document.getElementById('bill_format_sel');
        const tt     = document.getElementById('tax_treatment_sel');
        const claim  = document.getElementById('gst_claimable_sel');
        const warnBox  = document.getElementById('dispute_warnings');
        const warnList = document.getElementById('dispute_warnings_list');

        function getVal(el) { return el ? el.value : ''; }

        function checkDisputes() {
            if (!warnBox) return;
            const warnings = [];
            const bfVal    = getVal(bf);
            const ttVal    = getVal(tt);
            const claimed  = claim ? claim.value === '1' : false;

            if (ttVal === 'no_tax' && claimed) {
                warnings.push('Tax treatment is <strong>No tax</strong> but <strong>GST claimable</strong> is Yes. There is no tax to claim — set claimable to No.');
            }
            if (ttVal === 'no_tax' && bfVal === 'per_line_tax') {
                warnings.push('<strong>No tax</strong> + <strong>Per-line tax</strong>: per-line tax rates will be ignored. Use 0% on each line or switch tax treatment.');
            }

            if (warnings.length === 0) {
                warnBox.classList.add('d-none');
                return;
            }
            warnBox.classList.remove('d-none');
            warnList.innerHTML = warnings.map(w => '<li>' + w + '</li>').join('');
        }

        [bf, tt, claim].forEach(el => el && el.addEventListener('change', checkDisputes));
        checkDisputes();
    })();
</script>

<!-- Edit-line modal — populated from each row's pencil button -->
<?php if ($invoice['status'] === 'draft' && $lines !== []): ?>
<div class="modal fade" id="editPurchaseLineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="editPurchaseLineForm" method="post" action="" novalidate>
            <?= \App\Support\Csrf::field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i> Edit line — <span id="epl_product_name"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Product is locked for in-place edit. Use Delete + Add to swap product. Totals will be
                        recomputed automatically when you save.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="epl_quantity">Quantity</label>
                            <input type="number" id="epl_quantity" name="quantity"
                                   step="0.001" min="0.001" required
                                   class="form-control text-end">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="epl_unit_price">Unit Price (₹)</label>
                            <input type="number" id="epl_unit_price" name="unit_price"
                                   step="0.01" min="0" required
                                   class="form-control text-end">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="epl_addsub_percent">AddSub %</label>
                            <input type="number" id="epl_addsub_percent" name="addsub_percent"
                                   step="0.01" min="-100" max="100"
                                   class="form-control text-end">
                            <div class="form-text small">+ marks-up / − discounts. Leave 0 if not used.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="epl_tax_rate">Tax %</label>
                            <input type="number" id="epl_tax_rate" name="tax_rate"
                                   step="0.01" min="0" max="100"
                                   class="form-control text-end">
                            <?php if ($hideLineTax): ?>
                                <div class="form-text small text-muted">
                                    Hidden in totals (this invoice uses invoice-level tax).
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save line
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    document.querySelectorAll('.edit-line-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const d = btn.dataset;
            const form = document.getElementById('editPurchaseLineForm');
            form.action = '<?= url('/purchase-invoices/' . (int) $invoice['id']) ?>/lines/' + d.lineId + '/update';
            document.getElementById('epl_product_name').textContent     = d.productName;
            document.getElementById('epl_quantity').value               = d.quantity;
            document.getElementById('epl_unit_price').value             = d.unitPrice;
            document.getElementById('epl_addsub_percent').value         = d.addsubPercent;
            document.getElementById('epl_tax_rate').value               = d.taxRate;
            new bootstrap.Modal(document.getElementById('editPurchaseLineModal')).show();
        });
    });
</script>
<?php endif; ?>
