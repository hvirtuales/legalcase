<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

/*
 * La ficha del inmueble: la pantalla que justifica el componente.
 *
 * En un sitio: quién es el dueño, quién vive, desde cuándo, con qué carro, qué
 * mascota, a quién llamar en una emergencia, qué se autorizó y qué papeles hay.
 * Antes eso estaba repartido entre una carpeta física y la memoria del administrador.
 */
$id      = (int) $unit['id'];
$volver  = 'copropiedad/inmuebles/ficha/' . $id;
$fecha   = static fn (?string $d) => $d ? esc($d) : '—';
?>

<div class="mb-4 flex flex-wrap items-start justify-between gap-2">
    <div>
        <div class="flex items-center gap-2">
            <i class="icon icon-buildings text-xl"></i>
            <h4 class="text-xl font-semibold"><?= esc($unit['code']) ?></h4>
            <span class="badge <?= Meta::color(Meta::OCCUPANCY_COLORS, $unit['occupancy']) ?>">
                <?= esc(Meta::label(Meta::OCCUPANCIES, $unit['occupancy'])) ?>
            </span>
        </div>
        <p class="text-sm text-muted">
            <?= esc(Meta::label(Meta::UNIT_KINDS, $unit['kind'])) ?>
            <?= $block ? ' · ' . esc($block['name']) : '' ?>
            <?= $unit['floor'] ? ' · ' . esc(lang('Cop.unitFloor')) . ' ' . esc($unit['floor']) : '' ?>
            <?php if ($parent): ?>
                · <?= esc(lang('Cop.unitParent')) ?>
                <a class="text-primary hover:underline" href="<?= base_url('copropiedad/inmuebles/ficha/' . (int) $parent['id']) ?>"><?= esc($parent['code']) ?></a>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <a href="<?= base_url('copropiedad/inmuebles/historial/' . $id) ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-clock-history"></i> <?= lang('Cop.history') ?>
        </a>
        <a href="<?= base_url('copropiedad/inmuebles/editar/' . $id) ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-pencil"></i> <?= lang('Cop.edit') ?>
        </a>
        <a href="<?= base_url('copropiedad/inmuebles') ?>" class="btn btn-ghost btn-sm">
            <i class="icon icon-arrow-left"></i> <?= lang('Cop.back') ?>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">

        <?php // ── Quién vive aquí ─────────────────────────────────────────── ?>
        <div class="card mb-4">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2">
                    <i class="icon icon-people"></i>
                    <h5 class="font-semibold"><?= lang('Cop.relations') ?></h5>
                </div>
                <?php if ($shareSum > 0): ?>
                    <span class="badge <?= $shareWarning ? 'badge-warning' : 'badge-success' ?>">
                        <?= esc(rtrim(rtrim(number_format($shareSum, 4, ',', '.'), '0'), ',')) ?> %
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($shareWarning): ?>
                    <div class="alert alert-warning">
                        <i class="icon icon-exclamation-circle"></i>
                        <span><?= esc(lang('Cop.relShareWarn', [rtrim(rtrim(number_format($shareSum, 4, ',', '.'), '0'), ',')])) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (! $current): ?>
                    <p class="mb-4 text-sm text-muted"><?= lang('Cop.relNobody') ?></p>
                <?php else: ?>
                    <div class="mb-4 overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= lang('Cop.relPickPerson') ?></th>
                                    <th><?= lang('Cop.relRole') ?></th>
                                    <th><?= lang('Cop.relShare') ?></th>
                                    <th><?= lang('Cop.relStart') ?></th>
                                    <th class="text-right"><?= lang('Cop.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current as $r): ?>
                                    <tr>
                                        <td>
                                            <a class="font-medium text-primary hover:underline"
                                                href="<?= base_url('copropiedad/personas/ficha/' . (int) $r['person_id']) ?>">
                                                <?= esc(PersonModel::fullName($r)) ?>
                                            </a>
                                            <?php if ((int) $r['is_main'] === 1): ?>
                                                <span class="badge badge-primary"><?= lang('Cop.relMain') ?></span>
                                            <?php endif; ?>
                                            <div class="text-xs text-muted">
                                                <?= esc($r['doc_type'] . ' ' . $r['doc_number']) ?>
                                                <?= $r['phone'] ? ' · ' . esc($r['phone']) : '' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= Meta::color(Meta::ROLE_COLORS, $r['role']) ?>">
                                                <?= esc(Meta::label(Meta::ROLES, $r['role'])) ?>
                                            </span>
                                        </td>
                                        <td><?= $r['share'] !== null ? esc(rtrim(rtrim(number_format((float) $r['share'], 4, ',', '.'), '0'), ',')) . ' %' : '—' ?></td>
                                        <td><?= $fecha($r['start_date']) ?></td>
                                        <td>
                                            <div class="flex justify-end gap-1">
                                                <?php // Cerrar NO es eliminar: cerrar deja la fila
                                                      // como historia, eliminar la borra. Son dos
                                                      // botones distintos a propósito. ?>
                                                <form action="<?= base_url('copropiedad/relaciones/cerrar/' . (int) $r['id']) ?>" method="post"
                                                    onsubmit="return confirm('<?= esc(lang('Cop.relCloseConfirm'), 'js') ?>')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Cop.relClose'), 'attr') ?>">
                                                        <i class="icon icon-box-arrow-right"></i>
                                                    </button>
                                                </form>
                                                <?= view('partials/delete_button', [
                                                    'action'  => base_url('copropiedad/relaciones/eliminar/' . (int) $r['id']),
                                                    'confirm' => lang('Cop.relDelConfirm'),
                                                    'label'   => lang('Cop.delete'),
                                                ]) ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php // Vincular a alguien. El selector lista el censo de la empresa;
                      // el servidor vuelve a comprobar que la persona sea de ella. ?>
                <form action="<?= base_url('copropiedad/relaciones/nueva') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="unit_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">

                    <fieldset class="form-section">
                        <legend class="form-section-title"><?= lang('Cop.relAdd') ?></legend>

                        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-5">
                            <div class="form-group md:col-span-2">
                                <label for="rel-person" class="form-label"><?= lang('Cop.relPickPerson') ?> <span class="text-danger">*</span></label>
                                <select id="rel-person" name="person_id" class="form-select" required>
                                    <option value=""><?= lang('Cop.relSelectPerson') ?></option>
                                    <?php foreach ($people as $pid => $plabel): ?>
                                        <option value="<?= (int) $pid ?>"><?= esc($plabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="rel-role" class="form-label"><?= lang('Cop.relRole') ?> <span class="text-danger">*</span></label>
                                <select id="rel-role" name="role" class="form-select">
                                    <?php foreach (Meta::options(Meta::ROLES) as $val => $lbl): ?>
                                        <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="rel-share" class="form-label"><?= lang('Cop.relShare') ?></label>
                                <input type="number" step="0.0001" min="0" max="100" id="rel-share" name="share" class="form-input">
                                <small class="form-hint"><?= lang('Cop.relShareHint') ?></small>
                            </div>
                            <div class="form-group">
                                <label for="rel-start" class="form-label"><?= lang('Cop.relStart') ?></label>
                                <input type="date" id="rel-start" name="start_date" class="form-input" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <div class="form-check mb-2">
                            <input type="checkbox" id="rel-main" name="is_main" value="1" class="form-checkbox">
                            <label for="rel-main"><?= lang('Cop.relMain') ?></label>
                        </div>
                        <small class="form-hint"><?= lang('Cop.relMainHint') ?></small>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-person-plus"></i> <?= lang('Cop.add') ?></button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>

        <?php // ── Vehículos, mascotas, autorizaciones y contactos ──────────── ?>
        <?php
        /*
         * Los cuatro bloques comparten forma —título, ruta de alta, columnas—, así
         * que se pintan con un bucle en vez de con cuatro tablas copiadas: con
         * cuatro copias, cualquier arreglo habría que hacerlo cuatro veces.
         */
        $bloques = [
            [
                'icon' => 'icon-key', 'title' => lang('Cop.vehicles'), 'route' => 'copropiedad/vehiculos',
                'rows' => $vehicles, 'empty' => lang('Cop.noVehicles'),
                'cols' => [lang('Cop.vehPlate'), lang('Cop.vehKind'), lang('Cop.vehBrand')],
                'cell' => static fn ($x) => [
                    esc($x['plate'] ?: '—'),
                    esc(Meta::label(Meta::VEHICLE_KINDS, $x['kind'])),
                    esc(trim(($x['brand'] ?? '') . ' ' . ($x['model'] ?? '')) ?: '—'),
                ],
            ],
            [
                'icon' => 'icon-star', 'title' => lang('Cop.pets'), 'route' => 'copropiedad/mascotas',
                'rows' => $pets, 'empty' => lang('Cop.noPets'),
                'cols' => [lang('Cop.petName'), lang('Cop.petSpecies'), lang('Cop.petVaccineExp')],
                'cell' => static fn ($x) => [
                    esc($x['name']) . ((int) $x['is_dangerous'] === 1
                        ? ' <span class="badge badge-danger">' . esc(lang('Cop.petDangerous')) . '</span>' : ''),
                    esc(Meta::label(Meta::SPECIES, $x['species'])),
                    esc($x['vaccine_expires'] ?: '—'),
                ],
            ],
            [
                'icon' => 'icon-shield-check', 'title' => lang('Cop.auths'), 'route' => 'copropiedad/autorizaciones',
                'rows' => $authorizations, 'empty' => lang('Cop.noAuths'),
                'cols' => [lang('Cop.authWho'), lang('Cop.authKind'), lang('Cop.authStatus')],
                'cell' => static fn ($x) => [
                    esc($x['authorized_name'] ?: '—'),
                    esc(Meta::label(Meta::AUTH_KINDS, $x['kind'])),
                    '<span class="badge ' . Meta::color(Meta::AUTH_STATUS_COLORS, $x['status']) . '">'
                        . esc(Meta::label(Meta::AUTH_STATUSES, $x['status'])) . '</span>',
                ],
            ],
            [
                'icon' => 'icon-telephone', 'title' => lang('Cop.contacts'), 'route' => 'copropiedad/contactos',
                'rows' => $contacts, 'empty' => lang('Cop.noContacts'),
                'cols' => [lang('Cop.contactName'), lang('Cop.contactRel'), lang('Cop.contactPhone')],
                'cell' => static fn ($x) => [
                    esc($x['name']) . ((int) $x['is_primary'] === 1
                        ? ' <span class="badge badge-primary">1</span>' : ''),
                    esc($x['relationship'] ?: '—'),
                    esc($x['phone']),
                ],
            ],
        ];

        foreach ($bloques as $b): ?>
            <div class="card mb-4">
                <div class="card-header justify-between">
                    <div class="flex items-center gap-2">
                        <i class="icon <?= esc($b['icon'], 'attr') ?>"></i>
                        <h5 class="font-semibold"><?= esc($b['title']) ?></h5>
                    </div>
                    <a href="<?= base_url($b['route'] . '/nuevo') ?>" class="btn btn-secondary btn-sm">
                        <i class="icon icon-plus-circle"></i> <?= lang('Cop.add') ?>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (! $b['rows']): ?>
                        <p class="text-sm text-muted"><?= esc($b['empty']) ?></p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <?php foreach ($b['cols'] as $c): ?><th><?= esc($c) ?></th><?php endforeach; ?>
                                        <th class="text-right"><?= lang('Cop.actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($b['rows'] as $x): ?>
                                        <tr>
                                            <?php // Las celdas ya vienen escapadas de `cell`, que es
                                                  // quien decide qué lleva distintivo y qué no. ?>
                                            <?php foreach ($b['cell']($x) as $celda): ?><td><?= $celda ?></td><?php endforeach; ?>
                                            <td class="text-right">
                                                <a href="<?= base_url($b['route'] . '/editar/' . (int) $x['id']) ?>" class="btn btn-secondary btn-sm">
                                                    <i class="icon icon-pencil"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?= view('Components\Copropiedad\Views\partials\_documents', [
            'documents' => $documents,
            'ownerType' => 'unit',
            'ownerId'   => $id,
            'returnTo'  => $volver,
        ]) ?>
    </div>

    <?php // ── Columna derecha: datos y estado ─────────────────────────────── ?>
    <div>
        <div class="card mb-4">
            <div class="card-header"><h5 class="font-semibold"><?= lang('Cop.unitOccupancy') ?></h5></div>
            <div class="card-body">
                <form action="<?= base_url('copropiedad/inmuebles/ocupacion/' . $id) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <select name="occupancy" class="form-select">
                            <?php foreach (Meta::options(Meta::OCCUPANCIES) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $unit['occupancy'] === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint"><?= lang('Cop.unitOccupHint') ?></small>
                    </div>
                    <p class="mb-3 text-xs text-muted">
                        <?= esc(lang('Cop.unitDerived', [Meta::label(Meta::OCCUPANCIES, $derived)])) ?>
                    </p>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-save"></i> <?= lang('Cop.apply') ?></button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="font-semibold"><?= lang('Cop.units') ?></h5></div>
            <div class="card-body">
                <dl class="text-sm">
                    <?php
                    $datos = [
                        lang('Cop.unitArea')      => $unit['area'] !== null ? number_format((float) $unit['area'], 2, ',', '.') . ' m²' : null,
                        lang('Cop.unitCoeff')     => $unit['coefficient'] !== null ? rtrim(rtrim(number_format((float) $unit['coefficient'], 4, ',', '.'), '0'), ',') . ' %' : null,
                        lang('Cop.unitBedrooms')  => $unit['bedrooms'],
                        lang('Cop.unitBathrooms') => $unit['bathrooms'],
                        lang('Cop.unitMatricula') => $unit['matricula'],
                    ];

                    foreach ($datos as $rotulo => $valor): ?>
                        <div class="flex justify-between border-b border-border py-1.5 last:border-0">
                            <dt class="text-muted"><?= esc($rotulo) ?></dt>
                            <dd class="font-medium"><?= $valor === null || $valor === '' ? '—' : esc((string) $valor) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <?php if (! empty($unit['notes'])): ?>
                    <p class="mt-3 whitespace-pre-line text-sm text-muted"><?= esc($unit['notes']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php // Los parqueaderos y depósitos adscritos: son inmuebles propios, con
              // su ficha, y desde aquí se llega a ellos. ?>
        <?php if ($attached): ?>
            <div class="card mb-4">
                <div class="card-header"><h5 class="font-semibold"><?= lang('Cop.unitAttached') ?></h5></div>
                <div class="card-body">
                    <ul class="divide-y divide-border">
                        <?php foreach ($attached as $a): ?>
                            <li class="flex items-center justify-between py-2">
                                <a class="text-sm font-medium text-primary hover:underline"
                                    href="<?= base_url('copropiedad/inmuebles/ficha/' . (int) $a['id']) ?>"><?= esc($a['code']) ?></a>
                                <span class="text-xs text-muted"><?= esc(Meta::label(Meta::UNIT_KINDS, $a['kind'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php // Un vistazo al historial; el completo tiene su propia pantalla. ?>
        <div class="card">
            <div class="card-header justify-between">
                <h5 class="font-semibold"><?= lang('Cop.history') ?></h5>
                <a href="<?= base_url('copropiedad/inmuebles/historial/' . $id) ?>" class="btn btn-ghost btn-sm">
                    <?= lang('Cop.view') ?> <i class="icon icon-chevron-right"></i>
                </a>
            </div>
            <div class="card-body">
                <?php $cerradas = array_filter($history, static fn ($h) => (int) $h['is_current'] === 0); ?>
                <?php if (! $cerradas): ?>
                    <p class="text-sm text-muted"><?= lang('Cop.relNoHistory') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-border">
                        <?php foreach (array_slice($cerradas, 0, 5) as $h): ?>
                            <li class="py-2">
                                <div class="text-sm font-medium"><?= esc(PersonModel::fullName($h)) ?></div>
                                <div class="text-xs text-muted">
                                    <?= esc(Meta::label(Meta::ROLES, $h['role'])) ?>
                                    · <?= $fecha($h['start_date']) ?> → <?= $fecha($h['end_date']) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
