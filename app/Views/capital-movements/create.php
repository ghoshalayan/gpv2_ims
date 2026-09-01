<?php /** @var array $banks */ /** @var array $drawers */ /** @var string $preType */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Capital Movement</h1>
    <a href="<?= url('/capital-movements') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/capital-movements') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="movement_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="movement_date" name="movement_date" required
                           class="form-control" value="<?= e(old('movement_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="txn_type">Type <span class="text-danger">*</span></label>
                    <select id="txn_type" name="txn_type" class="form-select" required>
                        <?php $type = old('txn_type', $preType); ?>
                        <option value="capital_in"  <?= $type === 'capital_in' ? 'selected' : '' ?>>Capital injection (owner adds money)</option>
                        <option value="drawing"     <?= $type === 'drawing'    ? 'selected' : '' ?>>Drawing (owner takes money out)</option>
                        <option value="loan_in"     <?= $type === 'loan_in'    ? 'selected' : '' ?>>Loan received (borrowing)</option>
                        <option value="loan_repay"  <?= $type === 'loan_repay' ? 'selected' : '' ?>>Loan repayment (paying back)</option>
                        <option value="other"       <?= $type === 'other'      ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="counterparty_name">Counterparty <span class="text-danger">*</span></label>
                    <input type="text" id="counterparty_name" name="counterparty_name" maxlength="150" required
                           class="form-control" value="<?= e(old('counterparty_name')) ?>"
                           placeholder="Owner name / Lender name / Partner">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="amount">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" required
                           class="form-control" value="<?= e(old('amount')) ?>">
                </div>
                <div class="col-md-3" id="direction_wrap" style="display:none">
                    <label class="form-label">Direction <span class="text-danger">*</span></label>
                    <select name="direction" class="form-select">
                        <option value="">— Pick —</option>
                        <option value="in"  <?= old('direction') === 'in'  ? 'selected' : '' ?>>Money IN (account credited)</option>
                        <option value="out" <?= old('direction') === 'out' ? 'selected' : '' ?>>Money OUT (account debited)</option>
                    </select>
                    <div class="form-text small">Required when type = "Other".</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Account <span class="text-danger">*</span></label>
                    <select name="account" id="account_select" class="form-select" required>
                        <option value="">— Select bank or drawer —</option>
                        <?php if (!empty($banks)): ?>
                            <optgroup label="Bank Accounts">
                                <?php foreach ($banks as $b): ?>
                                    <option value="bank_account:<?= (int) $b['id'] ?>">
                                        🏦 <?= e($b['name']) ?> (₹<?= number_format((float) $b['current_balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($drawers)): ?>
                            <optgroup label="Cashier Drawers">
                                <?php foreach ($drawers as $d): ?>
                                    <option value="cashier_drawer:<?= (int) $d['id'] ?>">
                                        💵 <?= e($d['name']) ?> (₹<?= number_format((float) $d['current_balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="mode">Mode</label>
                    <select id="mode" name="mode" class="form-select">
                        <?php foreach (['cash', 'cheque', 'neft', 'imps', 'rtgs', 'upi', 'card', 'other'] as $m): ?>
                            <option value="<?= $m ?>" <?= old('mode') === $m ? 'selected' : '' ?>>
                                <?= strtoupper($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reference">Reference</label>
                    <input type="text" id="reference" name="reference" maxlength="100"
                           class="form-control" value="<?= e(old('reference')) ?>"
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
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Post Movement</button>
        <a href="<?= url('/capital-movements') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const txnType = document.getElementById('txn_type');
        const dirWrap = document.getElementById('direction_wrap');
        function refresh() {
            dirWrap.style.display = txnType.value === 'other' ? '' : 'none';
        }
        txnType.addEventListener('change', refresh);
        refresh();
    })();
</script>
