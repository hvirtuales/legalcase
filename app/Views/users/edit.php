<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <i class="icon icon-pencil"></i>
        <h5 class="font-semibold">Editar Usuario</h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-error">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('users/edit/' . $user['id']) ?>" method="post">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="first_name" class="form-label">Nombre *</label>
                    <input type="text" class="form-input" id="first_name" name="first_name"
                        value="<?= old('first_name', $user['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name" class="form-label">Apellido *</label>
                    <input type="text" class="form-input" id="last_name" name="last_name"
                        value="<?= old('last_name', $user['last_name']) ?>" required>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="phone" class="form-label">Teléfono / WhatsApp</label>
                    <input type="tel" class="form-input" id="phone" name="phone" data-phone
                        value="<?= old('phone', $user['phone'] ?? '') ?>" placeholder="300 123 4567">
                    <small class="form-hint">Con código de país, sin el signo +. Se usa para recuperación por WhatsApp.</small>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="username" class="form-label">Usuario *</label>
                    <input type="text" class="form-input" id="username" name="username"
                        value="<?= old('username', $user['username']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-input" id="email" name="email"
                        value="<?= old('email', $user['email']) ?>" required>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="password" class="form-label">Nueva Contraseña</label>
                    <input type="password" class="form-input" id="password" name="password" minlength="8"
                        autocomplete="new-password">
                    <small class="form-hint">Dejar en blanco para mantener la actual (mínimo 8 caracteres)</small>
                </div>
                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirmar Nueva Contraseña</label>
                    <input type="password" class="form-input" id="password_confirm" name="password_confirm"
                        autocomplete="new-password">
                    <small class="form-hint">Solo si cambias la contraseña.</small>
                </div>
            </div>

            <div class="form-group md:w-1/2">
                <label for="preferred_language" class="form-label">Idioma Preferido</label>
                <select class="form-select" id="preferred_language" name="preferred_language">
                    <option value="es" <?= old('preferred_language', $user['preferred_language']) === 'es' ? 'selected' : '' ?>>Español</option>
                    <option value="en" <?= old('preferred_language', $user['preferred_language']) === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>

            <label class="form-check mb-4" for="is_active">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                    <?= old('is_active', $user['is_active']) ? 'checked' : '' ?>>
                <span class="form-check-label">Usuario activo</span>
            </label>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-save"></i>
                    Guardar Cambios
                </button>
                <a href="<?= base_url('users') ?>" class="btn btn-secondary">
                    <i class="icon icon-x-circle"></i>
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
