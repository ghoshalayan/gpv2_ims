<?php /** @var array $employee */ /** @var array $states */ /** @var array $idProofs */ /** @var array $users */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Employee</h1>
    <a href="<?= url('/employees') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/employees/' . $employee['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <?php require __DIR__ . '/_form.php'; ?>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Save changes
        </button>
        <a href="<?= url('/employees') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
