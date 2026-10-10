<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\Auth;
use App\Support\Db;
use App\Support\Flash;
use App\Support\Superadmin;
use App\Support\Tenant;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        // A superadmin who has entered a tenant is authorised without being one of
        // that tenant's own users.
        $signedIn = Auth::check() || (Superadmin::check() && Tenant::check());

        if (!$signedIn) {
            Flash::error('Please log in to continue.');
            echo redirect('/login');
            return;
        }

        // Business pages read business data, so a tenant must be selected. Without
        // this check Db::pdo() would throw deep inside a repository; failing here
        // is both earlier and clearer. Single-tenant installs (no control database
        // yet) are unaffected.
        if (!Tenant::check() && Db::controlAvailable()) {
            Flash::error('Your session is no longer attached to a business. Please sign in again.');
            Auth::logout();
            echo redirect('/login');
            return;
        }

        $next();
    }
}
