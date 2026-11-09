<?php
declare(strict_types=1);

/** Transporte HTTPS. Las claves secretas de servicio nunca se usan en esta aplicación. */
function sb_request(string $method, string $path, $data = null, ?string $accessToken = null, array $extraHeaders = []): array {
    global $config;
    $base = rtrim((string)($config['supabase_url'] ?? ''), '/');
    if (!preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~i', $base) || !preg_match('~^(?:auth|rest|functions|storage)/v1/~', $path) || str_contains($path, "\r") || str_contains($path, "\n")) {
        throw new AppError('La conexión con la tienda no está configurada correctamente.', 503);
    }
    $key = (string)($config['supabase_key'] ?? '');
    // Las Edge Functions con verify_jwt requieren el JWT anon, no una clave sb_publishable.
    $bearer = $accessToken ?? (string)($config['anon_jwt'] ?? '');
    $headers = ['apikey: ' . $key, 'Accept: application/json'];
    if ($bearer !== '') $headers[] = 'Authorization: Bearer ' . $bearer;
    if ($data !== null) $headers[] = 'Content-Type: application/json';
    foreach ($extraHeaders as $header) $headers[] = $header;
    $body = $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (function_exists('curl_init')) {
        // Reutiliza DNS, TLS y conexión dentro de esta petición PHP; los headers se reinician.
        static $connection = null;
        $connection ??= curl_init();
        $ch = $connection;
        curl_reset($ch);
        $options = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $base . '/' . $path, CURLOPT_ENCODING => '', CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_DNS_CACHE_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
        if ($body !== null) $options[CURLOPT_POSTFIELDS] = $body;
        if (!empty($config['ca_bundle'])) $options[CURLOPT_CAINFO] = $config['ca_bundle'];
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errorNumber = curl_errno($ch);
        if ($raw === false) {
            error_log('[Powerbull] Error de transporte HTTPS ' . $errorNumber);
            throw new AppError('No pudimos conectar con la base de datos. Intentá nuevamente en unos momentos.', 503);
        }
    } else {
        $http = ['method' => $method, 'header' => implode("\r\n", $headers), 'timeout' => 25, 'ignore_errors' => true, 'follow_location' => 0];
        if ($body !== null) $http['content'] = $body;
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true];
        if (!empty($config['ca_bundle'])) $ssl['cafile'] = $config['ca_bundle'];
        $raw = @file_get_contents($base . '/' . $path, false, stream_context_create(['http' => $http, 'ssl' => $ssl]));
        if ($raw === false) throw new AppError('No pudimos conectar con la base de datos. Intentá nuevamente en unos momentos.', 503);
        preg_match('~\s(\d{3})\s~', $http_response_header[0] ?? '', $matches);
        $status = (int)($matches[1] ?? 0);
    }
    $result = $raw === '' ? [] : json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
        $error = is_array($result) ? $result : [];
        $code = (string)($error['error_code'] ?? $error['code'] ?? '');
        error_log('[Powerbull] Supabase HTTP ' . $status . ' código ' . preg_replace('/[^a-zA-Z0-9_]/', '', $code));
        $message = 'No pudimos completar la operación con la base de datos. Intentá nuevamente.';
        $publicStatus = $status === 429 ? 429 : ($status >= 500 ? 503 : 400);
        if (str_starts_with($path, 'functions/v1/')) {
            $message = (string)($error['error'] ?? $error['message'] ?? $message);
            $publicStatus = in_array($status, [400,401,403,409,413,429,503], true) ? $status : 503;
        } elseif (str_contains($path, 'grant_type=password') && $status === 400) {
            $message = $code === 'email_not_confirmed' ? 'Confirmá tu correo antes de iniciar sesión.' : 'El correo o la contraseña no son correctos. Si te registraste con Google, usá «Continuar con Google». Para ingresar con contraseña, primero creala desde Mi cuenta.';
            $publicStatus = 401;
        } elseif (str_contains($path, 'grant_type=pkce')) {
            $message = 'El acceso con Google venció o ya fue utilizado. Volvé a intentarlo desde Iniciar sesión.';
        } elseif (in_array($code, ['weak_password','same_password','password_too_short'], true)) {
            $message = 'Elegí una contraseña nueva de al menos 6 caracteres.';
        } elseif ($code === '23505') {
            $message = 'Ese correo, nombre de usuario o código ya está en uso.';
            $publicStatus = 409;
        } elseif ($status === 401) {
            $message = 'Tu sesión venció. Iniciá sesión nuevamente.';
            $publicStatus = 401;
        } elseif ($status === 403) {
            $message = 'No tenés permiso para realizar esta operación.';
            $publicStatus = 403;
        } elseif ($status === 429) {
            $message = 'Hubo demasiados intentos. Esperá unos minutos antes de volver a intentar.';
        }
        throw new DatabaseError($message, $publicStatus, $code);
    }
    if (!is_array($result) && !is_bool($result)) throw new AppError('La base de datos devolvió una respuesta inesperada.', 502);
    return ['data' => $result, 'status' => $status];
}

function sb(string $method, string $path, $data = null, bool $authenticated = false, array $headers = []) {
    $token = $authenticated ? auth_access_token() : null;
    return sb_request($method, $path, $data, $token, $headers)['data'];
}
