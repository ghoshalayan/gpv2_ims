<?php
/** @var array $employees */
/** @var array $filters */
/** @var array $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-person-workspace"></i> Employees</h1>
    <a href="<?= url('/employees/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Employee
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
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/employees') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search name, designation, phone, or email…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($employees === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== '' || array_filter($filters)): ?>
                    No matches.
                <?php else: ?>
                    No employees yet. <a href="<?= url('/employees/create') ?>">Add the first one</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Phone</th>
                            <th>Joined</th>
                            <th class="text-end">Salary</th>
                            <th>System Login</th>
                            <th>Customer Link</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $i => $emp): ?>
                            <tr>
                                <td><?= $pg['from'] + $i ?></td>
                                <td><strong><?= e($emp['name']) ?></strong></td>
                                <td><?= e($emp['designation']) ?></td>
                                <td class="text-nowrap"><?= e($emp['phone']) ?></td>
                                <td class="text-nowrap"><?= e($emp['joining_date']) ?></td>
                                <td class="text-end">
                                    <?php if ((float) $emp['salary_amount'] > 0): ?>
                                        ₹<?= number_format((float) $emp['salary_amount'], 2) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['linked_username']): ?>
                                        <span class="badge text-bg-info"><?= e($emp['linked_username']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['linked_customer_id']): ?>
                                        <span class="badge text-bg-info">#<?= (int) $emp['linked_customer_id'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Exited<?php if ($emp['exit_date']): ?> · <?= e($emp['exit_date']) ?><?php endif; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/employees/' . $emp['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="<?= url('/employees/' . $emp['id'] . '/toggle') ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('<?= $emp['status'] === 'active' ? 'Mark as exited?' : 'Mark as active?' ?> (<?= e($emp['name']) ?>)')">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <?= $emp['status'] === 'active' ? 'Mark Exited' : 'Mark Active' ?>
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
