<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="mb-6 text-center">
            <h4 class="text-xl font-semibold text-ink">Recuperar Contraseña</h4>
            <p class="mt-1 text-sm text-muted">Ingresa tu correo electrónico para recibir instrucciones.</p>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger">
                <i class="icon icon-exclamation-circle"></i>
                <span><?= session()->getFlashdata('error') ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <i class="icon icon-check-circle"></i>
                <span><?= session()->getFlashdata('success') ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('forgot-password') ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="icon icon-envelope"></i>
                    Correo Electrónico
                </label>
                <input type="email" class="form-input" id="email" name="email" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary btn-block mb-4">
                Enviar Enlace
            </button>

            <div class="text-center text-sm">
                <a href="<?= base_url('login') ?>" class="font-medium text-primary hover:underline">
                    <i class="icon icon-arrow-left"></i> Volver al Login
                </a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
