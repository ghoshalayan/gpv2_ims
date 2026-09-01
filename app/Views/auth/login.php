<?php /** @var string $username */ ?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm mt-5">
            <div class="card-body p-4">
                <h1 class="h4 mb-4 text-center"><?= e(env('APP_NAME')) ?></h1>
                <form method="post" action="<?= url('/login') ?>" autocomplete="off" novalidate>
                    <?= \App\Support\Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?= e($username) ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Sign in
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
