<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'System Administration') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* Deliberately unlike the tenant UI, so it is never ambiguous whether you
           are in the control plane or inside a business. */
        body      { background: #f4f5f7; font-family: 'Segoe UI', Roboto, 'Open Sans', sans-serif; }
        .sa-bar   { background: linear-gradient(90deg, #15213b 0%, #1f2937 100%); color: #fff; }
        .sa-bar a { color: #cbd5e1; text-decoration: none; }
        .sa-bar a:hover { color: #fff; }
    </style>
</head>
<body>
<nav class="sa-bar py-2 mb-4 shadow-sm">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <a href="<?= url('/superadmin') ?>" class="fw-bold">
                <i class="bi bi-shield-lock"></i> System Administration
            </a>
            <span class="ms-3 small text-secondary">tenant control plane</span>
        </div>
        <div class="small">
            <?php $sa = \App\Support\Superadmin::user(); ?>
            <?php if ($sa !== null): ?>
                <i class="bi bi-person-circle"></i> <?= e($sa['name']) ?>
                <a href="<?= url('/superadmin/password') ?>" class="ms-3">
                    <i class="bi bi-key"></i> Change password
                </a>
            <?php endif; ?>
            <a href="<?= url('/logout') ?>" class="ms-3"><i class="bi bi-box-arrow-right"></i> Sign out</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <?php foreach (\App\Support\Flash::pull() as $flash): ?>
        <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <?= $content ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
