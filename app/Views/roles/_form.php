<?php
/** Shared body for roles/create + roles/edit. Expects $role (?array). */
$role   = $role   ?? null;
$isEdit = $role !== null;
$lockedKey = $isEdit && (int) ($role['is_system'] ?? 0) === 1;
?>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="role_key">Role Key <span class="text-danger">*</span></label>
        <input type="text" id="role_key" name="role_key" maxlength="40"
               class="form-control font-monospace"
               value="<?= e(old('role_key', $role['role_key'] ?? '')) ?>"
               <?= $lockedKey ? 'readonly' : '' ?>
               pattern="[a-z][a-z0-9_]*" required>
        <div class="form-text">
            Slug — lowercase letters, digits, underscores. Used internally; not shown to users.
            <?php if ($lockedKey): ?><br><strong>System role — key is locked.</strong><?php endif; ?>
        </div>
    </div>
    <div class="col-md-5">
        <label class="form-label" for="label">Label <span class="text-danger">*</span></label>
        <input type="text" id="label" name="label" maxlength="100"
               class="form-control"
               value="<?= e(old('label', $role['label'] ?? '')) ?>" required>
        <div class="form-text">Human-readable name shown in UI.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="sort_order">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order"
               class="form-control"
               value="<?= e(old('sort_order', (string) ($role['sort_order'] ?? 100))) ?>">
        <div class="form-text">Lower = earlier in lists.</div>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" rows="2" maxlength="255"
                  class="form-control"><?= e(old('description', $role['description'] ?? '')) ?></textarea>
    </div>
</div>
