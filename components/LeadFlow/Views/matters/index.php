<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\MatterModel;

/**
 * Listado de expedientes.
 *
 * Misma estructura que `leads/index.php`: tarjeta, pestañas de vista, filtros en un
 * `<form method="get">` y `partials/datatable` del núcleo. No se crea desde aquí: un
 * expediente se abre desde la ficha de un lead ganado, así que la cabecera no lleva
 * acción principal y el estado vacío lo explica.
 *
 * @var array      $dt
 * @var string     $view
 * @var string[]   $views
 * @var int        $etapa
 * @var array      $stages
 * @var array      $counts   status => n
 * @var bool       $canEdit
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-folder"></i>
            <h5 class="font-semibold"><?= lang('Lfl.mtrListTitle') ?></h5>
            <?php if (($counts['active'] ?? 0) > 0): ?>
                <span class="badge badge-success"><?= (int) $counts['active'] ?> <?= lang('Lfl.msActive') ?></span>
            <?php endif; ?>
            <?php if (($counts['on_hold'] ?? 0) > 0): ?>
                <span class="badge badge-warning"><?= (int) $counts['on_hold'] ?> <?= lang('Lfl.msOnHold') ?></span>
            <?php endif; ?>
        </div>
        <a href="<?= base_url('leadflow/leads?vista=todos&estado=won') ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-people"></i> <?= lang('Lfl.navLeads') ?>
        </a>
    </div>
    <div class="card-body">
        <?php if ($access->scope === 'none'): ?>
            <?= view('Components\LeadFlow\Views\partials\_noaccess') ?>
        <?php else: ?>
            <?php // Las vistas son pestañas de esta misma pantalla, no cuatro menús. ?>
            <nav class="tabs mb-4">
                <?php foreach (['activos' => 'mtrViewActive', 'mios' => 'mtrViewMine',
                    'cerrados' => 'mtrViewClosed', 'todos' => 'mtrViewAll'] as $v => $label): ?>
                    <a href="<?= base_url('leadflow/expedientes?vista=' . $v) ?>"
                        class="tab-link <?= $view === $v ? 'active' : '' ?>"><?= lang('Lfl.' . $label) ?></a>
                <?php endforeach; ?>
            </nav>

            <form method="get" action="<?= base_url('leadflow/expedientes') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <input type="hidden" name="vista" value="<?= esc($view, 'attr') ?>">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Lfl.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Lfl.mtrNumber') . ' / ' . lang('Lfl.mtrClient'), 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="f-etapa" class="form-label text-xs"><?= lang('Lfl.mtrStage') ?></label>
                    <select id="f-etapa" name="etapa" class="form-select">
                        <option value=""><?= lang('Lfl.all') ?></option>
                        <?php foreach ($stages as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $etapa === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= esc($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Lfl.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <a href="<?= base_url('leadflow/expedientes?vista=' . $view) ?>" class="btn btn-ghost" title="<?= esc(lang('Lfl.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                    </div>
                </div>
            </form>

            <?php /* El estado vacío solo cuando no hay NADA, no cuando el filtro no
                     devuelve nada: ahí el mensaje correcto es el de la tabla. */ ?>
            <?php if (($dt['rows'] ?? []) === [] && ($dt['q'] ?? '') === '' && $etapa === 0 && $view === 'activos'): ?>
                <div class="alert alert-info">
                    <i class="icon icon-info-circle"></i>
                    <span><strong><?= lang('Lfl.mtrNone') ?></strong> <?= lang('Lfl.mtrNoneHint') ?></span>
                </div>
            <?php endif; ?>

            <?= view('partials/datatable', [
                'dt'          => $dt,
                'showSearch'  => false,
                'baseUrl'     => base_url('leadflow/expedientes'),
                'extraParams' => ['vista' => $view, 'etapa' => $etapa ?: ''],
                'emptyText'   => lang('Lfl.mtrNone'),
                'columns'     => [
                    ['key' => 'number', 'label' => lang('Lfl.mtrNumber'), 'sortable' => true, 'sortKey' => 'cmp_lfl_matters.number',
                        'render' => static fn ($m) => '<a class="font-mono font-semibold text-primary hover:underline" href="'
                            . base_url('leadflow/expedientes/ficha/' . (int) $m['id']) . '">' . esc($m['number']) . '</a>'
                            . '<div class="text-xs text-muted">' . esc($m['service_name'] ?: '—') . '</div>'],
                    ['key' => 'client_last_name', 'label' => lang('Lfl.mtrClient'), 'sortable' => false,
                        'render' => static fn ($m) => esc(MatterModel::clientName($m))],
                    ['key' => 'stage_name', 'label' => lang('Lfl.mtrStage'), 'sortable' => false,
                        'render' => static fn ($m) => Meta::stageBadge($m['stage_name']
                            ? ['name' => $m['stage_name'], 'color' => $m['stage_color']] : null)],
                    ['key' => 'status', 'label' => lang('Lfl.mtrStatus'), 'sortable' => false,
                        'render' => static fn ($m) => '<span class="badge '
                            . (Meta::MATTER_STATUS_COLORS[$m['status']] ?? 'badge-light') . '">'
                            . esc(lang('Lfl.' . (Meta::MATTER_STATUSES[$m['status']] ?? 'msActive'))) . '</span>'],
                    ['key' => 'lawyer_name', 'label' => lang('Lfl.mtrLawyer'), 'sortable' => false,
                        'render' => static fn ($m) => esc($m['lawyer_name'] ?: '—')],
                    ['key' => 'next_deadline_at', 'label' => lang('Lfl.mtrNextDeadline'), 'sortable' => true, 'sortKey' => 'cmp_lfl_matters.next_deadline_at',
                        'render' => static fn ($m) => $m['next_deadline_at']
                            ? '<span class="' . ($m['next_deadline_at'] < date('Y-m-d H:i:s') ? 'text-danger' : '') . '">'
                                . esc(Meta::date($m['next_deadline_at'])) . '</span>'
                            : '<span class="text-muted">—</span>'],
                    ['key' => 'opened_at', 'label' => lang('Lfl.mtrOpenedAt'), 'sortable' => true, 'sortKey' => 'cmp_lfl_matters.opened_at',
                        'render' => static fn ($m) => esc(Meta::date($m['opened_at'], false))
                            . '<div class="text-xs text-muted">' . esc(Meta::since($m['opened_at'])) . '</div>'],
                ],
                'actions' => static fn ($m) => '<div class="flex justify-end gap-1">'
                    . '<a href="' . base_url('leadflow/expedientes/ficha/' . (int) $m['id']) . '" class="btn btn-secondary btn-sm" title="'
                        . esc(lang('Lfl.mtrSeeMatter'), 'attr') . '"><i class="icon icon-eye"></i></a>'
                    . ($canEdit
                        ? '<a href="' . base_url('leadflow/expedientes/editar/' . (int) $m['id']) . '" class="btn btn-secondary btn-sm" title="'
                            . esc(lang('Lfl.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>'
                        : '')
                    . '</div>',
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
