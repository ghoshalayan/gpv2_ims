<?php
/** @var array $tenants */
/** @var array $activity */
/** @var array|null $admin */
?>
<?php if (!empty($_SESSION['_new_tenant'])): ?>
    <?php $nt = $_SESSION['_new_tenant']; unset($_SESSION['_new_tenant']); ?>
    <div class="alert alert-success">
        <h5 class="mb-2"><i class="bi bi-check-circle"></i> <?= e($nt['label']) ?> is ready</h5>
        <p class="mb-2">Sign-in details for this business — <strong>shown once</strong>, so write
           them down now. The password is not stored in readable form anywhere.</p>
        <table class="table table-sm w-auto mb-2">
            <tr><th class="pe-3">Business</th><td><?= e($nt['label']) ?></td></tr>
            <tr><th class="pe-3">Username</th><td><code><?= e($nt['username']) ?></code></td></tr>
            <tr><th class="pe-3">Password</th><td><code><?= e($nt['password']) ?></code></td></tr>
        </table>
        <div class="small text-muted">Have them change it at first sign-in.</div>
    </div>
<?php endif; ?>

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
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td>
                                <strong><?= e($t['label']) ?></strong>
                                <?php if (!empty($t['remarks'])): ?>
                                    <div class="small text-muted"><?= e($t['remarks']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($t['tenant_key']) ?></code></td>
                            <td>
                                <?php if ($t['status'] === 'active'): ?>
                                    <span class="badge bg-success">active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">suspended</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($t['status'] === 'active'): ?>
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
                                            title="Business is suspended">
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
