<?php /** @var array $brand */ /** @var bool $isSystem */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Brand</h1>
    <a href="<?= url('/brands') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($isSystem): ?>
    <div class="alert alert-info py-2 small">
        This is the <strong>system fallback brand</strong>. The name cannot be changed and the brand cannot be disabled.
        You may edit the description.
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= url('/brands/' . $brand['id'] . '/update') ?>" novalidate>
            <?= \App\Support\Csrf::field() ?>

            <div class="mb-3">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" maxlength="150"
                       class="form-control"
                       value="<?= e(old('name', $brand['name'])) ?>"
                       <?= $isSystem ? 'readonly' : '' ?>
                       required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" maxlength="255" rows="2"
                          class="form-control"><?= e(old('description', $brand['description'] ?? '')) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save changes
                </button>
                <a href="<?= url('/brands') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
