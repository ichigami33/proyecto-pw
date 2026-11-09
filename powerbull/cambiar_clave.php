<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_user();
$creating = empty($_SESSION['has_password']);
$needsGoogle = $creating && !can_create_password();
$title = $creating ? 'Crear contraseña' : 'Cambiar contraseña';
$error = auth_error();
page_header($title, 'cuenta');
?>
<main class="container narrow-page" id="contenido">
    <a class="back-link" href="<?=h(url('cuenta.php'))?>">← Volver a mi cuenta</a>
    <section class="auth-card" aria-labelledby="password-title">
        <h1 id="password-title"><?=h($title)?></h1>
        <?php if ($error): ?><p class="form-message auth-error" role="alert"><?=h($error)?></p><?php endif; ?>
        <?php if ($needsGoogle): ?>
            <p class="password-intro">Confirmá tu cuenta con Google para crear una contraseña de Powerbull.</p>
            <?php google_signin_form('cambiar_clave.php', true); ?>
        <?php else: ?>
            <?php if ($creating): ?><p class="password-intro">Elegí una contraseña para ingresar también con tu correo. Vas a seguir pudiendo usar Google.</p><?php endif; ?>
            <form data-api="password" method="post" action="<?=h(url('api.php'))?>">
                <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                <input type="hidden" name="action" value="password">
                <?php if (!$creating): ?>
                <div class="field">
                    <label for="current_password">Contraseña actual</label>
                    <div class="password-field">
                        <input id="current_password" type="password" name="current_password" maxlength="72" autocomplete="current-password" required>
                        <button type="button" class="icon-button" data-password-toggle="current_password" aria-label="Mostrar contraseña" aria-pressed="false"><?=icon('eye')?></button>
                    </div>
                </div>
                <?php endif; ?>
                <div class="field">
                    <label for="password">Nueva contraseña</label>
                    <div class="password-field">
                        <input id="password" type="password" name="password" minlength="6" maxlength="72" autocomplete="new-password" required>
                        <button type="button" class="icon-button" data-password-toggle="password" aria-label="Mostrar contraseña" aria-pressed="false"><?=icon('eye')?></button>
                    </div>
                    <small>Mínimo 6 caracteres.</small>
                </div>
                <div class="field">
                    <label for="confirm_password">Repetir contraseña</label>
                    <input id="confirm_password" type="password" name="confirm_password" minlength="6" maxlength="72" autocomplete="new-password" required>
                </div>
                <p data-form-message class="form-message" role="alert" hidden></p>
                <button class="button button-full" type="submit"><?=icon('check')?>Guardar contraseña</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php page_footer(); ?>
