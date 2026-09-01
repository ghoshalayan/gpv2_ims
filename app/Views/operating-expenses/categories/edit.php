<?php /** @var array $category */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Expense Category</h1>
    <a href="<?= url('/operating-expense-categories') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form method="post" action="<?= url('/operating-expense-categories/' . $category['id'] . '/update') ?>" novalidate>
    <?= \App\Support\Csrf::field() ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" maxlength="100" required class="form-control"
                           value="<?= e($category['name']) ?>">
                </div>
                <div class="col-md-7">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" maxlength="255" class="form-control"
                           value="<?= e($category['description'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
        <a href="<?= url('/operating-expense-categories') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
