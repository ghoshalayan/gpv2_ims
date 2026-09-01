<?php
/** @var array $invoices */
/** @var array $pg */
/** @var array $filters */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-bag"></i> Sales Invoices</h1>
    <a href="<?= url('/sales-invoices/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Sale
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
                        <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="f_pay">Payment</label>
                <select id="f_pay" name="payment_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['unpaid','partial','paid'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filters['payment_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
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
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/sales-invoices') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search invoice # or customer name…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($invoices === []): ?>
            <div class="text-center text-muted py-4">
                <?= $pg['search'] !== '' || array_filter($filters) ? 'No matches for the current filters.' : 'No sales yet.' ?>
                <?php if ($pg['search'] === '' && !array_filter($filters)): ?>
                    <a href="<?= url('/sales-invoices/create') ?>">Create the first one</a>.
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
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Header Salesman</th>
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
                                <td>
                                    <?php if ($inv['invoice_number']): ?>
                                        <strong><?= e($inv['invoice_number']) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= e($inv['invoice_date']) ?></td>
                                <td><?= e($inv['customer_name']) ?></td>
                                <td>
                                    <?php if ($inv['bill_type'] === 'tax_invoice'): ?>
                                        <span class="badge text-bg-primary">Tax Invoice</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Bill of Supply</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($inv['header_salesman_name'] ?? '') ?></td>
                                <td class="text-end">₹<?= number_format((float) $inv['grand_total'], 2) ?></td>
                                <td class="text-end <?= (float) $inv['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    ₹<?= number_format((float) $inv['due_amount'], 2) ?>
                                </td>
                                <td>
                                    <?php
                                        $sb = ['draft'=>'secondary','finalized'=>'success','cancelled'=>'warning'];
                                        $sl = ['draft'=>'Draft','finalized'=>'Finalized','cancelled'=>'Cancelled'];
                                    ?>
                                    <span class="badge text-bg-<?= $sb[$inv['status']] ?? 'secondary' ?>">
                                        <?= e($sl[$inv['status']] ?? $inv['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        $pb = ['unpaid'=>'danger','partial'=>'warning','paid'=>'success'];
                                    ?>
                                    <span class="badge text-bg-<?= $pb[$inv['payment_status']] ?? 'secondary' ?>">
                                        <?= e(ucfirst($inv['payment_status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ($inv['status'] === 'draft'): ?>
                                        <a href="<?= url('/sales-invoices/' . $inv['id'] . '/edit') ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('/sales-invoices/' . $inv['id']) ?>"
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
