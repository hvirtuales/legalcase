<?php
/**
 * Lienzo del editor, a pantalla completa.
 *
 * El editor NO usa `layouts/admin` como el resto de las pantallas del
 * componente: la barra lateral y el encabezado del panel se comen unos 20 rem
 * de ancho, y aquí el ancho es el material de trabajo. Con tres columnas
 * (campos, lienzo, inspector) lo que queda para el formulario es una tira. Es la
 * misma decisión que toma el editor de Páginas.
 *
 * Sigue siendo pantalla INTERNA, así que usa el sistema de diseño del núcleo: la
 * autosuficiencia del contrato 7.3 es para las vistas públicas. Lo único que no
 * se carga es `intl-tel-input`, que aquí no hace falta.
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

    <!-- Mismo guion que el layout del núcleo: fija el tema antes de pintar
         para que no haya un fogonazo blanco al entrar en modo oscuro. -->
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('theme'); } catch (e) {}
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    <!-- La MISMA hoja que el formulario publicado: lo que se ve al maquetar es
         lo que se publica. Sus reglas de `body` las repone editor.css, que
         carga después, para no teñir el panel entero. -->
    <?= view('Components\Formularios\Views\public\_styles', ['brand' => '#2563eb', 'ink' => '#ffffff']) ?>
    <link rel="stylesheet" href="<?= base_url('formulario/recurso/editor.css') ?>">

    <?= $this->renderSection('styles') ?>
</head>

<body class="frm-fs bg-canvas text-ink">
    <?= $this->renderSection('content') ?>

    <?php if (session('success') || session('error')): ?>
        <div id="frmFlash" class="frm-flash <?= session('error') ? 'alert-danger' : 'alert-success' ?>">
            <i class="icon icon-<?= session('error') ? 'exclamation-circle' : 'check-circle' ?>"></i>
            <span><?= esc(session('error') ?: session('success')) ?></span>
        </div>
        <script>
            setTimeout(function () {
                var f = document.getElementById('frmFlash');
                if (f) { f.classList.add('is-gone'); }
            }, 4500);
        </script>
    <?php endif; ?>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
