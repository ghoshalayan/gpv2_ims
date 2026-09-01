<?php
/** @var array $user */
/** @var array $tree */
/** @var array $abilityLabels */
/** @var array<string, true> $roleGrants */
/** @var array<string, bool> $overrides */
/** @var array<string, true> $effective */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">
            <i class="bi bi-person-fill-gear"></i>
            Permission Overrides —
            <?= e($user['name']) ?>
        </h1>
        <div class="small text-muted mt-1">
            @<?= e($user['username']) ?> &middot;
            role <span class="badge text-bg-secondary"><?= e($user['role_label'] ?? $user['role']) ?></span>
        </div>
    </div>
    <a href="<?= url('/users') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle"></i>
    Each ability has three states:
    <strong>inherit</strong> (use the role grant — default),
    <strong>allow</strong> (override grant beyond what the role gives),
    <strong>deny</strong> (override deny something the role would otherwise grant).
    The "role grants / role denies" hint under each ability shows the inherited state.
</div>

<?php if ($user['role'] === 'admin'): ?>
    <div class="alert alert-warning py-2 small">
        <i class="bi bi-exclamation-triangle"></i>
        This user has the <strong>admin</strong> role — the master switch makes overrides moot
        (admin always allowed). Save will store entries but they have no runtime effect.
    </div>
<?php endif; ?>

<form method="post" action="<?= url('/users/' . $user['id'] . '/permissions/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>

    <?php
    $treeMode = 'override';
    require APP_ROOT . '/app/Views/partials/permission-tree.php';
    ?>

    <div class="d-flex gap-2 mb-4 sticky-bottom bg-body py-2 border-top">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Save Overrides
        </button>
        <a href="<?= url('/users') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
