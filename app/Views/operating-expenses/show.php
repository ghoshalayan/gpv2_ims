<?php /** @var array $expense */ ?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-receipt-cutoff"></i> <?= e($expense['expense_number']) ?>
        <span class="badge text-bg-<?= $expense['status'] === 'posted' ? 'success' : 'secondary' ?> ms-2">
            <?= e($expense['status']) ?>
        </span>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/operating-expenses') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <?php if ($expense['status'] === 'posted'): ?>
            <a href="<?= url('/operating-expenses/' . $expense['id'] . '/edit') ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <a href="<?= url('/operating-expenses/' . $expense['id'] . '/receipt') ?>" target="_blank" class="btn btn-outline-info">
                <i class="bi bi-printer"></i> Print Receipt
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($expense['status'] === 'voided'): ?>
    <div class="alert alert-warning small">
        <strong>Voided</strong> on <?= e($expense['voided_at']) ?>.
        Reason: <?= e($expense['void_reason'] ?? '—') ?>.
        The paying account was credited back by ₹<?= number_format((float) $expense['total_with_gst'], 2) ?>.
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Details</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Date:</strong> <?= e($expense['expense_date']) ?></div>
                    <div class="col-md-4"><strong>Category:</strong> <?= e($expense['category_name']) ?></div>
                    <div class="col-md-4"><strong>Vendor:</strong> <?= e($expense['vendor_name'] ?? '—') ?></div>
                    <div class="col-md-4"><strong>Paid From:</strong>
                        <a href="<?= url(($expense['paid_from_kind'] === 'bank_account' ? '/bank-accounts/' : '/cashier-drawers/') . $expense['paid_from_id']) ?>">
                            <?= e($expense['paid_from_name']) ?>
                        </a>
                        <span class="text-muted">(<?= e(str_replace('_', ' ', $expense['paid_from_kind'])) ?>)</span>
                    </div>
                    <div class="col-md-4"><strong>Mode:</strong>
                        <span class="badge text-bg-info"><?= e($expense['payment_mode']) ?></span>
                    </div>
                    <div class="col-md-4"><strong>Reference:</strong>
                        <span class="font-monospace"><?= e($expense['payment_reference'] ?? '—') ?></span>
                    </div>
                    <?php if ($expense['remarks']): ?>
                        <div class="col-12"><strong>Remarks:</strong> <?= e($expense['remarks']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Totals</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Amount (pre-tax)</th>
                        <td class="text-end">₹<?= number_format((float) $expense['amount'], 2) ?></td></tr>
                    <?php if ((float) $expense['gst_amount'] > 0): ?>
                        <tr><th>GST @ <?= number_format((float) $expense['gst_rate'], 2) ?>%</th>
                            <td class="text-end">₹<?= number_format((float) $expense['gst_amount'], 2) ?></td></tr>
                        <tr><th class="text-muted small">Input claimable?</th>
                            <td class="text-end"><?= (int) $expense['gst_claimable'] === 1 ? 'Yes' : 'No' ?></td></tr>
                    <?php endif; ?>
                    <tr class="table-light"><th>Total</th>
                        <td class="text-end fw-bold fs-5">₹<?= number_format((float) $expense['total_with_gst'], 2) ?></td></tr>
                </table>
            </div>
        </div>

        <?php if ($isAdmin && $expense['status'] === 'posted'): ?>
            <form method="post" action="<?= url('/operating-expenses/' . $expense['id'] . '/void') ?>" class="mt-3"
                  onsubmit="return confirm('Void this expense? Paying account will be credited back by ₹<?= number_format((float) $expense['total_with_gst'], 2) ?>. This cannot be undone.')">
                <?= \App\Support\Csrf::field() ?>
                <input type="text" name="void_reason" maxlength="255" class="form-control mb-2"
                       placeholder="Reason for voiding (required)" required>
                <button class="btn btn-outline-danger w-100">
                    <i class="bi bi-x-circle"></i> Void Expense (admin)
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
