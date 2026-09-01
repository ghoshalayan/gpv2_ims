<?php /** @var array $customers */ /** @var array $salesmen */ /** @var array $customerDefaults */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Sale</h1>
    <a href="<?= url('/sales-invoices') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    Step 1 — create the sale header. Add lines and finalize on the next page.
    Invoice number is auto-generated when you finalize. Sales are multi-brand (D14).
</div>

<form method="post" action="<?= url('/sales-invoices') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Header</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                            <select id="customer_id" name="customer_id" class="form-select" required>
                                <option value="">— Select customer —</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"
                                        <?= (int) old('customer_id') === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="invoice_date">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" id="invoice_date" name="invoice_date"
                                   class="form-control" value="<?= e(old('invoice_date', date('Y-m-d'))) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="header_salesman_id">
                                Salesman <small class="text-muted">(fill-down default for lines)</small>
                            </label>
                            <select id="header_salesman_id" name="header_salesman_id" class="form-select">
                                <option value="">— None (direct sale) —</option>
                                <?php foreach ($salesmen as $sm): ?>
                                    <option value="<?= (int) $sm['id'] ?>"
                                        <?= (int) old('header_salesman_id') === (int) $sm['id'] ? 'selected' : '' ?>>
                                        <?= e($sm['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <i class="bi bi-info-circle text-muted" title="Per-line tax: each line carries its own rate. Discount then GST: single rate on the bill subtotal after line discounts."></i>
                        </label>
                        <select id="bill_format" name="bill_format" class="form-select">
                            <?php $bf = old('bill_format', 'per_line_tax'); ?>
                            <option value="per_line_tax"      <?= $bf === 'per_line_tax'      ? 'selected' : '' ?>>Per-line tax</option>
                            <option value="discount_then_tax" <?= $bf === 'discount_then_tax' ? 'selected' : '' ?>>Discount then GST</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="tax_treatment">Tax Treatment</label>
                        <select id="tax_treatment" name="tax_treatment" class="form-select">
                            <?php $tt = old('tax_treatment', 'exclusive'); ?>
                            <option value="exclusive" <?= $tt === 'exclusive' ? 'selected' : '' ?>>Exclusive (price excludes tax)</option>
                            <option value="inclusive" <?= $tt === 'inclusive' ? 'selected' : '' ?>>Inclusive (price includes tax)</option>
                            <option value="no_tax"    <?= $tt === 'no_tax'    ? 'selected' : '' ?>>No tax</option>
                        </select>
                    </div>
                    <div class="form-text small">
                        Bill-Type (Tax Invoice / Bill of Supply) is decided automatically from the customer's GSTIN at finalize.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create Draft
        </button>
        <a href="<?= url('/sales-invoices') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    window.customerSalesmanDefaults = <?= json_encode($customerDefaults, JSON_UNESCAPED_UNICODE) ?>;

    document.addEventListener('DOMContentLoaded', function () {
        jQuery(function ($) {
            // When customer changes, pre-fill header salesman with their default
            const $cust = $('#customer_id');
            const $sm   = $('#header_salesman_id');
            $cust.on('change', function () {
                if ($sm.val()) return; // don't overwrite user-set value
                const def = window.customerSalesmanDefaults[parseInt($cust.val())];
                if (def) { $sm.val(String(def)).trigger('change'); }
            });
        });
    });
</script>
