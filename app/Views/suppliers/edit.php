<?php
/** @var array $supplier */
/** @var array $states */
/** @var array $oldRecordsRollup */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Supplier</h1>
    <div>
        <a href="<?= url('/passbook/supplier/' . (int) $supplier['id']) ?>" class="btn btn-outline-primary me-2">
            <i class="bi bi-journal-text"></i> Passbook
        </a>
        <a href="<?= url('/suppliers') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php
$oldRecordsKind     = 'supplier';
$oldRecordsEntityId = (int) $supplier['id'];
require APP_ROOT . '/app/Views/partials/old-records-summary.php';
?>

<form method="post" action="<?= url('/suppliers/' . $supplier['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <?php require __DIR__ . '/_form.php'; ?>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Save changes
        </button>
        <a href="<?= url('/suppliers') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
