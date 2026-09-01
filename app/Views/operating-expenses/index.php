<?php /** @var array $expenses */ /** @var array $summary */ /** @var array $pg */ /** @var array $filters */ ?>
<?php $isAdmin = \App\Support\Auth::is(\App\Support\Auth::ROLE_ADMIN); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-receipt-cutoff"></i> Operating Expenses</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/operating-expense-categories') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-tags"></i> Categories
        </a>
        <a href="<?= url('/operating-expenses/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New Expense
        </a>
    </div>
</div>

<?php if (!empty($summary)): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Spend by category (all-time, posted only)</strong></div>
        <div class="card-body">
            <div class="row g-2">
                <?php foreach ($summary as $row): ?>
                    <div class="col-md-3">
                        <div class="border rounded p-2 small">
                            <div class="text-muted"><?= e($row['category_name']) ?></div>
                            <div class="fw-bold fs-6">₹<?= number_format((float) $row['total'], 2) ?></div>
                            <div class="text-muted">(<?= (int) $row['count'] ?> entries)</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search expense #, vendor, or category…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($expenses === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== ''): ?>
                    No matches for "<?= e($pg['search']) ?>".
                <?php elseif (array_filter($filters)): ?>
                    No expenses match the current filters.
                <?php else: ?>
                    No expenses yet.
                    <a href="<?= url('/operating-expenses/create') ?>">Post the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Expense #</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Paid From</th>
                            <th>Mode</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">GST</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $i => $e): ?>
                            <tr class="<?= $e['status'] === 'voided' ? 'text-muted' : '' ?>">
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <a href="<?= url('/operating-expenses/' . $e['id']) ?>">
                                        <strong><?= e($e['expense_number']) ?></strong>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e($e['expense_date']) ?></td>
                                <td><?= e($e['category_name']) ?></td>
                                <td><?= e($e['vendor_name'] ?? '') ?></td>
                                <td>
                                    <?= e($e['paid_from_name'] ?? '') ?>
                                    <span class="text-muted small">(<?= e(str_replace('_', ' ', $e['paid_from_kind'])) ?>)</span>
                                </td>
                                <td><span class="badge text-bg-info"><?= e($e['payment_mode']) ?></span></td>
                                <td class="text-end">₹<?= number_format((float) $e['amount'], 2) ?></td>
                                <td class="text-end text-muted">
                                    <?= (float) $e['gst_amount'] > 0 ? '₹' . number_format((float) $e['gst_amount'], 2) : '<span class="small">—</span>' ?>
                                </td>
                                <td class="text-end fw-bold">₹<?= number_format((float) $e['total_with_gst'], 2) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $e['status'] === 'posted' ? 'success' : 'secondary' ?>">
                                        <?= e($e['status']) ?>
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
