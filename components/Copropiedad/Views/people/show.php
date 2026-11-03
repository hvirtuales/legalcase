<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

/*
 * La ficha de la persona.
 *
 * Su bloque central es **dónde ha vivido**: las mismas filas de
 * `cmp_cop_unit_people` que se ven desde el inmueble, leídas por el otro extremo.
 */
$id     = (int) $person['id'];
$volver = 'copropiedad/personas/ficha/' . $id;
$fecha  = static fn (?string $d) => $d ? esc($d) : '—';
?>

<div class="mb-4 flex flex-wrap items-start justify-between gap-2">
    <div class="flex items-center gap-3">
        <?php if (! empty($person['photo'])): ?>
            <img src="<?= esc(media_url($person['photo']), 'attr') ?>" alt="" class="h-14 w-14 rounded-full object-cover">
        <?php else: ?>
            <i class="icon icon-person-circle text-4xl text-faint"></i>
        <?php endif; ?>
        <div>
            <h4 class="text-xl font-semibold"><?= esc(PersonModel::fullName($person)) ?></h4>
            <p class="text-sm text-muted">
                <?= esc($person['doc_type'] . ' ' . $person['doc_number']) ?>
                <?= $person['phone'] ? ' · ' . esc($person['phone']) : '' ?>
                <?= $person['email'] ? ' · ' . esc($person['email']) : '' ?>
            </p>
            <?php if ($family): ?>
                <p class="text-sm">
                    <a class="text-primary hover:underline" href="<?= base_url('copropiedad/familias/ficha/' . (int) $family['id']) ?>">
                        <?= esc($family['name']) ?>
                    </a>
                    <?php if ($person['family_role']): ?>
                        <span class="text-muted">· <?= esc(Meta::label(Meta::FAMILY_ROLES, $person['family_role'])) ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="<?= base_url('copropiedad/personas/editar/' . $id) ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-pencil"></i> <?= lang('Cop.edit') ?>
        </a>
        <a href="<?= base_url('copropiedad/personas') ?>" class="btn btn-ghost btn-sm">
            <i class="icon icon-arrow-left"></i> <?= lang('Cop.back') ?>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card mb-4">
            <div class="card-header">
                <i class="icon icon-buildings"></i>
                <h5 class="font-semibold"><?= lang('Cop.peopleWhere') ?></h5>
            </div>
            <div class="card-body">
                <?php if (! $relations): ?>
                    <p class="mb-4 text-sm text-muted"><?= lang('Cop.peopleNoRole') ?></p>
                <?php else: ?>
                    <div class="mb-4 overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= lang('Cop.units') ?></th>
                                    <th><?= lang('Cop.relRole') ?></th>
                                    <th><?= lang('Cop.relStart') ?></th>
                                    <th><?= lang('Cop.relEnd') ?></th>
                                    <th><?= lang('Cop.authStatus') ?></th>
                                    <th class="text-right"><?= lang('Cop.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($relations as $r): ?>
                                    <?php $vigente = (int) $r['is_current'] === 1; ?>
                                    <tr class="<?= $vigente ? '' : 'text-muted' ?>">
                                        <td>
                                            <a class="font-medium text-primary hover:underline"
                                                href="<?= base_url('copropiedad/inmuebles/ficha/' . (int) $r['unit_id']) ?>">
                                                <?= esc($r['unit_code']) ?>
                                            </a>
                                            <div class="text-xs text-muted">
                                                <?= esc($r['block_name'] ?: lang('Cop.unitNoBlock')) ?>
                                                · <?= esc(Meta::label(Meta::UNIT_KINDS, $r['unit_kind'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= Meta::color(Meta::ROLE_COLORS, $r['role']) ?>">
                                                <?= esc(Meta::label(Meta::ROLES, $r['role'])) ?>
                                            </span>
                                        </td>
                                        <td><?= $fecha($r['start_date']) ?></td>
                                        <td><?= $fecha($r['end_date']) ?></td>
                                        <td>
                                            <span class="badge <?= $vigente ? 'badge-success' : 'badge-secondary' ?>">
                                                <?= $vigente ? lang('Cop.relCurrent') : lang('Cop.relEnded') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php /*
                                             * Cerrar y reabrir también desde aquí: quien entra
                                             * por Personas está mirando a la persona, y hacerle
                                             * ir a la ficha del inmueble para terminar una
                                             * estancia es un rodeo sin motivo. `return_to`
                                             * devuelve a ESTA ficha; el controlador lo valida
                                             * contra un patrón cerrado.
                                             */ ?>
                                            <div class="flex justify-end gap-1">
                                                <form action="<?= base_url('copropiedad/relaciones/'
                                                    . ($vigente ? 'cerrar/' : 'reabrir/') . (int) $r['id']) ?>" method="post"
                                                    <?= $vigente ? 'onsubmit="return confirm(\'' . esc(lang('Cop.relCloseConfirm'), 'js') . '\')"' : '' ?>>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm"
                                                        title="<?= esc($vigente ? lang('Cop.relClose') : lang('Cop.relReopen'), 'attr') ?>">
                                                        <i class="icon <?= $vigente ? 'icon-box-arrow-right' : 'icon-arrow-counterclockwise' ?>"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php /*
                 * Aquí es donde se le da el PAPEL a una persona.
                 *
                 * Es el mismo formulario que la ficha del inmueble, leído por el otro
                 * extremo: allí se elige la persona, aquí el inmueble. Existir en los
                 * dos sitios no es duplicar por gusto — quien entra por Personas no
                 * tiene por qué saber que el papel se asigna desde Inmuebles, y sin
                 * esto el censo era de solo lectura desde este lado.
                 *
                 * El papel sigue sin ser un campo de la persona: va con un inmueble y
                 * unas fechas concretas, y la misma persona puede ser propietaria de
                 * uno y arrendataria de otro.
                 */ ?>
                <form action="<?= base_url('copropiedad/relaciones/nueva') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="person_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">

                    <fieldset class="form-section">
                        <legend class="form-section-title"><?= lang('Cop.relAddUnit') ?></legend>
                        <p class="form-section-hint"><?= lang('Cop.relAddUnitHint') ?></p>

                        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-5">
                            <div class="form-group md:col-span-2">
                                <label for="rel-unit" class="form-label"><?= lang('Cop.units') ?> <span class="text-danger">*</span></label>
                                <select id="rel-unit" name="unit_id" class="form-select" required>
                                    <option value=""><?= lang('Cop.relSelectUnit') ?></option>
                                    <?php foreach ($units as $uid => $ulabel): ?>
                                        <option value="<?= (int) $uid ?>"><?= esc($ulabel) ?></option>
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
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="icon icon-buildings"></i> <?= lang('Cop.add') ?>
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>

        <?= view('Components\Copropiedad\Views\partials\_documents', [
            'documents' => $documents,
            'ownerType' => 'person',
            'ownerId'   => $id,
            'returnTo'  => $volver,
        ]) ?>
    </div>

    <div>
        <?php
        // Los tres bloques laterales tienen la misma forma; se pintan con un bucle.
        $laterales = [
            ['icon-key', lang('Cop.vehicles'), $vehicles, lang('Cop.noVehicles'), 'copropiedad/vehiculos',
                static fn ($x) => [$x['plate'] ?: '—', Meta::label(Meta::VEHICLE_KINDS, $x['kind'])]],
            ['icon-star', lang('Cop.pets'), $pets, lang('Cop.noPets'), 'copropiedad/mascotas',
                static fn ($x) => [$x['name'], Meta::label(Meta::SPECIES, $x['species'])]],
            ['icon-telephone', lang('Cop.contacts'), $contacts, lang('Cop.noContacts'), 'copropiedad/contactos',
                static fn ($x) => [$x['name'], $x['phone']]],
        ];

        foreach ($laterales as [$icono, $rotulo, $filas, $vacio, $ruta, $celda]): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <i class="icon <?= esc($icono, 'attr') ?>"></i>
                    <h5 class="font-semibold"><?= esc($rotulo) ?></h5>
                </div>
                <div class="card-body">
                    <?php if (! $filas): ?>
                        <p class="text-sm text-muted"><?= esc($vacio) ?></p>
                    <?php else: ?>
                        <ul class="divide-y divide-border">
                            <?php foreach ($filas as $x): ?>
                                <?php [$principal, $secundario] = $celda($x); ?>
                                <li class="flex items-center justify-between py-2">
                                    <a class="text-sm font-medium text-primary hover:underline"
                                        href="<?= base_url($ruta . '/editar/' . (int) $x['id']) ?>"><?= esc($principal) ?></a>
                                    <span class="text-xs text-muted"><?= esc($secundario) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
