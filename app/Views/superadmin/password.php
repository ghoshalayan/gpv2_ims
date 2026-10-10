<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Change password</h1>
    <a href="<?= url('/superadmin') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <p class="text-muted small">
                    This account can manage every tenant, so treat its password like a root
                    password: strong, stored in your password vault, and not used for daily work.
                </p>
                <form method="post" action="<?= url('/superadmin/password') ?>">
                    <?= \App\Support\Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <input type="password" id="current_password" name="current_password"
                               class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">New password</label>
                        <input type="password" id="new_password" name="new_password"
                               class="form-control" required minlength="8" autocomplete="new-password">
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm new password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>
