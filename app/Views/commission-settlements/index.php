<?php
/** @var array $settlements */
/** @var array $outstanding */
/** @var ?int  $salesmanFilter */
/** @var array $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-cash-coin"></i> Commission Settlements</h1>
    <a href="<?= url('/commission-settlements/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Settlement
    </a>
</div>

<?php if (!empty($outstanding)): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <strong>Outstanding commission per salesman</strong>
            <span class="text-muted small ms-2">(earned − reversed − settled, posted only)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Salesman</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Earned</th>
                            <th class="text-end">Reversed</th>
                            <th class="text-end">Settled</th>
                            <th class="text-end">Outstanding</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outstanding as $o): ?>
                            <?php $net = (float) $o['earned'] - (float) $o['reversed'] - (float) $o['settled']; ?>
                            <tr>
                                <td>
                                    <a href="<?= url('/passbook/salesman/' . $o['salesman_id']) ?>">
                                        <strong><?= e($o['salesman_name']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-end text-muted"><?= number_format((float) $o['commission_rate'], 2) ?>%</td>
                                <td class="text-end">₹<?= number_format((float) $o['earned'], 2) ?></td>
                                <td class="text-end text-muted">₹<?= number_format((float) $o['reversed'], 2) ?></td>
                                <td class="text-end text-muted">₹<?= number_format((float) $o['settled'], 2) ?></td>
                                <td class="text-end fw-bold <?= $net > 0.0001 ? 'text-success' : ($net < -0.0001 ? 'text-danger' : 'text-muted') ?>">
                                    ₹<?= number_format($net, 2) ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($net > 0.0001): ?>
                                        <a href="<?= url('/commission-settlements/create?salesman_id=' . $o['salesman_id']) ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-cash-coin"></i> Settle
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong>All settlements</strong>
        <?php if ($salesmanFilter !== null): ?>
            <a href="<?= url('/commission-settlements') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x"></i> Clear filter
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php $searchPlaceholder = 'Search settlement # or salesman…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($settlements === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No matches for "<?= e($pg['search']) ?>".
                <?php elseif ($salesmanFilter !== null): ?>
                    No settlements for the selected salesman.
                <?php else: ?>
                    No settlements yet.
                    <a href="<?= url('/commission-settlements/create') ?>">Settle the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Number</th>
                            <th>Date</th>
                            <th>Salesman</th>
                            <th>Mode</th>
                            <th>Detail</th>
                            <th class="text-end">Amount Settled</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settlements as $i => $s): ?>
                            <tr class="<?= $s['status'] === 'voided' ? 'text-muted' : '' ?>">
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <a href="<?= url('/commission-settlements/' . $s['id']) ?>">
                                        <strong><?= e($s['settlement_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e($s['settlement_date']) ?></td>
                                <td><?= e($s['salesman_name']) ?></td>
                                <td>
                                    <?php if ($s['payout_mode'] === 'cash'): ?>
                                        <span class="badge text-bg-success"><i class="bi bi-cash"></i> Cash</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-info"><i class="bi bi-box-seam"></i> Goods</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($s['payout_mode'] === 'cash'): ?>
                                        From <?= e($s['paid_from_name'] ?? '—') ?>
                                        <span class="text-muted">(<?= e(strtoupper($s['payment_mode'] ?? '')) ?>)</span>
                                    <?php else: ?>
                                        <?= e($s['goods_product_name'] ?? '—') ?>
                                        × <?= number_format((float) $s['goods_quantity'], 3) ?>
                                        <?= e($s['goods_product_unit'] ?? '') ?>
                                        <span class="text-muted">@ ₹<?= number_format((float) $s['goods_unit_cost'], 2) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold">₹<?= number_format((float) $s['total_settled_amount'], 2) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $s['status'] === 'posted' ? 'success' : 'secondary' ?>">
                                        <?= e($s['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>
