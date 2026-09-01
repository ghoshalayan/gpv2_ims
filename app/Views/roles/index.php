<?php /** @var array<int, array<string, mixed>> $roles */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-shield-lock"></i> Roles</h1>
    <a href="<?= url('/roles/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Role
    </a>
</div>

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle"></i>
    A role is a named bundle of permissions. Users get permissions by being assigned a role; you
    can also override individual permissions per user from the Users page.
    The <strong>admin</strong> role is the master-switch (always allowed) and cannot be deleted.
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($roles === []): ?>
            <div class="text-center text-muted py-4">No roles.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Label</th>
                            <th>Key</th>
                            <th>Description</th>
                            <th class="text-center">Users</th>
                            <th class="text-center">Permissions</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $i => $r): ?>
                            <tr>
                                <td><?= (int) $r['sort_order'] ?></td>
                                <td>
                                    <strong><?= e($r['label']) ?></strong>
                                    <?php if ((int) $r['is_system'] === 1): ?>
                                        <span class="badge text-bg-danger ms-1">system</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace small"><?= e($r['role_key']) ?></td>
                                <td class="text-muted small"><?= e($r['description'] ?? '') ?></td>
                                <td class="text-center"><?= (int) $r['user_count'] ?></td>
                                <td class="text-center">
                                    <?php if ((int) $r['is_system'] === 1 && $r['role_key'] === 'admin'): ?>
                                        <span class="badge text-bg-secondary">all (master)</span>
                                    <?php else: ?>
                                        <?= (int) $r['perm_count'] ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/role-permissions/' . $r['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-shield-check"></i> Permissions
                                    </a>
                                    <a href="<?= url('/roles/' . $r['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ((int) $r['is_system'] === 0): ?>
                                        <form method="post" action="<?= url('/roles/' . $r['id'] . '/delete') ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete role <?= e($r['label']) ?>? Users assigned to it must be reassigned first.');">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
