<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); throw new AppError('Método no permitido.', 405); }
verify_csrf();

function input(string $key, int $max = 500): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || text_length($value) > $max) throw new AppError('Revisá los datos del formulario.');
    return trim($value);
}
function password_input(string $key): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || strlen($value) > 72) throw new AppError('La contraseña supera el máximo de 72 bytes.');
    return $value;
}
function api_user(bool $admin = false): array {
    $user = current_user();
    if (!$user) throw new AppError('Iniciá sesión para continuar.', 401);
    if ($admin && $user['rol'] !== 'administrador') throw new AppError('Esta acción es exclusiva del administrador.', 403);
    return $user;
}
function reply(array $data = []): never {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
function product_id(string $field = 'id'): int {
    $id = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) throw new AppError('El producto no es válido.');
    return $id;
}

switch (input('action', 40)) {
    case 'login':
        $email = lower(input('email', 254));
        $password = password_input('password');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') throw new AppError('Completá un correo válido y tu contraseña.');
        $next = safe_next(input('next', 600));
        $auth = sb('POST', 'auth/v1/token?grant_type=password', ['email' => $email, 'password' => $password]);
        store_auth($auth, true);
        reply(['redirect' => url($next)]);

    case 'register':
        $password = password_input('password');
        if ($password !== password_input('confirm_password')) throw new AppError('Las contraseñas no coinciden.');
        if (text_length($password) < 6) throw new AppError('La contraseña debe tener al menos 6 caracteres.');
        $data = [
            'nombre' => input('nombre', 80), 'apellido' => input('apellido', 80),
            'nombre_usuario' => input('nombre_usuario', 30), 'email' => lower(input('email', 254)),
            'direccion' => input('direccion', 200), 'password' => $password,
        ];
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new AppError('Ingresá un correo válido.');
        if (text_length($data['nombre']) < 2 || text_length($data['apellido']) < 2) throw new AppError('Completá tu nombre y apellido.');
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $data['nombre_usuario'])) throw new AppError('El nombre de usuario debe tener entre 3 y 30 letras, números, puntos o guiones.');
        if (text_length($data['direccion']) < 5) throw new AppError('Completá una dirección de al menos 5 caracteres.');
        sb('POST', 'functions/v1/powerbull-registro', $data);
        try {
            $auth = sb('POST', 'auth/v1/token?grant_type=password', ['email' => $data['email'], 'password' => $password]);
            store_auth($auth, true);
        } catch (AppError $error) {
            flash('Tu cuenta se creó correctamente. Ahora podés iniciar sesión.');
            reply(['redirect' => url('login.php')]);
        }
        flash('Tu cuenta está lista. ¡Bienvenido a Powerbull!');
        reply(['redirect' => url('cuenta.php')]);

    case 'logout':
        if (!empty($_SESSION['auth']['access_token'])) {
            try { sb_request('POST', 'auth/v1/logout?scope=local', null, $_SESSION['auth']['access_token']); }
            catch (AppError $error) { error_log('[Powerbull] Cierre remoto no disponible; se cerró la sesión local.'); }
        }
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        reply(['redirect' => url('index.php')]);

    case 'password':
        $user = api_user();
        $creating = empty($_SESSION['has_password']);
        if ($creating && !can_create_password()) throw new AppError('Confirmá tu cuenta con Google desde Mi cuenta antes de crear una contraseña.', 403);
        $new = password_input('password');
        if (text_length($new) < 6) throw new AppError('La nueva contraseña debe tener al menos 6 caracteres.');
        if ($new !== password_input('confirm_password')) throw new AppError('Las contraseñas nuevas no coinciden.');
        if ($creating) {
            $verified = $_SESSION['auth'];
            $payload = ['password' => $new];
        } else {
            $old = password_input('current_password');
            if ($old === '') throw new AppError('Ingresá tu contraseña actual.');
            if ($old === $new) throw new AppError('Elegí una contraseña diferente de la actual.');
            try { $verified = sb('POST', 'auth/v1/token?grant_type=password', ['email' => $user['Gmail'], 'password' => $old]); }
            catch (DatabaseError $error) {
                if ($error->status === 401) throw new AppError('La contraseña actual es incorrecta.');
                throw $error;
            }
            if (($verified['user']['id'] ?? '') !== $user['auth_id']) throw new AppError('No pudimos validar tu cuenta.', 403);
            $payload = ['password' => $new, 'current_password' => $old];
        }
        $verified['user'] = sb_request('PUT', 'auth/v1/user', $payload, $verified['access_token'])['data'];
        store_auth($verified, true, 'password');
        flash($creating ? 'Tu contraseña está lista. Podés ingresar con Google o con tu correo y esta contraseña.' : 'Tu contraseña se cambió correctamente.');
        reply(['redirect' => url('cuenta.php')]);

    case 'cart_add':
    case 'cart_update':
        api_user();
        $action = input('action', 40);
        $id = product_id();
        $quantity = filter_var($_POST['cantidad'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => $action === 'cart_update' ? 0 : 1, 'max_range' => 99]]);
        if ($quantity === false) throw new AppError('La cantidad debe estar entre 1 y 99.');
        if ($quantity === 0 && $action === 'cart_update') {
            unset($_SESSION['cart'][$id]);
            $_SESSION['checkout_id'] = uuid();
            reply(['message' => 'Producto eliminado del carrito.', 'count' => cart_count(), 'reload' => true]);
        }
        $product = get_product($id);
        if (!$product) throw new AppError('Este producto ya no está disponible.', 404);
        $final = $action === 'cart_add' ? (int)($_SESSION['cart'][$id] ?? 0) + $quantity : $quantity;
        if ($final > 99 || $final > (int)$product['stock']) throw new AppError('No hay stock suficiente. Quedan ' . (int)$product['stock'] . ' unidades.');
        if (!isset($_SESSION['cart'][$id]) && count($_SESSION['cart'] ?? []) >= 40) throw new AppError('El carrito admite hasta 40 productos diferentes.');
        $_SESSION['cart'][$id] = $final;
        $_SESSION['checkout_id'] = uuid();
        reply(['message' => $action === 'cart_add' ? 'Agregaste ' . $product['nombre'] . ' al carrito.' : 'Carrito actualizado.', 'count' => cart_count(), 'reload' => $action === 'cart_update']);

    case 'checkout':
        api_user();
        $request = input('solicitud', 36);
        if (!preg_match('/^[0-9a-f-]{36}$/i', $request)) throw new AppError('La solicitud no es válida. Actualizá la página.');
        if (($_SESSION['last_checkout']['id'] ?? null) === $request) reply(['purchase' => $_SESSION['last_checkout'], 'count' => 0]);
        if (!hash_equals($_SESSION['checkout_id'] ?? '', $request)) throw new AppError('Tu carrito cambió. Actualizá la página antes de confirmar.', 409);
        $cart = $_SESSION['cart'] ?? [];
        if (!$cart) throw new AppError('El carrito está vacío.');
        $items = [];
        foreach ($cart as $id => $quantity) $items[] = ['id' => (int)$id, 'cantidad' => (int)$quantity];
        $result = sb('POST', 'rest/v1/rpc/powerbull_comprar', ['p_items' => $items, 'p_solicitud' => $request], true);
        $_SESSION['cart'] = [];
        $_SESSION['checkout_id'] = uuid();
        $_SESSION['last_checkout'] = $result;
        $_SESSION['profile_checked_at'] = 0;
        reply(['purchase' => $result, 'count' => 0]);

    case 'product_save':
        api_user(true);
        $id = input('id', 20) === '' ? null : product_id();
        $product = $id ? get_product($id) : null;
        if ($id && !$product) throw new AppError('El producto ya no existe.', 404);
        $data = ['nombre' => input('nombre', 120), 'marca' => input('marca', 60), 'categoria' => input('categoria', 60),
            'descripcion' => input('descripcion', 600), 'detalle' => input('detalle', 4000), 'sku' => strtoupper(input('sku', 40))];
        if (text_length($data['nombre']) < 3 || text_length($data['marca']) < 1 || text_length($data['categoria']) < 2 || text_length($data['descripcion']) < 10) throw new AppError('Completá nombre, marca, categoría y descripción.');
        if (!preg_match('/^[A-Z0-9][A-Z0-9_.-]{2,39}$/', $data['sku'])) throw new AppError('Revisá el código del producto.');
        $priceText = input('precio', 30);
        $price = filter_var($priceText, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99999]]);
        if ($price === false || !is_finite($price) || $price <= 0 || $price > 999999999 || $stock === false) throw new AppError('Revisá el precio y el stock.');
        $data['precio'] = round($price, 2);
        $data['stock'] = $stock;
        $data['destacado'] = input('destacado', 1) === '1';
        require_once __DIR__ . '/includes/uploads.php';
        $uploaded = save_product_upload();
        $image = $uploaded ?? input('imagen_url', 1500);
        if (!valid_product_image($image)) throw new AppError('Subí una imagen o ingresá una URL HTTPS válida.');
        $data['imagen_url'] = $image;
        $data['imagen_alt'] = $data['nombre'];
        try {
            if ($id) {
                $version = input('version', 80);
                if ($version === '' || $version !== $product['updated_at']) throw new AppError('Este artículo cambió. Actualizá la página antes de guardar.', 409);
                $rows = sb('PATCH', 'rest/v1/productos?id=eq.' . $id . '&updated_at=eq.' . rawurlencode($version), $data, true, ['Prefer: return=representation']);
                if (!$rows) throw new AppError('Este artículo cambió. Actualizá la página antes de guardar.', 409);
            } else {
                sb('POST', 'rest/v1/productos', $data, true, ['Prefer: return=representation']);
            }
        } catch (Throwable $error) {
            if ($uploaded) @unlink(APP_ROOT . '/' . $uploaded);
            throw $error;
        }
        flash($id ? 'Artículo actualizado.' : 'Artículo agregado.');
        reply(['redirect' => url('admin.php')]);

    case 'product_delete':
        api_user(true);
        $id = product_id();
        sb('DELETE', 'rest/v1/productos?id=eq.' . $id, null, true);
        flash('Artículo eliminado.');
        reply(['redirect' => url('admin.php')]);

    default:
        throw new AppError('Acción no reconocida.', 400);
}
