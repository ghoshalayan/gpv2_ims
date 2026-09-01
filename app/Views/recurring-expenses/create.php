<?php /** @var array $categories */ /** @var array $banks */ /** @var array $drawers */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Recurring Expense</h1>
    <a href="<?= url('/recurring-expenses') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    Create a template that auto-posts an operating expense every N days/weeks/months.
    The first run happens on <strong>start date</strong>; each subsequent run advances by the chosen interval.
    Click <em>Run Due Recurrences</em> on the index to fire all templates whose next-run date has arrived.
</div>

<form method="post" action="<?= url('/recurring-expenses') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Template</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" maxlength="150" required
                           class="form-control" value="<?= e(old('name')) ?>"
                           placeholder="e.g. Shop rent (landlord A)">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) old('category_id') === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="vendor_name">Vendor / Payee</label>
                    <input type="text" id="vendor_name" name="vendor_name" maxlength="150"
                           class="form-control" value="<?= e(old('vendor_name')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="amount">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" required
                           class="form-control" value="<?= e(old('amount')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="gst_rate">GST %</label>
                    <input type="number" id="gst_rate" name="gst_rate" min="0" max="100" step="0.01"
                           class="form-control" value="<?= e(old('gst_rate', '0')) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input type="hidden" name="gst_claimable" value="0">
                        <input type="checkbox" id="gst_claimable" name="gst_claimable" value="1" class="form-check-input"
                               <?= old('gst_claimable') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="gst_claimable">GST input claimable</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Paid From <span class="text-danger">*</span></label>
                    <select name="paid_from_id" class="form-select" id="paid_from_id" required>
                        <option value="" data-kind="">— Select source —</option>
                        <optgroup label="Bank Accounts">
                            <?php foreach ($banks as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" data-kind="bank_account">
                                    🏦 <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Cashier Drawers">
                            <?php foreach ($drawers as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" data-kind="cashier_drawer">
                                    💵 <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <input type="hidden" name="paid_from_kind" id="paid_from_kind" value="">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="payment_mode">Mode</label>
                    <select id="payment_mode" name="payment_mode" class="form-select">
                        <?php foreach (['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'] as $m): ?>
                            <option value="<?= $m ?>" <?= old('payment_mode') === $m ? 'selected' : '' ?>>
                                <?= strtoupper($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="payment_reference">Reference template</label>
                    <input type="text" id="payment_reference" name="payment_reference" maxlength="100"
                           class="form-control" value="<?= e(old('payment_reference')) ?>"
                           placeholder="e.g. Rent payment">
                </div>

                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks (carried to each spawned expense)</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="500" class="form-control"><?= e(old('remarks')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Schedule</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label" for="frequency">Frequency</label>
                    <select id="frequency" name="frequency" class="form-select">
                        <?php foreach (['daily', 'weekly', 'monthly', 'quarterly', 'yearly'] as $f): ?>
                            <option value="<?= $f ?>" <?= old('frequency', 'monthly') === $f ? 'selected' : '' ?>>
                                <?= ucfirst($f) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="interval_count">Every N</label>
                    <input type="number" id="interval_count" name="interval_count" min="1" step="1"
                           class="form-control" value="<?= e(old('interval_count', '1')) ?>">
                    <div class="form-text small">e.g. 2 + Monthly = every 2 months</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="start_date">Start Date <span class="text-danger">*</span></label>
                    <input type="date" id="start_date" name="start_date" required
                           class="form-control" value="<?= e(old('start_date', date('Y-m-d'))) ?>">
                    <div class="form-text small">First run posts on this date.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control"
                           value="<?= e(old('end_date')) ?>">
                    <div class="form-text small">Optional — leave blank for open-ended.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Create Template</button>
        <a href="<?= url('/recurring-expenses') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const sel = document.getElementById('paid_from_id');
        const kind = document.getElementById('paid_from_kind');
        function sync() { kind.value = sel.options[sel.selectedIndex]?.dataset.kind || ''; }
        sel.addEventListener('change', sync);
        sync();
    })();
</script>
