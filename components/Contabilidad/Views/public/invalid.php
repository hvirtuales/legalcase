<?= $this->extend('Components\Contabilidad\Views\public\_layout') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body text-center">
        <div class="mb-2 text-4xl text-danger"><i class="icon icon-x-circle"></i></div>
        <h5 class="font-semibold"><?= isset($message) ? 'No disponible' : 'Enlace no válido' ?></h5>
        <p class="text-sm text-muted"><?= esc($message ?? 'La empresa no existe o no está activa. Verifica el enlace con quien te lo compartió.') ?></p>
    </div>
</div>
<?= $this->endSection() ?>
