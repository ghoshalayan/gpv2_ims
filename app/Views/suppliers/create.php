<?php /** @var array $states */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Supplier</h1>
    <a href="<?= url('/suppliers') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/suppliers') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <?php $supplier = null; require __DIR__ . '/_form.php'; ?>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create
        </button>
        <a href="<?= url('/suppliers') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
