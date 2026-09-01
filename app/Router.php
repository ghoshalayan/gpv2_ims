<?php
declare(strict_types=1);

namespace App;

use App\Middleware\MiddlewareInterface;

class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable|array, middleware:array}> */
    private array $routes = [];
    /** @var array<int, MiddlewareInterface|class-string> */
    private array $globalMiddleware = [];

    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    private function add(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . trim($this->stripBasePath($uri), '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = $this->compile($route['pattern']);
            if (\preg_match($regex, $uri, $matches)) {
                $params = \array_filter($matches, fn ($k) => !\is_int($k), ARRAY_FILTER_USE_KEY);
                $this->run($route, $params);
                return;
            }
        }

        http_response_code(404);
        require APP_ROOT . '/app/Views/errors/404.php';
    }

    private function stripBasePath(string $uri): string
    {
        $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '' && $script !== '/' && $script !== '\\' && \str_starts_with($uri, $script)) {
            return \substr($uri, \strlen($script));
        }
        return $uri;
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function run(array $route, array $params): void
    {
        $stack = array_merge($this->globalMiddleware, $route['middleware']);
        $core = function () use ($route, $params) {
            $handler = $route['handler'];
            if (\is_array($handler) && \is_string($handler[0])) {
                $controller = new $handler[0]();
                echo $controller->{$handler[1]}($params);
                return;
            }
            echo $handler($params);
        };

        $next = $core;
        foreach (array_reverse($stack) as $mw) {
            $instance = \is_string($mw) ? new $mw() : $mw;
            $current = $next;
            $next = fn () => $instance->handle($current);
        }
        $next();
    }
}
