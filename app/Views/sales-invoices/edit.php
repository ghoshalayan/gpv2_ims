<?php /** @var array $invoice */ /** @var array $lines */ /** @var array $products */ /** @var array $salesmen */ /** @var array $preview */ ?>
<?php $hasLines = $lines !== []; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-pencil"></i> Edit Sale
        <span class="badge text-bg-secondary ms-2">Draft</span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/sales-invoices') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/cancel') ?>"
              onsubmit="return confirm('Cancel this draft? It will be kept with status=cancelled for audit history.')">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-outline-warning"><i class="bi bi-x-circle"></i> Cancel Draft</button>
        </form>
        <?php if (\App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN)): ?>
            <form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/delete') ?>"
                  onsubmit="return confirm('PERMANENTLY DELETE this draft? Lines, extras, and components will be removed. This cannot be undone.')">
                <?= \App\Support\Csrf::field() ?>
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete Draft</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ============== HEADER ============== -->
<form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/update-header') ?>" class="mb-3">
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between">
            <strong>Header</strong>
            <?php if ($hasLines): ?>
                <span class="small text-muted">Customer and bill format are locked once lines exist.</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Customer</label>
                    <input type="text" class="form-control" value="<?= e($invoice['customer_name']) ?>" readonly>
                    <input type="hidden" name="customer_id" value="<?= (int) $invoice['customer_id'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" name="invoice_date" class="form-control"
                           value="<?= e($invoice['invoice_date']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Header Salesman</label>
                    <select name="header_salesman_id" class="form-select">
                        <option value="">— None (direct sale) —</option>
                        <?php foreach ($salesmen as $sm): ?>
                            <option value="<?= (int) $sm['id'] ?>"
                                <?= (int) $invoice['header_salesman_id'] === (int) $sm['id'] ? 'selected' : '' ?>>
                                <?= e($sm['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Intra-state?</label>
                    <input type="text" class="form-control"
                           value="<?= (int) $invoice['is_intra_state'] === 1 ? 'Yes (CGST+SGST)' : 'No (IGST)' ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Bill Format</label>
                    <?php if ($hasLines): ?>
                        <input type="text" class="form-control" value="<?= e($invoice['bill_format']) ?>" readonly>
                        <input type="hidden" name="bill_format" value="<?= e($invoice['bill_format']) ?>">
                    <?php else: ?>
                        <select name="bill_format" class="form-select" id="bill_format_sel">
                            <option value="per_line_tax"      <?= $invoice['bill_format'] === 'per_line_tax'      ? 'selected' : '' ?>>Per-line tax</option>
                            <option value="discount_then_tax" <?= $invoice['bill_format'] === 'discount_then_tax' ? 'selected' : '' ?>>Discount then GST</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tax Treatment</label>
                    <select name="tax_treatment" class="form-select">
                        <?php foreach (['exclusive', 'inclusive', 'no_tax'] as $tt): ?>
                            <option value="<?= e($tt) ?>" <?= $invoice['tax_treatment'] === $tt ? 'selected' : '' ?>>
                                <?= e($tt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" maxlength="500" class="form-control"
                           value="<?= e($invoice['remarks'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Save Header</button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- ============== LINES ============== -->
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Lines</strong>
        <span class="text-muted small ms-2">— per-line salesman attribution; display name is what prints on the bill</span>
    </div>
    <div class="card-body">
        <?php $hideLineTax = $invoice['bill_format'] === 'discount_then_tax'; ?>
        <?php if ($lines !== []): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Display Name<br><small class="text-muted fw-normal">(Product)</small></th>
                            <th>Brand</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">AddSub %</th>
                            <th class="text-end">Subtotal</th>
                            <?php if (!$hideLineTax): ?>
                                <th class="text-end">Tax %</th>
                                <th class="text-end">Tax</th>
                            <?php endif; ?>
                            <th class="text-end">Line Total</th>
                            <th>Salesman</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $l): ?>
                            <?php $isComposite = (int) $l['is_composite_line'] === 1; $isAdHoc = $isComposite && $l['product_id'] === null; ?>
                            <tr>
                                <td><?= (int) $l['line_no'] ?></td>
                                <td>
                                    <strong><?= e($l['display_name']) ?></strong>
                                    <?php if ($isComposite): ?>
                                        <span class="badge text-bg-<?= $isAdHoc ? 'warning' : 'info' ?> ms-1">
                                            <i class="bi bi-diagram-3"></i> <?= $isAdHoc ? 'ad-hoc composite' : 'composite' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$isAdHoc && $l['product_name'] && $l['display_name'] !== $l['product_name']): ?>
                                        <div class="small text-muted">orig: <?= e($l['product_name']) ?> (<?= e($l['product_unit']) ?>)</div>
                                    <?php elseif (!$isAdHoc && $l['product_unit']): ?>
                                        <div class="small text-muted">(<?= e($l['product_unit']) ?>)</div>
                                    <?php endif; ?>
                                    <?php if ($isComposite && !empty($lineComponents[(int) $l['id']])): ?>
                                        <ul class="small text-muted mb-0 ps-3 mt-1" style="list-style:'◦ ';">
                                            <?php foreach ($lineComponents[(int) $l['id']] as $comp): ?>
                                                <li>
                                                    <?= e($comp['component_name']) ?>
                                                    — <?= number_format((float) $comp['quantity_consumed'], 3) ?>
                                                    <?= e($comp['component_unit']) ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-muted small"><?= e($l['brand_name'] ?? ($isAdHoc ? 'ad-hoc' : '')) ?></span></td>
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
                                <td class="text-end <?= (float) $l['addsub_percent'] > 0 ? 'text-success' : '' ?>">
                                    <?= number_format((float) $l['addsub_percent'], 2) ?>%
                                </td>
                                <td class="text-end"><?= number_format((float) $l['subtotal'], 2) ?></td>
                                <?php if (!$hideLineTax): ?>
                                    <td class="text-end"><?= number_format((float) $l['tax_rate'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float) $l['tax_amount'], 2) ?></td>
                                <?php endif; ?>
                                <td class="text-end fw-bold"><?= number_format((float) $l['line_total'], 2) ?></td>
                                <td>
                                    <?php if ($l['salesman_name']): ?>
                                        <span class="badge text-bg-info"><?= e($l['salesman_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">direct</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-line-btn"
                                            data-bs-toggle="modal" data-bs-target="#editLineModal"
                                            data-line-id="<?= (int) $l['id'] ?>"
                                            data-display-name="<?= e($l['display_name']) ?>"
                                            data-quantity="<?= e($l['quantity']) ?>"
                                            data-unit-price="<?= e($l['unit_price']) ?>"
                                            data-addsub="<?= e($l['addsub_percent']) ?>"
                                            data-tax-rate="<?= e($l['tax_rate']) ?>"
                                            data-salesman="<?= (int) ($l['salesman_id'] ?? 0) ?>"
                                            data-comm-override="<?= $l['commission_rate_override'] === null ? '' : e((string) $l['commission_rate_override']) ?>"
                                            data-is-composite="<?= (int) $l['is_composite_line'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/lines/' . $l['id'] . '/delete') ?>"
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

        <!-- Add line form -->
        <?php if ($products === []): ?>
            <div class="alert alert-warning mt-3 mb-0 small">
                No active products available. <a href="<?= url('/products/create') ?>">Create one</a> first.
            </div>
        <?php else: ?>
            <form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/lines') ?>" class="mt-3 p-3 bg-light rounded">
                <?= \App\Support\Csrf::field() ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Product</label>
                        <?php $canSeeCost = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN, \App\Support\Auth::ROLE_SUBADMIN); ?>
                        <select name="product_id" id="add_product_id" class="form-select form-select-sm" required>
                            <option value="">— pick —</option>
                            <?php foreach ($products as $p): ?>
                                <?php
                                    $isComp = (int) $p['is_composite'] === 1;
                                    $effAvg    = $isComp ? (float) $p['derived_avg_cost']    : (float) $p['moving_avg_cost'];
                                    $effLatest = $isComp ? (float) $p['derived_market_price'] : (float) $p['latest_purchase_landed_cost'];
                                ?>
                                <option value="<?= (int) $p['id'] ?>"
                                        data-name="<?= e($p['name']) ?>"
                                        data-stock="<?= e($p['quantity_on_hand']) ?>"
                                        data-unit="<?= e($p['unit']) ?>"
                                        data-price="<?= e($p['default_selling_price']) ?>"
                                        data-tax="<?= e($p['tax_rate']) ?>"
                                        <?php if ($canSeeCost): ?>
                                        data-avg="<?= e((string) $effAvg) ?>"
                                        data-latest="<?= e((string) $effLatest) ?>"
                                        <?php endif; ?>
                                        data-composite="<?= (int) $p['is_composite'] ?>">
                                    <?= e($p['name']) ?> — <?= e($p['brand_name']) ?>
                                    (stock: <?= (int) $p['quantity_on_hand'] ?> <?= e($p['unit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($canSeeCost): ?>
                            <div id="cost_tooltip" class="small mt-1" style="display:none">
                                <table class="table table-sm mb-0 small bg-white border rounded" style="font-size:0.8rem">
                                    <tr><th class="px-2 py-1">Avg cost / unit</th><td class="text-end px-2 py-1" id="cost_tooltip_avg">—</td></tr>
                                    <tr><th class="px-2 py-1">Market / unit</th><td class="text-end px-2 py-1" id="cost_tooltip_latest">—</td></tr>
                                    <tr><th class="px-2 py-1">Selling price</th><td class="text-end px-2 py-1" id="cost_tooltip_price">—</td></tr>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Display Name <small class="text-muted">(printed on bill)</small></label>
                        <input type="text" name="display_name" id="add_display_name" maxlength="255" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">Qty</label>
                        <input type="number" name="quantity" id="add_quantity" min="0.001" step="0.001" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">Unit</label>
                        <select name="entry_unit_id" id="add_entry_unit" class="form-select form-select-sm no-select2"></select>
                        <div class="form-text small" id="add_unit_hint"></div>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">Price <span class="text-muted">/stock</span></label>
                        <input type="number" name="unit_price" id="add_unit_price" min="0" step="0.01" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">AddSub %</label>
                        <input type="number" name="addsub_percent" min="-100" max="100" step="0.01" class="form-control form-control-sm" value="0">
                    </div>
                    <?php if ($invoice['bill_format'] === 'per_line_tax'): ?>
                        <div class="col-md-1">
                            <label class="form-label small">Tax %</label>
                            <input type="number" name="tax_rate" id="add_tax_rate" min="0" max="100" step="0.01" class="form-control form-control-sm" value="0">
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="tax_rate" value="0">
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label small">Salesman</label>
                        <select name="salesman_id" id="add_salesman_id" class="form-select form-select-sm">
                            <option value="">— direct —</option>
                            <?php foreach ($salesmen as $sm): ?>
                                <option value="<?= (int) $sm['id'] ?>"
                                    <?= (int) $invoice['header_salesman_id'] === (int) $sm['id'] ? 'selected' : '' ?>>
                                    <?= e($sm['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small" title="Leave blank to use the salesman's own rate. Enter 0 for no commission on this sale.">
                            Comm %
                        </label>
                        <input type="number" name="commission_rate_override" id="add_commission_override"
                               min="0" max="100" step="0.01"
                               class="form-control form-control-sm" placeholder="auto">
                    </div>
                    <div class="col-md-1 text-end">
                        <button class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-plus-lg"></i> Add
                        </button>
                    </div>
                </div>
                <div class="form-text mt-2">
                    <i class="bi bi-info-circle"></i>
                    If you pick a saved <strong>Reversible composite</strong>, its recipe will be auto-expanded and components deducted on finalize.
                </div>
            </form>
        <?php endif; ?>

        <!-- ============== AD-HOC COMPOSITE LINE ============== -->
        <div class="mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#adhoc_box">
                <i class="bi bi-diagram-3"></i> Add Ad-hoc Composite Line
            </button>
        </div>

        <?php if ($products !== []): ?>
            <div id="adhoc_box" class="collapse mt-3">
                <div class="p-3 bg-warning-subtle rounded">
                    <div class="small mb-3">
                        Ad-hoc composite = on-the-spot recipe built from other products (e.g. custom paint mix).
                        No product master entry — just inline components consumed at finalize. At return time,
                        the system will ask whether to save it as a new product or split back to components.
                    </div>
                    <form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/ad-hoc-lines') ?>" id="adhoc_form">
                        <?= \App\Support\Csrf::field() ?>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small">Display Name <span class="text-danger">*</span></label>
                                <input type="text" name="display_name" maxlength="255" class="form-control form-control-sm" required
                                       placeholder="e.g. Custom Rose Pink Mix 1L">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small">Qty</label>
                                <input type="number" name="quantity" min="0.001" step="0.001" class="form-control form-control-sm" value="1" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Unit Price (₹)</label>
                                <input type="number" name="unit_price" min="0" step="0.01" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small">AddSub %</label>
                                <input type="number" name="addsub_percent" min="-100" max="100" step="0.01" class="form-control form-control-sm" value="0">
                            </div>
                            <?php if ($invoice['bill_format'] === 'per_line_tax'): ?>
                                <div class="col-md-1">
                                    <label class="form-label small">Tax %</label>
                                    <input type="number" name="tax_rate" min="0" max="100" step="0.01" class="form-control form-control-sm" value="0">
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="tax_rate" value="0">
                            <?php endif; ?>
                            <div class="col-md-2">
                                <label class="form-label small">Salesman</label>
                                <select name="salesman_id" class="form-select form-select-sm">
                                    <option value="">— direct —</option>
                                    <?php foreach ($salesmen as $sm): ?>
                                        <option value="<?= (int) $sm['id'] ?>"
                                            <?= (int) $invoice['header_salesman_id'] === (int) $sm['id'] ? 'selected' : '' ?>>
                                            <?= e($sm['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <strong class="small">Components</strong>
                            <span class="text-muted small ms-2">quantities are per 1 unit of the composite</span>
                        </div>
                        <div id="adhoc_components"></div>

                        <div class="row g-2 align-items-end mt-2">
                            <div class="col-md-4">
                                <label class="form-label small">Add component</label>
                                <select id="adhoc_pick_product" class="form-select form-select-sm">
                                    <option value="">— pick component —</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"
                                                data-name="<?= e($p['name']) ?>"
                                                data-unit="<?= e($p['unit']) ?>"
                                                data-stock="<?= e($p['quantity_on_hand']) ?>">
                                            <?= e($p['name']) ?> (<?= e($p['brand_name']) ?> · stock <?= (int) $p['quantity_on_hand'] ?> <?= e($p['unit']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Qty</label>
                                <input type="number" id="adhoc_pick_qty" min="0.001" step="0.001" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <button type="button" id="adhoc_add_btn" class="btn btn-sm btn-outline-secondary w-100">
                                    <i class="bi bi-plus-lg"></i> Add component
                                </button>
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="submit" class="btn btn-sm btn-warning w-100" id="adhoc_submit" disabled>
                                    <i class="bi bi-plus-lg"></i> Save Ad-hoc Composite Line
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============== FOOTER: EXTRAS + TOTALS + FINALIZE ============== -->
<?php $isModeA = $invoice['bill_format'] === 'discount_then_tax'; ?>
<div class="row g-3">
    <!-- ===== Extras editor (unified: each extra carries its own Tax %) ===== -->
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Extras / Charges</strong>
                <span class="text-muted small">Each extra has its own tax rate. 0% = non-taxable.</span>
            </div>
            <div class="card-body">
                <?php if (!empty($extras)): ?>
                    <div class="mb-3">
                    <?php foreach ($extras as $ex): ?>
                        <div class="row g-2 align-items-center mb-2">
                            <form method="post"
                                  action="<?= url('/sales-invoices/' . $invoice['id'] . '/extras/' . (int) $ex['id'] . '/update') ?>"
                                  class="row g-2 align-items-center flex-grow-1 mb-0"
                                  style="flex:1">
                                <?= \App\Support\Csrf::field() ?>
                                <div class="col-md-5">
                                    <input type="text" name="label" maxlength="100" class="form-control form-control-sm"
                                           value="<?= e($ex['label']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="amount" min="0.01" step="0.01"
                                           class="form-control form-control-sm text-end"
                                           value="<?= e((string) $ex['amount']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="tax_rate" min="0" max="100" step="0.01"
                                               class="form-control text-end"
                                               value="<?= e((string) $ex['tax_rate']) ?>">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <?php if ((float) $ex['tax_amount'] > 0): ?>
                                        <div class="form-text small">Tax ₹<?= number_format((float) $ex['tax_amount'], 2) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-1">
                                    <button class="btn btn-sm btn-outline-primary" title="Save">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </div>
                            </form>
                            <form method="post"
                                  action="<?= url('/sales-invoices/' . $invoice['id'] . '/extras/' . (int) $ex['id'] . '/delete') ?>"
                                  style="margin:0; width:auto"
                                  onsubmit="return confirm('Remove this extra?')">
                                <?= \App\Support\Csrf::field() ?>
                                <button class="btn btn-sm btn-outline-danger" title="Remove">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted small mb-3">No extras yet. Add e.g. Delivery, Packing, Loading below.</div>
                <?php endif; ?>

                <form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/extras') ?>">
                    <?= \App\Support\Csrf::field() ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small">Label</label>
                            <input type="text" name="label" maxlength="100" class="form-control form-control-sm" placeholder="e.g. Delivery">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Amount (₹)</label>
                            <input type="number" name="amount" min="0.01" step="0.01" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Tax %</label>
                            <input type="number" name="tax_rate" min="0" max="100" step="0.01" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-body small">
                <strong>Note:</strong> on Finalize, the system writes a stock-out for each line,
                snapshots COGS at the current moving-avg, snapshots commission per the assigned salesman's rate,
                computes CGST/SGST/IGST split, and assigns the next sequential <code>GP/{FY}/NNNNN</code> number.
                Insufficient stock blocks finalize.
            </div>
        </div>
    </div>

    <!-- ===== Totals (Mode A: editable discount + tax rate; Mode B: read-only) ===== -->
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Totals Preview</strong></div>
            <div class="card-body">
                <?php if ($isModeA): ?>
                    <form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/update-footer') ?>" class="mb-2">
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
                            <?php if ((float) $preview['total_extras'] > 0): ?>
                                <tr>
                                    <th>+ Extras</th>
                                    <td class="text-end">₹<?= number_format((float) $preview['total_extras'], 2) ?></td>
                                </tr>
                                <?php if ((float) $preview['total_extras_tax'] > 0): ?>
                                    <tr>
                                        <th>+ Extras Tax</th>
                                        <td class="text-end">₹<?= number_format((float) $preview['total_extras_tax'], 2) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                            <tr class="table-light">
                                <th>Grand Total</th>
                                <td class="text-end fw-bold">₹<?= number_format((float) $preview['grand_total'], 2) ?></td>
                            </tr>
                        </table>
                        <button class="btn btn-sm btn-outline-primary w-100 mt-2">
                            <i class="bi bi-arrow-repeat"></i> Update Discount & Tax Rate
                        </button>
                    </form>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Subtotal <small class="text-muted">(pre-tax)</small></th>
                            <td class="text-end">₹<?= number_format((float) $preview['subtotal'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>+ Line Taxes</th>
                            <td class="text-end">₹<?= number_format((float) $preview['line_tax'], 2) ?></td>
                        </tr>
                        <?php if ((float) $preview['total_extras'] > 0): ?>
                            <tr>
                                <th>+ Extras</th>
                                <td class="text-end">₹<?= number_format((float) $preview['total_extras'], 2) ?></td>
                            </tr>
                            <?php if ((float) $preview['total_extras_tax'] > 0): ?>
                                <tr>
                                    <th>+ Extras Tax</th>
                                    <td class="text-end">₹<?= number_format((float) $preview['total_extras_tax'], 2) ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                        <tr class="table-light">
                            <th>Grand Total</th>
                            <td class="text-end fw-bold">₹<?= number_format((float) $preview['grand_total'], 2) ?></td>
                        </tr>
                    </table>
                    <div class="form-text small mt-2">
                        No Final Discount in per-line-tax mode. Switch to "Discount then GST" to use one.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="<?= url('/sales-invoices/' . $invoice['id'] . '/finalize') ?>" class="mt-3"
              onsubmit="return confirm('Finalize this sale? Stock will be deducted. This cannot be undone in v1.')">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-success w-100" <?= $lines === [] ? 'disabled' : '' ?>>
                <i class="bi bi-lock-fill"></i> Finalize Sale
            </button>
            <?php if ($lines === []): ?>
                <div class="form-text">Add at least one line to enable.</div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ============== EDIT LINE MODAL ============== -->
<div class="modal fade" id="editLineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="editLineForm">
                <?= \App\Support\Csrf::field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Line</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="edit_composite_notice" class="alert alert-info py-2 small d-none">
                        This is a <strong>composite line</strong>. Quantity is locked — to change qty, delete this line and re-add.
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Display Name</label>
                            <input type="text" name="display_name" id="edit_display_name" maxlength="255" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Quantity</label>
                            <input type="number" name="quantity" id="edit_quantity" min="0.001" step="0.001" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Unit Price (₹)</label>
                            <input type="number" name="unit_price" id="edit_unit_price" min="0" step="0.01" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">AddSub %</label>
                            <input type="number" name="addsub_percent" id="edit_addsub" min="-100" max="100" step="0.01" class="form-control form-control-sm">
                        </div>
                        <?php if ($invoice['bill_format'] === 'per_line_tax'): ?>
                            <div class="col-md-6">
                                <label class="form-label small">Tax %</label>
                                <input type="number" name="tax_rate" id="edit_tax_rate" min="0" max="100" step="0.01" class="form-control form-control-sm">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="tax_rate" id="edit_tax_rate" value="0">
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label small">Salesman</label>
                            <select name="salesman_id" id="edit_salesman_id" class="form-select form-select-sm no-select2">
                                <option value="">— direct —</option>
                                <?php foreach ($salesmen as $sm): ?>
                                    <option value="<?= (int) $sm['id'] ?>"><?= e($sm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="edit_commission_override">Commission % (this sale)</label>
                            <input type="number" name="commission_rate_override" id="edit_commission_override"
                                   min="0" max="100" step="0.01"
                                   class="form-control form-control-sm" placeholder="auto">
                            <div class="form-text">
                                Blank uses the salesman's own rate. Enter 0 for no commission on this line.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Line</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    jQuery(function ($) {
        // --- Auto-fill display name + price + tax when product is selected. ---
        // (overwrites even if the fields hold an old value — bug fix for #3)
        const $sel = $('#add_product_id');
        function applyProductDefaults() {
            const opt = $sel[0].options[$sel[0].selectedIndex];
            if (!opt || !opt.value) { populateUnits(''); return; }
            $('#add_display_name').val(opt.dataset.name  || '');
            $('#add_unit_price').val(  opt.dataset.price || '');
            $('#add_tax_rate').val(    opt.dataset.tax   || '');
            showCostTooltip(opt);
            populateUnits(opt.value);
        }
        $sel.on('change', applyProductDefaults);

        // --- Entry-unit dropdown + live "= N base" hint (quantity converts to base) ---
        const unitMap = <?= json_encode($unitMap ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const unitSel = document.getElementById('add_entry_unit');
        const qtyEl   = document.getElementById('add_quantity');
        const hintEl  = document.getElementById('add_unit_hint');
        function refreshUnitHint() {
            if (!hintEl || !unitSel || !unitSel.selectedOptions.length) { if (hintEl) hintEl.textContent=''; return; }
            const o = unitSel.selectedOptions[0];
            const q = parseFloat(qtyEl && qtyEl.value || '0');
            if (o.dataset.isBase === '1' || !q) { hintEl.textContent = ''; return; }
            hintEl.textContent = '= ' + (+(q * parseFloat(o.dataset.factor || '1')).toFixed(3)) + ' ' + (o.dataset.base || '');
        }
        function populateUnits(productId) {
            if (!unitSel) return;
            unitSel.innerHTML = '';
            (unitMap[productId] || []).forEach(function (u, i, arr) {
                const opt = document.createElement('option');
                opt.value = u.unit_id;
                opt.textContent = u.code + (u.is_base ? ' (stock)' : ' ×' + u.factor);
                opt.dataset.factor = u.factor;
                opt.dataset.base = (arr[0] && arr[0].code) || u.code;
                opt.dataset.isBase = u.is_base ? '1' : '0';
                if (u.is_default) opt.selected = true;
                unitSel.appendChild(opt);
            });
            refreshUnitHint();
        }
        if (unitSel) unitSel.addEventListener('change', refreshUnitHint);
        if (qtyEl)   qtyEl.addEventListener('input', refreshUnitHint);

        // --- Cost tooltip (permission-gated server-side: data-avg/data-latest present only for admin/mgmt). ---
        const $tip = $('#cost_tooltip');
        function showCostTooltip(opt) {
            if (!$tip.length) return;
            const hasCost = opt && (opt.dataset.avg !== undefined);
            if (!hasCost || !opt.value) { $tip.hide(); return; }
            $('#cost_tooltip_avg').text('₹' + parseFloat(opt.dataset.avg || '0').toFixed(2));
            $('#cost_tooltip_latest').text('₹' + parseFloat(opt.dataset.latest || '0').toFixed(2));
            $('#cost_tooltip_price').text('₹' + parseFloat(opt.dataset.price || '0').toFixed(2));
            $tip.show();
        }

        // --- Edit Line modal population ---
        const invoiceId = <?= (int) $invoice['id'] ?>;
        $('.edit-line-btn').on('click', function () {
            const d = this.dataset;
            $('#editLineForm').attr('action', '<?= url('/sales-invoices/' . $invoice['id']) ?>/lines/' + d.lineId + '/update');
            $('#edit_display_name').val(d.displayName);
            $('#edit_quantity').val(d.quantity);
            $('#edit_unit_price').val(d.unitPrice);
            $('#edit_addsub').val(d.addsub);
            $('#edit_tax_rate').val(d.taxRate);
            $('#edit_salesman_id').val(d.salesman || '');
            // CR-6: '' means "use the salesman's rate"; '0' is a real 0% and must survive.
            $('#edit_commission_override').val(
                (d.commOverride === undefined || d.commOverride === null) ? '' : d.commOverride
            );
            const isComp = d.isComposite === '1';
            $('#edit_composite_notice').toggleClass('d-none', !isComp);
            $('#edit_quantity').prop('readonly', isComp);
        });

        // Bill format toggle (only when no lines yet)
        const $bf = $('#bill_format_sel');
        const $wrap = $('#invoice_level_tax_wrap');
        if ($bf.length) {
            $bf.on('change', () => $wrap.toggle($bf.val() === 'discount_then_tax'));
        }

        // Ad-hoc composite — client-side staging of components.
        const $pick   = $('#adhoc_pick_product');
        const $pickQ  = $('#adhoc_pick_qty');
        const $addBtn = $('#adhoc_add_btn');
        const $table  = $('#adhoc_components');
        const $submit = $('#adhoc_submit');
        let adhocIndex = 0;
        const staged = new Set();

        function refreshSubmit() {
            $submit.prop('disabled', adhocIndex === 0 || staged.size === 0);
        }

        $addBtn.on('click', function () {
            const id  = parseInt($pick.val() || '0');
            const qty = parseFloat($pickQ.val() || '0');
            if (!id) { alert('Pick a component first.'); return; }
            if (!(qty > 0)) { alert('Enter a positive quantity.'); return; }
            if (staged.has(id)) { alert('Component already staged — remove and re-add to change qty.'); return; }

            const opt = $pick[0].options[$pick[0].selectedIndex];
            const name = opt.dataset.name;
            const unit = opt.dataset.unit;
            const stock = parseFloat(opt.dataset.stock || '0');

            const i = adhocIndex++;
            staged.add(id);

            const row = $(`
                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-white mb-1" data-idx="${i}" data-pid="${id}">
                    <input type="hidden" name="components[${i}][product_id]" value="${id}">
                    <input type="hidden" name="components[${i}][quantity]"   value="${qty}">
                    <div class="flex-grow-1">
                        <strong>${$('<div>').text(name).html()}</strong>
                        <span class="text-muted small"> — ${qty.toFixed(3)} ${$('<div>').text(unit).html()}
                            (stock: ${stock}${stock < qty ? ' — short!' : ''})
                        </span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger adhoc-remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `);
            $table.append(row);
            $pick.val('').trigger('change');
            $pickQ.val('');
            refreshSubmit();
        });

        $table.on('click', '.adhoc-remove', function () {
            const $row = $(this).closest('[data-idx]');
            const pid = parseInt($row.data('pid'));
            staged.delete(pid);
            $row.remove();
            refreshSubmit();
        });

        refreshSubmit();
    });
});
</script>
