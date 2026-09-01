<?php /** @var array $brands */ /** @var string $systemBrand */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-bookmark-star"></i> Brands</h1>
    <a href="<?= url('/brands/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Brand
    </a>
</div>

<div class="alert alert-info py-2 small">
    Brands replace the legacy <code>bill_type</code> field. <strong>Purchase</strong> invoices are single-brand;
    <strong>Sales</strong> invoices may mix brands. Products will be tagged with a brand from module #6 onward.
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($brands === []): ?>
            <div class="text-center text-muted py-4">No brands yet.</div>
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
                        <?php foreach ($brands as $i => $b): ?>
                            <?php $isSystem = $b['name'] === $systemBrand; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= e($b['name']) ?></strong>
                                    <?php if ($isSystem): ?>
                                        <span class="badge text-bg-info ms-1" title="System brand — protected">system</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= e($b['description'] ?? '') ?></td>
                                <td>
                                    <?php if ($b['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('/brands/' . $b['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <?php if (!$isSystem): ?>
                                        <form method="post" action="<?= url('/brands/' . $b['id'] . '/toggle') ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Toggle status of <?= e($b['name']) ?>?')">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-secondary">
                                                <?= $b['status'] === 'active' ? 'Disable' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled
                                                title="System brand can't be disabled">Disable</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
