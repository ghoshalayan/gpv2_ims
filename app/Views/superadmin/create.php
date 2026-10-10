<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Add tenant</h1>
    <a href="<?= url('/superadmin') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info">
    <strong>Create the database first.</strong> This screen registers a database that already
    exists; it does not create one. In the hosting panel: create the database, grant the same
    MySQL user rights on it, then apply the IMS schema (all migrations) to it. Only then add
    it here.
    <div class="small mt-2">
        The application deliberately cannot create databases. An application that can create
        databases is one that can be made to create databases.
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
                    <label class="form-label" for="database_name">Database name <span class="text-danger">*</span></label>
                    <input type="text" id="database_name" name="database_name" maxlength="64" required
                           class="form-control font-monospace" value="<?= e(old('database_name')) ?>"
                           placeholder="srasinge1_acme">
                    <div class="form-text">
                        Exactly as it appears in the hosting panel. Checked for existence before saving.
                    </div>
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
