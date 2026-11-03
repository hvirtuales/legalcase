<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-body">
        <!-- Logo -->
        <div class="mb-4 text-center">
            <i class="icon icon-shield-lock text-primary" style="font-size: 4rem;"></i>
        </div>

        <!-- Title -->
        <h2 class="text-center text-xl font-semibold text-ink">Verificación en Dos Pasos</h2>
        <p class="mb-6 mt-1 text-center text-sm text-muted">Ingresa el código de tu aplicación de autenticación</p>

        <!-- Alerts -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error">
                <i class="icon icon-exclamation-circle"></i>
                <span><?= session()->getFlashdata('error') ?></span>
            </div>
        <?php endif; ?>

        <!-- 2FA Form -->
        <form action="<?= base_url('verify-2fa') ?>" method="post" id="twoFactorForm">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="code" class="form-label w-full justify-center">
                    <i class="icon icon-key"></i>
                    Código de Verificación
                </label>
                <input type="text" class="form-input text-center text-2xl tracking-[0.5rem]" id="code" name="code"
                    placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
                <small class="mt-2 block text-center text-xs text-muted">
                    Ingresa el código de 6 dígitos
                </small>
            </div>

            <input type="hidden" name="use_recovery_code" id="useRecoveryCode" value="0">

            <button type="submit" class="btn btn-primary btn-block mb-4">
                <i class="icon icon-check-circle"></i>
                Verificar Código
            </button>

            <div class="text-center">
                <button type="button" class="btn btn-ghost btn-sm" onclick="toggleRecoveryCode()">
                    <i class="icon icon-life-preserver"></i>
                    Usar código de recuperación
                </button>
            </div>
        </form>

        <div class="mt-4 text-center">
            <a href="<?= base_url('logout') ?>" class="text-sm text-muted hover:text-primary hover:underline">
                <i class="icon icon-arrow-left"></i>
                Volver al inicio de sesión
            </a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function toggleRecoveryCode() {
        const codeInput = document.getElementById('code');
        const useRecoveryInput = document.getElementById('useRecoveryCode');
        const isRecovery = useRecoveryInput.value === '1';

        if (isRecovery) {
            codeInput.placeholder = '000000';
            codeInput.maxLength = 6;
            codeInput.pattern = '[0-9]{6}';
            useRecoveryInput.value = '0';
        } else {
            codeInput.placeholder = 'XXXXXXXX';
            codeInput.maxLength = 16;
            codeInput.pattern = '[A-Z0-9]{8,16}';
            useRecoveryInput.value = '1';
        }

        codeInput.value = '';
        codeInput.focus();
    }
</script>
<?= $this->endSection() ?>
