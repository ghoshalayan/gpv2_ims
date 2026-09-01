<?php /** @var array $categories */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-tags"></i> Expense Categories</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/operating-expenses') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Expenses
        </a>
        <a href="<?= url('/operating-expense-categories/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New Category
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($categories === []): ?>
            <div class="text-center text-muted py-4">
                No categories yet.
                <a href="<?= url('/operating-expense-categories/create') ?>">Create the first one</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $i => $c): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= e($c['name']) ?></strong></td>
                                <td class="text-muted small"><?= e($c['description'] ?? '') ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= e($c['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/operating-expense-categories/' . $c['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="<?= url('/operating-expense-categories/' . $c['id'] . '/toggle') ?>" class="d-inline"
                                          onsubmit="return confirm('Toggle status for <?= e($c['name']) ?>?')">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
