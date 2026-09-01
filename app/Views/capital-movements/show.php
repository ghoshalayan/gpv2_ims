<?php /** @var array $row */ ?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<?php
$typeLabels = [
    'capital_in' => ['Capital injection',  'success'],
    'drawing'    => ['Drawing',            'warning'],
    'loan_in'    => ['Loan received',      'info'],
    'loan_repay' => ['Loan repayment',     'secondary'],
    'other'      => ['Other',              'dark'],
];
[$lbl, $color] = $typeLabels[$row['txn_type']] ?? ['?', 'secondary'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-bank"></i> <?= e($row['movement_number']) ?>
        <span class="badge text-bg-<?= $color ?> ms-2"><?= e($lbl) ?></span>
        <span class="badge text-bg-<?= $row['status'] === 'posted' ? 'success' : 'secondary' ?> ms-1">
            <?= e($row['status']) ?>
        </span>
    </h1>
    <a href="<?= url('/capital-movements') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($row['status'] === 'voided'): ?>
    <div class="alert alert-warning small">
        <strong>Voided</strong> on <?= e($row['voided_at']) ?>.
        Reason: <?= e($row['void_reason'] ?? '—') ?>.
        Account balance was restored by ₹<?= number_format((float) $row['amount'], 2) ?>.
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Details</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Date:</strong> <?= e($row['movement_date']) ?></div>
                    <div class="col-md-4"><strong>Direction:</strong>
                        <?php if ($row['direction'] === 'in'): ?>
                            <span class="badge text-bg-success">Money IN (+)</span>
                        <?php else: ?>
                            <span class="badge text-bg-danger">Money OUT (−)</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4"><strong>Counterparty:</strong> <?= e($row['counterparty_name']) ?></div>
                    <div class="col-md-4"><strong>Account:</strong>
                        <a href="<?= url(($row['account_kind'] === 'bank_account' ? '/bank-accounts/' : '/cashier-drawers/') . $row['account_id']) ?>">
                            <?= e($row['account_name']) ?>
                        </a>
                        <span class="text-muted">(<?= e(str_replace('_', ' ', $row['account_kind'])) ?>)</span>
                    </div>
                    <div class="col-md-4"><strong>Mode:</strong> <span class="badge text-bg-info"><?= e($row['mode']) ?></span></div>
                    <div class="col-md-4"><strong>Reference:</strong>
                        <span class="font-monospace"><?= e($row['reference'] ?? '—') ?></span>
                    </div>
                    <?php if ($row['remarks']): ?>
                        <div class="col-12"><strong>Remarks:</strong> <?= e($row['remarks']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Amount</strong></div>
            <div class="card-body">
                <div class="display-6 text-center <?= $row['direction'] === 'in' ? 'text-success' : 'text-danger' ?>">
                    <?= $row['direction'] === 'in' ? '+' : '−' ?> ₹<?= number_format((float) $row['amount'], 2) ?>
                </div>
            </div>
        </div>

        <?php if ($isAdmin && $row['status'] === 'posted'): ?>
            <form method="post" action="<?= url('/capital-movements/' . $row['id'] . '/void') ?>" class="mt-3"
                  onsubmit="return confirm('Void this movement? Account balance will be restored. This cannot be undone.')">
                <?= \App\Support\Csrf::field() ?>
                <input type="text" name="void_reason" maxlength="255" class="form-control mb-2"
                       placeholder="Reason for voiding (required)" required>
                <button class="btn btn-outline-danger w-100">
                    <i class="bi bi-x-circle"></i> Void Movement (admin)
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
