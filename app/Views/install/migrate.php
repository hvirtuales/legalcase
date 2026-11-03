<?= $this->extend('install/_layout') ?>

<?= $this->section('content') ?>

<h2 class="mb-1 text-lg font-semibold"><i class="icon icon-box-seam"></i> Migración y datos base</h2>
<p class="mb-4 text-sm text-muted">Se crearán las tablas del esquema y se cargarán los datos base (roles, permisos, configuración y planes).</p>

<?php if ($dbOk): ?>
    <div class="alert alert-success"><i class="icon icon-check-circle"></i><span>Conexión a la base de datos verificada.</span></div>
    <form action="<?= base_url('install/migrate') ?>" method="post" class="flex items-center justify-between gap-2">
        <?= csrf_field() ?>
        <a href="<?= base_url('install/database') ?>" class="btn btn-outline"><i class="icon icon-chevron-left"></i> Atrás</a>
        <button type="submit" class="btn btn-primary"><i class="icon icon-play-circle"></i> Ejecutar migraciones y datos</button>
    </form>
<?php else: ?>
    <div class="alert alert-error"><i class="icon icon-exclamation-circle"></i><span>No se pudo conectar con la configuración guardada: <?= esc($dbErr) ?></span></div>
    <a href="<?= base_url('install/database') ?>" class="btn btn-primary"><i class="icon icon-chevron-left"></i> Revisar la conexión</a>
<?php endif; ?>

<?= $this->endSection() ?>
