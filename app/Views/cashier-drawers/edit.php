<?php /** @var array $drawer */ /** @var array $users */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Cashier Drawer</h1>
    <a href="<?= url('/cashier-drawers') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/cashier-drawers/' . $drawer['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" maxlength="100" required class="form-control"
                           value="<?= e($drawer['name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" maxlength="100" class="form-control"
                           value="<?= e($drawer['location'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_user_id" class="form-select">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"
                                <?= (int) ($drawer['assigned_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['name']) ?> (<?= e($u['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Current Balance</label>
                    <input type="text" class="form-control" readonly
                           value="₹<?= number_format((float) $drawer['current_balance'], 2) ?>">
                    <div class="form-text small">Mutated only by transfers / payments.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" rows="2" maxlength="500" class="form-control"><?= e($drawer['remarks'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
        <a href="<?= url('/cashier-drawers') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
