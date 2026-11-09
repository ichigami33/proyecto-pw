<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/layout.php';
if(current_user())redirect('cuenta.php');
$error = auth_error();
page_header('Crear cuenta');
?>
<main class="container auth-page register-page" id="contenido">
    <section class="auth-card" aria-labelledby="register-title">
        <h1 id="register-title">Crear cuenta</h1>
        <?php if ($error): ?><p class="form-message auth-error" role="alert"><?=h($error)?></p><?php endif; ?>
        <?php google_signin_form('cuenta.php'); ?>
        <div class="auth-divider"><span>o con tu correo</span></div>
        <form data-api="register" method="post" action="<?=h(url('api.php'))?>">
            <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
            <input type="hidden" name="action" value="register">
            <div class="form-grid">
                <div class="field">
                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" minlength="2" maxlength="80" autocomplete="given-name" required>
                </div>
                <div class="field">
                    <label for="apellido">Apellido</label>
                    <input id="apellido" name="apellido" minlength="2" maxlength="80" autocomplete="family-name" required>
                </div>
            </div>
            <div class="field">
                <label for="nombre_usuario">Nombre de usuario</label>
                <input id="nombre_usuario" name="nombre_usuario" minlength="3" maxlength="30" pattern="[a-zA-Z0-9_.\-]{3,30}" autocomplete="username" required>
                <small>De 3 a 30 letras, números, puntos o guiones.</small>
            </div>
            <div class="field">
                <label for="email">Correo electrónico</label>
                <input id="email" type="email" name="email" maxlength="254" autocomplete="email" placeholder="tu@email.com" required>
            </div>
            <div class="field">
                <label for="direccion">Dirección</label>
                <input id="direccion" name="direccion" minlength="5" maxlength="200" autocomplete="street-address" placeholder="Calle, número y localidad" required>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="password">Contraseña</label>
                    <div class="password-field">
                        <input id="password" type="password" name="password" minlength="6" maxlength="72" autocomplete="new-password" aria-describedby="password-help" required>
                        <button type="button" class="icon-button" data-password-toggle="password" aria-label="Mostrar contraseña" aria-pressed="false"><?=icon('eye')?></button>
                    </div>
                </div>
                <div class="field">
                    <label for="confirm_password">Repetir contraseña</label>
                    <input id="confirm_password" type="password" name="confirm_password" minlength="6" maxlength="72" autocomplete="new-password" required>
                </div>
            </div>
            <p class="field-help" id="password-help">Mínimo 6 caracteres.</p>
            <p class="form-message" data-form-message role="alert" hidden></p>
            <button type="submit" class="button button-full">Crear cuenta</button>
        </form>
        <p class="auth-bottom"><a href="<?=h(url('login.php'))?>">Iniciar sesión</a></p>
    </section>
</main>
<?php page_footer(); ?>
