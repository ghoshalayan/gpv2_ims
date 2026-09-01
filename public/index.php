<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Router;

$router = new Router();
require __DIR__ . '/../routes/web.php';

try {
    $router->dispatch(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'
    );
} catch (\Throwable $e) {
    http_response_code(500);
    if (env('APP_DEBUG') === 'true') {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        require __DIR__ . '/../app/Views/errors/500.php';
    }
}
