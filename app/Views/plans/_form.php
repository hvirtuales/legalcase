<?php
/**
 * @var array $plan Valores actuales (vacío en creación)
 *
 * Los recuadros de servicios NO están cableados aquí: los genera
 * `ComponentManager::planFormGroups()` desde el Manifest de cada componente, y
 * se muestran los del **ámbito** del plan (empresa o tenant). Un componente
 * nuevo aparece solo, sin tocar esta vista ni `PlanController`.
 */
$plan ??= [];
$val = static fn (string $k, $def = '') => old($k, $plan[$k] ?? $def);

$scope   = \App\Models\PlanModel::scopeOf(['scope' => old('scope', $plan['scope'] ?? 'company')]);
$manager = new \App\Libraries\ComponentManager();

// Se pintan los recuadros de LOS DOS ámbitos y se muestra el que toca: así
// cambiar el ámbito no recarga la página ni pierde lo ya escrito. Al guardar,
// `PlanController::collect()` persiste solo las columnas del ámbito enviado
// (nunca las que dicte el navegador: los nombres de columna salen del Manifest).
$groupsByScope = [
    'company' => $manager->planFormGroups('company'),
    'tenant'  => $manager->planFormGroups('tenant'),
];
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

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>

    <!-- 1. Identificación del plan -->
    <fieldset class="form-section">
        <legend class="form-section-title">Datos del plan</legend>
        <p class="form-section-hint">Cómo se identifica el plan, a quién se le vende y cuánto dura el servicio.</p>

        <div class="form-group">
            <label for="scope" class="form-label"><?= lang('Tenant.planScope') ?> <span class="text-danger">*</span></label>
            <select class="form-select" id="scope" name="scope">
                <option value="company" <?= $scope === 'company' ? 'selected' : '' ?>><?= lang('Tenant.planScopeCompany') ?></option>
                <option value="tenant" <?= $scope === 'tenant' ? 'selected' : '' ?>><?= lang('Tenant.planScopeTenant') ?></option>
            </select>
            <small class="form-hint"><?= lang('Tenant.planScopeHint') ?></small>
        </div>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
            <div class="form-group md:col-span-2">
                <label for="name" class="form-label">Nombre del plan <span class="text-danger">*</span></label>
                <input type="text" class="form-input" id="name" name="name" value="<?= esc($val('name'), 'attr') ?>" required maxlength="80">
            </div>
            <div class="form-group">
                <label for="service_days" class="form-label">Días de servicio <span class="text-danger">*</span></label>
                <input type="number" class="form-input" id="service_days" name="service_days" value="<?= esc($val('service_days', 30), 'attr') ?>" min="0" required>
                <small class="form-hint">Vigencia de la suscripción.</small>
            </div>
        </div>

        <div class="form-group">
            <label for="description" class="form-label">Descripción</label>
            <textarea class="form-textarea" id="description" name="description" rows="2"><?= esc($val('description')) ?></textarea>
        </div>
    </fieldset>

    <!-- 2. Precio -->
    <fieldset class="form-section">
        <legend class="form-section-title">Precio</legend>
        <p class="form-section-hint">Valor que se cobra por el periodo de servicio.</p>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
            <div class="form-group md:col-span-2">
                <label for="price_amount" class="form-label">Precio</label>
                <input type="number" step="0.01" class="form-input" id="price_amount" name="price_amount" value="<?= esc($val('price_amount', '0.00'), 'attr') ?>" min="0">
            </div>
            <div class="form-group">
                <label for="price_currency" class="form-label">Moneda</label>
                <input type="text" class="form-input" id="price_currency" name="price_currency" value="<?= esc($val('price_currency', 'USD'), 'attr') ?>" maxlength="3">
                <small class="form-hint">Código ISO: USD, COP…</small>
            </div>
        </div>
    </fieldset>

    <!-- 3. Servicios incluidos: un recuadro por componente, generado del Manifest -->
    <fieldset class="form-section">
        <legend class="form-section-title"><?= lang('Tenant.planServices') ?></legend>
        <p class="form-section-hint">
            <?= lang('Tenant.planServicesHint') ?>
            <strong><?= lang('Tenant.planZeroUnlimited') ?></strong>
        </p>

        <?php // El cupo de empresas solo tiene sentido en un plan de tenant: es
              // cuántas puede agrupar. En un plan de empresa no se ofrece. ?>
        <div class="form-group md:w-1/3" data-scope-box="tenant" <?= $scope === 'tenant' ? '' : 'hidden' ?>>
            <label for="max_companies" class="form-label"><?= lang('Tenant.planMaxCompanies') ?></label>
            <input type="number" class="form-input" id="max_companies" name="max_companies" value="<?= esc($val('max_companies', 0), 'attr') ?>" min="0">
            <small class="form-hint"><?= lang('Tenant.planMaxCompaniesHint') ?></small>
        </div>

        <?php foreach ($groupsByScope as $groupScope => $groups): ?>
        <div data-scope-box="<?= esc($groupScope, 'attr') ?>" <?= $scope === $groupScope ? '' : 'hidden' ?>>
        <?php foreach ($groups as $g): ?>
            <div class="mb-3 rounded-md border border-line bg-surface p-3">
                <?php if ($g['flag']): ?>
                    <label class="form-check mb-3" for="<?= esc($g['flag'], 'attr') ?>">
                        <input class="form-check-input" type="checkbox" id="<?= esc($g['flag'], 'attr') ?>"
                            name="<?= esc($g['flag'], 'attr') ?>" value="1" <?= $val($g['flag'], 1) ? 'checked' : '' ?>>
                        <span class="form-check-label font-semibold">
                            <i class="icon <?= esc($g['icon']) ?>"></i> <?= esc($g['label']) ?>
                        </span>
                    </label>
                <?php else: ?>
                    <p class="mb-3 font-semibold"><i class="icon <?= esc($g['icon']) ?>"></i> <?= esc($g['label']) ?></p>
                <?php endif; ?>

                <?php if ($g['fields']): ?>
                    <?php
                    /*
                     * Dos formas según lo que declare el Manifest: `type => 'bool'`
                     * es un servicio que se incluye o no, y se pinta como casilla,
                     * porque un «1 / 0» en una caja de número no dice si está
                     * encendido. Todo lo demás es un cupo numérico, que es lo que
                     * eran todos los campos antes de que existiera el `type`.
                     *
                     * El `hint` del Manifest se muestra como tooltip y no como
                     * texto debajo: aquí hay una casilla por componente, y un
                     * párrafo en cada una haría ilegible el recuadro.
                     */
                    $bools = array_values(array_filter($g['fields'], static fn ($x) => ($x['type'] ?? 'number') === 'bool'));
                    $nums  = array_values(array_filter($g['fields'], static fn ($x) => ($x['type'] ?? 'number') !== 'bool'));
                    $tip   = static fn (?string $text) => $text === null || $text === ''
                        ? ''
                        : '<span class="tip"><button class="tip-btn" type="button" aria-label="'
                          . esc(lang('Tenant.planWhatIsThis'), 'attr') . '"><i class="icon icon-info-circle"></i></button>'
                          . '<span class="tip-body" role="tooltip">' . esc($text) . '</span></span>';
                    ?>

                    <?php foreach ($bools as $f): ?>
                        <div class="tip-check mb-2">
                            <label class="form-check" for="<?= esc($f['column'], 'attr') ?>">
                                <input class="form-check-input" type="checkbox" id="<?= esc($f['column'], 'attr') ?>"
                                    name="<?= esc($f['column'], 'attr') ?>" value="1" <?= $val($f['column'], $f['default'] ?? 0) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= esc($f['label']) ?></span>
                            </label>
                            <?= $tip($f['hint']) ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($nums): ?>
                        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                            <?php foreach ($nums as $f): ?>
                                <div class="tip-anchor form-group mb-0">
                                    <label for="<?= esc($f['column'], 'attr') ?>" class="form-label">
                                        <?= esc($f['label']) ?><?= $tip($f['hint']) ?>
                                    </label>
                                    <input type="number" class="form-input" id="<?= esc($f['column'], 'attr') ?>"
                                        name="<?= esc($f['column'], 'attr') ?>" value="<?= esc($val($f['column'], $f['default'] ?? 0), 'attr') ?>" min="0">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (!$groups): ?>
            <p class="text-sm text-muted"><?= lang('Tenant.planNoServices') ?></p>
        <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </fieldset>

    <!-- 4. Publicación -->
    <fieldset class="form-section">
        <legend class="form-section-title">Publicación</legend>
        <p class="form-section-hint">Lo que se muestra del plan y si está disponible para asignarse.</p>

        <div class="form-group">
            <label for="features" class="form-label">Características (una por línea)</label>
            <textarea class="form-textarea" id="features" name="features" rows="3"><?= esc($val('features')) ?></textarea>
        </div>

        <label class="form-check mb-2" for="is_active">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $val('is_active', 1) ? 'checked' : '' ?>>
            <span class="form-check-label">Plan activo</span>
        </label>
    </fieldset>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
        <a href="<?= base_url('plans') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
    </div>
</form>

<script>
    // Cambiar el ámbito solo muestra el juego de recuadros que toca; no recarga
    // ni pierde lo escrito. Lo que se guarda lo decide el servidor a partir del
    // ámbito enviado, no de lo que quede visible aquí.
    (function () {
        var select = document.getElementById('scope');
        if (!select) return;
        function apply() {
            document.querySelectorAll('[data-scope-box]').forEach(function (box) {
                box.hidden = box.getAttribute('data-scope-box') !== select.value;
            });
        }
        select.addEventListener('change', apply);
        apply();
    })();
</script>
