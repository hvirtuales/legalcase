<!DOCTYPE html>
<html lang="<?= session()->get('language') ?? 'es' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Sistema de Autenticación') ?></title>

    <!-- Favicon -->
    <?php
    helper('media');
    $settingModel = new \App\Models\AppSettingModel();
    $favicon = $settingModel->getSetting('favicon');
    if ($favicon): ?>
        <link rel="icon" href="<?= media_url($favicon) ?>" type="image/x-icon">
    <?php endif; ?>

    <!-- Inter (auto-hospedada) -->
    <link rel="stylesheet" href="<?= base_url('css/fonts.css') ?>?v=<?= filemtime(FCPATH . 'css/fonts.css') ?>">

    <!-- Iconos SVG Lucide (auto-hospedados, vía CSS mask) -->
    <link rel="stylesheet" href="<?= base_url('css/icons.css') ?>?v=<?= filemtime(FCPATH . 'css/icons.css') ?>">

    <!-- Tailwind (compilado) -->
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>?v=<?= filemtime(FCPATH . 'css/tailwind.css') ?>">

    <!-- Dynamic Theme Colors -->
    <link rel="stylesheet" href="<?= base_url('theme.css') ?>">

    <?= $this->renderSection('styles') ?>
</head>

<body class="bg-canvas text-ink">
    <?= $this->renderSection('content') ?>

    <?= $this->renderSection('scripts') ?>
</body>

</html>
