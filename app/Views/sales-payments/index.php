<?php
/** @var array  $invoices */
/** @var string $filter */
/** @var array  $counts */
/** @var array  $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-cash-coin"></i> Sales Payments</h1>
    <a href="<?= url('/customer-payments/create') ?>" class="btn btn-outline-primary">
        <i class="bi bi-collection"></i> Bulk Customer Payment
    </a>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'due' ? 'active' : '' ?>" href="<?= url('/sales-payments?filter=due') ?>">
            Outstanding <span class="badge text-bg-danger ms-1"><?= (int) $counts['due_count'] ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'paid' ? 'active' : '' ?>" href="<?= url('/sales-payments?filter=paid') ?>">
            Paid <span class="badge text-bg-success ms-1"><?= (int) $counts['paid_count'] ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="<?= url('/sales-payments?filter=all') ?>">
            All Finalized <span class="badge text-bg-secondary ms-1"><?= (int) $counts['all_count'] ?></span>
        </a>
    </li>
</ul>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search invoice # or customer name…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($invoices === []): ?>
            <div class="text-center text-muted py-4">No invoices match this filter.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th class="text-end">Grand Total</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Due</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                                <td class="text-nowrap"><?= e($inv['invoice_date']) ?></td>
                                <td><?= e($inv['customer_name']) ?></td>
                                <td>
                                    <?php if ($inv['bill_type'] === 'tax_invoice'): ?>
                                        <span class="badge text-bg-primary">Tax Invoice</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Bill of Supply</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">₹<?= number_format((float) $inv['grand_total'], 2) ?></td>
                                <td class="text-end">₹<?= number_format((float) $inv['paid_amount'], 2) ?></td>
                                <td class="text-end <?= (float) $inv['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    ₹<?= number_format((float) $inv['due_amount'], 2) ?>
                                </td>
                                <td>
                                    <?php
                                        $payBadge = [
                                            'unpaid'  => '<span class="badge text-bg-danger">Unpaid</span>',
                                            'partial' => '<span class="badge text-bg-warning">Partial</span>',
                                            'paid'    => '<span class="badge text-bg-success">Paid</span>',
                                        ][$inv['payment_status']] ?? e($inv['payment_status']);
                                    ?>
                                    <?= $payBadge ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('/sales-payments/' . $inv['id']) ?>"
                                       class="btn btn-sm btn-<?= (float) $inv['due_amount'] > 0 ? 'primary' : 'outline-secondary' ?>">
                                        <i class="bi bi-cash-coin"></i>
                                        <?= (float) $inv['due_amount'] > 0 ? 'Receive Payment' : 'View Payments' ?>
                                    </a>
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
