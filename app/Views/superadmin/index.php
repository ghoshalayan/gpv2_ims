<?php
/** @var array $tenants */
/** @var array $activity */
/** @var array|null $admin */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Tenants</h1>
        <div class="text-muted small">
            Each business keeps its own database. Nothing is shared between them.
        </div>
    </div>
    <a href="<?= url('/superadmin/tenants/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add tenant
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <?php if ($tenants === []): ?>
            <p class="text-center text-muted py-4 mb-0">No tenants registered yet.</p>
        <?php else: ?>
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Business</th>
                        <th>Key</th>
                        <th>Database</th>
                        <th>Schema</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                        <?php $i = $t['inspect']; ?>
                        <tr>
                            <td>
                                <strong><?= e($t['label']) ?></strong>
                                <?php if (!empty($t['remarks'])): ?>
                                    <div class="small text-muted"><?= e($t['remarks']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($t['tenant_key']) ?></code></td>
                            <td class="small font-monospace"><?= e($t['database_name']) ?></td>
                            <td class="small">
                                <?php if ($i['ready']): ?>
                                    <span class="text-success">
                                        <i class="bi bi-check-circle"></i>
                                        <?= (int) $i['tables'] ?> tables · <?= (int) $i['migrations'] ?> migrations
                                    </span>
                                <?php elseif ($i['exists']): ?>
                                    <span class="text-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <?= (int) $i['tables'] ?> tables, no migrations recorded
                                    </span>
                                <?php else: ?>
                                    <span class="text-danger">
                                        <i class="bi bi-x-circle"></i> database not reachable
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['status'] === 'active'): ?>
                                    <span class="badge bg-success">active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">suspended</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($t['status'] === 'active' && $i['ready']): ?>
                                    <form method="post" action="<?= url('/superadmin/tenants/' . (int) $t['id'] . '/enter') ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('Enter <?= e($t['label']) ?>? This opens that business\'s live books, and is recorded.');">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-arrow-in-right"></i> Enter
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled
                                            title="<?= $i['ready'] ? 'Tenant is suspended' : 'Database is not provisioned' ?>">
                                        Enter
                                    </button>
                                <?php endif; ?>
                                <form method="post" action="<?= url('/superadmin/tenants/' . (int) $t['id'] . '/toggle') ?>"
                                      class="d-inline"
                                      onsubmit="return confirm('<?= $t['status'] === 'active' ? 'Suspend' : 'Re-activate' ?> <?= e($t['label']) ?>?');">
                                    <?= \App\Support\Csrf::field() ?>
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <?= $t['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <strong>Recent system activity</strong>
        <span class="small text-muted ms-2">
            Entering a tenant opens another business's books, so it is recorded here.
        </span>
    </div>
    <div class="card-body p-0">
        <?php if ($activity === []): ?>
            <p class="text-center text-muted py-3 mb-0">Nothing recorded yet.</p>
        <?php else: ?>
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>When</th><th>Who</th><th>Action</th><th>Tenant</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($activity as $a): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e($a['created_at']) ?></td>
                            <td class="small"><?= e($a['username'] ?? '—') ?></td>
                            <td class="small">
                                <?php $danger = in_array($a['action'], ['login_failed'], true); ?>
                                <span class="badge bg-<?= $danger ? 'danger' : 'light text-dark' ?>">
                                    <?= e($a['action']) ?>
                                </span>
                            </td>
                            <td class="small"><?= e($a['tenant_key'] ?? '—') ?></td>
                            <td class="small text-muted"><?= e($a['ip_address'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
