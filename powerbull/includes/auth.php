<?php
declare(strict_types=1);

function clear_auth(): void {
    unset($_SESSION['auth'], $_SESSION['user'], $_SESSION['profile_checked_at'], $_SESSION['has_password'],
        $_SESSION['has_google'], $_SESSION['order_summary'], $_SESSION['login_method'], $_SESSION['google_authenticated_at']);
}
function load_account_context(string $access, string $authId): void {
    $context = sb_request('POST', 'rest/v1/rpc/powerbull_sesion', new stdClass(), $access)['data'];
    $profile = $context['profile'] ?? null;
    if (!is_array($profile) || ($profile['auth_id'] ?? null) !== $authId) throw new AppError('Tu cuenta no tiene un perfil disponible. Contactá al administrador.', 409);
    $_SESSION['user'] = $profile;
    $_SESSION['has_password'] = ($context['has_password'] ?? false) === true;
    $_SESSION['has_google'] = ($context['has_google'] ?? false) === true;
    $_SESSION['order_summary'] = $context['order_summary'] ?? ['count' => 0, 'total' => 0];
    $_SESSION['profile_checked_at'] = time();
}
function can_create_password(): bool {
    return !empty($_SESSION['has_google']) && !empty($_SESSION['google_authenticated_at'])
        && (int)$_SESSION['google_authenticated_at'] >= time() - 600;
}
function auth_access_token(): string {
    $auth = $_SESSION['auth'] ?? null;
    if (!is_array($auth) || empty($auth['access_token'])) throw new AppError('Iniciá sesión para continuar.', 401);
    if ((int)($auth['expires_at'] ?? 0) <= time() + 60) {
        try {
            $refreshed = sb_request('POST', 'auth/v1/token?grant_type=refresh_token', ['refresh_token' => $auth['refresh_token'] ?? ''])['data'];
            store_auth($refreshed, false, 'refresh');
        } catch (DatabaseError $error) {
            if ($error->status < 500 && $error->status !== 429) { clear_auth(); throw new AppError('Tu sesión venció. Iniciá sesión nuevamente.', 401); }
            throw $error;
        }
    }
    return (string)$_SESSION['auth']['access_token'];
}

function store_auth(array $auth, bool $regenerate = false, string $method = 'password'): void {
    $access = $auth['access_token'] ?? null;
    $refresh = $auth['refresh_token'] ?? null;
    if (!is_string($access) || $access === '' || !is_string($refresh) || $refresh === '') throw new AppError('No pudimos abrir tu sesión. Volvé a intentar.', 502);
    // Respuesta del servidor Auth por HTTPS. La RPC valida además el JWT y filtra por auth.uid().
    // No hace falta repetir GET /auth/v1/user tras un intercambio de tokens válido.
    $identity = $auth['user'] ?? null;
    if (!is_array($identity)) $identity = sb_request('GET', 'auth/v1/user', null, $access)['data'];
    $authId = $identity['id'] ?? '';
    if (!is_string($authId) || !preg_match('/^[0-9a-f-]{36}$/i', $authId)) throw new AppError('No pudimos validar tu cuenta.', 502);
    $reuse = $method === 'refresh' && ($_SESSION['user']['auth_id'] ?? '') === $authId
        && (int)($_SESSION['profile_checked_at'] ?? 0) >= time() - 60;
    if (!$reuse) load_account_context($access, $authId);
    if ($regenerate) {
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        unset($_SESSION['google_oauth']);
    }
    $_SESSION['auth'] = ['access_token' => $access, 'refresh_token' => $refresh,
        'expires_at' => (int)($auth['expires_at'] ?? (time() + (int)($auth['expires_in'] ?? 3600)))];
    if ($method !== 'refresh') $_SESSION['login_method'] = $method;
    if ($method === 'google') $_SESSION['google_authenticated_at'] = time();
    elseif ($regenerate) unset($_SESSION['google_authenticated_at']);
}

function current_user(): ?array {
    if (empty($_SESSION['auth']) || empty($_SESSION['user'])) return null;
    try {
        $token = auth_access_token();
        if (!array_key_exists('has_google', $_SESSION) || !isset($_SESSION['order_summary']) || (int)($_SESSION['profile_checked_at'] ?? 0) < time() - 60) {
            load_account_context($token, (string)$_SESSION['user']['auth_id']);
        }
        return $_SESSION['user'];
    } catch (AppError $error) {
        if ($error->status === 401) { clear_auth(); return null; }
        throw $error;
    }
}

function require_user(): array {
    $user = current_user();
    if (!$user) {
        $path = basename($_SERVER['SCRIPT_NAME'] ?? 'cuenta.php');
        if (!empty($_SERVER['QUERY_STRING'])) $path .= '?' . $_SERVER['QUERY_STRING'];
        redirect('login.php?next=' . rawurlencode(safe_next($path)));
    }
    return $user;
}
function require_admin(): array {
    $user = require_user();
    if ($user['rol'] !== 'administrador') throw new AppError('Esta sección es exclusiva del administrador.', 403);
    return $user;
}

function app_url(): string {
    global $config;
    $configured = rtrim((string)($config['app_url'] ?? ''), '/');
    if ($configured !== '') {
        $parts = parse_url($configured);
        $local = in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
        if (!$parts || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (($parts['scheme'] ?? '') !== 'https' && !(($parts['scheme'] ?? '') === 'http' && $local))) {
            throw new AppError('La dirección de la tienda no está configurada correctamente.', 503);
        }
        return $configured;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^(localhost|127\.0\.0\.1)(?::[0-9]{1,5})?$/i', $host)) throw new AppError('Falta configurar la dirección pública de la tienda para iniciar sesión con Google.', 503);
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    return $scheme . '://' . $host . BASE_URL;
}
