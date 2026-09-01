<?php /** @var array $account */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Bank Account</h1>
    <a href="<?= url('/bank-accounts') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/bank-accounts/' . $account['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Label <span class="text-danger">*</span></label>
                    <input type="text" name="name" maxlength="100" required class="form-control"
                           value="<?= e($account['name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" maxlength="100" class="form-control"
                           value="<?= e($account['bank_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Account Number</label>
                    <input type="text" name="account_number" maxlength="50" class="form-control font-monospace"
                           value="<?= e($account['account_number'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">IFSC</label>
                    <input type="text" name="ifsc" maxlength="20" class="form-control font-monospace"
                           value="<?= e($account['ifsc'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Branch</label>
                    <input type="text" name="branch" maxlength="100" class="form-control"
                           value="<?= e($account['branch'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Current Balance</label>
                    <input type="text" class="form-control" readonly
                           value="₹<?= number_format((float) $account['current_balance'], 2) ?>">
                    <div class="form-text small">Mutated only by transfers / payments. Cannot be edited directly.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" rows="2" maxlength="500" class="form-control"><?= e($account['remarks'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
        <a href="<?= url('/bank-accounts') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
