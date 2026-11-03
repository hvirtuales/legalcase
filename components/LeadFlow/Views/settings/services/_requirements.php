<?php

use Components\LeadFlow\Models\ServiceRequirementModel;

/*
 * La plantilla documental del servicio, dentro de su editor.
 *
 * Va aquí y no en una pestaña propia de Configuración porque un checklist no
 * significa nada sin el trámite al que pertenece: «pasaporte, partida de matrimonio y
 * tres pruebas de convivencia» es la lista de una visa familiar, no de la firma.
 *
 * Tiene sus propias rutas POST en vez de viajar con el formulario del servicio, igual
 * que las etapas del pipeline: `BaseCrud` guarda un registro plano y una lista de
 * hijas repetible no cabe ahí sin reescribir el flujo de sus tres subclases.
 *
 * @var array|null $row          el servicio (null si se está creando)
 * @var array      $requirements
 * @var array      $docTypes
 */
$sid = (int) ($row['id'] ?? 0);
?>
<div class="form-section">
    <h6 class="form-section-title"><?= lang('Lfl.svcRequirements') ?></h6>
    <p class="form-hint mb-3"><?= lang('Lfl.svcRequirementsHint') ?></p>

    <?php if ($sid === 0): ?>
        <?php // Sin servicio guardado no hay a qué colgar los requisitos. ?>
        <p class="rounded border border-line p-4 text-sm text-muted"><?= lang('Lfl.svcRequirementsSaveFirst') ?></p>
    <?php else: ?>
        <?php if ($requirements === []): ?>
            <p class="rounded border border-line p-4 text-sm text-muted"><?= lang('Lfl.svcRequirementsNone') ?></p>
        <?php else: ?>
            <div class="table-card mb-3">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.rqLabel') ?></th>
                            <th><?= lang('Lfl.rqAppliesTo') ?></th>
                            <th><?= lang('Lfl.type') ?></th>
                            <th><?= lang('Lfl.rqRequired') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requirements as $r): ?>
                            <tr>
                                <td>
                                    <span class="font-medium"><?= esc($r['label']) ?></span>
                                    <?php if ($r['notes']): ?>
                                        <div class="text-xs text-muted"><?= esc($r['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm"><?= lang('Lfl.ap_' . $r['applies_to']) ?></td>
                                <td class="text-sm text-muted">
                                    <?= esc($r['document_type_id'] ? ($docTypes[(int) $r['document_type_id']] ?? '—') : '—') ?>
                                </td>
                                <td>
                                    <span class="badge <?= (int) $r['is_required'] === 1 ? 'badge-primary' : 'badge-light' ?>">
                                        <?= (int) $r['is_required'] === 1 ? lang('Lfl.rqRequired') : lang('Lfl.rqOptional') ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?= view('partials/delete_button', [
                                        'action'  => base_url('leadflow/configuracion/servicios/requisitos/eliminar/' . (int) $r['id']),
                                        'confirm' => lang('Lfl.confirmDelete'),
                                        'label'   => lang('Lfl.delete'),
                                    ]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php
        /*
         * Formulario propio, FUERA del `<form>` del servicio.
         *
         * Un `<form>` dentro de otro no es HTML válido: el navegador cierra el de
         * dentro y el botón acabaría enviando el formulario del servicio. Por eso este
         * bloque se pinta al final de la vista, después del cierre del otro.
         */
        ?>
        <form action="<?= base_url('leadflow/configuracion/servicios/requisitos/' . $sid) ?>" method="post"
            class="flex flex-wrap items-end gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="leadflow/configuracion/servicios/editar/<?= (int) $sid ?>">

            <div class="form-group mb-0 flex-1" style="min-width:200px">
                <label for="sr-label" class="form-label text-xs"><?= lang('Lfl.rqLabel') ?></label>
                <input type="text" id="sr-label" name="label" class="form-input" maxlength="120" required>
            </div>
            <div class="form-group mb-0">
                <label for="sr-applies" class="form-label text-xs"><?= lang('Lfl.rqAppliesTo') ?></label>
                <select id="sr-applies" name="applies_to" class="form-select">
                    <?php foreach (ServiceRequirementModel::APPLIES as $a): ?>
                        <option value="<?= esc($a, 'attr') ?>"><?= lang('Lfl.ap_' . $a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="sr-type" class="form-label text-xs"><?= lang('Lfl.type') ?></label>
                <select id="sr-type" name="document_type_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($docTypes as $tid => $nombre): ?>
                        <option value="<?= (int) $tid ?>"><?= esc($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="sr-sort" class="form-label text-xs"><?= lang('Lfl.mstgSort') ?></label>
                <input type="number" id="sr-sort" name="sort" class="form-input" style="max-width:90px" value="10">
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs"><?= lang('Lfl.rqRequired') ?></label>
                <label class="form-check">
                    <input type="checkbox" name="is_required" value="1" checked>
                    <span><?= lang('Lfl.rqRequired') ?></span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Lfl.rqAdd') ?>
            </button>
        </form>
    <?php endif; ?>
</div>
