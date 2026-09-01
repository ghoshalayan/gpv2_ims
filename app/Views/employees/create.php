<?php /** @var array $states */ /** @var array $idProofs */ /** @var array $users */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Employee</h1>
    <a href="<?= url('/employees') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    A linked customer record will be auto-created (or merged with an existing customer of the same name &amp; phone)
    so this employee can buy on credit, settled against salary.
</div>

<form method="post" action="<?= url('/employees') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <?php $employee = null; require __DIR__ . '/_form.php'; ?>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create
        </button>
        <a href="<?= url('/employees') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
