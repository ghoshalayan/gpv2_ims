<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Add tenant</h1>
    <a href="<?= url('/superadmin') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info">
    <strong>Nothing to set up first.</strong> All businesses share one database, so creating
    one here is immediate — it writes the business record plus everything it needs to be
    usable: its business profile, settings, a fallback brand, and an administrator account.
    <div class="small mt-2">
        Its data is kept completely separate from every other business. The same customer,
        product or supplier may exist in several businesses as unrelated records.
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= url('/superadmin/tenants') ?>">
            <?= \App\Support\Csrf::field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="label">Business name <span class="text-danger">*</span></label>
                    <input type="text" id="label" name="label" maxlength="150" required
                           class="form-control" value="<?= e(old('label')) ?>"
                           placeholder="Acme Traders">
                    <div class="form-text">Shown in the sign-in picker.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="tenant_key">Key <span class="text-danger">*</span></label>
                    <input type="text" id="tenant_key" name="tenant_key" maxlength="40" required
                           class="form-control font-monospace" value="<?= e(old('tenant_key')) ?>"
                           placeholder="acme" pattern="[a-z0-9][a-z0-9_-]{1,39}">
                    <div class="form-text">Lowercase letters, digits, hyphen or underscore.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="remarks">Remarks</label>
                    <input type="text" id="remarks" name="remarks" maxlength="500"
                           class="form-control" value="<?= e(old('remarks')) ?>">
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Register tenant</button>
                <a href="<?= url('/superadmin') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
