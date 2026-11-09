<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

page_header('Productos', 'catalogo');
?>
<main id="contenido">
    <section class="catalog-template container" id="catalogo" aria-labelledby="catalog-title">
        <h1 id="catalog-title">Productos</h1>

        <div class="article-grid">
            <?php for ($i = 0; $i < 8; $i++): ?>
            <article class="article-template">
                <div class="article-placeholder">
                    <svg viewBox="0 0 40 32" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="1" y="1" width="38" height="30" rx="1" />
                        <circle cx="12" cy="10" r="3" />
                        <path d="m2 26 10-9 7 6 8-11 12 15" />
                    </svg>
                    <span>Imagen del artículo</span>
                </div>
                <h2>Nombre del artículo</h2>
            </article>
            <?php endfor; ?>
        </div>
    </section>

    <section class="about-section container" id="nosotros" aria-labelledby="about-title">
        <h2 id="about-title">Sobre nosotros</h2>
        <p>Powerbull · Venta de productos tecnológicos y accesorios.</p>
    </section>
</main>
<?php page_footer(); ?>
