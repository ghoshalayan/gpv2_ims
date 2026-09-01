<?php
/** @var array $invoices */
/** @var array $pg */
/** @var array $filters */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-briefcase-fill"></i> Purchase Invoices</h1>
    <a href="<?= url('/purchase-invoices/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Purchase
    </a>
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small" for="f_status">Status</label>
                <select id="f_status" name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['draft','finalized','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_pay">Payment</label>
                <select id="f_pay" name="payment_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['unpaid','partial','paid'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($filters['payment_status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_from">Date From</label>
                <input type="date" id="f_from" name="date_from" class="form-control form-control-sm"
                       value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_to">Date To</label>
                <input type="date" id="f_to" name="date_to" class="form-control form-control-sm"
                       value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/purchase-invoices') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search invoice # or supplier name…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($invoices === []): ?>
            <div class="text-center text-muted py-4">
                <?= $pg['search'] !== '' || array_filter($filters) ? 'No matches.' : 'No purchase invoices yet.' ?>
                <?php if ($pg['search'] === '' && !array_filter($filters)): ?>
                    <a href="<?= url('/purchase-invoices/create') ?>">Create your first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Brand</th>
                            <th class="text-end">Grand Total</th>
                            <th class="text-end">Due</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $i => $inv): ?>
                            <tr>
                                <td><?= $pg['from'] + $i ?></td>
                                <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                                <td class="text-nowrap"><?= e($inv['invoice_date']) ?></td>
                                <td><?= e($inv['supplier_name']) ?></td>
                                <td><?= e($inv['brand_name']) ?></td>
                                <td class="text-end">₹<?= number_format((float) $inv['grand_total'], 2) ?></td>
                                <td class="text-end <?= (float) $inv['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    ₹<?= number_format((float) $inv['due_amount'], 2) ?>
                                </td>
                                <td>
                                    <?php
                                        $statusBadge = [
                                            'draft'     => '<span class="badge text-bg-secondary">Draft</span>',
                                            'finalized' => '<span class="badge text-bg-success">Finalized</span>',
                                            'cancelled' => '<span class="badge text-bg-warning">Cancelled</span>',
                                        ][$inv['status']] ?? e($inv['status']);
                                    ?>
                                    <?= $statusBadge ?>
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
                                <td class="text-end text-nowrap">
                                    <?php if ($inv['status'] === 'draft'): ?>
                                        <a href="<?= url('/purchase-invoices/' . $inv['id'] . '/edit') ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('/purchase-invoices/' . $inv['id']) ?>"
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
