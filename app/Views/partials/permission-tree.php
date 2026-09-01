<?php
/**
 * Reusable permission tree-view widget. Renders Section → Group → Resource → Abilities
 * as nested Bootstrap accordions/cards with checkboxes for each ability.
 *
 * Modes:
 *   $treeMode = 'role' (default)
 *     - One checkbox per ability. name="permissions[<resource>.<ability>]" value="1".
 *     - $granted is a flat set ['users.view' => true, ...] of currently checked keys.
 *
 *   $treeMode = 'override'
 *     - Three-state radio (allow / deny / inherit) per ability.
 *       name="override[<resource>.<ability>]" value="allow"|"deny"|"".
 *     - $overrides is a map ['key' => bool|absent]; $roleGrants is the role's set
 *       (used to render an "inherits-allow / inherits-deny" hint per ability).
 *
 * Required vars:
 *   $tree           — MenuPermissionRegistry::tree()
 *   $abilityLabels  — MenuPermissionRegistry::ABILITY_LABELS
 *   $treeMode       — 'role' | 'override'
 *   $granted        — array<string, true>   (mode=role)
 *   $roleGrants     — array<string, true>   (mode=override)
 *   $overrides      — array<string, bool>   (mode=override)
 *   $sectionLabels  — optional override for section display (defaults to key)
 */

/** @var array $tree */
/** @var array $abilityLabels */
$treeMode      = $treeMode ?? 'role';
$granted       = $granted ?? [];
$roleGrants    = $roleGrants ?? [];
$overrides     = $overrides ?? [];
$sectionLabels = $sectionLabels ?? [];
?>
<style>
    .perm-tree .accordion-button { padding: 0.6rem 1rem; }
    .perm-tree .accordion-body  { padding: 0.6rem 1rem; }
    .perm-resource { padding: 0.5rem 0.6rem; border-top: 1px solid #eef0f3; }
    .perm-resource:first-child { border-top: 0; }
    .perm-resource:hover { background: #f8f9fa; }
    .perm-resource .ability-cell { min-width: 88px; }
    .perm-resource .ability-cell .form-check-label { font-size: 0.8rem; cursor: pointer; }
    .perm-resource .ability-cell .form-check-input { cursor: pointer; }
    .perm-resource .resource-label { min-width: 200px; font-weight: 600; }
    .perm-resource .resource-key   { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: .72rem; color: #6c757d; }
    .perm-toolbar  { padding: 0.5rem 1rem; background: #fcfcfd; border-top: 1px solid #eef0f3; font-size: 0.8rem; }
    .perm-override-row label { font-size: .72rem; }
    .perm-override-row .inherit-hint { font-size: .68rem; opacity: .7; }
</style>

<div class="perm-tree">
<?php foreach ($tree as $sectionKey => $groups): ?>
    <?php $sectionId = 'sec_' . \preg_replace('/[^a-z0-9]/i', '_', $sectionKey); ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
            <strong>
                <i class="bi bi-folder2-open"></i>
                <?= e($sectionLabels[$sectionKey] ?? $sectionKey) ?>
            </strong>
            <?php if ($treeMode === 'role'): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm perm-bulk"
                            data-scope="#<?= e($sectionId) ?>" data-action="all">
                        <i class="bi bi-check-all"></i> All
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm perm-bulk"
                            data-scope="#<?= e($sectionId) ?>" data-action="none">
                        <i class="bi bi-x"></i> None
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-0" id="<?= e($sectionId) ?>">

        <?php foreach ($groups as $groupKey => $resources): ?>
            <?php
            $groupId = $sectionId . '_' . \preg_replace('/[^a-z0-9]/i', '_', $groupKey);
            $isTopLevel = $groupKey === '__top__';
            ?>

            <div class="border-top">
                <?php if (!$isTopLevel): ?>
                    <div class="px-3 py-2 d-flex justify-content-between align-items-center bg-body-tertiary">
                        <span class="text-uppercase fw-semibold small text-muted">
                            <i class="bi bi-folder"></i>
                            <?= e($groupKey) ?>
                        </span>
                        <?php if ($treeMode === 'role'): ?>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-link btn-sm py-0 perm-bulk"
                                        data-scope="#<?= e($groupId) ?>" data-action="all">grant all</button>
                                <span class="text-muted">·</span>
                                <button type="button" class="btn btn-link btn-sm py-0 perm-bulk"
                                        data-scope="#<?= e($groupId) ?>" data-action="none">clear</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div id="<?= e($groupId) ?>">
                <?php foreach ($resources as $resourceKey => $r): ?>
                    <div class="perm-resource">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="resource-label">
                                <i class="bi bi-file-earmark-text"></i>
                                <?= e($r['label']) ?>
                                <div class="resource-key"><?= e($resourceKey) ?></div>
                            </div>

                            <?php foreach ($r['abilities'] as $ability):
                                $key = $resourceKey . '.' . $ability;
                                $abilityLabel = $abilityLabels[$ability] ?? \ucfirst($ability);
                            ?>
                                <div class="ability-cell">
                                    <?php if ($treeMode === 'role'): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   id="p_<?= e(\str_replace('.', '_', $key)) ?>"
                                                   name="permissions[<?= e($key) ?>]" value="1"
                                                   <?= !empty($granted[$key]) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="p_<?= e(\str_replace('.', '_', $key)) ?>">
                                                <?= e($abilityLabel) ?>
                                            </label>
                                        </div>
                                    <?php else:
                                        // override mode: tri-state select
                                        $current = $overrides[$key] ?? null;
                                        $state = $current === true ? 'allow' : ($current === false ? 'deny' : '');
                                        $hint = isset($roleGrants[$key]) ? 'role grants' : 'role denies';
                                    ?>
                                        <div class="perm-override-row">
                                            <label class="d-block fw-semibold mb-1"><?= e($abilityLabel) ?></label>
                                            <select name="override[<?= e($key) ?>]"
                                                    class="form-select form-select-sm no-select2"
                                                    style="min-width:108px">
                                                <option value=""      <?= $state === ''      ? 'selected' : '' ?>>inherit</option>
                                                <option value="allow" <?= $state === 'allow' ? 'selected' : '' ?>>allow</option>
                                                <option value="deny"  <?= $state === 'deny'  ? 'selected' : '' ?>>deny</option>
                                            </select>
                                            <div class="inherit-hint text-muted"><?= $hint ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>

        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($treeMode === 'role'): ?>
<script>
    // Bulk-toggle: button data-scope="#card" data-action="all|none" toggles every checkbox inside scope.
    document.querySelectorAll('.perm-bulk').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var scope = document.querySelector(btn.dataset.scope);
            if (!scope) return;
            var on = btn.dataset.action === 'all';
            scope.querySelectorAll('input[type=checkbox]').forEach(function (cb) { cb.checked = on; });
        });
    });
</script>
<?php endif; ?>
