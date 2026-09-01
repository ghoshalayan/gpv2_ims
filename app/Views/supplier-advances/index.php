<?php /** @var array $advances */ /** @var ?int $supplierId */ /** @var string $statusFilter */ /** @var array $pg */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-piggy-bank"></i> Supplier Advances</h1>
    <a href="<?= url('/supplier-advances/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Pay Advance
    </a>
</div>

<div class="alert alert-info py-2 small">
    Money paid to a supplier <em>before</em> their invoice arrives, or in excess of one.
    Each row debits the paying account immediately and creates a credit balance with the supplier
    that future purchase payments can draw from. Net cash effect at apply time = zero.
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong>All advances</strong>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary <?= $statusFilter === '' ? 'active' : '' ?>"
               href="<?= url('/supplier-advances') ?>">All</a>
            <a class="btn btn-outline-success <?= $statusFilter === 'active' ? 'active' : '' ?>"
               href="<?= url('/supplier-advances?status=active') ?>">Active</a>
            <a class="btn btn-outline-secondary <?= $statusFilter === 'exhausted' ? 'active' : '' ?>"
               href="<?= url('/supplier-advances?status=exhausted') ?>">Exhausted</a>
            <a class="btn btn-outline-warning <?= $statusFilter === 'refunded' ? 'active' : '' ?>"
               href="<?= url('/supplier-advances?status=refunded') ?>">Refunded</a>
        </div>
    </div>
    <div class="card-body">
        <?php $searchPlaceholder = 'Search advance # or supplier name…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($advances === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No matches for "<?= e($pg['search']) ?>".
                <?php else: ?>
                    No advances <?= $statusFilter || $supplierId ? '(for this filter)' : 'yet' ?>.
                    <?php if (!$statusFilter && !$supplierId): ?>
                        <a href="<?= url('/supplier-advances/create') ?>">Pay the first one</a>.
                    <?php endif; ?>
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
                            <th>Supplier</th>
                            <th>Paid From</th>
                            <th>Mode</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($advances as $i => $a): ?>
                            <?php
                                $sb = ['active'=>'success','exhausted'=>'secondary','refunded'=>'warning'];
                                $color = $sb[$a['status']] ?? 'secondary';
                            ?>
                            <tr class="<?= $a['status'] !== 'active' ? 'text-muted' : '' ?>">
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <a href="<?= url('/supplier-advances/' . $a['id']) ?>">
                                        <strong><?= e($a['advance_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e($a['advance_date']) ?></td>
                                <td><?= e($a['supplier_name']) ?></td>
                                <td>
                                    <?php if ($a['paid_from_kind'] !== null): ?>
                                        <?= e($a['paid_from_name'] ?? '') ?>
                                        <span class="text-muted small">(<?= e(str_replace('_', ' ', $a['paid_from_kind'])) ?>)</span>
                                    <?php else: ?>
                                        <span class="text-muted small">— (rebate)</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge text-bg-info"><?= e($a['payment_mode']) ?></span></td>
                                <td class="text-end">₹<?= number_format((float) $a['amount'], 2) ?></td>
                                <td class="text-end fw-bold <?= (float) $a['balance'] > 0.001 ? 'text-success' : '' ?>">
                                    ₹<?= number_format((float) $a['balance'], 2) ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $color ?>"><?= e($a['status']) ?></span>
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
