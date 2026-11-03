<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<form action="<?= base_url('companies/create') ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger">
            <ul class="list-inside list-disc">
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Datos de la empresa -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="icon icon-buildings"></i>
            <h6 class="font-semibold">Datos de la Empresa</h6>
        </div>
        <div class="card-body">
            <?= view('companies/_fields', ['company' => [], 'tenants' => $tenants ?? []]) ?>
        </div>
    </div>

    <!-- Administrador -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="icon icon-person-gear"></i>
            <h6 class="font-semibold">Administrador de la Empresa</h6>
        </div>
        <div class="card-body">
            <div class="mb-4 flex flex-wrap gap-4">
                <label class="form-check" for="admin_mode_existing">
                    <input class="form-check-input" type="radio" id="admin_mode_existing" name="admin_mode" value="existing"
                        <?= old('admin_mode', 'existing') === 'existing' ? 'checked' : '' ?> onchange="toggleAdminMode()">
                    <span class="form-check-label">Usuario existente</span>
                </label>
                <label class="form-check" for="admin_mode_new">
                    <input class="form-check-input" type="radio" id="admin_mode_new" name="admin_mode" value="new"
                        <?= old('admin_mode') === 'new' ? 'checked' : '' ?> onchange="toggleAdminMode()">
                    <span class="form-check-label">Crear nuevo administrador</span>
                </label>
            </div>

            <!-- Existente -->
            <div id="adminExisting">
                <div class="form-group">
                    <label for="admin_user_id" class="form-label">Selecciona el administrador</label>
                    <select class="form-select" id="admin_user_id" name="admin_user_id">
                        <option value="">— Selecciona un usuario —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= esc($u['id'], 'attr') ?>" <?= (string) old('admin_user_id') === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= esc(trim($u['first_name'] . ' ' . $u['last_name'])) ?> (<?= esc($u['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Se enlazará a la empresa y recibirá el rol Admin.</small>
                </div>
            </div>

            <!-- Nuevo -->
            <div id="adminNew" class="hidden">
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="admin_first_name" class="form-label">Nombre</label>
                        <input type="text" class="form-input" id="admin_first_name" name="admin_first_name" value="<?= esc(old('admin_first_name'), 'attr') ?>" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="admin_last_name" class="form-label">Apellido</label>
                        <input type="text" class="form-input" id="admin_last_name" name="admin_last_name" value="<?= esc(old('admin_last_name'), 'attr') ?>" maxlength="50">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="admin_username" class="form-label">Usuario</label>
                        <input type="text" class="form-input" id="admin_username" name="admin_username" value="<?= esc(old('admin_username'), 'attr') ?>" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="admin_email" class="form-label">Email</label>
                        <input type="email" class="form-input" id="admin_email" name="admin_email" value="<?= esc(old('admin_email'), 'attr') ?>" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="admin_phone" class="form-label">Teléfono / WhatsApp</label>
                        <input type="tel" class="form-input" id="admin_phone" name="admin_phone" data-phone value="<?= esc(old('admin_phone'), 'attr') ?>" maxlength="30" placeholder="300 123 4567">
                    </div>
                </div>
                <div class="form-group md:w-1/2">
                    <label for="admin_password" class="form-label">Contraseña</label>
                    <input type="password" class="form-input" id="admin_password" name="admin_password" autocomplete="new-password" minlength="8">
                </div>
            </div>
        </div>
    </div>

    <!-- Plan / Suscripción -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="icon icon-box-seam"></i>
            <h6 class="font-semibold">Plan y Suscripción</h6>
        </div>
        <div class="card-body">
            <?= view('companies/_subscription', ['plans' => $plans, 'subscription' => null]) ?>
        </div>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Crear Empresa</button>
        <a href="<?= base_url('companies') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function toggleAdminMode() {
        const isNew = document.getElementById('admin_mode_new').checked;
        document.getElementById('adminNew').classList.toggle('hidden', !isNew);
        document.getElementById('adminExisting').classList.toggle('hidden', isNew);
    }
    toggleAdminMode();
</script>
<?= $this->endSection() ?>
