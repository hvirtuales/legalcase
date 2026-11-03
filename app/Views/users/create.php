<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <i class="icon icon-person-plus"></i>
        <h5 class="font-semibold">Crear Nuevo Usuario</h5>
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

        <?php if (!empty($scoped) && !empty($companyName)): ?>
            <div class="alert alert-info mb-4"><i class="icon icon-info-circle"></i>
                <span>El usuario se registrará como <strong>miembro de <?= esc($companyName) ?></strong>. Elige su rol abajo.</span></div>
        <?php endif; ?>

        <form action="<?= base_url('users/create') ?>" method="post">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="first_name" class="form-label">Nombre *</label>
                    <input type="text" class="form-input" id="first_name" name="first_name"
                        value="<?= old('first_name') ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name" class="form-label">Apellido *</label>
                    <input type="text" class="form-input" id="last_name" name="last_name"
                        value="<?= old('last_name') ?>" required>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="phone" class="form-label">Teléfono / WhatsApp</label>
                    <input type="tel" class="form-input" id="phone" name="phone" data-phone
                        value="<?= old('phone') ?>" placeholder="300 123 4567">
                    <small class="form-hint">Con código de país, sin el signo +. Se usa para recuperación por WhatsApp.</small>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="username" class="form-label">Usuario *</label>
                    <input type="text" class="form-input" id="username" name="username"
                        value="<?= old('username') ?>" required>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-input" id="email" name="email"
                        value="<?= old('email') ?>" required>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="password" class="form-label">Contraseña *</label>
                    <input type="password" class="form-input" id="password" name="password" required minlength="8"
                        autocomplete="new-password">
                    <small class="form-hint">Mínimo 8 caracteres</small>
                </div>
                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirmar Contraseña *</label>
                    <input type="password" class="form-input" id="password_confirm" name="password_confirm" required
                        autocomplete="new-password">
                </div>
            </div>

            <div class="form-group md:w-1/2">
                <label for="preferred_language" class="form-label">Idioma Preferido</label>
                <select class="form-select" id="preferred_language" name="preferred_language">
                    <option value="es" <?= old('preferred_language') === 'es' ? 'selected' : '' ?>>Español</option>
                    <option value="en" <?= old('preferred_language') === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Roles</label>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <?php foreach ($roles as $role): ?>
                        <label class="form-check" for="role_<?= $role['id'] ?>">
                            <input class="form-check-input" type="checkbox" id="role_<?= $role['id'] ?>"
                                name="roles[]" value="<?= $role['id'] ?>">
                            <span class="form-check-label">
                                <?= esc($role['name']) ?>
                                <?php if ($role['description']): ?>
                                    <small class="block text-muted"><?= esc($role['description']) ?></small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="form-check mb-4" for="is_active">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                <span class="form-check-label">Usuario activo</span>
            </label>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-save"></i>
                    Crear Usuario
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
