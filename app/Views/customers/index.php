<?php
/** @var array $customers */
/** @var array $filters */
/** @var array $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-person-badge"></i> Customers</h1>
    <a href="<?= url('/customers/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Customer
    </a>
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="f_status">Status</label>
                <select id="f_status" name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active"   <?= ($filters['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-9 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/customers') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search name, phone, email, or GSTIN…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($customers === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== '' || array_filter($filters)): ?>
                    No matches.
                <?php else: ?>
                    No customers yet. <a href="<?= url('/customers/create') ?>">Create the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>GSTIN</th>
                            <th>State</th>
                            <th>Default Salesman</th>
                            <th class="text-end">Credit Limit</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $i => $c): ?>
                            <tr>
                                <td><?= $pg['from'] + $i ?></td>
                                <td>
                                    <strong><?= e($c['name']) ?></strong>
                                    <?php if (!empty($c['linked_salesman_id'])): ?>
                                        <span class="badge text-bg-info ms-1" title="Mirror of a salesman record">salesman mirror</span>
                                    <?php endif; ?>
                                    <?php if (!empty($c['linked_employee_id'])): ?>
                                        <span class="badge text-bg-warning ms-1" title="Mirror of an employee record">employee mirror</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= e($c['phone']) ?></td>
                                <td class="text-monospace small"><?= e($c['gstin'] ?? '') ?></td>
                                <td><?= e($c['state'] ?? '') ?></td>
                                <td><?= e($c['default_salesman_name'] ?? '') ?></td>
                                <td class="text-end">
                                    <?php if ((float) $c['credit_limit'] > 0): ?>
                                        ₹<?= number_format((float) $c['credit_limit'], 2) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['gstin']): ?>
                                        <span class="badge text-bg-primary">B2B</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">B2C</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/passbook/customer/' . $c['id']) ?>"
                                       class="btn btn-sm btn-outline-info" title="Passbook">
                                        <i class="bi bi-journal-text"></i>
                                    </a>
                                    <a href="<?= url('/customers/' . $c['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="<?= url('/customers/' . $c['id'] . '/toggle') ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('Toggle status of <?= e($c['name']) ?>?')">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <?= $c['status'] === 'active' ? 'Disable' : 'Activate' ?>
                                        </button>
                                    </form>
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
