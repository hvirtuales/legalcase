<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-buildings"></i>
            <h5 class="font-semibold"><?= lang('Cop.units') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex gap-2">
                <a href="<?= base_url('copropiedad/torres') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-diagram-3"></i> <?= lang('Cop.blocks') ?>
                </a>
                <?php // Solo con el registro vacío: nunca puede pisar datos reales. ?>
                <?php if ($isEmpty && ! $planBlock): ?>
                    <form action="<?= base_url('copropiedad/demo') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Cop.demoHint'), 'attr') ?>">
                            <i class="icon icon-sparkles"></i> <?= lang('Cop.demo') ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (! $planBlock): ?>
                    <a href="<?= base_url('copropiedad/inmuebles/nuevo') ?>" class="btn btn-primary btn-sm">
                        <i class="icon icon-plus-circle"></i> <?= lang('Cop.unitsNew') ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
        <?php else: ?>

            <?php if ($planBlock): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= esc($planBlock) ?></span></div>
            <?php elseif ($unitsLeft !== null): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= esc(lang('Cop.planUnitsLeft', [$unitsLeft])) ?></span></div>
            <?php endif; ?>

            <?php // Buscador y los tres filtros en una sola fila: el DataTable no
                  // pinta el suyo (`showSearch => false`) para no gastar dos renglones. ?>
            <form method="get" action="<?= base_url('copropiedad/inmuebles') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Cop.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Cop.unitsSearch'), 'attr') ?>">
                    </div>
                </div>
                <?php if ($blocks): ?>
                    <div class="form-group mb-0">
                        <label for="f-torre" class="form-label text-xs"><?= lang('Cop.unitBlock') ?></label>
                        <select id="f-torre" name="torre" class="form-select">
                            <option value=""><?= lang('Cop.all') ?></option>
                            <?php foreach ($blocks as $bid => $bname): ?>
                                <option value="<?= (int) $bid ?>" <?= $block === (int) $bid ? 'selected' : '' ?>><?= esc($bname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="form-group mb-0">
                    <label for="f-tipo" class="form-label text-xs"><?= lang('Cop.unitKind') ?></label>
                    <select id="f-tipo" name="tipo" class="form-select">
                        <option value=""><?= lang('Cop.all') ?></option>
                        <?php foreach (Meta::options(Meta::UNIT_KINDS) as $v => $l): ?>
                            <option value="<?= esc($v, 'attr') ?>" <?= $kind === $v ? 'selected' : '' ?>><?= esc($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="f-estado" class="form-label text-xs"><?= lang('Cop.unitOccupancy') ?></label>
                    <select id="f-estado" name="estado" class="form-select">
                        <option value=""><?= lang('Cop.all') ?></option>
                        <?php foreach (Meta::options(Meta::OCCUPANCIES) as $v => $l): ?>
                            <option value="<?= esc($v, 'attr') ?>" <?= $occupancy === $v ? 'selected' : '' ?>><?= esc($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Cop.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <?php if ($block || $kind !== '' || $occupancy !== '' || ($dt['q'] ?? '') !== ''): ?>
                            <a href="<?= base_url('copropiedad/inmuebles') ?>" class="btn btn-ghost" title="<?= esc(lang('Cop.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'          => $dt,
                'showSearch'  => false,
                'baseUrl'     => base_url('copropiedad/inmuebles'),
                'extraParams' => [
                    'torre'  => $block ?: '',
                    'tipo'   => $kind,
                    'estado' => $occupancy,
                ],
                'emptyText'   => lang('Cop.noUnits'),
                'columns'     => [
                    // `sortKey` califica la columna: `cmp_cop_blocks` también tiene
                    // `code` y `name`, así que un ORDER BY sin calificar es ambiguo.
                    ['key' => 'code', 'label' => lang('Cop.unitCode'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.code',
                        'render' => static fn ($u) => '<a class="font-semibold text-primary hover:underline" href="'
                            . base_url('copropiedad/inmuebles/ficha/' . (int) $u['id']) . '">' . esc($u['code']) . '</a>'
                            . '<div class="text-xs text-muted">' . esc($u['block_name'] ?: lang('Cop.unitNoBlock')) . '</div>'],
                    ['key' => 'kind', 'label' => lang('Cop.unitKind'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.kind',
                        'render' => static fn ($u) => esc(Meta::label(Meta::UNIT_KINDS, $u['kind']))],
                    ['key' => 'floor', 'label' => lang('Cop.unitFloor'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.floor',
                        'render' => static fn ($u) => esc($u['floor'] ?: '—')],
                    ['key' => 'area', 'label' => lang('Cop.unitArea'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.area',
                        'render' => static fn ($u) => $u['area'] !== null ? esc(number_format((float) $u['area'], 2, ',', '.')) : '—'],
                    ['key' => 'coefficient', 'label' => lang('Cop.unitCoeff'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.coefficient',
                        'render' => static fn ($u) => $u['coefficient'] !== null
                            ? esc(rtrim(rtrim(number_format((float) $u['coefficient'], 4, ',', '.'), '0'), ',')) . ' %'
                            : '—'],
                    ['key' => 'occupancy', 'label' => lang('Cop.unitOccupancy'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.occupancy',
                        'render' => static fn ($u) => '<span class="badge ' . Meta::color(Meta::OCCUPANCY_COLORS, $u['occupancy']) . '">'
                            . esc(Meta::label(Meta::OCCUPANCIES, $u['occupancy'])) . '</span>'],
                ],
                'actions' => static function ($u) {
                    $id = (int) $u['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('copropiedad/inmuebles/ficha/' . $id) . '" class="btn btn-secondary btn-sm" title="'
                        . esc(lang('Cop.viewCard'), 'attr') . '"><i class="icon icon-eye"></i></a>'
                        . '<a href="' . base_url('copropiedad/inmuebles/editar/' . $id) . '" class="btn btn-secondary btn-sm" title="'
                        . esc(lang('Cop.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action'  => base_url('copropiedad/inmuebles/eliminar/' . $id),
                            'confirm' => lang('Cop.unitDelConfirm'),
                            'label'   => lang('Cop.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
