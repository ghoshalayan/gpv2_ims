<?php /** @var array $suppliers */ /** @var array $brands */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Purchase Invoice</h1>
    <a href="<?= url('/purchase-invoices') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    Step 1 — create the invoice header. After saving, you'll add lines and expenses, then finalize.
    Header fields like supplier, brand, bill format, and GST claimability are locked once you add the first line.
</div>

<form method="post" action="<?= url('/purchase-invoices') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Header</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="invoice_number">Supplier's Invoice # <span class="text-danger">*</span></label>
                            <input type="text" id="invoice_number" name="invoice_number" maxlength="100"
                                   class="form-control" value="<?= e(old('invoice_number')) ?>" required autofocus>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="invoice_date">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" id="invoice_date" name="invoice_date"
                                   class="form-control" value="<?= e(old('invoice_date', date('Y-m-d'))) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            <select id="supplier_id" name="supplier_id" class="form-select" required>
                                <option value="">— Select supplier —</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"
                                        <?= (int) old('supplier_id') === (int) $s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <div class="form-text">Single-brand enforcement (D14): every line must be this brand's product.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="2" maxlength="500"
                                      class="form-control"><?= e(old('remarks')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Tax &amp; Bill Format</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="bill_format">
                            Bill Format
                            <i class="bi bi-info-circle text-muted" title="Per-line tax: each product line has its own tax rate (standard GST invoice). Discount then GST: apply discount on line subtotals first, then a single GST rate on the full bill."></i>
                        </label>
                        <select id="bill_format" name="bill_format" class="form-select">
                            <?php $bf = old('bill_format', 'per_line_tax'); ?>
                            <option value="per_line_tax"      <?= $bf === 'per_line_tax'      ? 'selected' : '' ?>
                                    title="Standard GST invoice — each line carries its own tax rate.">
                                Per-line tax (standard GST bill)
                            </option>
                            <option value="discount_then_tax" <?= $bf === 'discount_then_tax' ? 'selected' : '' ?>
                                    title="Single GST rate on bill subtotal (after line discounts).">
                                Discount then GST (single rate on subtotal)
                            </option>
                        </select>
                        <div class="form-text" id="bill_format_help"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="tax_treatment">
                            Tax Treatment
                            <i class="bi bi-info-circle text-muted" title="Exclusive: price excludes tax (added on top). Inclusive: price includes tax (backed out for accounting). No tax: no GST on this bill."></i>
                        </label>
                        <select id="tax_treatment" name="tax_treatment" class="form-select">
                            <?php $tt = old('tax_treatment', 'exclusive'); ?>
                            <option value="exclusive" <?= $tt === 'exclusive' ? 'selected' : '' ?>
                                    title="Unit price excludes tax. Tax is added on top.">
                                Tax exclusive (price excludes tax)
                            </option>
                            <option value="inclusive" <?= $tt === 'inclusive' ? 'selected' : '' ?>
                                    title="Unit price already includes tax. Backed out for accounting.">
                                Tax inclusive (price includes tax)
                            </option>
                            <option value="no_tax"    <?= $tt === 'no_tax'    ? 'selected' : '' ?>
                                    title="No GST anywhere on this invoice (exempt supplier / exempt goods / composition).">
                                No tax
                            </option>
                        </select>
                        <div class="form-text" id="tax_treatment_help"></div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <?php $claimable = (string) old('gst_claimable', '1') === '1'; ?>
                        <input class="form-check-input" type="checkbox" id="gst_claimable" name="gst_claimable" value="1" <?= $claimable ? 'checked' : '' ?>>
                        <label class="form-check-label" for="gst_claimable">
                            GST input claimable
                            <i class="bi bi-info-circle text-muted" title="Yes: tax stays OUT of landed cost — posted to GST Input Register. No: tax FOLDS INTO landed cost (supplier unregistered / composition / mixed-basis)."></i>
                        </label>
                        <div class="form-text">If off, GST folds into landed cost (D3/D12).</div>
                    </div>

                    <div id="dispute_warnings" class="alert alert-warning small mt-3 d-none mb-0">
                        <strong><i class="bi bi-exclamation-triangle"></i> Check your selections:</strong>
                        <ul class="mb-0 mt-1" id="dispute_warnings_list"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create Draft
        </button>
        <a href="<?= url('/purchase-invoices') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const bf     = document.getElementById('bill_format');
        const tt     = document.getElementById('tax_treatment');
        const claim  = document.getElementById('gst_claimable');
        const bfHelp = document.getElementById('bill_format_help');
        const ttHelp = document.getElementById('tax_treatment_help');
        const warnBox = document.getElementById('dispute_warnings');
        const warnList = document.getElementById('dispute_warnings_list');

        const bfTexts = {
            per_line_tax: 'Each line carries its own tax rate. Used for most GST invoices.',
            discount_then_tax: 'Enter a single GST rate (set it in the footer of the edit screen) on the whole bill after line discounts.',
        };
        const ttTexts = {
            exclusive: 'Line prices you enter do not include tax. Tax is added on top.',
            inclusive: 'Line prices you enter already include tax. We back it out for accounting.',
            no_tax: 'No GST on this invoice. Any tax rate you enter will be ignored.',
        };

        function refresh() {
            bfHelp.textContent = bfTexts[bf.value] || '';
            ttHelp.textContent = ttTexts[tt.value] || '';
            checkDisputes();
        }

        function checkDisputes() {
            const warnings = [];
            const bfVal = bf.value, ttVal = tt.value, claimed = claim.checked;

            if (ttVal === 'no_tax' && claimed) {
                warnings.push('Tax treatment is <strong>No tax</strong> but <strong>GST claimable</strong> is on. There is no tax to claim — turn claimable off.');
            }
            if (ttVal === 'no_tax' && bfVal === 'per_line_tax') {
                warnings.push('<strong>No tax</strong> with <strong>Per-line tax</strong>: per-line tax rates will be ignored. Enter 0% on each line, or switch tax treatment.');
            }

            if (warnings.length === 0) {
                warnBox.classList.add('d-none');
                return;
            }
            warnBox.classList.remove('d-none');
            warnList.innerHTML = warnings.map(w => '<li>' + w + '</li>').join('');
        }

        [bf, tt, claim].forEach(el => el && el.addEventListener('change', refresh));
        refresh();
    })();
</script>
