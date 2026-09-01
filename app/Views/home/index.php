<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3"><i class="bi bi-rocket-takeoff text-primary"></i> <?= e($appName) ?> booted successfully</h1>
                <p class="text-muted mb-4">
                    Front controller, router, autoloader, env loader, sessions, CSRF, flash, view layer — all wired and serving this page.
                </p>

                <table class="table table-sm">
                    <tbody>
                        <tr><th class="w-25">App env</th><td><span class="badge text-bg-secondary"><?= e($appEnv) ?></span></td></tr>
                        <tr><th>PHP version</th><td><?= e($phpVersion) ?></td></tr>
                        <tr><th>Database</th><td>
                            <?php if ($dbStatus['ok']): ?>
                                <span class="badge text-bg-success">Connected</span>
                                <span class="text-muted ms-2"><?= (int) $dbStatus['tables'] ?> table(s)</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Not reachable</span>
                                <div class="small text-danger mt-1"><?= e($dbStatus['message']) ?></div>
                            <?php endif; ?>
                        </td></tr>
                        <tr><th>Session ID</th><td><code><?= e(session_id()) ?></code></td></tr>
                        <tr><th>CSRF token</th><td><code class="small text-truncate d-inline-block" style="max-width: 480px;"><?= e(\App\Support\Csrf::token()) ?></code></td></tr>
                    </tbody>
                </table>

                <div class="alert alert-info mt-3 mb-0 small">
                    <strong>Next:</strong> Phase 1 module #2 — Authentication. After that, the reference-data CRUD modules (Categories, Sub-Categories, Brands, Suppliers, Customers, Salesmen, Employees, Users).
                </div>
            </div>
        </div>
    </div>
</div>
