<?php

/**
 * Lienzo del editor de mapas, a pantalla completa.
 *
 * No usa `layouts/admin` por la misma razón que los editores de Páginas,
 * Formularios y Mailer: con la barra lateral y el encabezado del panel, al
 * plano le queda una tira, y aquí el ancho ES el material de trabajo —un mapa
 * de centro comercial se juzga viéndolo entero.
 *
 * Sigue siendo pantalla INTERNA, así que usa el sistema de diseño del núcleo:
 * la autosuficiencia del §7.3 es para las vistas públicas.
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
    <link rel="stylesheet" href="<?= base_url('mapa/recurso/editor.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/editor.css') ?>">

    <!-- Los estilos del MAPA, los mismos que la página pública. -->
    <?= view('Components\Mapas\Views\_map_styles') ?>

    <!-- El mismo guion que el layout del núcleo: fija el tema antes de pintar
         para que no haya un fogonazo blanco al entrar en modo oscuro. -->
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('theme'); } catch (e) {}
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>
</head>

<body class="map-fs bg-canvas text-ink">
    <?= $this->renderSection('content') ?>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
