<?php /** @var array<int, array<string, mixed>> $roles */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-shield-lock"></i> Role Permissions</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('/roles/create') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-lg"></i> New Role
        </a>
        <form method="post" action="<?= url('/role-permissions/reset-defaults') ?>" class="m-0"
              onsubmit="return confirm('Reset ALL role permissions to factory defaults? Custom roles keep their grants if their key matches a default; everything else is wiped.');">
            <?= \App\Support\Csrf::field() ?>
            <button class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Reset to defaults
            </button>
        </form>
    </div>
</div>

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle"></i>
    Pick a role to edit its permission tree. Permissions are organised by
    <strong>section &rarr; group &rarr; page &rarr; abilities</strong> — toggle
    pages to control sidebar visibility, or individual abilities (Create / Update /
    Delete / Print / Export / Import / etc.) for finer control.
</div>

<div class="row g-3">
    <?php foreach ($roles as $r): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <?= e($r['label']) ?>
                        <?php if ((int) $r['is_system'] === 1): ?>
                            <span class="badge text-bg-danger ms-1">system</span>
                        <?php endif; ?>
                    </h5>
                    <p class="text-muted small mb-2 font-monospace"><?= e($r['role_key']) ?></p>
                    <p class="card-text small text-muted">
                        <?= e($r['description'] ?? 'No description.') ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center small text-muted">
                        <span><i class="bi bi-people"></i> <?= (int) $r['user_count'] ?> users</span>
                        <span>
                            <?php if ($r['role_key'] === 'admin'): ?>
                                <i class="bi bi-shield-fill-check text-success"></i> all (master switch)
                            <?php else: ?>
                                <i class="bi bi-shield-check"></i> <?= (int) $r['perm_count'] ?> permissions
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="<?= url('/role-permissions/' . $r['id'] . '/edit') ?>"
                       class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-pencil"></i> Edit Permissions
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
