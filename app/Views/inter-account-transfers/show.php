<?php /** @var array $transfer */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-arrow-left-right"></i> <?= e($transfer['transfer_number']) ?>
    </h1>
    <a href="<?= url('/inter-account-transfers') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-danger">
                    <div class="card-header bg-danger-subtle"><strong>FROM</strong></div>
                    <div class="card-body">
                        <div class="h5"><?= e($transfer['from_name']) ?></div>
                        <div class="text-muted small mb-2"><?= e(str_replace('_', ' ', $transfer['from_kind'])) ?></div>
                        <a href="<?= url(($transfer['from_kind'] === 'bank_account' ? '/bank-accounts/' : '/cashier-drawers/') . $transfer['from_id']) ?>"
                           class="btn btn-sm btn-outline-secondary">View account</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-header bg-success-subtle"><strong>TO</strong></div>
                    <div class="card-body">
                        <div class="h5"><?= e($transfer['to_name']) ?></div>
                        <div class="text-muted small mb-2"><?= e(str_replace('_', ' ', $transfer['to_kind'])) ?></div>
                        <a href="<?= url(($transfer['to_kind'] === 'bank_account' ? '/bank-accounts/' : '/cashier-drawers/') . $transfer['to_id']) ?>"
                           class="btn btn-sm btn-outline-secondary">View account</a>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <table class="table table-sm mb-0">
                    <tr><th style="width:30%">Date</th><td><?= e($transfer['transfer_date']) ?></td></tr>
                    <tr><th>Amount</th><td class="fw-bold fs-5">₹<?= number_format((float) $transfer['amount'], 2) ?></td></tr>
                    <tr><th>Mode</th><td><span class="badge text-bg-info"><?= e($transfer['mode']) ?></span></td></tr>
                    <?php if (!empty($transfer['reference'])): ?>
                        <tr><th>Reference</th><td class="font-monospace"><?= e($transfer['reference']) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($transfer['remarks'])): ?>
                        <tr><th>Remarks</th><td><?= e($transfer['remarks']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>
