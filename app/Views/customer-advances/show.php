<?php /** @var array $row */ ?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<?php
$sb = ['active'=>'success','exhausted'=>'secondary','refunded'=>'warning'];
$color = $sb[$row['status']] ?? 'secondary';
$applied = (float) $row['amount'] - (float) $row['balance'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-piggy-bank"></i> <?= e($row['advance_number']) ?>
        <span class="badge text-bg-<?= $color ?> ms-2"><?= e($row['status']) ?></span>
    </h1>
    <a href="<?= url('/customer-advances') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($row['status'] === 'refunded'): ?>
    <div class="alert alert-warning small">
        <strong>Refunded</strong> on <?= e($row['refunded_at']) ?>.
        Reason: <?= e($row['refund_reason'] ?? '—') ?>.
        Receiving account was debited by the remaining balance at refund time.
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Details</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Date:</strong> <?= e($row['advance_date']) ?></div>
                    <div class="col-md-4"><strong>Customer:</strong>
                        <a href="<?= url('/passbook/customer/' . $row['customer_id']) ?>">
                            <?= e($row['customer_name']) ?>
                        </a>
                    </div>
                    <div class="col-md-4"><strong>Received Into:</strong>
                        <a href="<?= url(($row['received_into_kind'] === 'bank_account' ? '/bank-accounts/' : '/cashier-drawers/') . $row['received_into_id']) ?>">
                            <?= e($row['received_into_name']) ?>
                        </a>
                    </div>
                    <div class="col-md-4"><strong>Mode:</strong>
                        <span class="badge text-bg-info"><?= e($row['payment_mode']) ?></span>
                    </div>
                    <div class="col-md-4"><strong>Reference:</strong>
                        <span class="font-monospace"><?= e($row['payment_reference'] ?? '—') ?></span>
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
            <div class="card-header bg-white"><strong>Balance</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Original amount</th>
                        <td class="text-end">₹<?= number_format((float) $row['amount'], 2) ?></td></tr>
                    <tr><th>Applied so far</th>
                        <td class="text-end text-muted">₹<?= number_format($applied, 2) ?></td></tr>
                    <tr class="table-light"><th>Balance available</th>
                        <td class="text-end fw-bold fs-5 <?= (float) $row['balance'] > 0.001 ? 'text-success' : 'text-muted' ?>">
                            ₹<?= number_format((float) $row['balance'], 2) ?>
                        </td></tr>
                </table>
            </div>
        </div>

        <?php if ($isAdmin && $row['status'] !== 'refunded'): ?>
            <form method="post" action="<?= url('/customer-advances/' . $row['id'] . '/refund') ?>" class="mt-3"
                  onsubmit="return confirm('Refund this advance? Receiving account will be debited by ₹<?= number_format((float) $row['balance'], 2) ?>. This cannot be undone.')">
                <?= \App\Support\Csrf::field() ?>
                <input type="text" name="refund_reason" maxlength="255" class="form-control mb-2"
                       placeholder="Reason for refund (required)" required>
                <button class="btn btn-outline-danger w-100">
                    <i class="bi bi-arrow-counterclockwise"></i> Refund Remaining Balance (admin)
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
