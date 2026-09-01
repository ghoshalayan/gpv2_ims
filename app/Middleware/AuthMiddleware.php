<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\Auth;
use App\Support\Flash;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        if (!Auth::check()) {
            Flash::error('Please log in to continue.');
            echo redirect('/login');
            return;
        }
        $next();
    }
}
