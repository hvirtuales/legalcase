<?php
/**
 * Formulario del tenant, compartido por crear y editar.
 *
 * @var array      $tenant        valores actuales (vacío al crear)
 * @var array      $plans         planes de ámbito TENANT (lo que contrata el grupo)
 * @var array      $companyPlans  planes de ámbito EMPRESA (lo que puede repartir)
 * @var array      $whitelist     ids de los planes ya habilitados
 * @var int|null   $defaultPlanId plan por defecto de la lista blanca
 * @var array      $users         usuarios para elegir administrador
 * @var array|null $subscription  suscripción vigente (al editar)
 * @var bool       $isNew
 *
 * Los campos de marca y contacto se reusan de `companies/_fields`: un tenant es
 * una fila de `companies`, así que tiene exactamente los mismos datos. Se le
 * pasa `tenants => []` para que NO muestre el selector de grupo (un tenant no
 * cuelga de otro: un solo nivel de anidación).
 */
$tenant        ??= [];
$whitelist     ??= [];
$defaultPlanId ??= null;
$subscription  ??= null;
$isNew         ??= empty($tenant);
$v = static fn (string $k, $def = '') => old($k, $tenant[$k] ?? $def);
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

<!-- 1. Identificación del grupo -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-diagram-3"></i>
        <h6 class="font-semibold"><?= lang('Tenant.tenantData') ?></h6>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Tenant.tenantDataHint') ?></p>
        <?= view('companies/_fields', ['company' => $tenant, 'tenants' => []]) ?>
    </div>
</div>

<!-- 2. Quién lo administra -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-person-gear"></i>
        <h6 class="font-semibold"><?= lang('Tenant.tenantAdmin') ?></h6>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Tenant.tenantAdminHint') ?></p>

        <?php if ($isNew): ?>
            <div class="mb-4 flex flex-wrap gap-4">
                <label class="form-check" for="admin_mode_existing">
                    <input class="form-check-input" type="radio" id="admin_mode_existing" name="admin_mode" value="existing"
                        <?= old('admin_mode', 'existing') === 'existing' ? 'checked' : '' ?> onchange="toggleTenantAdminMode()">
                    <span class="form-check-label">Usuario existente</span>
                </label>
                <label class="form-check" for="admin_mode_new">
                    <input class="form-check-input" type="radio" id="admin_mode_new" name="admin_mode" value="new"
                        <?= old('admin_mode') === 'new' ? 'checked' : '' ?> onchange="toggleTenantAdminMode()">
                    <span class="form-check-label">Crear nuevo administrador</span>
                </label>
            </div>

            <div id="tenantAdminExisting">
                <div class="form-group md:w-1/2">
                    <label for="admin_user_id" class="form-label"><?= lang('Tenant.admin') ?> <span class="text-danger">*</span></label>
                    <select class="form-select" id="admin_user_id" name="admin_user_id">
                        <option value="">— Selecciona un usuario —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= esc($u['id'], 'attr') ?>" <?= (string) old('admin_user_id') === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= esc(trim($u['first_name'] . ' ' . $u['last_name'])) ?> (<?= esc($u['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="tenantAdminNew" class="hidden">
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
        <?php else: ?>
            <div class="form-group md:w-1/2">
                <label for="owner_id" class="form-label"><?= lang('Tenant.admin') ?></label>
                <select class="form-select" id="owner_id" name="owner_id">
                    <option value="">—</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= esc($u['id'], 'attr') ?>" <?= (string) $v('owner_id') === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= esc(trim($u['first_name'] . ' ' . $u['last_name'])) ?> (<?= esc($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <fieldset class="form-section mt-4">
            <legend class="form-section-title"><?= lang('Tenant.permissions') ?></legend>
            <label class="form-check" for="can_create_children">
                <input class="form-check-input" type="checkbox" id="can_create_children" name="can_create_children" value="1"
                    <?= $v('can_create_children') ? 'checked' : '' ?>>
                <span class="form-check-label"><?= lang('Tenant.canCreateChildren') ?></span>
            </label>
            <small class="form-hint"><?= lang('Tenant.canCreateChildrenHint') ?></small>
        </fieldset>
    </div>
</div>

<!-- 3. Lo que contrata el grupo -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-box-seam"></i>
        <h6 class="font-semibold"><?= lang('Tenant.planAndSub') ?></h6>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Tenant.planAndSubHint') ?></p>

        <?php if (!$plans): ?>
            <div class="alert alert-warning">
                <i class="icon icon-exclamation-circle"></i>
                <span><?= lang('Tenant.errPlanScope') ?> <a class="underline" href="<?= base_url('plans/create') ?>"><?= lang('Tenant.planScopeTenant') ?></a></span>
            </div>
        <?php endif; ?>

        <?= view('companies/_subscription', ['plans' => $plans, 'subscription' => $subscription]) ?>
    </div>
</div>

<!-- 4. Qué puede repartir entre sus empresas -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-list-check"></i>
        <h6 class="font-semibold"><?= lang('Tenant.assignablePlans') ?></h6>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Tenant.assignablePlansHint') ?></p>

        <?php if (!$companyPlans): ?>
            <p class="text-sm text-muted"><?= lang('Tenant.planNoServices') ?></p>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <?php foreach ($companyPlans as $p): ?>
                    <?php $checked = in_array((int) $p['id'], array_map('intval', (array) old('assignable_plans', $whitelist)), true); ?>
                    <label class="form-check rounded-md border border-line p-2" for="plan_<?= esc($p['id'], 'attr') ?>">
                        <input class="form-check-input" type="checkbox" id="plan_<?= esc($p['id'], 'attr') ?>"
                            name="assignable_plans[]" value="<?= esc($p['id'], 'attr') ?>" <?= $checked ? 'checked' : '' ?>>
                        <span class="form-check-label">
                            <?= esc($p['name']) ?>
                            <span class="text-xs text-muted">
                                (<?= esc(number_format((float) $p['price_amount'], 2)) ?> <?= esc($p['price_currency']) ?> / <?= esc($p['service_days']) ?>d)
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-group mt-4 md:w-1/2">
                <label for="default_plan" class="form-label"><?= lang('Tenant.defaultPlan') ?></label>
                <select class="form-select" id="default_plan" name="default_plan">
                    <option value=""><?= lang('Tenant.noDefaultPlan') ?></option>
                    <?php foreach ($companyPlans as $p): ?>
                        <option value="<?= esc($p['id'], 'attr') ?>" <?= (string) old('default_plan', (string) $defaultPlanId) === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= esc($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint"><?= lang('Tenant.defaultPlanHint') ?></small>
            </div>
        <?php endif; ?>
    </div>
</div>
