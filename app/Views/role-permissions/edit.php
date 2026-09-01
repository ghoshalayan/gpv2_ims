<?php
/** @var array $role */
/** @var array $tree */
/** @var array $abilityLabels */
/** @var array $granted */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">
            <i class="bi bi-shield-check"></i>
            Permissions —
            <?= e($role['label']) ?>
            <?php if ((int) $role['is_system'] === 1): ?>
                <span class="badge text-bg-danger ms-1">system</span>
            <?php endif; ?>
        </h1>
        <div class="small text-muted font-monospace mt-1"><?= e($role['role_key']) ?></div>
    </div>
    <a href="<?= url('/role-permissions') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($role['role_key'] === 'admin'): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Admin is the master-switch role.</strong> Whatever is checked here is moot —
        admin always has access to everything. Storing grants is allowed for clarity, but
        unchecking them does NOT remove admin access.
    </div>
<?php endif; ?>

<form method="post" action="<?= url('/role-permissions/' . $role['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-outline-success btn-sm perm-bulk-global" data-action="all">
            <i class="bi bi-check-all"></i> Grant All
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm perm-bulk-global" data-action="none">
            <i class="bi bi-x"></i> Clear All
        </button>
        <span class="text-muted small align-self-center ms-2">
            Click section headers above to bulk-toggle that section. The page is grouped
            <strong>Section &rarr; Group &rarr; Page &rarr; Abilities</strong>.
        </span>
    </div>

    <?php
    $treeMode = 'role';
    require APP_ROOT . '/app/Views/partials/permission-tree.php';
    ?>

    <div class="d-flex gap-2 mb-4 sticky-bottom bg-body py-2 border-top">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Save Permissions
        </button>
        <a href="<?= url('/role-permissions') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    document.querySelectorAll('.perm-bulk-global').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var on = btn.dataset.action === 'all';
            document.querySelectorAll('.perm-tree input[type=checkbox]').forEach(function (cb) {
                cb.checked = on;
            });
        });
    });
</script>
