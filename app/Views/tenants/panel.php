<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Panel del Tenant: lo que ve el administrador del grupo.
 *
 * Es una vista **de grupo**, no un revuelto de datos de las empresas: aquí se
 * ven sus empresas, su plan y su vigencia, y se **entra** a cada una. Los datos
 * de los componentes se siguen viendo dentro de cada empresa, con el aislamiento
 * por `active_company_id` intacto.
 *
 * @var array  $tenant @var ?array $plan @var ?array $sub @var bool $valid
 * @var ?int   $daysLeft @var array $children @var array $subMap @var object $subModel
 * @var int    $used @var ?int $left @var array $plans @var array $services @var bool $canCreate
 */
$enabled = array_values(array_filter($services, static fn ($s) => $s['enabled']));

/** Barra de consumo: primaria tranquila, ámbar al 80 %, roja al llegar. */
$tone = static function (int $used, int $max): array {
    if ($max <= 0) {
        return ['bg-success', 0];
    }
    $pct = min(100, (int) round($used * 100 / $max));

    return [$pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-primary'), $pct];
};

$maxCompanies = $left === null ? 0 : $used + $left;
[$quotaBar, $quotaPct] = $tone($used, $maxCompanies);
?>

<!-- Cabecera del grupo -->
<div class="card mb-6">
    <div class="card-body flex flex-wrap items-center gap-4">
        <?php if (!empty($tenant['logo'])): ?>
            <img src="<?= media_url($tenant['logo']) ?>" alt="" class="h-14 w-14 rounded-md object-contain">
        <?php else: ?>
            <div class="flex h-14 w-14 items-center justify-center rounded-md bg-surface-muted">
                <i class="icon icon-diagram-3 text-2xl text-muted"></i>
            </div>
        <?php endif; ?>

        <div class="min-w-0 flex-1">
            <h2 class="truncate text-xl font-semibold"><?= esc($tenant['name']) ?></h2>
            <p class="text-sm text-muted">
                <?= lang('Tenant.tenantPanel') ?>
                <?= !empty($tenant['nit']) ? ' · NIT ' . esc($tenant['nit']) : '' ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <?php if ($plan): ?>
                <span class="badge <?= $valid ? 'badge-primary' : 'badge-danger' ?> px-3 py-1">
                    <i class="icon icon-box-seam"></i> <?= esc($plan['name']) ?>
                </span>
            <?php else: ?>
                <span class="badge badge-light px-3 py-1"><?= lang('Tenant.noPlan') ?></span>
            <?php endif; ?>
            <a href="<?= base_url('tenant/empresas') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-buildings"></i> <?= lang('Tenant.groupCompanies') ?>
            </a>
            <a href="<?= base_url('tenant/usuarios') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-people"></i> <?= lang('Tenant.groupUsers') ?>
            </a>
        </div>
    </div>
</div>

<!-- Vigencia y cupo -->
<div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('Tenant.subscription') ?></p>
                <span class="badge <?= $valid ? 'badge-success' : 'badge-danger' ?>">
                    <?= $valid ? lang('Tenant.valid') : lang('Tenant.expired') ?>
                </span>
                <?php if ($daysLeft !== null): ?>
                    <p class="mt-1 text-xs <?= $daysLeft < 0 ? 'text-danger' : ($daysLeft <= 15 ? 'text-warning' : 'text-muted') ?>">
                        <?= $daysLeft < 0 ? lang('Tenant.expiredAgo', [abs($daysLeft)]) : lang('Tenant.daysLeft', [$daysLeft]) ?>
                    </p>
                <?php endif; ?>
            </div>
            <i class="icon <?= $valid ? 'icon-check-circle text-success' : 'icon-exclamation-circle text-danger' ?> text-3xl"></i>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="mb-2 flex items-baseline justify-between">
                <p class="text-sm text-muted"><?= lang('Tenant.companiesCount') ?></p>
                <span class="font-semibold">
                    <?= (int) $used ?><?= $left === null ? '' : ' / ' . (int) $maxCompanies ?>
                    <?php if ($left === null): ?>
                        <span class="text-xs font-normal text-muted"><?= lang('Tenant.unlimited') ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($left !== null): ?>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-muted">
                    <div class="h-full <?= $quotaBar ?>" style="width: <?= $quotaPct ?>%"></div>
                </div>
                <?php if ($left <= 0): ?>
                    <p class="mt-2 text-xs text-danger"><?= lang('Tenant.quotaFull') ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('Tenant.services') ?></p>
                <h3 class="text-2xl font-bold"><?= count($enabled) ?><span class="text-base text-muted">/<?= count($services) ?></span></h3>
            </div>
            <i class="icon icon-puzzle text-3xl text-success"></i>
        </div>
    </div>
</div>

<?php if (!$valid): ?>
    <div class="alert alert-danger mb-6">
        <i class="icon icon-exclamation-circle"></i>
        <span><?= lang('Tenant.errTenantOwnExpired', [$tenant['name']]) ?></span>
    </div>
<?php endif; ?>

<!-- Las empresas del grupo -->
<div class="card mb-6">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-buildings"></i>
            <h5 class="font-semibold"><?= lang('Tenant.groupCompanies') ?></h5>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= base_url('tenant/empresas') ?>" class="btn btn-ghost btn-sm"><?= lang('Tenant.manageCompanies') ?></a>
            <?php // Alta solo si el Super Admin se lo habilitó, el cupo alcanza y el grupo está vigente. ?>
            <?php if ($canCreate && $valid && ($left === null || $left > 0)): ?>
                <a href="<?= base_url('tenant/empresas/nueva') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> <?= lang('Tenant.newCompany') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$plans): ?>
            <div class="alert alert-warning mb-4">
                <i class="icon icon-exclamation-circle"></i>
                <span><?= lang('Tenant.noAssignablePlans') ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$children): ?>
            <p class="text-sm text-muted"><?= lang('Tenant.noCompanies') ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Tenant.company') ?></th>
                            <th><?= lang('Tenant.plan') ?></th>
                            <th><?= lang('Tenant.subscription') ?></th>
                            <th class="text-right"><?= lang('Tenant.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $c): ?>
                            <?php
                            $cid      = (int) $c['id'];
                            $csub     = $subMap[$cid] ?? null;
                            $cvalid   = $subModel->isValid($csub);
                            $planName = $planNames[(int) ($c['plan_id'] ?? 0)] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= esc($c['name']) ?></strong>
                                    <?php if (!empty($c['nit'])): ?>
                                        <div class="text-xs text-muted"><?= esc($c['nit']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($plans): ?>
                                        <form action="<?= base_url('tenant/empresas/' . $cid . '/plan') ?>" method="post" class="flex items-center gap-1">
                                            <?= csrf_field() ?>
                                            <select name="plan_id" class="form-select text-sm">
                                                <?php foreach ($plans as $p): ?>
                                                    <option value="<?= esc($p['id'], 'attr') ?>" <?= (int) $p['id'] === (int) ($c['plan_id'] ?? 0) ? 'selected' : '' ?>>
                                                        <?= esc($p['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Tenant.assignPlan'), 'attr') ?>">
                                                <i class="icon icon-save"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <?= esc($planName ?? lang('Tenant.noPlan')) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $cvalid ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $cvalid ? lang('Tenant.valid') : lang('Tenant.expired') ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php if ($cvalid && $valid): ?>
                                        <a href="<?= base_url('switch-company/' . $cid) ?>" class="btn btn-primary btn-sm">
                                            <?= lang('Tenant.enter') ?> <i class="icon icon-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-muted"><?= lang('Tenant.expired') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Servicios de nivel grupo (componentes de ámbito tenant) -->
<?php if ($services): ?>
    <div class="card">
        <div class="card-header">
            <i class="icon icon-puzzle"></i>
            <h5 class="font-semibold"><?= lang('Tenant.services') ?></h5>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($services as $s): ?>
                    <div class="rounded-lg border p-4 <?= $s['enabled'] ? 'border-line' : 'border-line opacity-60' ?>">
                        <div class="mb-3 flex items-center gap-2">
                            <i class="icon <?= esc($s['icon']) ?> text-xl <?= $s['enabled'] ? 'text-primary' : 'text-muted' ?>"></i>
                            <h6 class="flex-1 font-semibold"><?= esc($s['label']) ?></h6>
                            <span class="badge <?= $s['enabled'] ? 'badge-success' : 'badge-secondary' ?>">
                                <?= $s['enabled'] ? lang('App.serviceOn') : lang('App.serviceOff') ?>
                            </span>
                        </div>

                        <?php foreach ($s['limits'] as $l): ?>
                            <?php [$bar, $pct] = $tone((int) $l['used'], (int) $l['max']); ?>
                            <div class="mb-3 last:mb-0">
                                <div class="mb-1 flex items-baseline justify-between text-xs">
                                    <span class="text-muted"><?= esc($l['label']) ?></span>
                                    <span class="font-semibold">
                                        <?= (int) $l['used'] ?><?= $l['max'] > 0 ? ' / ' . (int) $l['max'] : '' ?>
                                    </span>
                                </div>
                                <?php if ($l['max'] > 0): ?>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-muted">
                                        <div class="h-full <?= $bar ?>" style="width: <?= $pct ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($s['enabled'] && $s['route']): ?>
                            <a href="<?= base_url($s['route']) ?>" class="btn btn-ghost btn-sm mt-3">
                                <?= lang('App.serviceOpen') ?> <i class="icon icon-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
