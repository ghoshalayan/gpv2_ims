<?php /** @var array $subcategory */ /** @var array $categories */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-pencil"></i> Edit Sub-Category</h1>
    <a href="<?= url('/subcategories') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= url('/subcategories/' . $subcategory['id'] . '/update') ?>" novalidate>
            <?= \App\Support\Csrf::field() ?>

            <div class="mb-3">
                <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                <select id="category_id" name="category_id" class="form-select" required>
                    <?php $selected = (int) old('category_id', (string) $subcategory['category_id']); ?>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"
                                <?= (int) $c['id'] === $selected ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                            <?= $c['status'] !== 'active' ? ' (inactive)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" maxlength="150"
                       class="form-control"
                       value="<?= e(old('name', $subcategory['name'])) ?>" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" maxlength="255" rows="2"
                          class="form-control"><?= e(old('description', $subcategory['description'] ?? '')) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save changes
                </button>
                <a href="<?= url('/subcategories') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
