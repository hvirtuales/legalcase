<?= $this->extend('install/_layout') ?>

<?= $this->section('content') ?>

<h2 class="mb-1 text-lg font-semibold"><i class="icon icon-person-gear"></i> Cuenta de administrador</h2>
<p class="mb-4 text-sm text-muted">Crea la cuenta Super Admin con la que gestionarás la plataforma.</p>

<form action="<?= base_url('install/admin') ?>" method="post">
    <?= csrf_field() ?>

    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
        <div class="form-group">
            <label for="first_name" class="form-label">Nombre *</label>
            <input type="text" class="form-input" id="first_name" name="first_name" value="<?= old('first_name') ?>" required>
        </div>
        <div class="form-group">
            <label for="last_name" class="form-label">Apellido *</label>
            <input type="text" class="form-input" id="last_name" name="last_name" value="<?= old('last_name') ?>" required>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
        <div class="form-group">
            <label for="username" class="form-label">Usuario *</label>
            <input type="text" class="form-input" id="username" name="username" value="<?= old('username', 'admin') ?>" required>
        </div>
        <div class="form-group">
            <label for="email" class="form-label">Email *</label>
            <input type="email" class="form-input" id="email" name="email" value="<?= old('email') ?>" required>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
        <div class="form-group">
            <label for="password" class="form-label">Contraseña *</label>
            <input type="password" class="form-input" id="password" name="password" required minlength="8" autocomplete="new-password">
            <small class="form-hint">Mínimo 8 caracteres.</small>
        </div>
        <div class="form-group">
            <label for="password_confirm" class="form-label">Confirmar contraseña *</label>
            <input type="password" class="form-input" id="password_confirm" name="password_confirm" required autocomplete="new-password">
        </div>
    </div>

    <div class="flex items-center justify-between gap-2">
        <a href="<?= base_url('install/migrate') ?>" class="btn btn-outline"><i class="icon icon-chevron-left"></i> Atrás</a>
        <button type="submit" class="btn btn-primary">Continuar <i class="icon icon-chevron-right"></i></button>
    </div>
</form>

<?= $this->endSection() ?>
