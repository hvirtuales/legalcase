<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta y edición de una empresa **del grupo**.
 *
 * Los datos son los mismos de cualquier empresa (`companies/_fields`), pero lo
 * que la sitúa —el grupo— no se pregunta: sale del contexto. Y lo que se
 * contrata está acotado a la lista blanca que le habilitó el Super Admin.
 *
 * @var array      $tenant  grupo activo
 * @var array|null $company null al crear
 * @var array      $plans   planes que el grupo puede repartir
 * @var array      $roles   roles asignables (de Admin hacia abajo)
 * @var array      $users   usuarios para elegir administrador
 * @var array      $members miembros actuales (al editar)
 * @var int|null   $left    plazas libres del cupo
 */
$isNew   = empty($company);
$company ??= [];
$v       = static fn (string $k, $def = '') => old($k, $company[$k] ?? $def);
$action  = $isNew ? 'tenant/empresas/nueva' : 'tenant/empresas/' . (int) $company['id'] . '/editar';

// Rol sugerido: Admin. Es el administrador de la empresa; sin rol no podría gestionarla.
$defaultRole = 0;
foreach ($roles as $r) {
    if ($r['name'] === 'Admin') {
        $defaultRole = (int) $r['id'];
        break;
    }
}
?>

<?php if (session()->getFlashdata('errors')): ?>
    <div class="alert alert-danger">
        <ul class="list-inside list-disc">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= base_url($action) ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <!-- 1. Datos de la empresa -->
    <div class="card mb-6">
        <div class="card-header justify-between">
            <div class="flex items-center gap-2">
                <i class="icon icon-buildings"></i>
                <h6 class="font-semibold"><?= lang('Tenant.companyData') ?></h6>
                <span class="badge badge-light"><i class="icon icon-diagram-3"></i> <?= esc($tenant['name']) ?></span>
                <?php if ($isNew && $left !== null): ?>
                    <span class="badge badge-secondary"><?= lang('Tenant.quotaLeft', [$left]) ?></span>
                <?php endif; ?>
            </div>
            <a href="<?= base_url('tenant/empresas') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-arrow-left"></i> <?= lang('App.back') ?>
            </a>
        </div>
        <div class="card-body">
            <?php // `tenants => []` a propósito: el grupo lo pone el contexto, no el formulario. ?>
            <?= view('companies/_fields', ['company' => $company, 'tenants' => []]) ?>
        </div>
    </div>

    <!-- 2. Plan -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="icon icon-box-seam"></i>
            <h6 class="font-semibold"><?= lang('Tenant.plan') ?></h6>
        </div>
        <div class="card-body">
            <?php if (!$plans): ?>
                <div class="alert alert-warning">
                    <i class="icon icon-exclamation-circle"></i>
                    <span><?= lang('Tenant.noAssignablePlans') ?></span>
                </div>
            <?php else: ?>
                <p class="mb-4 text-sm text-muted"><?= lang('Tenant.companyPlanHint') ?></p>
                <div class="form-group md:w-1/2">
                    <label for="plan_id" class="form-label"><?= lang('Tenant.plan') ?> <span class="text-danger">*</span></label>
                    <select class="form-select" id="plan_id" name="plan_id" required>
                        <option value="">—</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= esc($p['id'], 'attr') ?>" <?= (string) $v('plan_id') === (string) $p['id'] ? 'selected' : '' ?>>
                                <?= esc($p['name']) ?>
                                (<?= esc(number_format((float) $p['price_amount'], 2)) ?> <?= esc($p['price_currency']) ?> / <?= esc($p['service_days']) ?>d)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Administrador de la empresa -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="icon icon-person-gear"></i>
            <h6 class="font-semibold"><?= lang('Tenant.companyAdmin') ?></h6>
        </div>
        <div class="card-body">
            <p class="mb-4 text-sm text-muted"><?= lang('Tenant.companyAdminHint') ?></p>

            <?php if (!$isNew): ?>
                <?php $current = array_values(array_filter($members, static fn ($m) => !empty($m['is_admin']))); ?>
                <?php if ($current): ?>
                    <p class="mb-4 text-sm">
                        <span class="text-muted"><?= lang('Tenant.currentAdmin') ?>:</span>
                        <?php foreach ($current as $m): ?>
                            <span class="badge badge-primary ml-1"><?= esc(trim($m['first_name'] . ' ' . $m['last_name'])) ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="mb-4 flex flex-wrap gap-4">
                <?php if (!$isNew): ?>
                    <label class="form-check" for="admin_mode_keep">
                        <input class="form-check-input" type="radio" id="admin_mode_keep" name="admin_mode" value="keep"
                            <?= old('admin_mode', 'keep') === 'keep' ? 'checked' : '' ?> onchange="toggleCompanyAdminMode()">
                        <span class="form-check-label"><?= lang('Tenant.adminKeep') ?></span>
                    </label>
                <?php endif; ?>
                <label class="form-check" for="admin_mode_existing">
                    <input class="form-check-input" type="radio" id="admin_mode_existing" name="admin_mode" value="existing"
                        <?= old('admin_mode', $isNew ? 'existing' : 'keep') === 'existing' ? 'checked' : '' ?> onchange="toggleCompanyAdminMode()">
                    <span class="form-check-label"><?= lang('Tenant.adminExisting') ?></span>
                </label>
                <label class="form-check" for="admin_mode_new">
                    <input class="form-check-input" type="radio" id="admin_mode_new" name="admin_mode" value="new"
                        <?= old('admin_mode') === 'new' ? 'checked' : '' ?> onchange="toggleCompanyAdminMode()">
                    <span class="form-check-label"><?= lang('Tenant.adminNew') ?></span>
                </label>
            </div>

            <div id="companyAdminExisting" class="hidden">
                <div class="form-group md:w-1/2">
                    <label for="admin_user_id" class="form-label"><?= lang('Tenant.admin') ?> <span class="text-danger">*</span></label>
                    <select class="form-select" id="admin_user_id" name="admin_user_id">
                        <option value="">—</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= esc($u['id'], 'attr') ?>" <?= (string) old('admin_user_id') === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= esc(trim($u['first_name'] . ' ' . $u['last_name'])) ?> (<?= esc($u['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="companyAdminNew" class="hidden">
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="admin_first_name" class="form-label"><?= lang('Tenant.firstName') ?></label>
                        <input type="text" class="form-input" id="admin_first_name" name="admin_first_name" value="<?= esc(old('admin_first_name'), 'attr') ?>" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="admin_last_name" class="form-label"><?= lang('Tenant.lastName') ?></label>
                        <input type="text" class="form-input" id="admin_last_name" name="admin_last_name" value="<?= esc(old('admin_last_name'), 'attr') ?>" maxlength="50">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="admin_username" class="form-label"><?= lang('Tenant.username') ?></label>
                        <input type="text" class="form-input" id="admin_username" name="admin_username" value="<?= esc(old('admin_username'), 'attr') ?>" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="admin_email" class="form-label"><?= lang('Tenant.email') ?></label>
                        <input type="email" class="form-input" id="admin_email" name="admin_email" value="<?= esc(old('admin_email'), 'attr') ?>" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="admin_phone" class="form-label"><?= lang('Tenant.phone') ?></label>
                        <input type="tel" class="form-input" id="admin_phone" name="admin_phone" data-phone value="<?= esc(old('admin_phone'), 'attr') ?>" maxlength="30" placeholder="300 123 4567">
                    </div>
                </div>
                <div class="form-group md:w-1/2">
                    <label for="admin_password" class="form-label"><?= lang('Tenant.password') ?></label>
                    <input type="password" class="form-input" id="admin_password" name="admin_password" autocomplete="new-password" minlength="8">
                </div>
            </div>

            <div id="companyAdminRole" class="hidden">
                <div class="form-group md:w-1/2">
                    <label for="role_id" class="form-label"><?= lang('Tenant.role') ?></label>
                    <select class="form-select" id="role_id" name="role_id">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= esc($r['id'], 'attr') ?>" <?= (int) old('role_id', $defaultRole) === (int) $r['id'] ? 'selected' : '' ?>>
                                <?= esc($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint"><?= lang('Tenant.roleHint') ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary" <?= $plans ? '' : 'disabled' ?>>
            <i class="icon icon-save"></i> <?= $isNew ? lang('Tenant.newCompany') : lang('Tenant.save') ?>
        </button>
        <a href="<?= base_url('tenant/empresas') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Tenant.cancel') ?></a>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function toggleCompanyAdminMode() {
        const mode = document.querySelector('input[name="admin_mode"]:checked').value;
        document.getElementById('companyAdminExisting').classList.toggle('hidden', mode !== 'existing');
        document.getElementById('companyAdminNew').classList.toggle('hidden', mode !== 'new');
        document.getElementById('companyAdminRole').classList.toggle('hidden', mode === 'keep');
    }
    toggleCompanyAdminMode();
</script>
<?= $this->endSection() ?>
