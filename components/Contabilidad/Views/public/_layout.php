<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Cuenta de cobro') ?></title>
    <link rel="stylesheet" href="<?= base_url('css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/icons.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>">
    <?php $brand = $company['brand_color'] ?? ''; ?>
    <?php if ($brand): ?>
        <style>:root{--color-primary: <?= esc($brand) ?>; --color-primary-hover: <?= esc($brand) ?>;}</style>
    <?php endif; ?>
    <script>
        (function () { try { var s = localStorage.getItem('theme'); document.documentElement.setAttribute('data-theme', s || 'light'); } catch (e) {} })();
    </script>
</head>

<body class="min-h-screen bg-canvas text-ink">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <div class="mb-6 text-center">
            <?php if (!empty($company['logo'])): ?>
                <img src="<?= base_url($company['logo']) ?>" alt="logo" class="mx-auto mb-2 max-h-16">
            <?php endif; ?>
            <h1 class="text-xl font-bold text-primary"><?= esc($company['name'] ?? 'Cuenta de cobro') ?></h1>
            <?php if (!empty($company['nit'])): ?><p class="text-sm text-muted">NIT: <?= esc($company['nit']) ?></p><?php endif; ?>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error"><i class="icon icon-exclamation-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>

        <p class="mt-6 text-center text-xs text-faint">Formulario de cuenta de cobro</p>
    </div>
</body>

</html>
