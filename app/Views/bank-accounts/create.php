<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Bank Account</h1>
    <a href="<?= url('/bank-accounts') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/bank-accounts') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="name">Label <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" maxlength="100" required
                           class="form-control" value="<?= e(old('name')) ?>"
                           placeholder="e.g. HDFC Current">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="bank_name">Bank Name</label>
                    <input type="text" id="bank_name" name="bank_name" maxlength="100"
                           class="form-control" value="<?= e(old('bank_name')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="account_number">Account Number</label>
                    <input type="text" id="account_number" name="account_number" maxlength="50"
                           class="form-control font-monospace" value="<?= e(old('account_number')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="ifsc">IFSC</label>
                    <input type="text" id="ifsc" name="ifsc" maxlength="20"
                           class="form-control font-monospace" value="<?= e(old('ifsc')) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="branch">Branch</label>
                    <input type="text" id="branch" name="branch" maxlength="100"
                           class="form-control" value="<?= e(old('branch')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="opening_balance">Opening Balance (₹)</label>
                    <input type="number" id="opening_balance" name="opening_balance" min="0" step="0.01"
                           class="form-control" value="<?= e(old('opening_balance', '0')) ?>">
                    <div class="form-text small">Sets the current balance at creation. Can be 0.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="500" class="form-control"><?= e(old('remarks')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Create</button>
        <a href="<?= url('/bank-accounts') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
