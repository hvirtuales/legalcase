<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/*
 * Manual de LeadFlow, dentro de Configuración.
 *
 * Abre con el CATÁLOGO de funcionalidades —qué sabe hacer el componente, una por
 * una, generado desde `HelpCatalog`— y sigue ordenado como se usa el sistema —recorrido, puesta en marcha, conceptos,
 * visibilidad, score, captación, automatizaciones, Docketwise y qué hacer cuando
 * algo falla—, no por pantallas: quien llega nuevo no sabe todavía cómo se llaman
 * las pantallas.
 *
 * La puesta en marcha muestra el ESTADO REAL de esta firma, no una lista genérica,
 * y cada punto enlaza a la pestaña que lo resuelve solo si quien mira puede
 * configurar.
 *
 * @var array $setup
 * @var bool  $canConfigure
 * @var array $planStatus  servicios del plan que esta empresa tiene
 * @var array $myPerms     permisos de quien mira, para no ofrecer enlaces que no abren
 */
$setupRows = [
    ['team', 'leadflow/directorio', 'icon-people'],
    ['services', 'leadflow/configuracion/servicios', 'icon-briefcase'],
    ['stages', 'leadflow/configuracion/etapas', 'icon-list-columns-reverse'],
    ['scoring', 'leadflow/configuracion/scoring', 'icon-star'],
    ['forms', 'leadflow/formularios', 'icon-clipboard'],
    ['docketwise', 'leadflow/docketwise', 'icon-plug'],
];

$labels = ['team' => 'helpSetupTeam', 'services' => 'helpSetupServices', 'stages' => 'helpSetupStages',
    'scoring' => 'helpSetupScoring', 'forms' => 'helpSetupForms', 'docketwise' => 'helpSetupDw'];

$sections = [
    'funciones' => ['icon-list', 'fnTitle'],
    'flujo'    => ['icon-signpost', 'helpFlowTitle'],
    'montaje'  => ['icon-check-circle', 'helpSetupTitle'],
    'palabras' => ['icon-journal-text', 'helpConceptsTitle'],
    'quienve'  => ['icon-people', 'helpRolesTitle'],
    'score'    => ['icon-star', 'helpScoreTitle'],
    'captar'   => ['icon-clipboard', 'helpFormsTitle'],
    'autos'    => ['icon-robot', 'helpAutoTitle'],
    'docketwise' => ['icon-plug', 'helpDwTitle'],
    'problemas'  => ['icon-life-preserver', 'helpFaqTitle'],
];

$concepts = ['Lead', 'Stage', 'Status', 'Score', 'Priority', 'Service', 'Source', 'Activity', 'Appt', 'Document', 'Note'];

