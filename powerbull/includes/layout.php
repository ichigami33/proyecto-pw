<?php
require_once __DIR__.'/bootstrap.php';
function icon(string $name, string $class = ''): string {
    $paths = [
        'cart'=>'<path d="M3 3h2l2.4 11.4a2 2 0 0 0 2 1.6H18a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'arrow'=>'<path d="M4 12h15m-6-6 6 6-6 6"/>',
        'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'edit'=>'<path d="m16 3 5 5-12 12-6 1 1-6Z"/><path d="m14 5 5 5"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'minus'=>'<path d="M5 12h14"/>',
        'trash'=>'<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
        'close'=>'<path d="m6 6 12 12M6 18 18 6"/>',
        'lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>',
        'logout'=>'<path d="M9 4H4v16h5m5-13 5 5-5 5m-6-5h11"/>',
        'box'=>'<path d="m3 7 9-4 9 4v10l-9 4-9-4Zm0 0 9 4 9-4M12 11v10M7 5l10 4"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'chevron'=>'<path d="m8 4 8 8-8 8"/>',
        'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'upload'=>'<path d="M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5"/>',
        'pause'=>'<path d="M9 5v14M15 5v14"/>',
        'play'=>'<path d="m8 5 11 7-11 7Z"/>',
    ];
    return '<svg class="icon '.h($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['box']).'</svg>';
}
function page_header(string $title = 'Productos', string $active = ''): void {
    $user=current_user(); $css=filemtime(APP_ROOT.'/assets/css/styles.css');
?>
<!doctype html><html lang="es-AR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Powerbull. Productos tecnológicos, audio y accesorios.">
<meta name="csrf-token" content="<?=h($_SESSION['csrf'])?>"><meta name="app-base" content="<?=h(BASE_URL)?>">
<title><?=h($title)?> | Powerbull</title>
<link rel="icon" href="<?=h(url('assets/images/favicon.svg'))?>" type="image/svg+xml">
<link rel="stylesheet" href="<?=h(url('assets/css/styles.css?v='.$css))?>">
<script src="<?=h(url('assets/js/app.js?v='.filemtime(APP_ROOT.'/assets/js/app.js')))?>" defer></script>
</head><body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="site-header"><div class="container header-inner">
<a class="brand" href="<?=h(url())?>" aria-label="Powerbull, inicio"><span class="brand-mark">P<span>B</span></span><span class="brand-type">Powerbull</span></a>
<div class="header-actions">
<?php if($user):?><a class="account-link <?=$active==='cuenta'?'is-active':''?>" href="<?=h(url('cuenta.php'))?>"><?=icon('user')?><span><small>Mi cuenta</small><strong><?=h($user['Nombre'])?></strong></span></a><a class="cart-link <?=$active==='carrito'?'is-active':''?>" href="<?=h(url('carrito.php'))?>" aria-label="Carrito: <?=cart_count()?> unidades"><?=icon('cart')?><span class="cart-count" data-cart-count><?=cart_count()?></span></a>
<?php else:?><a class="login-link" href="<?=h(url('login.php'))?>"><?=icon('user')?><span>Iniciar sesión</span></a><?php endif;?>
</div></div>
<nav class="main-nav" aria-label="Navegación principal"><div class="container"><a class="<?= $active==='catalogo'?'is-active':''?>" href="<?=h(url('index.php#catalogo'))?>">Productos</a><a href="<?=h(url('index.php#nosotros'))?>">Sobre nosotros</a><?php if($user):?><a class="<?=$active==='pedidos'?'is-active':''?>" href="<?=h(url('pedidos.php'))?>">Mis pedidos</a><?php endif;?></div></nav>
</header>
<?php if($message=flash()):?><div class="container"><p class="notice notice-success" role="status"><?=icon('check')?><?=h($message)?></p></div><?php endif;
}
function page_footer(): void {
    $user=current_user();
?>
<footer class="site-footer"><div class="container footer-main"><span class="footer-brand">Powerbull</span><nav aria-label="Enlaces del pie"><a href="<?=h(url('index.php#catalogo'))?>">Productos</a><a href="<?=h(url('index.php#nosotros'))?>">Sobre nosotros</a><a href="<?=h(url($user?'cuenta.php':'login.php'))?>"><?=$user?'Mi cuenta':'Iniciar sesión'?></a></nav><span class="footer-copyright">© <?=date('Y')?> Powerbull</span></div></footer>
<?php if($user && $user['rol']==='administrador'):?>
<div class="admin-fab"><div class="admin-fab-menu" id="admin-menu" hidden><a href="<?=h(url('admin.php?modo=agregar'))?>"><?=icon('plus')?>Agregar artículo</a><a href="<?=h(url('admin.php?modo=modificar'))?>"><?=icon('edit')?>Modificar artículo</a><a href="<?=h(url('admin.php?modo=eliminar'))?>"><?=icon('trash')?>Eliminar artículo</a></div><button class="fab-button" type="button" aria-label="Administrar productos" aria-expanded="false" aria-controls="admin-menu" data-admin-toggle><?=icon('edit')?></button></div>
<?php endif;?>
<div class="toast-region" aria-live="polite" aria-atomic="true" id="toast-region"></div>
<dialog id="confirm-dialog" class="modal" aria-labelledby="delete-title"><button type="button" class="icon-button modal-close" data-dialog-close aria-label="Cerrar"><?=icon('close')?></button><span class="modal-symbol danger"><?=icon('trash')?></span><h2 id="delete-title">¿Eliminar artículo?</h2><p data-confirm-text>Se quitará del catálogo. El historial de compras se conserva.</p><p class="form-message" data-confirm-error role="alert" hidden></p><div class="modal-actions"><button type="button" class="button button-light" data-dialog-close>Cancelar</button><button type="button" class="button button-danger" data-confirm-delete>Eliminar</button></div></dialog>
</body></html>
<?php }
function product_card(array $p): void {
    $user=current_user();$available=(int)$p['stock']>0;
?>
<article class="product-card"><a class="product-photo" href="<?=h(url('producto.php?id='.$p['id']))?>"><img src="<?=h(image_url($p['imagen_url']))?>" alt="<?=h($p['imagen_alt']?:$p['nombre'])?>" loading="lazy" width="400" height="330" data-fallback="<?=h(url('assets/images/sin-imagen.svg'))?>"><?php if(!$available):?><span class="image-label">Sin stock</span><?php endif;?></a><div class="product-content"><div class="product-category"><span><?=h($p['marca'])?></span><span><?=h($p['categoria'])?></span></div><h3><a href="<?=h(url('producto.php?id='.$p['id']))?>"><?=h($p['nombre'])?></a></h3><p class="product-description"><?=h($p['descripcion'])?></p><div class="product-price"><?=money($p['precio'])?></div><div class="product-bottom"><span class="stock-label <?=$available?'':'no-stock'?>"><i></i><?=$available?((int)$p['stock'].' disponibles'):'Sin stock'?></span><?php if($user):?><button type="button" class="button button-small" data-add-cart="<?=(int)$p['id']?>" <?=$available?'':'disabled'?>><?=icon('plus')?>Agregar</button><?php else:?><a class="button button-small button-light" href="<?=h(url('login.php?next='.rawurlencode('producto.php?id='.$p['id'])))?>">Iniciar sesión</a><?php endif;?></div></div></article>
<?php }
function google_signin_form(string $next = 'cuenta.php', bool $reauth = false): void {
?>
<form class="google-signin-form" method="post" action="<?=h(url('google.php'))?>" data-google-form data-google-label-text="<?=h($reauth ? 'Confirmar con Google' : 'Continuar con Google')?>">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
    <input type="hidden" name="next" value="<?=h(safe_next($next))?>">
    <?php if ($reauth): ?><input type="hidden" name="reauth" value="1"><?php endif; ?>
    <button class="google-signin-button" type="submit">
        <svg class="google-signin-logo" viewBox="0 0 48 48" width="20" height="20" aria-hidden="true" focusable="false">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6C44.4 38.02 46.98 31.87 46.98 24.55Z"/>
            <path fill="#FBBC05" d="M10.53 28.59A14.41 14.41 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.87 23.87 0 0 0 0 24c0 3.87.93 7.53 2.56 10.78l7.97-6.19Z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.15 1.45-4.92 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z"/>
        </svg>
        <span data-google-label><?=$reauth ? 'Confirmar con Google' : 'Continuar con Google'?></span>
    </button>
</form>
<?php
}
