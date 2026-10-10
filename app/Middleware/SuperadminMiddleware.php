<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\Flash;
use App\Support\Superadmin;

/**
 * Guards the control plane.
 *
 * Only a signed-in superadmin may reach the tenant console. A tenant's own admin
 * — however privileged inside their business — is not a superadmin and is turned
 * away here, which is what "tenant admins come under the superadmin" means in
 * practice.
 */
class SuperadminMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        if (!Superadmin::check()) {
            Flash::error('Sign in as a system administrator to manage tenants.');
            echo redirect('/login');
            return;
        }
        $next();
    }
}
