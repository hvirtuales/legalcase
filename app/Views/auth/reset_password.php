<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="mb-6 text-center">
            <h4 class="text-xl font-semibold text-ink">Restablecer Contraseña</h4>
            <p class="mt-1 text-sm text-muted">Ingresa tu nueva contraseña a continuación.</p>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger">
                <i class="icon icon-exclamation-circle"></i>
                <span><?= session()->getFlashdata('error') ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('reset-password') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($token) ?>">

            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="icon icon-lock"></i>
                    Nueva Contraseña
                </label>
                <input type="password" class="form-input" id="password" name="password" required minlength="8">
                <div class="form-hint">Mínimo 8 caracteres.</div>
            </div>

            <div class="form-group">
                <label for="password_confirm" class="form-label">
                    <i class="icon icon-lock-fill"></i>
                    Confirmar Contraseña
                </label>
                <input type="password" class="form-input" id="password_confirm" name="password_confirm" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Cambiar Contraseña
            </button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
