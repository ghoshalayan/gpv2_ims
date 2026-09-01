<?php
declare(strict_types=1);

use App\Support\Env;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = [], ?string $layout = 'app'): string
    {
        $viewFile = APP_ROOT . '/app/Views/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$name}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutFile = APP_ROOT . '/app/Views/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }
        ob_start();
        require $layoutFile;
        return ob_get_clean();
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): string
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        header('Location: ' . $base . $path);
        return '';
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url($path);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve a value from the previous request's POST data (for form repopulation).
     * Stored by controllers as $_SESSION['_old_input'] before redirect on validation failure.
     * The cache is loaded once per request, so multiple calls within a view all see the same data.
     */
    function old(string $key, string $default = ''): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = $_SESSION['_old_input'] ?? [];
            unset($_SESSION['_old_input']);
        }
        return (string) ($cache[$key] ?? $default);
    }
}

if (!function_exists('flash_old')) {
    /** Stash POST data so it can be re-displayed via old() after a redirect. */
    function flash_old(array $data): void
    {
        unset($data['password'], $data['password_confirmation'], $data['_csrf']);
        $_SESSION['_old_input'] = $data;
    }
}

if (!function_exists('is_active_path')) {
    /**
     * True if the current request URI matches the given prefix. Two flavours:
     *  - prefix without `?`: matches the path portion (or any sub-path).
     *  - prefix with `?key=value`: matches when the request's path is the same AND
     *    every key=value pair in the prefix's query is present in the request's query.
     *    (Lets sidebar items disambiguate `/old-records?kind=customer` vs `?kind=supplier`.)
     */
    function is_active_path(string $prefix): bool
    {
        $reqUri  = $_SERVER['REQUEST_URI'] ?? '/';
        $reqPath = parse_url($reqUri, PHP_URL_PATH) ?? '/';
        $reqQs   = [];
        $reqQsRaw = parse_url($reqUri, PHP_URL_QUERY);
        if ($reqQsRaw) { parse_str($reqQsRaw, $reqQs); }

        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        if ($base !== '' && str_starts_with($reqPath, $base)) {
            $reqPath = substr($reqPath, strlen($base));
        }
        $reqPath = '/' . trim($reqPath, '/');

        $prefPath = parse_url($prefix, PHP_URL_PATH) ?? $prefix;
        $prefPath = '/' . trim($prefPath, '/');
        $prefQsRaw = parse_url($prefix, PHP_URL_QUERY);

        // Path must match (exact or sub-path).
        $pathMatch = $reqPath === $prefPath || str_starts_with($reqPath, $prefPath . '/');
        if (!$pathMatch) { return false; }

        // No query string in the prefix → path match is sufficient.
        if ($prefQsRaw === null || $prefQsRaw === '') { return true; }

        // Query string in prefix → every key=value must be present in the request.
        $prefQs = [];
        parse_str($prefQsRaw, $prefQs);
        foreach ($prefQs as $k => $v) {
            if (!isset($reqQs[$k]) || (string) $reqQs[$k] !== (string) $v) {
                return false;
            }
        }
        return true;
    }
}
