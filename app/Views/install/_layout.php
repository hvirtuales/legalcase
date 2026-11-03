<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente de Instalación</title>

    <!-- Recursos auto-hospedados (sin CDN, sin BD) -->
    <link rel="stylesheet" href="<?= base_url('css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/icons.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>">

    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('theme'); } catch (e) {}
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>
</head>

<body class="min-h-screen bg-canvas text-ink">
    <div class="mx-auto max-w-3xl px-5 py-8">

        <!-- Encabezado -->
        <div class="mb-6 text-center">
            <div class="mb-2 inline-flex size-12 items-center justify-center rounded-lg bg-primary text-primary-contrast">
                <i class="icon icon-box-seam text-2xl"></i>
            </div>
            <h1 class="text-xl font-bold text-ink">Asistente de Instalación</h1>
            <p class="text-sm text-muted">Configuración inicial de la plataforma</p>
        </div>

        <!-- Stepper -->
        <?php
        $stepKeys = array_keys($steps);
        $curIndex = array_search($current, $stepKeys, true);
        ?>
        <ol class="mb-6 flex flex-wrap items-center justify-center gap-1 text-xs">
            <?php foreach ($stepKeys as $i => $key): ?>
                <?php
                $state = $i < $curIndex ? 'done' : ($i === $curIndex ? 'current' : 'pending');
                $circle = $state === 'done' ? 'bg-success text-white' : ($state === 'current' ? 'bg-primary text-primary-contrast' : 'bg-surface-muted text-muted border border-line');
                $text = $state === 'current' ? 'text-ink font-semibold' : 'text-muted';
                ?>
                <li class="flex items-center gap-1">
                    <span class="inline-flex size-6 items-center justify-center rounded-full <?= $circle ?>">
                        <?php if ($state === 'done'): ?><i class="icon icon-check-circle"></i><?php else: ?><?= $i + 1 ?><?php endif; ?>
                    </span>
                    <span class="hidden <?= $text ?> sm:inline"><?= esc($steps[$key]) ?></span>
                </li>
                <?php if ($i < count($stepKeys) - 1): ?>
                    <li class="mx-1 h-px w-4 bg-line sm:w-6"></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>

        <!-- Alertas -->
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><i class="icon icon-check-circle"></i><span><?= esc(session()->getFlashdata('success')) ?></span></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error"><i class="icon icon-exclamation-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-error">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Contenido del paso -->
        <div class="card">
            <div class="card-body">
                <?= $this->renderSection('content') ?>
            </div>
        </div>

        <p class="mt-4 text-center text-xs text-faint">CodeIgniter 4 · Instalador</p>
    </div>
</body>

</html>
