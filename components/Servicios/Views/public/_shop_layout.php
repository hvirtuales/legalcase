<?php
/**
 * Lienzo del catálogo digital. **Autosuficiente** (contrato §7.3): no carga el
 * CSS ni los iconos del núcleo, todo el estilo va en el `<style>` de abajo y los
 * iconos son SVG en línea (`_icons.php`). Así la vitrina se puede llevar a otra
 * plataforma con solo la carpeta del componente.
 *
 * **Ambiente minimalista en blanco y negro**: el color lo ponen las fotos de los
 * productos, no la interfaz. Por eso aquí NO se inyecta el color de marca de la
 * empresa (a diferencia de la tarjeta digital).
 *
 * @var string     $pageTitle
 * @var array|null $og
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= esc($pageTitle ?? '') ?></title>
    <?php if (!empty($og)): ?>
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?= esc($og['title'] ?? $pageTitle, 'attr') ?>">
        <meta property="og:description" content="<?= esc($og['description'] ?? '', 'attr') ?>">
        <meta property="og:url" content="<?= esc($og['url'] ?? current_url(), 'attr') ?>">
        <?php if (!empty($og['image'])): ?>
            <meta property="og:image" content="<?= esc($og['image'], 'attr') ?>">
        <?php endif; ?>
        <meta name="twitter:card" content="summary_large_image">
        <meta name="description" content="<?= esc($og['description'] ?? '', 'attr') ?>">
    <?php endif; ?>
    <style>
        /* ---------------------------------------------------------------
           Catálogo digital — hoja de estilos completa. Blanco y negro: sin
           framework, sin fuentes que descargar, sin iconos externos.
           --------------------------------------------------------------- */
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --sh-bg: #ffffff;
            --sh-ink: #111111;
            --sh-muted: #6b6b6b;
            --sh-line: #e4e4e4;
            --sh-soft: #f6f6f6;
        }

        html, body { margin: 0; padding: 0; }
        body {
            background: var(--sh-bg); color: var(--sh-ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased; line-height: 1.45; padding-bottom: 84px;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        button { font: inherit; color: inherit; }

        .sh-i { display: inline-flex; }
        .sh-i > svg { width: 1em; height: 1em; display: block; }

        .sh-wrap { max-width: 62rem; margin: 0 auto; padding: 0 16px; }

        /* --------------------- banner: logo + título + buscador -----------
           Solo eso. Quién vende y quién atiende viven en la tarjeta que abre
           el logo: en el banner competían con el nombre del catálogo. */
        .sh-top { border-bottom: 1px solid var(--sh-line); padding-bottom: 14px; }
        .sh-hero { display: flex; align-items: center; gap: 14px; padding: 20px 0 16px; }
        .sh-logo { height: 52px; width: auto; max-width: 120px; object-fit: contain; display: block; }
        .sh-hero-txt { min-width: 0; flex: 1; }
        .sh-title { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -.02em; line-height: 1.1; }
        /* La usa la pantalla de «pedido enviado», donde sí toca firmar quién recibe. */
        .sh-brand { margin: 3px 0 0; font-size: 11px; font-weight: 700; letter-spacing: .16em;
                    text-transform: uppercase; color: var(--sh-muted); }

        /* El logo es el botón que abre la tarjeta de la empresa. Lleva medidas
           propias para que el hueco exista aunque la imagen aún no haya cargado:
           si no, el logo se monta sobre el título mientras llega. */
        .sh-logobtn { flex-shrink: 0; display: flex; align-items: center; justify-content: center;
                    min-width: 52px; height: 52px; padding: 0; border: 0; background: none;
                    cursor: pointer; border-radius: 4px; transition: opacity .15s ease; }
        .sh-logobtn:hover { opacity: .7; }
        .sh-logobtn > .sh-i { font-size: 34px; color: var(--sh-ink); }

        @media (max-width: 639px) { .sh-title { font-size: 20px; } }
        @media (min-width: 640px) { .sh-title { font-size: 30px; } }

        /* Tarjeta de la empresa */
        .sh-about { padding: 30px 26px; text-align: center; }
        .sh-about-logo { height: 64px; width: auto; max-width: 190px; object-fit: contain; margin: 0 auto 16px; }
        .sh-about h3 { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -.01em; }
        .sh-about-legal { margin: 5px 0 0; font-size: 11.5px; color: var(--sh-muted); }
        .sh-about-desc { margin: 10px 0 0; font-size: 13.5px; line-height: 1.6; color: var(--sh-muted); white-space: pre-line; }
        .sh-about-cat { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--sh-line); }
        .sh-about-cat > span { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .16em;
                               text-transform: uppercase; color: var(--sh-muted); }
        .sh-about-cat p { margin: 8px 0 0; font-size: 13.5px; line-height: 1.6; white-space: pre-line; }

        .sh-about-seller { margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--sh-line); }
        .sh-about-seller span { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .16em;
                                text-transform: uppercase; color: var(--sh-muted); }
        .sh-about-seller b { display: block; margin-top: 3px; font-size: 15px; }


        /* «Agregar más datos»: abre los campos opcionales del pedido */
        .sh-more-btn { display: inline-flex; align-items: center; gap: 8px; margin-top: 12px;
                       padding: 9px 14px; border: 1px dashed var(--sh-line); background: none;
                       cursor: pointer; font-size: 12.5px; font-weight: 700; color: var(--sh-muted); }
        .sh-more-btn:hover { border-color: var(--sh-ink); color: var(--sh-ink); }
        .sh-more-btn .sh-i { font-size: 14px; }
        #moreData { margin-top: 14px; }
        #moreData[hidden] { display: none; }

        /* Buscador dentro del banner */
        .sh-search { display: flex; align-items: center; gap: 10px; padding: 11px 14px;
                     border: 1px solid var(--sh-ink); background: #fff; }
        .sh-search input { flex: 1; min-width: 0; border: 0; outline: 0; font: inherit; color: inherit;
                           background: none; -webkit-appearance: none; appearance: none; }
        .sh-search input::-webkit-search-cancel-button { display: none; }
        .sh-search .sh-i { color: var(--sh-muted); }
        .sh-search button { border: 0; background: none; cursor: pointer; color: var(--sh-muted); }
        .sh-search button:hover { color: var(--sh-ink); }

        /* Categorías en carrusel: una sola fila que se arrastra */
        .sh-cats { display: flex; gap: 8px; margin-top: 12px; overflow-x: auto; scrollbar-width: none;
                   scroll-snap-type: x proximity; -webkit-overflow-scrolling: touch; padding-bottom: 2px; }
        .sh-cats::-webkit-scrollbar { display: none; }
        .sh-cats button { flex: 0 0 auto; scroll-snap-align: start; padding: 8px 14px; cursor: pointer;
                          border: 1px solid var(--sh-line); background: #fff; color: var(--sh-muted);
                          font-size: 12px; font-weight: 700; letter-spacing: .04em; white-space: nowrap; }
        .sh-cats button:hover { border-color: var(--sh-ink); color: var(--sh-ink); }
        .sh-cats button.is-on { background: var(--sh-ink); border-color: var(--sh-ink); color: #fff; }
        .sh-card[hidden] { display: none; }

        /* ------------------------------- rejilla ------------------------- */
        /* Móvil: SIEMPRE 2 columnas. De tablet en adelante, las que quepan. */
        .sh-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 22px 14px; padding: 24px 0; }
        @media (min-width: 640px) {
            .sh-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 28px 24px; padding: 30px 0; }
        }
        .sh-card { display: flex; flex-direction: column; }
        .sh-photo { position: relative; aspect-ratio: 1 / 1; background: var(--sh-soft);
                    border: 1px solid var(--sh-line); overflow: hidden; cursor: zoom-in; width: 100%; padding: 0; }
        .sh-photo:hover img { transform: scale(1.03); }
        .sh-photo img { transition: transform .25s ease; }
        .sh-photo img { width: 100%; height: 100%; object-fit: cover; }
        .sh-photo span { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
                         color: #c9c9c9; font-size: 34px; }
        .sh-code { margin-top: 12px; font-size: 10px; letter-spacing: .14em; text-transform: uppercase; color: var(--sh-muted); }
        .sh-name { margin: 3px 0 0; font-size: 15px; font-weight: 700; }
        .sh-desc { margin: 4px 0 0; font-size: 12.5px; color: var(--sh-muted); }
        .sh-price { margin-top: 8px; font-size: 15px; font-weight: 700; }
        .sh-price small { display: block; font-size: 11px; font-weight: 500; color: var(--sh-muted); }

        /* Selector de cantidad: cuadrado, sin adornos. */
        .sh-qty { display: flex; align-items: stretch; margin-top: 10px; border: 1px solid var(--sh-ink); width: max-content; }
        .sh-qty button { width: 34px; background: none; border: 0; cursor: pointer; font-size: 16px; line-height: 1; }
        .sh-qty button:hover { background: var(--sh-ink); color: #fff; }
        .sh-qty input { width: 52px; border: 0; border-left: 1px solid var(--sh-line); border-right: 1px solid var(--sh-line);
                        text-align: center; font: inherit; padding: 7px 0; -moz-appearance: textfield; }
        .sh-qty input::-webkit-outer-spin-button, .sh-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .sh-card.is-picked .sh-photo { border-color: var(--sh-ink); box-shadow: 0 0 0 1px var(--sh-ink); }

        /* ------------------------------- pedido -------------------------- */
        .sh-order { border-top: 1px solid var(--sh-line); padding: 30px 0 40px; }
        .sh-order h2 { margin: 0 0 4px; font-size: 18px; font-weight: 800; letter-spacing: -.01em; }
        .sh-order p.sh-hint { margin: 0 0 18px; font-size: 13px; color: var(--sh-muted); }
        .sh-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .sh-field label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .1em;
                          text-transform: uppercase; color: var(--sh-muted); margin-bottom: 5px; }
        .sh-field input, .sh-field textarea {
            width: 100%; padding: 10px 12px; font: inherit; color: inherit;
            background: #fff; border: 1px solid var(--sh-line); border-radius: 0;
        }
        .sh-field input:focus, .sh-field textarea:focus { outline: 0; border-color: var(--sh-ink); }
        .sh-field.sh-wide { grid-column: 1 / -1; }

        .sh-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                  padding: 13px 22px; border: 1px solid var(--sh-ink); background: var(--sh-ink); color: #fff;
                  font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
                  cursor: pointer; text-decoration: none; }
        .sh-btn:hover { background: #fff; color: var(--sh-ink); }
        .sh-btn[disabled] { opacity: .35; cursor: not-allowed; }
        .sh-btn-ghost { background: #fff; color: var(--sh-ink); }
        .sh-btn-ghost:hover { background: var(--sh-ink); color: #fff; }

        /* ------------------------------- barra fija ---------------------- */
        .sh-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 30;
                  background: rgb(255 255 255 / .96); border-top: 1px solid var(--sh-line);
                  padding-bottom: env(safe-area-inset-bottom); }
        @supports (backdrop-filter: blur(1px)) { .sh-bar { backdrop-filter: blur(10px); } }
        .sh-bar-in { max-width: 62rem; margin: 0 auto; padding: 12px 16px;
                     display: flex; align-items: center; gap: 14px; }
        .sh-bar-sum { font-size: 12px; color: var(--sh-muted); }
        .sh-bar-sum b { display: block; font-size: 16px; color: var(--sh-ink); }
        .sh-bar .sh-btn { margin-left: auto; }

        .sh-alert { border: 1px solid var(--sh-ink); padding: 12px 14px; font-size: 13px; margin: 18px 0; }

        /* ------------------------------- navbar (abajo, tipo app) --------- */
        .sh-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; background: rgb(255 255 255 / .97);
                  border-top: 1px solid var(--sh-line); padding-bottom: env(safe-area-inset-bottom); }
        @supports (backdrop-filter: blur(1px)) { .sh-nav { backdrop-filter: blur(10px); } }
        .sh-nav-in { max-width: 62rem; margin: 0 auto; padding: 8px 16px; display: flex; gap: 6px; }
        .sh-nav button, .sh-nav a {
            flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 3px;
            padding: 8px 4px; background: none; border: 0; cursor: pointer; text-decoration: none; color: inherit;
        }
        .sh-nav .sh-i { font-size: 17px; }
        .sh-nav b { font-size: 9.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
                    text-align: center; line-height: 1.1; }
        .sh-nav button:hover, .sh-nav a:hover { color: #000; text-decoration: underline; }
        .sh-badge { position: absolute; transform: translate(14px, -4px); min-width: 16px; height: 16px;
                    padding: 0 4px; border-radius: 8px; background: var(--sh-ink); color: #fff;
                    font-size: 10px; font-weight: 700; line-height: 16px; text-align: center; }
        .sh-badge[hidden] { display: none; }
        .sh-nav button:active, .sh-nav a:active { transform: scale(.94); }
        @media (min-width: 640px) { .sh-nav b { font-size: 10.5px; } }

        /* ------------------------------- capas --------------------------- */
        .sh-modal { position: fixed; inset: 0; z-index: 60; display: none;
                    background: rgb(0 0 0 / .55); padding: 16px; overflow-y: auto; }
        @supports (backdrop-filter: blur(1px)) { .sh-modal { backdrop-filter: blur(3px); } }
        .sh-modal.is-open { display: flex; align-items: center; justify-content: center; }
        .sh-modal-box { position: relative; width: 100%; max-width: 46rem; background: #fff;
                        border: 1px solid var(--sh-ink); max-height: 92vh; overflow-y: auto; }
        /* En móvil la capa ocupa la pantalla: es un paso del flujo, no un aviso. */
        @media (max-width: 639px) {
            .sh-modal { padding: 0; }
            .sh-modal-box { max-height: 100vh; min-height: 100vh; border: 0; }
            .sh-modal-sm { min-height: 0; }
        }
        .sh-modal-sm { max-width: 24rem; }
        .sh-modal-close { position: absolute; top: 8px; right: 8px; width: 34px; height: 34px; display: flex;
                          align-items: center; justify-content: center; background: #fff; border: 1px solid var(--sh-line);
                          cursor: pointer; font-size: 15px; }
        .sh-modal-close:hover { background: var(--sh-ink); color: #fff; }

        /* Detalle del producto: foto grande + ficha */
        .sh-detail { display: grid; grid-template-columns: 1fr; }
        @media (min-width: 640px) { .sh-detail { grid-template-columns: 1fr 1fr; } }
        .sh-detail-photo { aspect-ratio: 1 / 1; background: var(--sh-soft); border-bottom: 1px solid var(--sh-line); }
        @media (min-width: 640px) { .sh-detail-photo { border-bottom: 0; border-right: 1px solid var(--sh-line); } }
        .sh-detail-photo img { width: 100%; height: 100%; object-fit: cover; }
        .sh-detail-body { padding: 22px; }
        .sh-detail-body h3 { margin: 4px 0 0; font-size: 20px; font-weight: 800; letter-spacing: -.01em; }
        .sh-detail-desc { margin: 12px 0 0; font-size: 13.5px; color: var(--sh-muted); white-space: pre-line; }
        .sh-detail-price { margin-top: 16px; font-size: 20px; font-weight: 800; }
        .sh-detail-price small { display: block; font-size: 12px; font-weight: 500; color: var(--sh-muted); }

        /* Orden de pedido */
        .sh-cart { width: 100%; border-collapse: collapse; font-size: 13px; }
        .sh-cart th { text-align: left; font-size: 10px; letter-spacing: .12em; text-transform: uppercase;
                      color: var(--sh-muted); border-bottom: 1px solid var(--sh-line); padding: 8px 6px; font-weight: 700; }
        .sh-cart td { border-bottom: 1px solid var(--sh-line); padding: 10px 6px; vertical-align: middle; }
        .sh-cart .sh-num { text-align: right; white-space: nowrap; }
        .sh-cart img { width: 44px; height: 44px; object-fit: cover; border: 1px solid var(--sh-line); }
        .sh-cart-foot { position: sticky; bottom: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
                        padding: 14px 0 2px; margin-top: 6px; background: #fff; border-top: 1px solid var(--sh-line); }
        .sh-cart-foot .sh-total { margin-right: auto; font-size: 12px; color: var(--sh-muted); }
        .sh-cart-foot .sh-total b { display: block; font-size: 20px; color: var(--sh-ink); }
        .sh-x { border: 0; background: none; cursor: pointer; color: var(--sh-muted); font-size: 14px; }
        .sh-x:hover { color: var(--sh-ink); }


        /* Carga progresiva: centinela y contador */
        .sh-more { display: flex; align-items: center; justify-content: center; gap: 10px;
                   padding: 22px 0; font-size: 12px; color: var(--sh-muted); cursor: pointer; }
        .sh-more[hidden] { display: none; }
        .sh-spin { width: 16px; height: 16px; border: 2px solid var(--sh-line); border-top-color: var(--sh-ink);
                   border-radius: 50%; animation: sh-turn .7s linear infinite; }
        @keyframes sh-turn { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .sh-spin { animation: none; } }
        .sh-count { margin: 0 0 26px; text-align: center; font-size: 11px; letter-spacing: .1em;
                    text-transform: uppercase; color: var(--sh-muted); }
        .sh-count[hidden] { display: none; }

        /* Pestañas de la capa «Mi pedido» */
        .sh-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--sh-line); margin: 0 0 16px; }
        .sh-tabs button { flex: 1 1 0; padding: 12px 8px; background: none; border: 0; border-bottom: 2px solid transparent;
                          cursor: pointer; font-size: 12px; font-weight: 700; letter-spacing: .08em;
                          text-transform: uppercase; color: var(--sh-muted); }
        .sh-tabs button.is-on { color: var(--sh-ink); border-bottom-color: var(--sh-ink); }
        .sh-pane[hidden] { display: none; }

        .sh-qr { display: block; width: 220px; max-width: 100%; margin: 0 auto; }

        /* ------------------------------- gracias ------------------------- */
        .sh-done { max-width: 34rem; margin: 60px auto; padding: 0 16px; text-align: center; }
        .sh-done .sh-mark { width: 56px; height: 56px; margin: 0 auto 18px; border: 1px solid var(--sh-ink);
                            display: flex; align-items: center; justify-content: center; font-size: 26px; }
        .sh-done h1 { margin: 0; font-size: 22px; font-weight: 800; }
        .sh-done p { color: var(--sh-muted); font-size: 14px; }
        .sh-ref { display: inline-block; margin: 10px 0 22px; padding: 6px 14px; border: 1px solid var(--sh-line);
                  font-size: 13px; font-weight: 700; letter-spacing: .08em; }


        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
        @media print { .sh-bar, .sh-qty, .sh-order { display: none; } body { padding-bottom: 0; } }
    </style>
</head>
<body>
    <?= $this->renderSection('content') ?>
</body>
</html>
