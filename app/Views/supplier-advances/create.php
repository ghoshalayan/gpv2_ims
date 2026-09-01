<?php
/** @var array $suppliers */
/** @var array $banks */
/** @var array $drawers */
/** @var int   $preSupplier */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> Pay Supplier Advance</h1>
    <a href="<?= url('/supplier-advances') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/supplier-advances') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="advance_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="advance_date" name="advance_date" required
                           class="form-control" value="<?= e(old('advance_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="supplier_id">Supplier <span class="text-danger">*</span></label>
                    <select id="supplier_id" name="supplier_id" class="form-select" required>
                        <option value="">— Select supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <?php $sel = (int) $preSupplier === (int) $s['id'] || (int) old('supplier_id') === (int) $s['id']; ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="amount">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" required
                           class="form-control" value="<?= e(old('amount')) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Paid From <span class="text-danger">*</span></label>
                    <select name="paid_from" class="form-select" required>
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
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Pay Advance</button>
        <a href="<?= url('/supplier-advances') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
