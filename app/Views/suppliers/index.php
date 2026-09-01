<?php
/** @var array $suppliers */
/** @var array $filters */
/** @var array $pg */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-truck"></i> Suppliers</h1>
    <a href="<?= url('/suppliers/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Supplier
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
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/suppliers') ?>">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <?php $searchPlaceholder = 'Search name, phone, email, or GSTIN…'; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <?php if ($suppliers === []): ?>
            <div class="text-center text-muted py-4">
                <?php if ($pg['search'] !== '' || array_filter($filters)): ?>
                    No matches.
                <?php else: ?>
                    No suppliers yet. <a href="<?= url('/suppliers/create') ?>">Create the first one</a>.
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
                            <th>Email</th>
                            <th>GSTIN</th>
                            <th>State</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $i => $s): ?>
                            <tr>
                                <td><?= $pg['from'] + $i ?></td>
                                <td><strong><?= e($s['name']) ?></strong></td>
                                <td class="text-nowrap"><?= e($s['phone']) ?></td>
                                <td class="text-muted"><?= e($s['email'] ?? '') ?></td>
                                <td class="text-monospace small"><?= e($s['gstin'] ?? '') ?></td>
                                <td><?= e($s['state'] ?? '') ?></td>
                                <td>
                                    <?php if ($s['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/passbook/supplier/' . $s['id']) ?>"
                                       class="btn btn-sm btn-outline-info" title="Passbook">
                                        <i class="bi bi-journal-text"></i>
                                    </a>
                                    <a href="<?= url('/suppliers/' . $s['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="<?= url('/suppliers/' . $s['id'] . '/toggle') ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('Toggle status of <?= e($s['name']) ?>?')">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <?= $s['status'] === 'active' ? 'Disable' : 'Activate' ?>
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
