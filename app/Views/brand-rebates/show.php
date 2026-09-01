<?php
/** @var array $row */
/** @var array $costAdjustments */
?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<?php
    $modeColors = [
        'cash_back'      => 'success',
        'reduce_payable' => 'primary',
        'cost_reduction' => 'warning',
        'free_stock'     => 'info',
    ];
    $modeColor = $modeColors[$row['mode']] ?? 'secondary';
    $stsColor  = $row['status'] === 'posted' ? 'success' : 'secondary';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        <i class="bi bi-gift"></i> <?= e($row['rebate_number']) ?>
        <span class="badge text-bg-<?= $modeColor ?> ms-2"><?= e(str_replace('_', ' ', $row['mode'])) ?></span>
        <span class="badge text-bg-<?= $stsColor ?> ms-1"><?= e($row['status']) ?></span>
    </h1>
    <a href="<?= url('/brand-rebates') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($row['status'] === 'voided'): ?>
    <div class="alert alert-warning small">
        <strong>Voided</strong> on <?= e($row['voided_at']) ?>.
        Reason: <?= e($row['void_reason'] ?? '—') ?>.
        All effects have been reversed.
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Details</strong></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><strong>Date:</strong> <?= e($row['rebate_date']) ?></div>
                    <div class="col-md-4"><strong>Brand:</strong> <?= e($row['brand_name']) ?></div>
                    <div class="col-md-4"><strong>Supplier:</strong>
                        <a href="<?= url('/passbook/supplier/' . $row['supplier_id']) ?>">
                            <?= e($row['supplier_name']) ?>
                        </a>
                    </div>
                    <div class="col-md-6"><strong>Credit Note:</strong>
                        <span class="font-monospace"><?= e($row['credit_note_number']) ?></span>
                        <span class="text-muted">(dated <?= e($row['credit_note_date']) ?>)</span>
                    </div>

                    <?php if ($row['mode'] === 'cash_back'): ?>
                        <div class="col-md-6"><strong>Received Into:</strong>
                            <a href="<?= url(($row['received_into_kind'] === 'bank_account' ? '/bank-accounts/' : '/cashier-drawers/') . $row['received_into_id']) ?>">
                                <?= e($row['received_into_name']) ?>
                            </a>
                            <span class="badge text-bg-info ms-1"><?= e($row['payment_mode']) ?></span>
                        </div>
                        <?php if (!empty($row['payment_reference'])): ?>
                            <div class="col-md-6"><strong>UTR / Cheque:</strong>
                                <span class="font-monospace"><?= e($row['payment_reference']) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($row['mode'] === 'reduce_payable'): ?>
                        <div class="col-md-6"><strong>Linked Advance:</strong>
                            <?php if ($row['linked_advance_id']): ?>
                                <a href="<?= url('/supplier-advances/' . $row['linked_advance_id']) ?>">
                                    <?= e($row['linked_advance_number']) ?>
                                </a>
                                <span class="text-muted ms-1">
                                    (balance ₹<?= number_format((float) $row['linked_advance_balance'], 2) ?>,
                                    status <?= e($row['linked_advance_status']) ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($row['mode'] === 'cost_reduction'): ?>
                        <?php if ($row['target_invoice_id']): ?>
                            <div class="col-md-6"><strong>Target Invoice:</strong>
                                <a href="<?= url('/purchase-invoices/' . $row['target_invoice_id']) ?>">
                                    <?= e($row['target_invoice_number']) ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="col-md-6"><strong>Scope:</strong> All on-hand stock for the brand</div>
                        <?php endif; ?>
                        <?php if ((float) $row['income_spillover'] > 0.001): ?>
                            <div class="col-12">
                                <div class="alert alert-secondary py-2 mb-0 small">
                                    <strong>Spillover to Other Income:</strong>
                                    ₹<?= number_format((float) $row['income_spillover'], 2) ?>
                                    — rebate exceeded on-hand value at the time of post.
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($row['mode'] === 'free_stock'): ?>
                        <div class="col-md-6"><strong>Free Product:</strong>
                            <?= e($row['free_product_name']) ?>
                        </div>
                        <div class="col-md-3"><strong>Quantity:</strong>
                            <?= rtrim(rtrim(number_format((float) $row['free_quantity'], 3, '.', ''), '0'), '.') ?>
                        </div>
                        <div class="col-md-3"><strong>Value/unit:</strong>
                            ₹<?= number_format((float) $row['free_value_per_unit'], 4) ?>
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
            <div class="card-header bg-white"><strong>Amount</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Rebate</th>
                        <td class="text-end fw-bold fs-5">₹<?= number_format((float) $row['amount'], 2) ?></td></tr>
                    <?php if ($row['mode'] === 'cost_reduction'): ?>
                        <tr><th>Applied to inventory</th>
                            <td class="text-end">₹<?= number_format((float) $row['amount'] - (float) $row['income_spillover'], 2) ?></td></tr>
                        <tr><th>Spillover (income)</th>
                            <td class="text-end text-muted">₹<?= number_format((float) $row['income_spillover'], 2) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php if ($isAdmin && $row['status'] === 'posted'): ?>
            <form method="post" action="<?= url('/brand-rebates/' . $row['id'] . '/void') ?>" class="mt-3"
                  onsubmit="return confirm('Void this rebate? All effects will be reversed (cash back returned, advance refunded, cost-reduction undone, or free stock removed).')">
                <?= \App\Support\Csrf::field() ?>
                <input type="text" name="void_reason" maxlength="255" class="form-control mb-2"
                       placeholder="Reason for void (required)" required>
                <button class="btn btn-outline-danger w-100">
                    <i class="bi bi-x-octagon"></i> Void Rebate (admin)
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($row['mode'] === 'cost_reduction' && $costAdjustments !== []): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Per-product cost adjustments</strong>
            <span class="text-muted small ms-2">snapshot at post time — used to reverse on void</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th class="text-end">On-hand at Post</th>
                        <th class="text-end">Avg Before</th>
                        <th class="text-end">Δ per unit</th>
                        <th class="text-end">Avg After</th>
                        <th class="text-end">Amount Applied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($costAdjustments as $adj): ?>
                        <tr>
                            <td><?= e($adj['product_name']) ?></td>
                            <td class="text-end"><?= rtrim(rtrim(number_format((float) $adj['on_hand_qty_at_post'], 3, '.', ''), '0'), '.') ?></td>
                            <td class="text-end">₹<?= number_format((float) $adj['moving_avg_before'], 4) ?></td>
                            <td class="text-end text-danger">−₹<?= number_format((float) $adj['moving_avg_delta'], 4) ?></td>
                            <td class="text-end">₹<?= number_format((float) $adj['moving_avg_after'], 4) ?></td>
                            <td class="text-end fw-bold">₹<?= number_format((float) $adj['amount_applied'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
