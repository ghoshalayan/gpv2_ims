<?php /** @var array $users */ /** @var int $currentUserId */ ?>
<?php
$roleBadge = function (array $u): string {
    $key   = (string) $u['role'];
    $label = (string) ($u['role_label'] ?? $u['role']);
    $cls = match ($key) {
        'admin'    => 'text-bg-danger',
        'subadmin' => 'text-bg-warning',
        'cashier'  => 'text-bg-info',
        default    => 'text-bg-secondary',
    };
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-people"></i> Users</h1>
    <a href="<?= url('/users/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New User
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($users === []): ?>
            <div class="text-center text-muted py-4">No users.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Last Login</th>
                            <th>Employee Link</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $i => $u): $isSelf = (int) $u['id'] === $currentUserId; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= e($u['name']) ?></strong>
                                    <?php if ($isSelf): ?>
                                        <span class="badge text-bg-dark ms-1">you</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace small"><?= e($u['username']) ?></td>
                                <td class="text-nowrap"><?= e($u['phone']) ?></td>
                                <td class="text-muted"><?= e($u['email'] ?? '') ?></td>
                                <td><?= $roleBadge($u) ?></td>
                                <td class="text-muted small text-nowrap">
                                    <?= $u['last_login_at'] ? e($u['last_login_at']) : '<span class="text-muted">never</span>' ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['linked_employee_id'])): ?>
                                        <span class="badge text-bg-warning">#<?= (int) $u['linked_employee_id'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= url('/users/' . $u['id'] . '/permissions') ?>"
                                       class="btn btn-sm btn-outline-info"
                                       title="Per-user permission overrides">
                                        <i class="bi bi-shield-fill-exclamation"></i>
                                    </a>
                                    <a href="<?= url('/users/' . $u['id'] . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($isSelf): ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled
                                                title="Can't disable your own account">Disable</button>
                                    <?php else: ?>
                                        <form method="post" action="<?= url('/users/' . $u['id'] . '/toggle') ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Toggle status of <?= e($u['name']) ?>?')">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-secondary">
                                                <?= $u['status'] === 'active' ? 'Disable' : 'Activate' ?>
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
