<?php /** @var array $users */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Cashier Drawer</h1>
    <a href="<?= url('/cashier-drawers') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/cashier-drawers') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" maxlength="100" required
                           class="form-control" value="<?= e(old('name')) ?>"
                           placeholder="e.g. Main Counter">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="location">Location</label>
                    <input type="text" id="location" name="location" maxlength="100"
                           class="form-control" value="<?= e(old('location')) ?>"
                           placeholder="e.g. Front desk">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="assigned_user_id">Assigned To</label>
                    <select id="assigned_user_id" name="assigned_user_id" class="form-select">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) old('assigned_user_id') === (int) $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['name']) ?> (<?= e($u['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="opening_balance">Opening Balance (₹)</label>
                    <input type="number" id="opening_balance" name="opening_balance" min="0" step="0.01"
                           class="form-control" value="<?= e(old('opening_balance', '0')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="500" class="form-control"><?= e(old('remarks')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Create</button>
        <a href="<?= url('/cashier-drawers') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
