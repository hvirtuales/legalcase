<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\LeadModel;

/*
 * El Inicio responde las preguntas del §35 del documento de requerimientos, en el
 * orden en que se hacen al empezar el día: qué entró nuevo, a quién contactar ya,
 * qué se venció, quién tiene consulta hoy, qué tiene más probabilidad y qué está
 * listo para pasar a Docketwise.
 */
$ficha = static fn (array $l) => base_url('leadflow/leads/ficha/' . (int) $l['id']);
?>
<?php if (! $companyId): ?>
    <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
<?php elseif ($home === null): ?>
    <?= view('Components\LeadFlow\Views\partials\_noaccess') ?>
<?php else: ?>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h4 class="text-xl font-semibold"><?= lang('Lfl.homeTitle') ?></h4>
            <p class="text-sm text-muted"><?= lang('Lfl.homeSubtitle') ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= base_url('leadflow/pipeline') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-list-columns-reverse"></i> <?= lang('Lfl.navPipeline') ?>
            </a>
            <?php if ($canCreate && ! $planBlock): ?>
                <a href="<?= base_url('leadflow/leads/nuevo') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-person-plus"></i> <?= lang('Lfl.leadNew') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($planBlock): ?>
        <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= esc($planBlock) ?></span></div>
    <?php elseif ($leadsLeft !== null): ?>
        <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= esc(lang('Lfl.planLeft', [$leadsLeft])) ?></span></div>
    <?php endif; ?>

    <?php if ($isEmpty && $canCreate): ?>
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="icon icon-briefcase text-4xl text-faint"></i>
                <p class="mt-2 text-muted"><?= lang('Lfl.homeEmpty') ?></p>
                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    <a href="<?= base_url('leadflow/leads/nuevo') ?>" class="btn btn-primary btn-sm">
                        <i class="icon icon-person-plus"></i> <?= lang('Lfl.leadNew') ?>
                    </a>
                    <form action="<?= base_url('leadflow/leads/demo') ?>" method="post"
                        onsubmit="return confirm('<?= esc(lang('Lfl.demoConfirm'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-sparkles"></i> <?= lang('Lfl.demoButton') ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php /* Los totales llevan a su listado: el panel sirve para decidir a dónde ir. */ ?>
    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <?php
        $tarjetas = [
            ['leadflow/leads?estado=open', 'icon-people', $home['counts']['open'], lang('Lfl.kpiOpen')],
            ['leadflow/leads?vista=nuevos', 'icon-plus-circle', $home['counts']['new'], lang('Lfl.kpiNew')],
            ['leadflow/leads?vista=sin_asignar', 'icon-person-badge', $home['counts']['unassigned'], lang('Lfl.kpiUnassigned')],
            ['leadflow/leads?estado=won', 'icon-box-arrow-up-right', $home['counts']['ready'], lang('Lfl.kpiReady')],
        ];
        foreach ($tarjetas as [$ruta, $icono, $valor, $rotulo]): ?>
            <a href="<?= base_url($ruta) ?>" class="card transition hover:shadow-md">
                <div class="card-body">
                    <div class="flex items-center gap-2 text-muted">
                        <i class="icon <?= esc($icono, 'attr') ?>"></i>
                        <span class="text-xs"><?= esc($rotulo) ?></span>
                    </div>
                    <div class="mt-1 text-2xl font-semibold"><?= (int) $valor ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <?php
        // Las seis preguntas del §35 comparten forma: título, icono, filas y vacío.
        // Cada fila: [nombre, subtítulo] en texto plano (se escapan al pintar) y el
        // distintivo de la derecha ya compuesto y escapado pieza a pieza.
        $bloques = [
            [lang('Lfl.homeNew'), 'icon-plus-circle', $home['newLeads'], static fn ($l) => [
                LeadModel::fullName($l), ($services[(int) $l['service_id']] ?? '—') . ' · ' . Meta::since($l['created_at']),
                Meta::scoreBadge((int) $l['score'], $l['priority'])]],
            [lang('Lfl.homeContactNow'), 'icon-telephone', $home['contactNow'], static fn ($l) => [
                LeadModel::fullName($l), lang('Lfl.waiting', [Meta::since($l['created_at'])]),
                ! empty($l['assigned_user_id']) ? '<span class="text-xs text-muted">' . esc($team[(int) $l['assigned_user_id']] ?? '') . '</span>' : '<span class="badge badge-warning">' . esc(lang('Lfl.unassigned')) . '</span>']],
            [lang('Lfl.homeTopScore'), 'icon-star', $home['topScore'], static fn ($l) => [
                LeadModel::fullName($l), $services[(int) $l['service_id']] ?? '—',
                Meta::scoreBadge((int) $l['score'], $l['priority'])]],
            [lang('Lfl.homeReady'), 'icon-box-arrow-up-right', $home['ready'], static fn ($l) => [
                LeadModel::fullName($l), lang('Lfl.wonSince', [Meta::since($l['won_at'])]),
                '<span class="badge badge-success">' . esc(lang('Lfl.stWon')) . '</span>']],
        ];
        foreach ($bloques as [$titulo, $icono, $filas, $celda]): ?>
            <div class="card">
                <div class="card-header"><i class="icon <?= esc($icono, 'attr') ?>"></i><h5 class="font-semibold"><?= esc($titulo) ?></h5></div>
                <div class="card-body">
                    <?php if (! $filas): ?>
                        <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
                    <?php else: ?>
                        <ul class="divide-y divide-line">
                            <?php foreach ($filas as $l): ?>
                                <?php [$nombre, $sub, $extraHtml] = $celda($l); ?>
                                <li class="flex items-center justify-between gap-2 py-2">
                                    <div class="min-w-0">
                                        <a class="block truncate text-sm font-medium text-primary hover:underline" href="<?= $ficha($l) ?>"><?= esc($nombre) ?></a>
                                        <div class="truncate text-xs text-muted"><?= esc($sub) ?></div>
                                    </div>
                                    <div class="shrink-0"><?= $extraHtml ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2"><i class="icon icon-exclamation-circle"></i><h5 class="font-semibold"><?= lang('Lfl.homeOverdue') ?></h5></div>
                <a href="<?= base_url('leadflow/actividades?vista=vencidas') ?>" class="btn btn-ghost btn-sm"><?= lang('Lfl.viewAll') ?> <i class="icon icon-chevron-right"></i></a>
            </div>
            <div class="card-body">
                <?php if (! $home['overdue']): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($home['overdue'] as $act): ?>
                            <li class="flex items-center justify-between gap-2 py-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium">
                                        <i class="icon <?= esc(Meta::ACTIVITY_ICONS[$act['activity_type']] ?? 'icon-circle', 'attr') ?>"></i> <?= esc($act['title']) ?>
                                    </div>
                                    <a class="truncate text-xs text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $act['lead_id'] . '?tab=actividades') ?>">
                                        <?= esc(trim($act['first_name'] . ' ' . $act['last_name'])) ?>
                                    </a>
                                </div>
                                <span class="badge badge-danger shrink-0"><?= esc(Meta::date($act['due_at'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2"><i class="icon icon-headset"></i><h5 class="font-semibold"><?= lang('Lfl.homeToday') ?></h5></div>
                <a href="<?= base_url('leadflow/consultas') ?>" class="btn btn-ghost btn-sm"><?= lang('Lfl.viewAll') ?> <i class="icon icon-chevron-right"></i></a>
            </div>
            <div class="card-body">
                <?php if (! $home['today']): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($home['today'] as $ap): ?>
                            <li class="flex items-center justify-between gap-2 py-2">
                                <div class="min-w-0">
                                    <a class="truncate text-sm font-medium text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $ap['lead_id'] . '?tab=consultas') ?>">
                                        <?= esc(trim($ap['first_name'] . ' ' . $ap['last_name'])) ?>
                                    </a>
                                    <div class="text-xs text-muted">
                                        <?= esc(Meta::label(Meta::APPT_MODES, $ap['mode'])) ?>
                                        <?= $ap['lawyer_user_id'] ? ' · ' . esc($team[(int) $ap['lawyer_user_id']] ?? '') : '' ?>
                                    </div>
                                </div>
                                <span class="badge <?= Meta::color(Meta::APPT_COLORS, $ap['status']) ?> shrink-0"><?= esc(date('H:i', strtotime($ap['scheduled_at']))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
