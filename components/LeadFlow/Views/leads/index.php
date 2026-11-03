<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\LeadModel;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-people"></i>
            <h5 class="font-semibold"><?= lang('Lfl.navLeads') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex flex-wrap gap-2">
                <?php if ($canImport): ?>
                    <a href="<?= base_url('leadflow/leads/importar') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-upload"></i> <?= lang('Lfl.import') ?></a>
                <?php endif; ?>
                <a href="<?= base_url('leadflow/pipeline') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-list-columns-reverse"></i> <?= lang('Lfl.navPipeline') ?></a>
                <?php if ($canCreate && ! $planBlock): ?>
                    <a href="<?= base_url('leadflow/leads/nuevo') ?>" class="btn btn-primary btn-sm"><i class="icon icon-person-plus"></i> <?= lang('Lfl.leadNew') ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
        <?php elseif ($access->scope === 'none'): ?>
            <?= view('Components\LeadFlow\Views\partials\_noaccess') ?>
        <?php else: ?>
            <?php if ($planBlock): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= esc($planBlock) ?></span></div>
            <?php elseif ($leadsLeft !== null): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= esc(lang('Lfl.planLeft', [$leadsLeft])) ?></span></div>
            <?php endif; ?>

            <?php /* Las vistas del menú §7 (Todos, Mis leads, Nuevos, Sin asignar) son
                    pestañas de esta misma pantalla, no cuatro menús. */ ?>
            <nav class="tabs mb-4">
                <?php foreach (['todos' => 'viewAll', 'mis' => 'viewMine', 'nuevos' => 'viewNew', 'sin_asignar' => 'viewUnassigned'] as $v => $label): ?>
                    <a href="<?= base_url('leadflow/leads?vista=' . $v) ?>" class="tab-link <?= $view === $v ? 'active' : '' ?>"><?= lang('Lfl.' . $label) ?></a>
                <?php endforeach; ?>
            </nav>

            <form method="get" action="<?= base_url('leadflow/leads') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <input type="hidden" name="vista" value="<?= esc($view, 'attr') ?>">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Lfl.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Lfl.leadSearch'), 'attr') ?>">
                    </div>
                </div>
                <?php
                $selects = [
                    ['etapa', lang('Lfl.stage'), array_column($stages, 'name', 'id')],
                    ['servicio', lang('Lfl.service'), $services],
                    ['fuente', lang('Lfl.source'), $sources],
                    ['prioridad', lang('Lfl.priority'), Meta::options(Meta::PRIORITIES)],
                    ['estado', lang('Lfl.status'), Meta::options(Meta::STATUSES)],
                ];
                foreach ($selects as [$name, $label, $opts]): ?>
                    <div class="form-group mb-0">
                        <label for="f-<?= esc($name, 'attr') ?>" class="form-label text-xs"><?= esc($label) ?></label>
                        <select id="f-<?= esc($name, 'attr') ?>" name="<?= esc($name, 'attr') ?>" class="form-select">
                            <option value=""><?= lang('Lfl.all') ?></option>
                            <?php foreach ($opts as $val => $lbl): ?>
                                <option value="<?= esc((string) $val, 'attr') ?>" <?= (string) $filters[$name] === (string) $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Lfl.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <a href="<?= base_url('leadflow/leads?vista=' . $view) ?>" class="btn btn-ghost" title="<?= esc(lang('Lfl.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                    </div>
                </div>
            </form>

            <?php if ($isEmpty && $canCreate): ?>
                <div class="alert alert-info">
                    <i class="icon icon-info-circle"></i>
                    <span class="flex flex-1 flex-wrap items-center justify-between gap-2">
                        <?= lang('Lfl.demoHint') ?>
                        <form action="<?= base_url('leadflow/leads/demo') ?>" method="post" onsubmit="return confirm('<?= esc(lang('Lfl.demoConfirm'), 'js') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-sparkles"></i> <?= lang('Lfl.demoButton') ?></button>
                        </form>
                    </span>
                </div>
            <?php endif; ?>

            <?= view('partials/datatable', [
                'dt'          => $dt,
                'showSearch'  => false,
                'baseUrl'     => base_url('leadflow/leads'),
                'extraParams' => ['vista' => $view] + $filters,
                'emptyText'   => lang('Lfl.noLeads'),
                'columns'     => [
                    ['key' => 'last_name', 'label' => lang('Lfl.lead'), 'sortable' => true, 'sortKey' => 'cmp_lfl_leads.last_name',
                        'render' => static fn ($l) => '<a class="font-semibold text-primary hover:underline" href="' . base_url('leadflow/leads/ficha/' . (int) $l['id']) . '">'
                            . esc(LeadModel::fullName($l)) . '</a><div class="text-xs text-muted">' . esc($l['email'] ?: ($l['phone'] ?: '—')) . '</div>'],
                    ['key' => 'service_name', 'label' => lang('Lfl.service'), 'sortable' => false,
                        'render' => static fn ($l) => esc($l['service_name'] ?: '—')],
                    ['key' => 'stage_name', 'label' => lang('Lfl.stage'), 'sortable' => false,
                        'render' => static fn ($l) => Meta::stageBadge($l['stage_name'] ? ['name' => $l['stage_name'], 'color' => $l['stage_color']] : null)],
                    ['key' => 'score', 'label' => lang('Lfl.score'), 'sortable' => true, 'sortKey' => 'cmp_lfl_leads.score',
                        'render' => static fn ($l) => Meta::scoreBadge((int) $l['score'], $l['priority'])],
                    ['key' => 'assigned_user_id', 'label' => lang('Lfl.owner'), 'sortable' => false,
                        'render' => static fn ($l) => $l['assigned_user_id'] ? esc($team[(int) $l['assigned_user_id']] ?? '—')
                            : '<span class="badge badge-warning">' . esc(lang('Lfl.unassigned')) . '</span>'],
                    ['key' => 'next_activity_at', 'label' => lang('Lfl.nextActivity'), 'sortable' => true, 'sortKey' => 'cmp_lfl_leads.next_activity_at',
                        'render' => static fn ($l) => $l['next_activity_at']
                            ? '<span class="' . ($l['next_activity_at'] < date('Y-m-d H:i:s') ? 'text-danger' : '') . '">' . esc(Meta::date($l['next_activity_at'])) . '</span>'
                            : '<span class="text-muted">—</span>'],
                    ['key' => 'created_at', 'label' => lang('Lfl.created'), 'sortable' => true, 'sortKey' => 'cmp_lfl_leads.created_at',
                        'render' => static fn ($l) => esc(Meta::date($l['created_at'], false)) . '<div class="text-xs text-muted">' . esc(Meta::since($l['created_at'])) . '</div>'],
                ],
                'actions' => static fn ($l) => '<div class="flex justify-end gap-1">'
                    . '<a href="' . base_url('leadflow/leads/ficha/' . (int) $l['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Lfl.viewCard'), 'attr') . '"><i class="icon icon-eye"></i></a>'
                    . ($canEdit
                        ? '<a href="' . base_url('leadflow/leads/editar/' . (int) $l['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Lfl.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>'
                        : '')
                    . '</div>',
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
