<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\Auth;
use App\Support\Flash;

class RbacMiddleware implements MiddlewareInterface
{
    /** @param array<int, string> $allowedRoles */
    public function __construct(private array $allowedRoles) {}

    public function handle(callable $next): void
    {
        if (!Auth::check()) {
            Flash::error('Please log in to continue.');
            echo redirect('/login');
            return;
        }
        if (!Auth::is(...$this->allowedRoles)) {
            \http_response_code(403);
            $title = 'Forbidden';
            $content = '<div class="text-center py-5"><h1 class="display-1 text-muted">403</h1>'
                . '<p class="lead">You do not have permission to view this page.</p>'
                . '<a href="' . url('/dashboard') . '" class="btn btn-primary">Dashboard</a></div>';
            require APP_ROOT . '/app/Views/layouts/app.php';
            return;
        }
        $next();
    }
}
