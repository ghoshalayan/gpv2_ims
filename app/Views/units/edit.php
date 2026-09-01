<?php /** @var array $unit */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Unit · <?= e($unit['code']) ?></h1>
    <a href="<?= url('/units') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/units/' . (int) $unit['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
        <a href="<?= url('/units') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
