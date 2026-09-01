<?php
/** @var array       $families */
/** @var array       $bases */
/** @var ?array      $unit */
$unit = $unit ?? null;
$isEdit = $unit !== null;
$selectedFamily = old('family', $unit['family'] ?? 'count');
$selectedBase   = (int) old('base_unit_id', (string) ($unit['base_unit_id'] ?? 0));
?>
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
        <input type="text" id="code" name="code" maxlength="20" required
               class="form-control font-monospace text-uppercase"
               pattern="[A-Z][A-Z0-9_]*"
               value="<?= e(old('code', $unit['code'] ?? '')) ?>">
        <div class="form-text">Uppercase, letters/digits/underscore. e.g. LTR, KG, CASE.</div>
    </div>
    <div class="col-md-5">
        <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" maxlength="100" required
               class="form-control"
               value="<?= e(old('name', $unit['name'] ?? '')) ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="family">Family <span class="text-danger">*</span></label>
        <select id="family" name="family" class="form-select" required>
            <?php foreach ($families as $f): ?>
                <option value="<?= e($f) ?>" <?= $selectedFamily === $f ? 'selected' : '' ?>><?= e($f) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label" for="decimals">Decimals</label>
        <input type="number" id="decimals" name="decimals" min="0" max="6"
               class="form-control text-end"
               value="<?= e(old('decimals', (string) ($unit['decimals'] ?? 3))) ?>">
        <div class="form-text">Display precision.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="base_unit_id">Base unit</label>
        <select id="base_unit_id" name="base_unit_id" class="form-select">
            <option value="">— this IS a base unit —</option>
            <?php foreach ($bases as $b): ?>
                <option value="<?= (int) $b['id'] ?>"
                        data-family="<?= e($b['family']) ?>"
                        <?= $selectedBase === (int) $b['id'] ? 'selected' : '' ?>>
                    <?= e($b['code']) ?> — <?= e($b['name']) ?> (<?= e($b['family']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Pick the base in the same family this converts to. Leave blank if this IS a base.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="factor_to_base">Factor to base</label>
        <input type="number" id="factor_to_base" name="factor_to_base"
               step="0.00000001" min="0.00000001"
               class="form-control text-end font-monospace"
               value="<?= e(old('factor_to_base', (string) ($unit['factor_to_base'] ?? 1))) ?>">
        <div class="form-text">1 of THIS = factor × base. e.g. ML→LTR = 0.001.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <div class="form-check form-switch mt-2">
            <?php $active = (int) old('is_active', (string) ($unit['is_active'] ?? 1)) === 1; ?>
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <input type="text" id="description" name="description" maxlength="255"
               class="form-control"
               value="<?= e(old('description', $unit['description'] ?? '')) ?>">
    </div>
</div>
