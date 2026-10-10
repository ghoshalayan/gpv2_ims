<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/app/Support/helpers.php';

App\Support\Env::load(APP_ROOT . '/.env');

date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', env('APP_DEBUG') === 'true' ? '1' : '0');
error_reporting(E_ALL);

// Mark the session cookie "secure" whenever the request is actually served over
// HTTPS, so the auth cookie is HTTPS-only in production but still works on a
// plain-HTTP local box. We detect HTTPS from several signals because shared
// hosts / proxies don't always set $_SERVER['HTTPS'] directly.
$isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    || \strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

session_name(env('SESSION_NAME', 'gpv2_session'));
session_set_cookie_params([
    'lifetime' => (int) env('SESSION_LIFETIME_MINUTES', '120') * 60,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

/**
 * Cross-tenant session guard.
 *
 * PHP stores every session in one shared directory on the server. The session
 * COOKIE is already safe — no cookie domain is set above, so it is host-only — but
 * the STORE is not: a session id created while working in one tenant would still
 * resolve if presented in another context, carrying that tenant's authenticated
 * user with it.
 *
 * So the tenant is stamped into the session and checked on every request. If the
 * stamp does not match the tenant now selected, the session is discarded rather
 * than trusted. Costs nothing when single-tenant (both sides are null).
 */
$__tenantNow = $_SESSION['_tenant']['tenant_key'] ?? null;
if (!array_key_exists('_tenant_stamp', $_SESSION)) {
    $_SESSION['_tenant_stamp'] = $__tenantNow;
} elseif ($_SESSION['_tenant_stamp'] !== $__tenantNow) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['_tenant_stamp'] = null;
}
unset($__tenantNow);
