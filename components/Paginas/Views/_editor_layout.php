<?php
/**
 * Lienzo del editor a pantalla completa.
 *
 * El editor **no** usa `layouts/admin` como el resto de las pantallas del
 * componente: la barra lateral y el encabezado del panel se comen unos 20 rem
 * de ancho, y aquí el ancho es el material de trabajo — con tres columnas
 * (paleta, lienzo, inspector) lo que queda para la página es una tira. Es la
 * misma decisión que toma Gutenberg, que ocupa la pantalla entera y deja una
 * salida clara arriba a la izquierda.
 *
 * Sigue siendo una pantalla **interna**, así que usa el sistema de diseño del
 * núcleo (Tailwind compilado, iconos, Inter y el `theme.css` dinámico): la regla
 * de autosuficiencia del contrato §7.3 es para las vistas públicas. Lo único que
 * no se carga es `intl-tel-input`, porque aquí no hay ningún campo de teléfono.
 *
 * @var string $title
 */
helper('media');
$settings = new \App\Models\AppSettingModel();
$favicon  = $settings->getSetting('favicon');
?>
<!DOCTYPE html>
<html lang="<?= session()->get('language') ?? 'es' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? '') ?></title>

    <?php if ($favicon): ?>
        <link rel="icon" href="<?= media_url($favicon) ?>" type="image/x-icon">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= base_url('css/fonts.css') ?>?v=<?= filemtime(FCPATH . 'css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/icons.css') ?>?v=<?= filemtime(FCPATH . 'css/icons.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>?v=<?= filemtime(FCPATH . 'css/tailwind.css') ?>">
    <link rel="stylesheet" href="<?= base_url('theme.css') ?>">

    <!-- Mismo guion que el layout del núcleo: fija el tema antes de pintar para
         que no haya un fogonazo blanco al entrar en modo oscuro. -->
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('theme'); } catch (e) {}
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    <?= $this->renderSection('styles') ?>
</head>

<body class="pag-fs bg-canvas text-ink">
    <?= $this->renderSection('content') ?>

    <?php if (session('success') || session('error')): ?>
        <!-- Aviso de la operación que trajo hasta aquí (crear la página, usarla
             desde una plantilla…). Se va solo: en pantalla completa un aviso
             fijo estorba, y el mensaje ya no importa una vez leído. -->
        <div id="pagFlash" class="pag-flash <?= session('error') ? 'alert-danger' : 'alert-success' ?>">
            <i class="icon icon-<?= session('error') ? 'exclamation-circle' : 'check-circle' ?>"></i>
            <span><?= esc(session('error') ?: session('success')) ?></span>
        </div>
        <script>
            setTimeout(function () {
                var f = document.getElementById('pagFlash');
                if (f) { f.classList.add('is-gone'); }
            }, 4500);
        </script>
    <?php endif; ?>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
