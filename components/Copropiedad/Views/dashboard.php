<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;

?>
<?php if (! $companyId): ?>
    <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
<?php else: ?>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h4 class="text-xl font-semibold"><?= lang('Cop.dashTitle') ?></h4>
            <p class="text-sm text-muted"><?= lang('Cop.dashSubtitle') ?></p>
        </div>
        <?php if (! $planBlock): ?>
            <a href="<?= base_url('copropiedad/inmuebles/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Cop.unitsNew') ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($planBlock): ?>
        <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= esc($planBlock) ?></span></div>
    <?php elseif ($unitsLeft !== null): ?>
        <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= esc(lang('Cop.planUnitsLeft', [$unitsLeft])) ?></span></div>
    <?php endif; ?>

    <?php if ($totalUnits === 0): ?>
        <div class="card">
            <div class="card-body text-center">
                <i class="icon icon-buildings text-4xl text-faint"></i>
                <p class="mt-2 text-muted"><?= lang('Cop.dashEmpty') ?></p>
                <a href="<?= base_url('copropiedad/torres/nuevo') ?>" class="btn btn-primary btn-sm mt-3">
                    <i class="icon icon-plus-circle"></i> <?= lang('Cop.dashStart') ?>
                </a>
            </div>
        </div>
    <?php else: ?>

        <?php /* Los cinco totales. Cada tarjeta es un enlace a su listado: el panel
                sirve para decidir a dónde ir, no solo para mirar. */ ?>
        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
            <?php
            $tarjetas = [
                ['copropiedad/inmuebles', 'icon-buildings', $totalUnits, lang('Cop.dashUnits')],
                ['copropiedad/personas', 'icon-people', $totalPeople, lang('Cop.dashPeople')],
                ['copropiedad/familias', 'icon-person-badge', $families, lang('Cop.dashFamilies')],
                ['copropiedad/vehiculos', 'icon-key', $vehicles, lang('Cop.dashVehicles')],
                ['copropiedad/mascotas', 'icon-star', $pets, lang('Cop.dashPets')],
            ];

            foreach ($tarjetas as [$ruta, $icono, $valor, $rotulo]): ?>
                <a href="<?= base_url($ruta) ?>" class="card transition hover:shadow-md">
                    <div class="card-body">
                        <div class="flex items-center gap-2 text-muted">
                            <i class="icon <?= esc($icono, 'attr') ?>"></i>
                            <span class="text-xs"><?= esc($rotulo) ?></span>
                        </div>
                        <div class="mt-1 text-2xl font-semibold"><?= (int) $valor ?></div>
                        <?php if ($ruta === 'copropiedad/mascotas' && $dangerous > 0): ?>
                            <div class="mt-1 text-xs text-danger"><?= esc(lang('Cop.dashDangerous', [$dangerous])) ?></div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php /* El aviso que justifica el panel: los coeficientes tienen que sumar
                100 %, y ese error no se ve en ninguna ficha por separado. */ ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <i class="icon icon-percent text-lg"></i>
                        <span class="font-semibold"><?= lang('Cop.dashCoeff') ?></span>
                        <span class="badge <?= $coeffWarning ? 'badge-warning' : 'badge-success' ?>">
                            <?= esc(rtrim(rtrim(number_format($coeffSum, 4, ',', '.'), '0'), ',')) ?> %
                        </span>
                    </div>
                    <p class="text-sm <?= $coeffWarning ? 'text-warning' : 'text-muted' ?>">
                        <?= esc($coeffWarning
                            ? lang('Cop.dashCoeffWarn', [rtrim(rtrim(number_format($coeffSum, 4, ',', '.'), '0'), ',')])
                            : lang('Cop.dashCoeffOk')) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <?php
            // Los tres desgloses comparten forma: rótulo, mapa y datos.
            $desgloses = [
                [lang('Cop.dashByOccup'), Meta::OCCUPANCIES, $byOccupancy, Meta::OCCUPANCY_COLORS],
                [lang('Cop.dashByKind'), Meta::UNIT_KINDS, $byKind, []],
                [lang('Cop.dashByRole'), Meta::ROLES, $byRole, Meta::ROLE_COLORS],
            ];

            foreach ($desgloses as [$rotulo, $mapa, $datos, $colores]):
                $suma = array_sum($datos) ?: 1; ?>
                <div class="card">
                    <div class="card-header"><h5 class="font-semibold"><?= esc($rotulo) ?></h5></div>
                    <div class="card-body">
                        <?php foreach ($mapa as $valor => $claveIdioma): ?>
                            <?php $n = (int) ($datos[$valor] ?? 0); ?>
                            <div class="mb-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span><?= esc(lang('Cop.' . $claveIdioma)) ?></span>
                                    <span class="font-semibold"><?= (int) $n ?></span>
                                </div>
                                <?php /* Barra proporcional: el número solo no dice si
                                        12 arrendados son muchos o pocos. */ ?>
                                <div class="mt-1 h-1.5 w-full rounded bg-surface-alt">
                                    <div class="h-1.5 rounded bg-primary" style="width: <?= (int) round($n * 100 / $suma) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php /* Lo que vence. La otra mitad del valor de guardar una fecha de
                vencimiento es que alguien la vea a tiempo. */ ?>
        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h5 class="font-semibold"><?= lang('Cop.dashExpiring') ?></h5></div>
                <div class="card-body">
                    <?php if (! $expiring): ?>
                        <p class="text-sm text-muted"><?= lang('Cop.dashNothingDue') ?></p>
                    <?php else: ?>
                        <ul class="divide-y divide-border">
                            <?php foreach ($expiring as $d): ?>
                                <li class="flex items-center justify-between py-2">
                                    <div>
                                        <div class="text-sm font-medium"><?= esc($d['title']) ?></div>
                                        <div class="text-xs text-muted"><?= esc(Meta::label(Meta::DOC_KINDS, $d['kind'])) ?></div>
                                    </div>
                                    <span class="badge <?= $d['expires_at'] < date('Y-m-d') ? 'badge-danger' : 'badge-warning' ?>">
                                        <?= esc($d['expires_at']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="font-semibold"><?= lang('Cop.dashVaccines') ?></h5></div>
                <div class="card-body">
                    <?php if (! $vaccines): ?>
                        <p class="text-sm text-muted"><?= lang('Cop.dashNothingDue') ?></p>
                    <?php else: ?>
                        <ul class="divide-y divide-border">
                            <?php foreach ($vaccines as $p): ?>
                                <li class="flex items-center justify-between py-2">
                                    <div>
                                        <div class="text-sm font-medium"><?= esc($p['name']) ?></div>
                                        <div class="text-xs text-muted">
                                            <?= esc(Meta::label(Meta::SPECIES, $p['species'])) ?>
                                            <?= $p['unit_code'] ? ' · ' . esc($p['unit_code']) : '' ?>
                                        </div>
                                    </div>
                                    <span class="badge <?= $p['vaccine_expires'] < date('Y-m-d') ? 'badge-danger' : 'badge-warning' ?>">
                                        <?= esc($p['vaccine_expires']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php /* Las autorizaciones, con su semáforo. */ ?>
        <div class="card mt-4">
            <div class="card-header justify-between">
                <h5 class="font-semibold"><?= lang('Cop.dashAuths') ?></h5>
                <a href="<?= base_url('copropiedad/autorizaciones') ?>" class="btn btn-ghost btn-sm">
                    <?= lang('Cop.view') ?> <i class="icon icon-chevron-right"></i>
                </a>
            </div>
            <div class="card-body flex flex-wrap gap-4">
                <?php foreach (Meta::AUTH_STATUSES as $valor => $claveIdioma): ?>
                    <div class="flex items-center gap-2">
                        <span class="badge <?= Meta::color(Meta::AUTH_STATUS_COLORS, $valor) ?>"><?= esc(lang('Cop.' . $claveIdioma)) ?></span>
                        <span class="text-lg font-semibold"><?= (int) ($authCounts[$valor] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
