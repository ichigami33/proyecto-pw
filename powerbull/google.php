<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); throw new AppError('Abrí Iniciar sesión para continuar con Google.', 405); }
verify_csrf();
$user = current_user();
$reauth = $user && ($_POST['reauth'] ?? '') === '1';
if ($user && !$reauth) redirect('cuenta.php');
try {
    $flow = bin2hex(random_bytes(24));
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $callback = app_url() . '/google_callback.php?flow=' . $flow;
    foreach ($_SESSION['google_oauth'] ?? [] as $key => $value) {
        if (($value['created_at'] ?? 0) < time() - 600) unset($_SESSION['google_oauth'][$key]);
    }
    if (count($_SESSION['google_oauth'] ?? []) >= 5) array_shift($_SESSION['google_oauth']);
    $_SESSION['google_oauth'][$flow] = ['verifier' => $verifier, 'created_at' => time(),
        'next' => $reauth ? 'cambiar_clave.php' : safe_next($_POST['next'] ?? 'cuenta.php'),
        'reauth_id' => $reauth ? $user['auth_id'] : null];
    $parameters = [
        'provider' => 'google', 'redirect_to' => $callback, 'scopes' => 'email profile',
        'code_challenge' => $challenge, 'code_challenge_method' => 's256',
    ];
    if ($reauth) {
        $parameters['login_hint'] = $user['Gmail'];
        $parameters['prompt'] = 'select_account';
    }
    $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    session_write_close();
    header('Location: ' . rtrim($config['supabase_url'], '/') . '/auth/v1/authorize?' . $query, true, 303);
    exit;
} catch (AppError $error) {
    $_SESSION['auth_error'] = $error->getMessage();
    redirect('login.php', 303);
}
