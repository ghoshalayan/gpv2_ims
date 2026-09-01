<?php /** @var array $categories */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-tag"></i> Categories</h1>
    <a href="<?= url('/categories/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Category
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($categories === []): ?>
            <div class="text-center text-muted py-4">
                No categories yet.
                <a href="<?= url('/categories/create') ?>">Create the first one</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table">
                    <thead>
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th style="width:120px">Status</th>
                            <th style="width:200px" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $i => $c): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= e($c['name']) ?></strong></td>
                                <td class="text-muted"><?= e($c['description'] ?? '') ?></td>
                                <td>
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('/categories/' . $c['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <form method="post" action="<?= url('/categories/' . $c['id'] . '/toggle') ?>"
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
        <?php endif; ?>
    </div>
</div>
