<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Role</h1>
    <a href="<?= url('/roles') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/roles') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <?php $role = null; require __DIR__ . '/_form.php'; ?>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create &amp; Configure Permissions
        </button>
        <a href="<?= url('/roles') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
