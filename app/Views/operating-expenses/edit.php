<?php /** @var array $expense */ /** @var array $categories */ /** @var array $banks */ /** @var array $drawers */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-pencil"></i> Edit <?= e($expense['expense_number']) ?>
    </h1>
    <a href="<?= url('/operating-expenses/' . $expense['id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-warning py-2 small">
    <strong>Editing a posted expense rebalances accounts atomically:</strong>
    the original paying account is credited back by the old total, and the (possibly new) paying account
    is debited by the new total. Expense number stays the same.
</div>

<form method="post" action="<?= url('/operating-expenses/' . $expense['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="expense_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="expense_date" name="expense_date" required
                           class="form-control" value="<?= e($expense['expense_date']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">— Select category —</option>
                        <?php foreach ($categories as $c): ?>
                            <?php $disabled = $c['status'] !== 'active' && (int) $c['id'] !== (int) $expense['category_id']; ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (int) $expense['category_id'] === (int) $c['id'] ? 'selected' : '' ?>
                                <?= $disabled ? 'disabled' : '' ?>>
                                <?= e($c['name']) ?><?= $c['status'] !== 'active' ? ' (inactive)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="vendor_name">Vendor / Payee</label>
                    <input type="text" id="vendor_name" name="vendor_name" maxlength="150"
                           class="form-control" value="<?= e($expense['vendor_name'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="amount">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" required
                           class="form-control" value="<?= e($expense['amount']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="gst_rate">GST %</label>
                    <input type="number" id="gst_rate" name="gst_rate" min="0" max="100" step="0.01"
                           class="form-control" value="<?= e($expense['gst_rate']) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input type="hidden" name="gst_claimable" value="0">
                        <input type="checkbox" id="gst_claimable" name="gst_claimable" value="1" class="form-check-input"
                               <?= (int) $expense['gst_claimable'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="gst_claimable">GST input claimable</label>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="text-muted small">
                        Total with GST: <strong id="total_preview">₹0.00</strong>
                        <div class="small">Was: ₹<?= number_format((float) $expense['total_with_gst'], 2) ?></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Paid From <span class="text-danger">*</span></label>
                    <select name="paid_from_id" class="form-select" id="paid_from_id" required>
                        <option value="" data-kind="">— Select source —</option>
                        <optgroup label="Bank Accounts">
                            <?php foreach ($banks as $b): ?>
                                <?php $sel = $expense['paid_from_kind'] === 'bank_account' && (int) $expense['paid_from_id'] === (int) $b['id']; ?>
                                <option value="<?= (int) $b['id'] ?>" data-kind="bank_account" <?= $sel ? 'selected' : '' ?>>
                                    🏦 <?= e($b['name']) ?> (₹<?= number_format((float) $b['current_balance'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Cashier Drawers">
                            <?php foreach ($drawers as $d): ?>
                                <?php $sel = $expense['paid_from_kind'] === 'cashier_drawer' && (int) $expense['paid_from_id'] === (int) $d['id']; ?>
                                <option value="<?= (int) $d['id'] ?>" data-kind="cashier_drawer" <?= $sel ? 'selected' : '' ?>>
                                    💵 <?= e($d['name']) ?> (₹<?= number_format((float) $d['current_balance'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <input type="hidden" name="paid_from_kind" id="paid_from_kind" value="<?= e($expense['paid_from_kind']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="payment_mode">Mode</label>
                    <select id="payment_mode" name="payment_mode" class="form-select">
                        <?php foreach (['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'] as $m): ?>
                            <option value="<?= $m ?>" <?= $expense['payment_mode'] === $m ? 'selected' : '' ?>>
                                <?= strtoupper($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="payment_reference">Reference</label>
                    <input type="text" id="payment_reference" name="payment_reference" maxlength="100"
                           class="form-control" value="<?= e($expense['payment_reference'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="500" class="form-control"><?= e($expense['remarks'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save &amp; Rebalance</button>
        <a href="<?= url('/operating-expenses/' . $expense['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const amount  = document.getElementById('amount');
        const gstRate = document.getElementById('gst_rate');
        const totalEl = document.getElementById('total_preview');
        const sel     = document.getElementById('paid_from_id');
        const kindHid = document.getElementById('paid_from_kind');
        function recompute() {
            const a = parseFloat(amount.value || '0');
            const r = parseFloat(gstRate.value || '0');
            totalEl.textContent = '₹' + (a + a * r / 100).toFixed(2);
        }
        function syncKind() {
            const opt = sel.options[sel.selectedIndex];
            kindHid.value = opt?.dataset.kind || '';
        }
        amount.addEventListener('input', recompute);
        gstRate.addEventListener('input', recompute);
        sel.addEventListener('change', syncKind);
        recompute(); syncKind();
    })();
</script>
