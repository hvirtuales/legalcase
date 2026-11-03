<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Panel Empresa: lo que la empresa activa tiene contratado y cómo va de consumo.
 *
 * Los servicios y los cupos NO están cableados aquí: salen de
 * `ComponentManager::planStatus()`, que lee el `plan_flag` y el `plan_limits`
 * declarados por cada componente. Un componente nuevo aparece solo.
 *
 * @var array  $company @var ?array $plan @var ?array $sub @var bool $valid
 * @var ?int   $daysLeft @var array $services @var int $members @var bool $isManager
 */
$enabled = array_values(array_filter($services, static fn ($s) => $s['enabled']));
$off     = array_values(array_filter($services, static fn ($s) => !$s['enabled']));

/** Barra de consumo: verde tranquila, ámbar al 80 %, roja al llegar. */
$tone = static function (int $used, int $max): array {
    if ($max <= 0) {
        return ['bg-success', 0];   // sin límite
    }
    $pct = min(100, (int) round($used * 100 / $max));

    return [$pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-primary'), $pct];
};
?>

<!-- Cabecera: quién es la empresa y qué plan tiene -->
<div class="card mb-6">
    <div class="card-body flex flex-wrap items-center gap-4">
        <?php if (!empty($company['logo'])): ?>
            <img src="<?= media_url($company['logo']) ?>" alt="" class="h-14 w-14 rounded-md object-contain">
        <?php else: ?>
            <div class="flex h-14 w-14 items-center justify-center rounded-md bg-surface-muted">
                <i class="icon icon-buildings text-2xl text-muted"></i>
            </div>
        <?php endif; ?>

        <div class="min-w-0 flex-1">
            <h2 class="truncate text-xl font-semibold"><?= esc($company['name']) ?></h2>
            <p class="text-sm text-muted">
                <?= lang('App.welcome') ?>, <?= esc($user['first_name']) ?>
                <?= !empty($company['nit']) ? ' · NIT ' . esc($company['nit']) : '' ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <?php if ($plan): ?>
                <span class="badge <?= $valid ? 'badge-primary' : 'badge-danger' ?> px-3 py-1">
                    <i class="icon icon-box-seam"></i> <?= esc($plan['name']) ?>
                </span>
            <?php else: ?>
                <span class="badge badge-light px-3 py-1"><?= lang('App.planNone') ?></span>
            <?php endif; ?>

            <?php if ($isManager): ?>
                <a href="<?= base_url('mi-empresa') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-pencil"></i> <?= lang('App.myCompany') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Vigencia y tamaño -->
<div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('App.planStatus') ?></p>
                <span class="badge <?= $valid ? 'badge-success' : 'badge-danger' ?>">
                    <?= $valid ? lang('App.planValid') : lang('App.planExpired') ?>
                </span>
            </div>
            <i class="icon <?= $valid ? 'icon-check-circle text-success' : 'icon-exclamation-circle text-danger' ?> text-3xl"></i>
        </div>
    </div>

    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('App.planEnds') ?></p>
                <p class="font-medium">
                    <?= $sub && !empty($sub['end_date']) ? esc(date('d/m/Y', strtotime($sub['end_date']))) : '—' ?>
                </p>
                <?php if ($daysLeft !== null): ?>
                    <p class="text-xs <?= $daysLeft < 0 ? 'text-danger' : ($daysLeft <= 15 ? 'text-warning' : 'text-muted') ?>">
                        <?= $daysLeft < 0 ? lang('App.planDaysAgo', [abs($daysLeft)]) : lang('App.planDaysLeft', [$daysLeft]) ?>
                    </p>
                <?php endif; ?>
            </div>
            <i class="icon icon-clock-history text-3xl text-info"></i>
        </div>
    </div>

    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('App.companyMembers') ?></p>
                <h3 class="text-2xl font-bold"><?= (int) $members ?></h3>
            </div>
            <i class="icon icon-people text-3xl text-primary"></i>
        </div>
    </div>

    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('App.servicesActive') ?></p>
                <h3 class="text-2xl font-bold"><?= count($enabled) ?><span class="text-base text-muted">/<?= count($services) ?></span></h3>
            </div>
            <i class="icon icon-puzzle text-3xl text-success"></i>
        </div>
    </div>
</div>

<?php if (!$valid): ?>
    <div class="alert alert-danger mb-6">
        <i class="icon icon-exclamation-circle"></i>
        <span><?= lang('App.planExpiredHint') ?></span>
    </div>
<?php endif; ?>

<!-- Servicios del plan, con su consumo -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-puzzle"></i>
        <h5 class="font-semibold"><?= lang('App.planServices') ?></h5>
    </div>
    <div class="card-body">
        <?php if (!$services): ?>
            <p class="text-muted"><?= lang('App.planNoServices') ?></p>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($services as $s): ?>
                    <div class="rounded-lg border p-4 <?= $s['enabled'] ? 'border-line' : 'border-line opacity-60' ?>">
                        <div class="mb-3 flex items-center gap-2">
                            <i class="icon <?= esc($s['icon']) ?> text-xl <?= $s['enabled'] ? 'text-primary' : 'text-muted' ?>"></i>
                            <h6 class="flex-1 font-semibold"><?= esc($s['label']) ?></h6>
                            <?php if ($s['enabled']): ?>
                                <span class="badge badge-success"><?= lang('App.serviceOn') ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?= lang('App.serviceOff') ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($s['enabled'] && $s['limits']): ?>
                            <?php foreach ($s['limits'] as $l): ?>
                                <?php [$bar, $pct] = $tone((int) $l['used'], (int) $l['max']); ?>
                                <div class="mb-3 last:mb-0">
                                    <div class="mb-1 flex items-baseline justify-between text-xs">
                                        <span class="text-muted"><?= esc($l['label']) ?></span>
                                        <span class="font-semibold">
                                            <?= (int) $l['used'] ?><?= $l['max'] > 0 ? ' / ' . (int) $l['max'] : '' ?>
                                            <?php if ($l['max'] <= 0): ?>
                                                <span class="font-normal text-muted"><?= lang('App.planUnlimited') ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($l['max'] > 0): ?>
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-muted">
                                            <div class="h-full <?= $bar ?>" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($s['enabled']): ?>
                            <p class="text-xs text-muted"><?= lang('App.serviceNoLimits') ?></p>
                        <?php else: ?>
                            <p class="text-xs text-muted"><?= lang('App.serviceOffHint') ?></p>
                        <?php endif; ?>

                        <?php if ($s['enabled'] && $s['route']): ?>
                            <a href="<?= base_url($s['route']) ?>" class="btn btn-ghost btn-sm mt-3">
                                <?= lang('App.serviceOpen') ?> <i class="icon icon-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($plan && !empty($plan['features'])): ?>
    <div class="card">
        <div class="card-header">
            <i class="icon icon-list-check"></i>
            <h5 class="font-semibold"><?= lang('App.planIncludes', [$plan['name']]) ?></h5>
        </div>
        <div class="card-body">
            <p class="whitespace-pre-line text-sm text-muted"><?= esc($plan['features']) ?></p>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
