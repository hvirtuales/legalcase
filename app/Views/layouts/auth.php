<!DOCTYPE html>
<html lang="<?= session()->get('language') ?? 'es' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Sistema de Autenticación') ?></title>

    <?php
    // Load settings
    helper('media');
    $settingModel = new \App\Models\AppSettingModel();
    $allSettings = $settingModel->getAllSettings();
    $favicon = $allSettings['favicon'] ?? null;
    $loginBg = $allSettings['login_background'] ?? 'gradient';
    $bgColorPrimary = $allSettings['bg_color_primary'] ?? '#e10e30';
    $bgColorSecondary = $allSettings['bg_color_secondary'] ?? '#b90b26';
    $backgroundImage = $allSettings['background_image'] ?? null;
    ?>

    <!-- Favicon -->
    <?php if ($favicon): ?>
        <link rel="icon" href="<?= media_url($favicon) ?>" type="image/x-icon">
    <?php endif; ?>

    <!-- Inter (auto-hospedada) -->
    <link rel="stylesheet" href="<?= base_url('css/fonts.css') ?>?v=<?= filemtime(FCPATH . 'css/fonts.css') ?>">

    <!-- Iconos (SVG Lucide, auto-hospedados) -->
    <link rel="stylesheet" href="<?= base_url('css/icons.css') ?>?v=<?= filemtime(FCPATH . 'css/icons.css') ?>">

    <!-- intl-tel-input (banderas de teléfono) — auto-hospedado -->
    <link rel="stylesheet" href="<?= base_url('vendor/iti/css/intlTelInput.css') ?>">
    <style>.iti { width: 100%; } .iti__country-list { color: #1e293b; }</style>

    <!-- Tailwind (compilado) -->
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>?v=<?= filemtime(FCPATH . 'css/tailwind.css') ?>">

    <!-- Dynamic Theme Colors (sobre-escribe --color-primary*) -->
    <link rel="stylesheet" href="<?= base_url('theme.css') ?>">

    <!-- Prevent FOUC - Apply theme immediately -->
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('theme'); } catch (e) {}
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    <!-- Dynamic Background Styles -->
    <style>
        body {
            min-height: 100vh;
            <?php if ($loginBg === 'image' && $backgroundImage): ?>
                background-image: url('<?= media_url($backgroundImage) ?>') !important;
                background-size: cover !important;
                background-position: center !important;
                background-repeat: no-repeat !important;
                background-attachment: fixed !important;
            <?php elseif ($loginBg === 'gradient'): ?>
                background: linear-gradient(135deg, <?= $bgColorPrimary ?> 0%, <?= $bgColorSecondary ?> 100%) !important;
            <?php elseif ($loginBg === 'solid'): ?>
                background: <?= $bgColorPrimary ?> !important;
            <?php else: ?>
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%) !important;
            <?php endif; ?>
        }
    </style>

    <?= $this->renderSection('styles') ?>
</head>

<body class="flex min-h-screen items-center justify-center p-5">

    <!-- Theme toggle + Language Switcher -->
    <div class="absolute right-5 top-5 flex items-center gap-2">
        <button type="button" data-theme-toggle class="btn btn-secondary btn-sm"
            title="<?= lang('App.toggleTheme') ?>" aria-label="<?= lang('App.toggleTheme') ?>">
            <i class="icon icon-moon-stars" data-theme-icon></i>
        </button>
        <details class="dd">
            <summary class="btn btn-secondary btn-sm">
                <i class="icon icon-globe"></i>
                <?= session()->get('language') === 'en' ? 'EN' : 'ES' ?>
            </summary>
            <div class="dd-menu">
                <a class="dd-item" href="<?= base_url('language/es') ?>">Español</a>
                <a class="dd-item" href="<?= base_url('language/en') ?>">English</a>
            </div>
        </details>
    </div>

    <div class="w-full max-w-md">
        <?= $this->renderSection('content') ?>
    </div>

    <!-- Support Widget -->
    <?= view('partials/support_widget') ?>

    <!-- Theme Manager - Auto Dark Mode -->
    <script src="<?= base_url('js/theme-manager.js') ?>?v=<?= filemtime(FCPATH . 'js/theme-manager.js') ?>"></script>

    <!-- Teléfono con banderas (registro) -->
    <script>window.ITI_UTILS = '<?= base_url('vendor/iti/js/utils.js') ?>';</script>
    <script src="<?= base_url('vendor/iti/js/intlTelInput.min.js') ?>"></script>
    <script src="<?= base_url('js/phone-input.js') ?>?v=<?= filemtime(FCPATH . 'js/phone-input.js') ?>"></script>

    <?= $this->renderSection('scripts') ?>
</body>

</html>
