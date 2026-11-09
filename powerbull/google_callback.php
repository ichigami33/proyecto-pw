<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Referrer-Policy: no-referrer');
$flow = $_GET['flow'] ?? '';
$entry = is_string($flow) ? ($_SESSION['google_oauth'][$flow] ?? null) : null;
$next = is_array($entry) ? safe_next($entry['next'] ?? 'cuenta.php') : 'cuenta.php';
try {
    if (!is_array($entry) || ($entry['created_at'] ?? 0) < time() - 600) throw new AppError('El acceso con Google venció. Volvé a iniciarlo desde esta página.');
    unset($_SESSION['google_oauth'][$flow]);
    if (isset($_GET['error'])) throw new AppError('No se completó el acceso con Google. Podés volver a intentarlo o usar tu correo.');
    $code = $_GET['code'] ?? '';
    if (!is_string($code) || $code === '' || strlen($code) > 4096) throw new AppError('No recibimos la confirmación de Google. Volvé a intentar.');
    $auth = sb('POST', 'auth/v1/token?grant_type=pkce', ['auth_code' => $code, 'code_verifier' => $entry['verifier']]);
    if (!empty($entry['reauth_id']) && ($auth['user']['id'] ?? '') !== $entry['reauth_id']) {
        $_SESSION['auth_error'] = 'Elegí la misma cuenta de Google con la que ingresaste a Powerbull.';
        redirect('cambiar_clave.php', 303);
    }
    store_auth($auth, true, 'google');
    redirect($next, 303);
} catch (AppError $error) {
    $_SESSION['auth_error'] = $error->getMessage();
    if (!empty($entry['reauth_id']) && current_user()) redirect('cambiar_clave.php', 303);
    redirect('login.php?next=' . rawurlencode($next), 303);
}
