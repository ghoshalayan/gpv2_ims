<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\Csrf;
use App\Support\Flash;

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!Csrf::verify($token)) {
                http_response_code(419);
                Flash::error('Session expired or invalid request token. Please try again.');
                require APP_ROOT . '/app/Views/errors/419.php';
                return;
            }
        }
        $next();
    }
}
