<?php /** @var array $categories */ /** @var array $banks */ /** @var array $drawers */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Expense</h1>
    <a href="<?= url('/operating-expenses') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    Posting an expense atomically debits the paying account's current balance.
    To correct a mistake, an admin can void the expense (which credits the account back).
</div>

<form method="post" action="<?= url('/operating-expenses') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="expense_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="expense_date" name="expense_date" required
                           class="form-control" value="<?= e(old('expense_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">— Select category —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) old('category_id') === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="vendor_name">Vendor / Payee</label>
                    <input type="text" id="vendor_name" name="vendor_name" maxlength="150"
                           class="form-control" value="<?= e(old('vendor_name')) ?>"
                           placeholder="e.g. Reliance Energy / Mr. Sharma (landlord)">
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
                <div class="col-md-4 d-flex align-items-end">
                    <div class="text-muted small">
                        Total with GST: <strong id="total_preview">₹0.00</strong>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Paid From <span class="text-danger">*</span></label>
                    <select name="paid_from_id" class="form-select" id="paid_from_id" required>
                        <option value="" data-kind="">— Select source —</option>
                        <?php if (!empty($banks)): ?>
                            <optgroup label="Bank Accounts">
                                <?php foreach ($banks as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>" data-kind="bank_account">
                                        🏦 <?= e($b['name']) ?> (₹<?= number_format((float) $b['current_balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($drawers)): ?>
                            <optgroup label="Cashier Drawers">
                                <?php foreach ($drawers as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>" data-kind="cashier_drawer">
                                        💵 <?= e($d['name']) ?> (₹<?= number_format((float) $d['current_balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
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
                    <label class="form-label" for="payment_reference">Reference</label>
                    <input type="text" id="payment_reference" name="payment_reference" maxlength="100"
                           class="form-control" value="<?= e(old('payment_reference')) ?>"
                           placeholder="UTR / cheque #">
                </div>

                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="500" class="form-control"><?= e(old('remarks')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Post Expense</button>
        <a href="<?= url('/operating-expenses') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const amount   = document.getElementById('amount');
        const gstRate  = document.getElementById('gst_rate');
        const totalEl  = document.getElementById('total_preview');
        const sel      = document.getElementById('paid_from_id');
        const kindHid  = document.getElementById('paid_from_kind');

        function recompute() {
            const a = parseFloat(amount.value || '0');
            const r = parseFloat(gstRate.value || '0');
            const total = a + (a * r / 100);
            totalEl.textContent = '₹' + total.toFixed(2);
        }
        function syncKind() {
            const opt = sel.options[sel.selectedIndex];
            kindHid.value = opt?.dataset.kind || '';
        }

        amount.addEventListener('input', recompute);
        gstRate.addEventListener('input', recompute);
        sel.addEventListener('change', syncKind);
        recompute();
        syncKind();
    })();
</script>
