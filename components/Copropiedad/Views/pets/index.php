<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

$hoy = date('Y-m-d');
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-star"></i>
            <h5 class="font-semibold"><?= lang('Cop.pets') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('copropiedad/mascotas/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Cop.petsNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
        <?php else: ?>
            <form method="get" action="<?= base_url('copropiedad/mascotas') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Cop.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Cop.petsSearch'), 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="f-especie" class="form-label text-xs"><?= lang('Cop.petSpecies') ?></label>
                    <select id="f-especie" name="especie" class="form-select">
                        <option value=""><?= lang('Cop.all') ?></option>
                        <?php foreach (Meta::options(Meta::SPECIES) as $v => $l): ?>
                            <option value="<?= esc($v, 'attr') ?>" <?= $species === $v ? 'selected' : '' ?>><?= esc($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="form-check">
                        <input type="checkbox" id="f-especial" name="especial" value="1" class="form-checkbox" <?= $dangerous ? 'checked' : '' ?>>
                        <label for="f-especial" class="text-sm"><?= lang('Cop.petsOnlyDanger') ?></label>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Cop.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <?php if ($species !== '' || $dangerous || ($dt['q'] ?? '') !== ''): ?>
                            <a href="<?= base_url('copropiedad/mascotas') ?>" class="btn btn-ghost" title="<?= esc(lang('Cop.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'          => $dt,
                'showSearch'  => false,
                'baseUrl'     => base_url('copropiedad/mascotas'),
                'extraParams' => ['especie' => $species, 'especial' => $dangerous ? '1' : ''],
                'emptyText'   => lang('Cop.noPets'),
                'columns'     => [
                    ['key' => 'name', 'label' => lang('Cop.petName'), 'sortable' => true, 'sortKey' => 'cmp_cop_pets.name',
                        'render' => static fn ($x) => '<strong>' . esc($x['name']) . '</strong>'
                            . ((int) $x['is_dangerous'] === 1
                                ? ' <span class="badge badge-danger">' . esc(lang('Cop.petDangerous')) . '</span>' : '')
                            . ($x['breed'] ? '<div class="text-xs text-muted">' . esc($x['breed']) . '</div>' : '')],
                    ['key' => 'species', 'label' => lang('Cop.petSpecies'), 'sortable' => true, 'sortKey' => 'cmp_cop_pets.species',
                        'render' => static fn ($x) => esc(Meta::label(Meta::SPECIES, $x['species']))],
                    ['key' => 'unit_code', 'label' => lang('Cop.units'), 'sortable' => true, 'sortKey' => 'cmp_cop_units.code',
                        'render' => static fn ($x) => $x['unit_code']
                            ? '<a class="text-primary hover:underline" href="' . base_url('copropiedad/inmuebles/ficha/' . (int) $x['unit_id']) . '">'
                                . esc(($x['block_name'] ? $x['block_name'] . ' · ' : '') . $x['unit_code']) . '</a>'
                            : '—'],
                    ['key' => 'person', 'label' => lang('Cop.petOwner'), 'sortable' => false,
                        'render' => static fn ($x) => $x['first_name'] ? esc(PersonModel::fullName($x)) : '—'],
                    // Lo vencido en rojo: es la razón de guardar la fecha.
                    ['key' => 'vaccine_expires', 'label' => lang('Cop.petVaccineExp'), 'sortable' => true, 'sortKey' => 'cmp_cop_pets.vaccine_expires',
                        'render' => static function ($x) use ($hoy) {
                            if (empty($x['vaccine_expires'])) {
                                return '—';
                            }

                            return '<span class="badge ' . ($x['vaccine_expires'] < $hoy ? 'badge-danger' : 'badge-secondary') . '">'
                                . esc($x['vaccine_expires']) . '</span>';
                        }],
                ],
                'actions' => static function ($x) {
                    $id = (int) $x['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('copropiedad/mascotas/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action' => base_url('copropiedad/mascotas/eliminar/' . $id),
                            'label'  => lang('Cop.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
