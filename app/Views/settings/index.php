<?php /** @var array $profile */ /** @var array $settings */ /** @var array $states */ /** @var array $months */ /** @var array $commissionModes */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gear"></i> System Settings</h1>
</div>

<form method="post" action="<?= url('/settings') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Business Profile</strong></div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Appears on invoice headers. Also used to determine intra-state (CGST+SGST) vs inter-state (IGST)
                        GST on sales and purchases.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="legal_name">Legal Name <span class="text-danger">*</span></label>
                            <input type="text" id="legal_name" name="legal_name" maxlength="250"
                                   class="form-control"
                                   value="<?= e(old('legal_name', $profile['legal_name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="display_name">Display Name <span class="text-danger">*</span></label>
                            <input type="text" id="display_name" name="display_name" maxlength="150"
                                   class="form-control"
                                   value="<?= e(old('display_name', $profile['display_name'] ?? '')) ?>" required>
                            <div class="form-text">Shown on invoices.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="gstin">GSTIN</label>
                            <input type="text" id="gstin" name="gstin" maxlength="15"
                                   class="form-control text-uppercase"
                                   value="<?= e(old('gstin', $profile['gstin'] ?? '')) ?>"
                                   pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]">
                            <div class="form-text">Leave blank if unregistered.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="state">State <span class="text-danger">*</span></label>
                            <select id="state" name="state" class="form-select" required>
                                <?php $selectedState = old('state', $profile['state'] ?? ''); ?>
                                <?php foreach ($states as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $selectedState === $st ? 'selected' : '' ?>>
                                        <?= e($st) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="financial_year_start_month">FY Starts</label>
                            <select id="financial_year_start_month" name="financial_year_start_month" class="form-select">
                                <?php $selectedMonth = (int) old('financial_year_start_month', (string) ($profile['financial_year_start_month'] ?? 4)); ?>
                                <?php foreach ($months as $mNum => $mName): ?>
                                    <option value="<?= $mNum ?>" <?= $mNum === $selectedMonth ? 'selected' : '' ?>>
                                        <?= e($mName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Usually April in India.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" maxlength="20"
                                   class="form-control"
                                   value="<?= e(old('phone', $profile['phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" maxlength="150"
                                   class="form-control"
                                   value="<?= e(old('email', $profile['email'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <textarea id="address" name="address" rows="2"
                                      class="form-control"><?= e(old('address', $profile['address'] ?? '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Invoice Behaviour</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="commission_accrues_on">Commission Accrues On</label>
                        <select id="commission_accrues_on" name="commission_accrues_on" class="form-select">
                            <?php $selectedCm = old('commission_accrues_on', $settings['commission_accrues_on'] ?? 'invoice_finalization'); ?>
                            <?php foreach ($commissionModes as $m): ?>
                                <option value="<?= e($m) ?>" <?= $selectedCm === $m ? 'selected' : '' ?>>
                                    <?= e(\str_replace('_', ' ', $m)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            <strong>invoice_finalization</strong>: accrue when sale is finalized.<br>
                            <strong>invoice_payment</strong>: accrue proportionally as customer pays.<br>
                            <strong>manual_only</strong>: no auto-posting; admin posts ledger entries.
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <?php $roundOff = (string) old('enable_round_off', $settings['enable_round_off'] ?? '1') === '1'; ?>
                        <input class="form-check-input" type="checkbox" id="enable_round_off" name="enable_round_off" value="1"
                               <?= $roundOff ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enable_round_off">
                            Round invoice grand total to nearest rupee
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <?php $lowStock = (string) old('low_stock_dashboard_widget', $settings['low_stock_dashboard_widget'] ?? '1') === '1'; ?>
                        <input class="form-check-input" type="checkbox" id="low_stock_dashboard_widget" name="low_stock_dashboard_widget" value="1"
                               <?= $lowStock ? 'checked' : '' ?>>
                        <label class="form-check-label" for="low_stock_dashboard_widget">
                            Show low-stock widget on dashboard
                        </label>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Reference</strong></div>
                <div class="card-body small text-muted">
                    Last updated: <?= e($profile['updated_at'] ?? '-') ?><br>
                    <em>Runtime environment:</em> <?= e(env('APP_ENV')) ?> &middot; php <?= e(PHP_VERSION) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Save Settings
        </button>
    </div>
</form>

<div class="card shadow-sm mt-4 border-danger">
    <div class="card-header bg-danger text-white">
        <strong><i class="bi bi-exclamation-octagon"></i> Danger Zone — Hard Reset</strong>
    </div>
    <div class="card-body">
        <p class="mb-2">
            <strong>Wipes every transactional and master record</strong> — invoices, payments,
            returns, advances, rebates, products, customers, suppliers, employees, expenses,
            transfers, capital movements, stock adjustments, legacy records, the activity log,
            and all users. The schema (migration history) is preserved.
        </p>
        <p class="mb-3 small text-muted">
            After reset, the system reseeds: business-profile placeholder, default system
            settings, default role permissions, and a single admin user. You will be logged out
            immediately — log back in with the new admin credentials.
        </p>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#hardResetModal">
            <i class="bi bi-trash3"></i> Hard Reset System
        </button>
    </div>
</div>

<div class="modal fade" id="hardResetModal" tabindex="-1" aria-labelledby="hardResetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= url('/settings/hard-reset') ?>" novalidate>
            <?= \App\Support\Csrf::field() ?>
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="hardResetModalLabel">
                        <i class="bi bi-exclamation-octagon"></i> Confirm Hard Reset
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger small mb-3">
                        This action <strong>cannot be undone</strong>. All data in this database will
                        be permanently deleted. Make sure you have a backup before proceeding.
                    </div>

                    <h6 class="text-uppercase small text-muted mb-2">Step 1 — Confirmation phrase</h6>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_phrase">
                            Type <code>RESET DATA</code> exactly to confirm:
                        </label>
                        <input type="text" class="form-control" name="confirm_phrase" id="confirm_phrase"
                               autocomplete="off" required pattern="RESET DATA">
                    </div>

                    <h6 class="text-uppercase small text-muted mb-2">Step 2 — Verify your admin password</h6>
                    <div class="mb-3">
                        <label class="form-label" for="reset_password">Your current admin password</label>
                        <input type="password" class="form-control" name="password" id="reset_password"
                               autocomplete="current-password" required>
                    </div>

                    <h6 class="text-uppercase small text-muted mb-2">Step 3 — New admin (optional)</h6>
                    <p class="small text-muted">
                        Leave blank to recreate a default admin (<code>admin / admin123</code>).
                        Override below to set fresh credentials atomically with the reset.
                    </p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small" for="new_admin_username">Username</label>
                            <input type="text" class="form-control form-control-sm" id="new_admin_username"
                                   name="new_admin_username" placeholder="admin" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="new_admin_password">Password</label>
                            <input type="password" class="form-control form-control-sm" id="new_admin_password"
                                   name="new_admin_password" placeholder="admin123" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="new_admin_name">Name</label>
                            <input type="text" class="form-control form-control-sm" id="new_admin_name"
                                   name="new_admin_name" placeholder="Administrator">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="new_admin_phone">Phone</label>
                            <input type="text" class="form-control form-control-sm" id="new_admin_phone"
                                   name="new_admin_phone" placeholder="0000000000">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="new_admin_email">Email</label>
                            <input type="email" class="form-control form-control-sm" id="new_admin_email"
                                   name="new_admin_email" placeholder="admin@example.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="hardResetSubmit" disabled>
                        <i class="bi bi-trash3"></i> Wipe data &amp; reseed defaults
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Enable the destructive submit button only after the confirmation phrase is exact.
    (function () {
        var phrase = document.getElementById('confirm_phrase');
        var pwd    = document.getElementById('reset_password');
        var btn    = document.getElementById('hardResetSubmit');
        if (!phrase || !pwd || !btn) return;
        function refresh() {
            btn.disabled = !(phrase.value === 'RESET DATA' && pwd.value.length > 0);
        }
        phrase.addEventListener('input', refresh);
        pwd.addEventListener('input', refresh);
    })();
</script>
