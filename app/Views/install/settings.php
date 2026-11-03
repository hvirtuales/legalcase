<?= $this->extend('install/_layout') ?>

<?= $this->section('content') ?>

<h2 class="mb-1 text-lg font-semibold"><i class="icon icon-gear"></i> Parámetros iniciales</h2>
<p class="mb-4 text-sm text-muted">Configura los datos básicos de la plataforma. Podrás cambiarlos después en Configuración.</p>

<form action="<?= base_url('install/settings') ?>" method="post">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="app_name" class="form-label">Nombre de la plataforma</label>
        <input type="text" class="form-input" id="app_name" name="app_name" value="<?= old('app_name', 'Mi Plataforma') ?>">
    </div>

    <div class="form-group">
        <label for="app_description" class="form-label">Descripción</label>
        <textarea class="form-textarea" id="app_description" name="app_description" rows="2"><?= old('app_description') ?></textarea>
    </div>

    <div class="form-group">
        <label for="support_email" class="form-label">Email de soporte</label>
        <input type="email" class="form-input" id="support_email" name="support_email" value="<?= old('support_email') ?>" placeholder="soporte@midominio.com">
    </div>

    <label class="form-check mb-4" for="public_registration">
        <input class="form-check-input" type="checkbox" id="public_registration" name="public_registration" value="1">
        <span class="form-check-label">Permitir registro público de usuarios</span>
    </label>

    <div class="flex items-center justify-between gap-2">
        <a href="<?= base_url('install/admin') ?>" class="btn btn-outline"><i class="icon icon-chevron-left"></i> Atrás</a>
        <button type="submit" class="btn btn-primary">Continuar <i class="icon icon-chevron-right"></i></button>
    </div>
</form>

<?= $this->endSection() ?>
