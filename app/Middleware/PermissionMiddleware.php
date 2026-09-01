<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\Auth;
use App\Support\Flash;
use App\Support\Permissions;

/**
 * Route-level authorization driven by the dynamic permission system.
 *
 * Each protected route is tagged with the resource + ability it requires
 * (e.g. 'sales_invoices' + 'create'). Access is granted when
 * Permissions::can($resource, $ability) is true. The 'admin' role is a
 * master-switch (always allowed) so a misconfigured grant can never lock the
 * administrator out — and the administrator can always re-grant anything via
 * Role Permissions.
 *
 * Replaces the old hard-coded RbacMiddleware role-tier checks so that custom
 * roles created in the Roles UI are actually enforced, not just shown/hidden
 * in the sidebar.
 */
class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $resource,
        private string $ability = 'view',
    ) {}

    public function handle(callable $next): void
    {
        if (!Auth::check()) {
            Flash::error('Please log in to continue.');
            echo redirect('/login');
            return;
        }
        if (!Permissions::can($this->resource, $this->ability)) {
            \http_response_code(403);
            $title = 'Forbidden';
            $content = '<div class="text-center py-5"><h1 class="display-1 text-muted">403</h1>'
                . '<p class="lead">You do not have permission to perform this action.</p>'
                . '<p class="text-muted small">Requires <code>' . e($this->resource . '.' . $this->ability)
                . '</code>. Ask an administrator to grant it under Role Permissions.</p>'
                . '<a href="' . url('/dashboard') . '" class="btn btn-primary">Dashboard</a></div>';
            require APP_ROOT . '/app/Views/layouts/app.php';
            return;
        }
        $next();
    }
}
