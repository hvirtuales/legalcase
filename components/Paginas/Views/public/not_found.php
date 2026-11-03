<?php
/**
 * 404 de la página pública. Mismo lienzo autosuficiente que la página (§7.3):
 * estilos en línea, tipografía del sistema, cero CDN.
 *
 * No dice si la página no existe, está en borrador o su plan ya no incluye el
 * servicio: desde fuera son el mismo «aquí no hay nada», y distinguirlas
 * convertiría esta pantalla en una forma de sondear qué tiene contratado cada
 * empresa.
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc(lang('Pag.notFoundTitle')) ?></title>
    <style>
        /* Inter, como el resto de la plataforma; si no resuelve (la carpeta del
           componente movida a otro sitio), entra la pila del sistema y la página
           se ve igual de bien. */
        @font-face {
            font-family: 'Inter'; font-style: normal; font-weight: 400; font-display: swap;
            src: url('<?= base_url('fonts/inter/inter-400.woff2') ?>') format('woff2');
        }
        @font-face {
            font-family: 'Inter'; font-style: normal; font-weight: 700; font-display: swap;
            src: url('<?= base_url('fonts/inter/inter-700.woff2') ?>') format('woff2');
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; }
        body {
            display: flex; align-items: center; justify-content: center;
            min-height: 100%; padding: 24px;
            background: #ffffff; color: #16181d;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased; line-height: 1.6;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #14161a; color: #e9eaee; }
            .nf-code { color: #2b2f37; }
            .nf-text { color: #a0a5b1; }
        }
        .nf { text-align: center; max-width: 30rem; }
        .nf-code { font-size: clamp(4rem, 18vw, 7rem); font-weight: 800; line-height: 1; letter-spacing: -.04em; color: #e3e5ea; margin: 0; }
        .nf-title { margin: .2em 0 .4em; font-size: 1.5rem; font-weight: 700; letter-spacing: -.01em; }
        .nf-text { margin: 0; color: #5f6470; }
    </style>
</head>
<body>
    <div class="nf">
        <p class="nf-code">404</p>
        <h1 class="nf-title"><?= esc(lang('Pag.notFoundTitle')) ?></h1>
        <p class="nf-text"><?= esc(lang('Pag.notFoundText')) ?></p>
    </div>
</body>
</html>
