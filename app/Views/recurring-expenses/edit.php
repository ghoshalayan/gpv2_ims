<?php /** @var array $row */ /** @var array $categories */ /** @var array $banks */ /** @var array $drawers */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Recurring Expense</h1>
    <a href="<?= url('/recurring-expenses/' . $row['id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/recurring-expenses/' . $row['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Template</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" maxlength="150" required class="form-control"
                           value="<?= e($row['name']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <?php foreach ($categories as $c): ?>
                            <?php $disabled = $c['status'] !== 'active' && (int) $c['id'] !== (int) $row['category_id']; ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (int) $row['category_id'] === (int) $c['id'] ? 'selected' : '' ?>
                                <?= $disabled ? 'disabled' : '' ?>>
                                <?= e($c['name']) ?><?= $c['status'] !== 'active' ? ' (inactive)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vendor / Payee</label>
                    <input type="text" name="vendor_name" maxlength="150" class="form-control"
                           value="<?= e($row['vendor_name'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" min="0.01" step="0.01" required
                           class="form-control" value="<?= e($row['amount']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">GST %</label>
                    <input type="number" name="gst_rate" min="0" max="100" step="0.01"
                           class="form-control" value="<?= e($row['gst_rate']) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input type="hidden" name="gst_claimable" value="0">
                        <input type="checkbox" name="gst_claimable" value="1" class="form-check-input"
                               <?= (int) $row['gst_claimable'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label">GST input claimable</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Paid From <span class="text-danger">*</span></label>
                    <select name="paid_from_id" class="form-select" id="paid_from_id" required>
                        <optgroup label="Bank Accounts">
                            <?php foreach ($banks as $b): ?>
                                <?php $sel = $row['paid_from_kind'] === 'bank_account' && (int) $row['paid_from_id'] === (int) $b['id']; ?>
                                <option value="<?= (int) $b['id'] ?>" data-kind="bank_account" <?= $sel ? 'selected' : '' ?>>
                                    🏦 <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Cashier Drawers">
                            <?php foreach ($drawers as $d): ?>
                                <?php $sel = $row['paid_from_kind'] === 'cashier_drawer' && (int) $row['paid_from_id'] === (int) $d['id']; ?>
                                <option value="<?= (int) $d['id'] ?>" data-kind="cashier_drawer" <?= $sel ? 'selected' : '' ?>>
                                    💵 <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <input type="hidden" name="paid_from_kind" id="paid_from_kind" value="<?= e($row['paid_from_kind']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mode</label>
                    <select name="payment_mode" class="form-select">
                        <?php foreach (['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'] as $m): ?>
                            <option value="<?= $m ?>" <?= $row['payment_mode'] === $m ? 'selected' : '' ?>>
                                <?= strtoupper($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference template</label>
                    <input type="text" name="payment_reference" maxlength="100" class="form-control"
                           value="<?= e($row['payment_reference'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" rows="2" maxlength="500" class="form-control"><?= e($row['remarks'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Schedule</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Frequency</label>
                    <select name="frequency" class="form-select">
                        <?php foreach (['daily', 'weekly', 'monthly', 'quarterly', 'yearly'] as $f): ?>
                            <option value="<?= $f ?>" <?= $row['frequency'] === $f ? 'selected' : '' ?>>
                                <?= ucfirst($f) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Every N</label>
                    <input type="number" name="interval_count" min="1" step="1"
                           class="form-control" value="<?= e($row['interval_count']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" required class="form-control"
                           value="<?= e($row['start_date']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control"
                           value="<?= e($row['end_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Next Run</label>
                    <input type="text" class="form-control" readonly value="<?= e($row['next_run_date']) ?>">
                    <div class="form-text small">Read-only here.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
        <a href="<?= url('/recurring-expenses/' . $row['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
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
