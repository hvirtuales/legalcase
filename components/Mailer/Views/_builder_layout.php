<?php

/**
 * Lienzo del constructor de correos, a PANTALLA COMPLETA.
 *
 * Las pantallas que montan un correo —el asistente de campaña y el formulario de
 * plantilla— no usan `layouts/admin`: la barra lateral y el encabezado del panel
 * se comen unos 20 rem, y con tres columnas (elementos, lienzo, propiedades) lo
 * que le queda al correo es una tira. Aquí el ancho es el material de trabajo.
 * Es la misma decisión que toman los editores de Páginas y de Formularios.
 *
 * Sigue siendo pantalla INTERNA, así que usa el sistema de diseño del núcleo: la
 * autosuficiencia del §7.3 es para las vistas públicas. Lo único que no carga es
 * `intl-tel-input`, que aquí no hace falta.
 *
 * @var string $title
 */
helper('media');
$favicon = (new \App\Models\AppSettingModel())->getSetting('favicon');
?>
<!DOCTYPE html>
<html lang="<?= esc(session()->get('language') ?? 'es', 'attr') ?>">

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
    <link rel="stylesheet" href="<?= base_url('mailer-publico/recurso/builder.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/builder.css') ?>">

    <!-- El mismo guion que el layout del núcleo: fija el tema antes de pintar,
         para que no haya un fogonazo blanco al entrar en modo oscuro. -->
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

<body class="ml-fs bg-canvas text-ink">
    <?= $this->renderSection('content') ?>

    <?php if (session('success') || session('error')): ?>
        <div id="mlFlash" class="ml-flash <?= session('error') ? 'alert-danger' : 'alert-success' ?>">
            <i class="icon icon-<?= session('error') ? 'exclamation-circle' : 'check-circle' ?>"></i>
            <span><?= esc(session('error') ?: session('success')) ?></span>
        </div>
        <script>
            setTimeout(function () {
                var f = document.getElementById('mlFlash');
                if (f) { f.classList.add('is-gone'); }
            }, 4500);
        </script>
    <?php endif; ?>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
