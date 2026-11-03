<?php
/**
 * Lienzo de las páginas que ve el CLIENTE. Deliberadamente neutro: el membrete y
 * la identidad de la empresa van DENTRO del documento (como en una factura), no
 * en una barra superior, para que lo que se lleva la atención sea el papel.
 *
 * Solo CSS propio del núcleo (Tailwind compilado, iconos y tipografía
 * auto-hospedados): política de cero CDN.
 *
 * @var string     $pageTitle
 * @var array|null $company   se usa para el color de marca y el pie
 */
$company = $company ?? null;

// El DOCUMENTO es minimalista en blanco y negro: no se inyecta el color de marca
// de la empresa (su identidad la aporta el logo). Los controles de la página
// siguen el tema del sistema.
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($pageTitle ?? 'Cotización') ?></title>
    <?php if (!empty($og)): ?>
        <?php /* Previsualización del enlace al compartirlo (WhatsApp, Telegram, redes). */ ?>
        <meta property="og:type" content="profile">
        <meta property="og:title" content="<?= esc($og['title'] ?? $pageTitle, 'attr') ?>">
        <meta property="og:description" content="<?= esc($og['description'] ?? '', 'attr') ?>">
        <meta property="og:url" content="<?= esc($og['url'] ?? current_url(), 'attr') ?>">
        <?php if (!empty($og['image'])): ?>
            <meta property="og:image" content="<?= esc($og['image'], 'attr') ?>">
        <?php endif; ?>
        <meta name="twitter:card" content="<?= empty($og['image']) ? 'summary' : 'summary_large_image' ?>">
        <meta name="description" content="<?= esc($og['description'] ?? '', 'attr') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= base_url('css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/icons.css') ?>">
    <style>
        /* Impresión: el documento a página completa, sin cromo de la aplicación. */
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .card { box-shadow: none !important; border: 0 !important; }
            @page { margin: 14mm; }
        }
    </style>
</head>
<body class="bg-body text-ink antialiased">
    <div class="mx-auto max-w-4xl px-4 py-6 md:py-10">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success no-print"><i class="icon icon-check-circle"></i><span><?= esc(session()->getFlashdata('success')) ?></span></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger no-print"><i class="icon icon-exclamation-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </div>
</body>
</html>
