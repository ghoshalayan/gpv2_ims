<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-plus-lg"></i> New Brand</h1>
    <a href="<?= url('/brands') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= url('/brands') ?>" novalidate>
            <?= \App\Support\Csrf::field() ?>

            <div class="mb-3">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" maxlength="150"
                       class="form-control" value="<?= e(old('name')) ?>" required autofocus>
                <div class="form-text">Up to 150 characters. Must be unique. Examples: Berger, Asian Paints.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" maxlength="255" rows="2"
                          class="form-control"><?= e(old('description')) ?></textarea>
                <div class="form-text">Optional. Up to 255 characters.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Create
                </button>
                <a href="<?= url('/brands') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
