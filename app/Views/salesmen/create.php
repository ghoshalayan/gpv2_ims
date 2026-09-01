<?php /** @var array $states */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Salesman</h1>
    <a href="<?= url('/salesmen') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    When you save, a linked customer record will be auto-created (per D29) so this salesman can also buy on credit.
    Customer auto-creation activates once the Customers module is built (next).
</div>

<form method="post" action="<?= url('/salesmen') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <?php $salesman = null; require __DIR__ . '/_form.php'; ?>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create
        </button>
        <a href="<?= url('/salesmen') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
