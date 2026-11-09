<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
if (current_user()) redirect('cuenta.php');
$next = safe_next($_GET['next'] ?? 'cuenta.php');
$error = auth_error();
page_header('Iniciar sesión');
?>
<main class="container auth-page" id="contenido">
    <section class="auth-card" aria-labelledby="login-title">
        <h1 id="login-title">Iniciar sesión</h1>
        <?php if ($error): ?><p class="form-message auth-error" role="alert"><?=h($error)?></p><?php endif; ?>
        <?php google_signin_form($next); ?>
        <div class="auth-divider"><span>o con tu correo</span></div>
        <form data-api="login" method="post" action="<?=h(url('api.php'))?>">
            <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
            <input type="hidden" name="next" value="<?=h($next)?>">
            <input type="hidden" name="action" value="login">
            <div class="field">
                <label for="email">Correo electrónico</label>
                <input id="email" type="email" name="email" maxlength="254" autocomplete="email" placeholder="tu@email.com" required>
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <div class="password-field">
                    <input id="password" type="password" name="password" maxlength="72" autocomplete="current-password" required>
                    <button type="button" class="icon-button" data-password-toggle="password" aria-label="Mostrar contraseña" aria-pressed="false"><?=icon('eye')?></button>
                </div>
            </div>
            <p class="form-message" data-form-message role="alert" hidden></p>
            <button type="submit" class="button button-full">Iniciar sesión</button>
        </form>
        <p class="auth-bottom"><a href="<?=h(url('registro.php'))?>">Crear cuenta</a></p>
    </section>
</main>
<?php page_footer(); ?>
