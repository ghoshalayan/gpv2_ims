<?php /** @var array $returns */ /** @var array $pg */ /** @var array $filters */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-arrow-counterclockwise"></i> Sales Returns</h1>
    <a href="<?= url('/sales-returns/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Sales Return
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search return #, customer, or invoice #…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($returns === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No matches for "<?= e($pg['search']) ?>".
                <?php elseif (array_filter($filters)): ?>
                    No returns match the current filters.
                <?php else: ?>
                    No returns yet.
                    <a href="<?= url('/sales-returns/create') ?>">Create the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Return #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Against Invoice</th>
                            <th class="text-end">Grand Total</th>
                            <th class="text-end">COGS Reversed</th>
                            <th class="text-end">Comm. Reversed</th>
                            <th>Refund</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returns as $i => $r): ?>
                            <tr>
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <?php if ($r['return_number']): ?>
                                        <strong><?= e($r['return_number']) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= e($r['return_date']) ?></td>
                                <td><?= e($r['customer_name']) ?></td>
                                <td><?= e($r['original_invoice_number']) ?></td>
                                <td class="text-end">₹<?= number_format((float) $r['grand_total'], 2) ?></td>
                                <td class="text-end text-muted">₹<?= number_format((float) $r['total_cogs'], 2) ?></td>
                                <td class="text-end text-muted">₹<?= number_format((float) $r['total_commission_reversed'], 2) ?></td>
                                <td>
                                    <span class="badge text-bg-info"><?= e(str_replace('_', ' ', $r['refund_method'])) ?></span>
                                </td>
                                <td>
                                    <?php
                                        $sb = ['draft'=>'secondary','finalized'=>'success','cancelled'=>'warning'];
                                        $sl = ['draft'=>'Draft','finalized'=>'Finalized','cancelled'=>'Cancelled'];
                                    ?>
                                    <span class="badge text-bg-<?= $sb[$r['status']] ?? 'secondary' ?>">
                                        <?= e($sl[$r['status']] ?? $r['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ($r['status'] === 'draft'): ?>
                                        <a href="<?= url('/sales-returns/' . $r['id'] . '/edit') ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('/sales-returns/' . $r['id']) ?>"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    <?php endif; ?>
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
