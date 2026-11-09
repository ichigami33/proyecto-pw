<?php
declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
define('APP_ROOT', dirname(__DIR__));
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/.'));
$config = require __DIR__ . '/config.php';

class AppError extends RuntimeException {
    public function __construct(string $message, public int $status = 400) { parent::__construct($message); }
}
class DatabaseError extends AppError {
    public function __construct(string $message, int $status, public string $providerCode = '') { parent::__construct($message, $status); }
}

set_exception_handler(function (Throwable $error): void {
    $status = $error instanceof AppError ? $error->status : 500;
    $message = $error instanceof AppError ? $error->getMessage() : 'No pudimos completar la operación. Intentá nuevamente.';
    error_log('[Powerbull] ' . get_class($error) . ' HTTP ' . $status . ' en ' . basename($error->getFile()) . ':' . $error->getLine());
    http_response_code($status);
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'api.php') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Powerbull</title><body><main><h1>No pudimos completar la operación</h1><p>' . h($message) . '</p><a href="' . h(url('login.php')) . '">Volver a iniciar sesión</a></main></body></html>';
});

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('POWERBULL_SESSION');
session_set_cookie_params([
    'lifetime' => 0, 'path' => (BASE_URL ?: '') . '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || parse_url((string)($config['app_url'] ?? ''), PHP_URL_SCHEME) === 'https',
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
// El POST local puede redirigir a Supabase y Google. Los scripts siguen siendo locales.
$authOrigin = preg_match('~^https://[a-z0-9.-]+$~i', (string)($config['supabase_url'] ?? '')) ? $config['supabase_url'] : '';
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' https: data: blob:; connect-src 'self'; frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self' " . $authOrigin . " https://accounts.google.com");
header('Cache-Control: private, no-store');

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = 'index.php'): string {
    if (BASE_URL !== '' && str_starts_with($path, BASE_URL . '/')) return $path;
    return (BASE_URL ?: '') . '/' . ltrim($path, '/');
}
function money($value): string {
    $n = (float)$value;
    return '$ ' . number_format($n, abs($n - round($n)) > 0.001 ? 2 : 0, ',', '.');
}
function lower(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value); }
function text_length(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value); }
function image_url(?string $path): string {
    if ($path && preg_match('~^https://~i', $path)) return $path;
    if ($path && preg_match('~^(?:assets/images|uploads)/[a-zA-Z0-9_.-]+$~', $path)) return url($path);
    return url('assets/images/sin-imagen.svg');
}
function safe_next($next): string {
    if (!is_string($next) || strlen($next) > 600 || preg_match('~[\x00-\x20\\\\]~', rawurldecode($next))) return 'index.php';
    if (BASE_URL !== '' && str_starts_with($next, BASE_URL . '/')) $next = substr($next, strlen(BASE_URL) + 1);
    $parts = parse_url($next);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || str_starts_with($next, '/')) return 'index.php';
    $path = $parts['path'] ?? '';
    if (!in_array($path, ['index.php','cuenta.php','carrito.php','pedidos.php','producto.php','admin.php','cambiar_clave.php','login.php','registro.php'], true)) return 'index.php';
    return $path . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}
function redirect(string $path, int $status = 302): never { header('Location: ' . url($path), true, $status); exit; }
function verify_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) throw new AppError('La página venció. Actualizala y volvé a intentar.', 403);
}
function flash(?string $message = null): ?string {
    if ($message !== null) { $_SESSION['flash_message'] = $message; return null; }
    $value = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    return $value;
}
function auth_error(): ?string {
    $message = $_SESSION['auth_error'] ?? null;
    unset($_SESSION['auth_error']);
    return $message;
}
function cart_count(): int { return array_sum(array_map('intval', $_SESSION['cart'] ?? [])); }
function uuid(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $hex = bin2hex($b);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}
function page_error(string $title, string $message, int $status = 404): never {
    http_response_code($status);
    require_once __DIR__ . '/layout.php';
    page_header($title);
    echo '<main class="container page-section"><section class="empty-state"><h1>' . h($title) . '</h1><p>' . h($message) . '</p><a class="button" href="' . h(url()) . '">Volver al inicio</a></section></main>';
    page_footer();
    exit;
}

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/catalog.php';
