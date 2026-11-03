<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-key"></i>
            <h5 class="font-semibold"><?= lang('Cop.vehicles') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('copropiedad/vehiculos/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Cop.vehiclesNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
        <?php else: ?>
            <form method="get" action="<?= base_url('copropiedad/vehiculos') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Cop.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Cop.vehSearch'), 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="f-tipo" class="form-label text-xs"><?= lang('Cop.vehKind') ?></label>
                    <select id="f-tipo" name="tipo" class="form-select">
                        <option value=""><?= lang('Cop.all') ?></option>
                        <?php foreach (Meta::options(Meta::VEHICLE_KINDS) as $v => $l): ?>
                            <option value="<?= esc($v, 'attr') ?>" <?= $kind === $v ? 'selected' : '' ?>><?= esc($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Cop.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <?php if ($kind !== '' || ($dt['q'] ?? '') !== ''): ?>
                            <a href="<?= base_url('copropiedad/vehiculos') ?>" class="btn btn-ghost" title="<?= esc(lang('Cop.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'          => $dt,
                'showSearch'  => false,
                'baseUrl'     => base_url('copropiedad/vehiculos'),
                'extraParams' => ['tipo' => $kind],
                'emptyText'   => lang('Cop.noVehicles'),
                'columns'     => [
                    ['key' => 'plate', 'label' => lang('Cop.vehPlate'), 'sortable' => true, 'sortKey' => 'cmp_cop_vehicles.plate',
                        'render' => static fn ($x) => '<strong>' . esc($x['plate'] ?: '—') . '</strong>'
                            . ($x['sticker'] ? '<div class="text-xs text-muted">' . esc($x['sticker']) . '</div>' : '')],
                    ['key' => 'kind', 'label' => lang('Cop.vehKind'), 'sortable' => true, 'sortKey' => 'cmp_cop_vehicles.kind',
                        'render' => static fn ($x) => esc(Meta::label(Meta::VEHICLE_KINDS, $x['kind']))],
                    ['key' => 'brand', 'label' => lang('Cop.vehBrand'), 'sortable' => true, 'sortKey' => 'cmp_cop_vehicles.brand',
                        'render' => static fn ($x) => esc(trim(($x['brand'] ?? '') . ' ' . ($x['model'] ?? '')) ?: '—')
                            . ($x['color'] ? '<div class="text-xs text-muted">' . esc($x['color']) . '</div>' : '')],
                    // La columna que da sentido al listado: a qué puerta pertenece.
                    ['key' => 'unit_code', 'label' => lang('Cop.units'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.code',
                        'render' => static fn ($x) => $x['unit_code']
                            ? '<a class="text-primary hover:underline" href="' . base_url('copropiedad/inmuebles/ficha/' . (int) $x['unit_id']) . '">'
                                . esc(($x['block_name'] ? $x['block_name'] . ' · ' : '') . $x['unit_code']) . '</a>'
                            : '—'],
                    ['key' => 'parking_code', 'label' => lang('Cop.vehParking'), 'sortable' => false,
                        'render' => static fn ($x) => esc($x['parking_code'] ?: '—')],
                    ['key' => 'person', 'label' => lang('Cop.vehOwner'), 'sortable' => false,
                        'render' => static fn ($x) => $x['first_name'] ? esc(PersonModel::fullName($x)) : '—'],
                ],
                'actions' => static function ($x) {
                    $id = (int) $x['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('copropiedad/vehiculos/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action' => base_url('copropiedad/vehiculos/eliminar/' . $id),
                            'label'  => lang('Cop.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
