<?php /** @var array $subcategories */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-tags"></i> Sub-Categories</h1>
    <a href="<?= url('/subcategories/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Sub-Category
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($subcategories === []): ?>
            <div class="text-center text-muted py-4">
                No sub-categories yet.
                <a href="<?= url('/subcategories/create') ?>">Create the first one</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table">
                    <thead>
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Category</th>
                            <th>Sub-Category</th>
                            <th>Description</th>
                            <th style="width:120px">Status</th>
                            <th style="width:200px" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subcategories as $i => $s): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <?= e($s['category_name']) ?>
                                    <?php if ($s['category_status'] !== 'active'): ?>
                                        <span class="badge text-bg-warning ms-1" title="Parent category is inactive">parent inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= e($s['name']) ?></strong></td>
                                <td class="text-muted"><?= e($s['description'] ?? '') ?></td>
                                <td>
                                    <?php if ($s['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('/subcategories/' . $s['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <form method="post" action="<?= url('/subcategories/' . $s['id'] . '/toggle') ?>"
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
        <?php endif; ?>
    </div>
</div>