// El catálogo de funcionalidades: una entrada por cosa que el componente sabe
// hacer. La definición está en `HelpCatalog` y no aquí, para que añadir una sea
// una entrada y dos claves de idioma, sin tocar esta plantilla.
$catalog = \Components\LeadFlow\Libraries\HelpCatalog::items();
$roles    = ['Sup', 'Adv', 'Law', 'Aud'];
?>
<div class="card">
    <div class="card-header"><i class="icon icon-book"></i><h5 class="font-semibold"><?= lang('Lfl.helpTitle') ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_settings_tabs', ['active' => 'help', 'canConfigure' => $canConfigure]) ?>

        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.helpIntro') ?></p>

        <nav class="mb-6 flex flex-wrap gap-2">
            <?php foreach ($sections as $anchor => [$icon, $key]): ?>
                <a href="#<?= esc($anchor, 'attr') ?>" class="btn btn-outline btn-sm">
                    <i class="icon <?= esc($icon, 'attr') ?>"></i> <?= lang('Lfl.' . $key) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- ── Qué hace cada cosa ─────────────────────────────────────────── -->
        <section id="funciones" class="mb-8 scroll-mt-4">
            <h6 class="mb-1 flex items-center gap-2 text-base font-semibold"><i class="icon icon-list"></i> <?= lang('Lfl.fnTitle') ?></h6>
            <p class="mb-4 text-sm text-muted"><?= lang('Lfl.fnIntro') ?></p>

            <?php foreach ($catalog as $group => $items): ?>
                <?php $g = ucfirst($group); ?>
                <div class="mb-5">
                    <p class="flex items-center gap-2 text-sm font-semibold">
                        <i class="icon <?= esc(\Components\LeadFlow\Libraries\HelpCatalog::GROUPS[$group], 'attr') ?>"></i>
                        <?= lang('Lfl.fnGrp' . $g) ?>
                    </p>
                    <p class="mb-2 text-xs text-muted"><?= lang('Lfl.fnGrp' . $g . 'D') ?></p>

                    <ul class="divide-y divide-line rounded-md border border-line">
                        <?php foreach ($items as $it): ?>
                            <?php
                            $plan    = $it['plan'] ?? null;
                            $hasPlan = $plan === null ? null : (bool) ($planStatus[$plan] ?? false);
                            // El enlace solo si existe pantalla Y quien mira tiene el permiso:
                            // ofrecer un enlace que devuelve «no autorizado» es peor que no ofrecerlo.
                            $canOpen = ! empty($it['route']) && (empty($it['perm']) || in_array($it['perm'], $myPerms, true));
                            ?>
                            <li class="flex flex-wrap items-start gap-x-3 gap-y-2 p-3">
                                <i class="icon <?= esc($it['icon'], 'attr') ?> mt-1 shrink-0 text-muted"></i>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium"><?= lang('Lfl.fn' . $it['key']) ?></p>
                                    <p class="mt-0.5 text-xs text-muted"><?= lang('Lfl.fn' . $it['key'] . 'D') ?></p>

                                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                        <?php if (! empty($it['perm'])): ?>
                                            <span class="badge badge-light gap-1"><?= lang('Lfl.fnNeedsPerm') ?>:<code><?= esc($it['perm']) ?></code></span>
                                        <?php endif; ?>
                                        <?php if (! empty($it['where'])): ?>
                                            <span class="text-xs text-muted"><?= lang('Lfl.fnWhere') ?>: <?= lang('Lfl.' . $it['where']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($hasPlan !== null): ?>
                                            <span class="badge <?= $hasPlan ? 'badge-success' : 'badge-warning' ?>">
                                                <?= lang($hasPlan ? 'Lfl.fnPlanOn' : 'Lfl.fnPlanOff') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($canOpen): ?>
                                    <a href="<?= base_url($it['route']) ?>" class="btn btn-ghost btn-sm shrink-0">
                                        <i class="icon icon-chevron-right"></i> <?= lang('Lfl.fnOpen') ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- ── El recorrido ───────────────────────────────────────────────── -->
        <section id="flujo" class="mb-8 scroll-mt-4">
            <h6 class="mb-1 flex items-center gap-2 text-base font-semibold"><i class="icon icon-signpost"></i> <?= lang('Lfl.helpFlowTitle') ?></h6>
            <p class="mb-3 text-sm text-muted"><?= lang('Lfl.helpFlowIntro') ?></p>
            <ol class="list-decimal space-y-1 pl-5 text-sm">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <li><?= lang('Lfl.helpFlow' . $i) ?></li>
                <?php endfor; ?>
            </ol>
        </section>

        <!-- ── Puesta en marcha, con el estado real ───────────────────────── -->
        <section id="montaje" class="mb-8 scroll-mt-4">
            <h6 class="mb-1 flex items-center gap-2 text-base font-semibold"><i class="icon icon-check-circle"></i> <?= lang('Lfl.helpSetupTitle') ?></h6>
            <p class="mb-3 text-sm text-muted"><?= lang('Lfl.helpSetupIntro') ?></p>
            <ul class="divide-y divide-line">
                <?php foreach ($setupRows as [$key, $route, $icon]): ?>
                    <?php
                    $value = $setup[$key] ?? 0;
                    $done  = $key === 'docketwise' ? (bool) $value : (int) $value > 0;
                    ?>
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
                        <i class="icon <?= $done ? 'icon-check-circle text-success' : 'icon-exclamation-circle text-warning' ?> shrink-0"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium"><?= lang('Lfl.' . $labels[$key]) ?></p>
                            <p class="text-xs text-muted"><?= lang('Lfl.' . $labels[$key] . 'Hint') ?></p>
                        </div>
                        <span class="badge <?= $done ? 'badge-success' : 'badge-warning' ?>">
                            <?php if ($key === 'docketwise'): ?>
                                <?= lang($done ? 'Lfl.helpSetupDone' : 'Lfl.helpSetupPending') ?>
                            <?php else: ?>
                                <?= $done ? esc(lang('Lfl.helpSetupCount', [(int) $value])) : lang('Lfl.helpSetupNone') ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($canConfigure): ?>
                            <a href="<?= base_url($route) ?>" class="btn btn-ghost btn-sm"><i class="icon <?= esc($icon, 'attr') ?>"></i> <?= lang('Lfl.edit') ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- ── Conceptos ──────────────────────────────────────────────────── -->
        <section id="palabras" class="mb-8 scroll-mt-4">
            <h6 class="mb-3 flex items-center gap-2 text-base font-semibold"><i class="icon icon-journal-text"></i> <?= lang('Lfl.helpConceptsTitle') ?></h6>
            <dl class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <?php foreach ($concepts as $c): ?>
                    <div>
                        <dt class="text-sm font-medium"><?= lang('Lfl.helpCo' . $c) ?></dt>
                        <dd class="text-xs text-muted"><?= lang('Lfl.helpCo' . $c . 'D') ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <!-- ── Quién ve qué ───────────────────────────────────────────────── -->
        <section id="quienve" class="mb-8 scroll-mt-4">
            <h6 class="mb-1 flex items-center gap-2 text-base font-semibold"><i class="icon icon-people"></i> <?= lang('Lfl.helpRolesTitle') ?></h6>
            <p class="mb-3 text-sm text-muted"><?= lang('Lfl.helpRolesIntro') ?></p>
            <dl class="space-y-2">
                <?php foreach ($roles as $r): ?>
                    <div class="flex flex-wrap items-baseline gap-2">
                        <dt class="text-sm font-medium"><?= lang('Lfl.helpRole' . $r) ?></dt>
                        <dd class="flex-1 text-xs text-muted"><?= lang('Lfl.helpRole' . $r . 'D') ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
            <div class="alert alert-info mt-3"><i class="icon icon-info-circle"></i><span><?= lang('Lfl.helpRolesNone') ?></span></div>
        </section>

        <!-- ── Score ──────────────────────────────────────────────────────── -->
        <section id="score" class="mb-8 scroll-mt-4">
            <h6 class="mb-3 flex items-center gap-2 text-base font-semibold"><i class="icon icon-star"></i> <?= lang('Lfl.helpScoreTitle') ?></h6>
            <ul class="list-disc space-y-1 pl-5 text-sm">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <li><?= lang('Lfl.helpScore' . $i) ?></li>
                <?php endfor; ?>
            </ul>
        </section>

        <!-- ── Captación ──────────────────────────────────────────────────── -->
        <section id="captar" class="mb-8 scroll-mt-4">
            <h6 class="mb-3 flex items-center gap-2 text-base font-semibold"><i class="icon icon-clipboard"></i> <?= lang('Lfl.helpFormsTitle') ?></h6>
            <ul class="list-disc space-y-1 pl-5 text-sm">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <li><?= lang('Lfl.helpForms' . $i) ?></li>
                <?php endfor; ?>
            </ul>
        </section>

        <!-- ── Automatizaciones ───────────────────────────────────────────── -->
        <section id="autos" class="mb-8 scroll-mt-4">
            <h6 class="mb-3 flex items-center gap-2 text-base font-semibold"><i class="icon icon-robot"></i> <?= lang('Lfl.helpAutoTitle') ?></h6>
            <ul class="list-disc space-y-1 pl-5 text-sm">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <li><?= lang('Lfl.helpAuto' . $i) ?></li>
                <?php endfor; ?>
            </ul>
        </section>

        <!-- ── Docketwise ─────────────────────────────────────────────────── -->
        <section id="docketwise" class="mb-8 scroll-mt-4">
            <h6 class="mb-3 flex items-center gap-2 text-base font-semibold"><i class="icon icon-plug"></i> <?= lang('Lfl.helpDwTitle') ?></h6>
            <ul class="list-disc space-y-1 pl-5 text-sm">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <li><?= lang('Lfl.helpDw' . $i) ?></li>
                <?php endfor; ?>
            </ul>
        </section>

        <!-- ── Cuando algo no sale ────────────────────────────────────────── -->
        <section id="problemas" class="scroll-mt-4">
            <h6 class="mb-3 flex items-center gap-2 text-base font-semibold"><i class="icon icon-life-preserver"></i> <?= lang('Lfl.helpFaqTitle') ?></h6>
            <dl class="divide-y divide-line">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="py-2">
                        <dt class="text-sm font-medium"><?= lang('Lfl.helpFaqQ' . $i) ?></dt>
                        <dd class="text-xs text-muted"><?= lang('Lfl.helpFaqA' . $i) ?></dd>
                    </div>
                <?php endfor; ?>
            </dl>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
