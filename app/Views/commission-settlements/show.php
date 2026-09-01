<?php /** @var array $row */ ?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-cash-coin"></i> <?= e($row['settlement_number']) ?>
        <?php if ($row['payout_mode'] === 'cash'): ?>
            <span class="badge text-bg-success ms-2"><i class="bi bi-cash"></i> Cash</span>
        <?php else: ?>
            <span class="badge text-bg-info ms-2"><i class="bi bi-box-seam"></i> Goods</span>
        <?php endif; ?>
        <span class="badge text-bg-<?= $row['status'] === 'posted' ? 'success' : 'secondary' ?> ms-1">
            <?= e($row['status']) ?>
        </span>
    </h1>
    <a href="<?= url('/commission-settlements') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($row['status'] === 'voided'): ?>
    <div class="alert alert-warning small">
        <strong>Voided</strong> on <?= e($row['voided_at']) ?>.
        Reason: <?= e($row['void_reason'] ?? '—') ?>.
        <?= $row['payout_mode'] === 'cash'
            ? 'Paying account credited back by ₹' . number_format((float) $row['cash_amount'], 2) . '.'
            : 'Stock restored at ₹' . number_format((float) $row['goods_unit_cost'], 2) . ' × ' . number_format((float) $row['goods_quantity'], 3) . '.' ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Details</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Date:</strong> <?= e($row['settlement_date']) ?></div>
                    <div class="col-md-4"><strong>Salesman:</strong>
                        <a href="<?= url('/passbook/salesman/' . $row['salesman_id']) ?>">
                            <?= e($row['salesman_name']) ?>
                        </a>
                    </div>
                    <div class="col-md-4"><strong>Mode:</strong> <?= e($row['payout_mode']) ?></div>

                    <?php if ($row['payout_mode'] === 'cash'): ?>
                        <div class="col-md-4"><strong>Paid From:</strong>
                            <a href="<?= url(($row['paid_from_kind'] === 'bank_account' ? '/bank-accounts/' : '/cashier-drawers/') . $row['paid_from_id']) ?>">
                                <?= e($row['paid_from_name']) ?>
                            </a>
                        </div>
                        <div class="col-md-4"><strong>Mode:</strong>
                            <span class="badge text-bg-info"><?= e(strtoupper($row['payment_mode'] ?? '')) ?></span>
                        </div>
                        <div class="col-md-4"><strong>Reference:</strong>
                            <span class="font-monospace"><?= e($row['payment_reference'] ?? '—') ?></span>
                        </div>
                    <?php else: ?>
                        <div class="col-md-4"><strong>Product:</strong> <?= e($row['goods_product_name']) ?></div>
                        <div class="col-md-4"><strong>Quantity:</strong>
                            <?= number_format((float) $row['goods_quantity'], 3) ?>
                            <?= e($row['goods_product_unit']) ?>
                        </div>
                        <div class="col-md-4"><strong>Unit cost <small>(snapshot)</small>:</strong>
                            ₹<?= number_format((float) $row['goods_unit_cost'], 4) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($row['remarks']): ?>
                        <div class="col-12"><strong>Remarks:</strong> <?= e($row['remarks']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Settled Amount</strong></div>
            <div class="card-body">
                <div class="display-6 text-center text-primary">
                    ₹<?= number_format((float) $row['total_settled_amount'], 2) ?>
                </div>
                <div class="text-muted small text-center">
                    Applied against the salesman's commission outstanding.
                </div>
            </div>
        </div>

        <?php if ($isAdmin && $row['status'] === 'posted'): ?>
            <form method="post" action="<?= url('/commission-settlements/' . $row['id'] . '/void') ?>" class="mt-3"
                  onsubmit="return confirm('Void this settlement? <?= $row['payout_mode'] === 'cash' ? 'Paying account will be credited back.' : 'Stock will be restored at the snapshotted cost.' ?> This cannot be undone.')">
                <?= \App\Support\Csrf::field() ?>
                <input type="text" name="void_reason" maxlength="255" class="form-control mb-2"
                       placeholder="Reason for voiding (required)" required>
                <button class="btn btn-outline-danger w-100">
                    <i class="bi bi-x-circle"></i> Void Settlement (admin)
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
