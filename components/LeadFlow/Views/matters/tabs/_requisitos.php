<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\MatterRequirementModel;
use Components\LeadFlow\Models\ServiceRequirementModel;

/*
 * El checklist documental del expediente.
 *
 * Agrupado por `applies_to` porque en inmigración la mitad de los papeles no son del
 * solicitante: sin agrupar, «Pasaporte» aparece tres veces seguidas y no se sabe de
 * quién es cuál.
 *
 * @var array $matter
 * @var array $requirements
 * @var array $docTypes
 * @var bool  $canEdit
 */
$id      = (int) $matter['id'];
$volver  = 'leadflow/expedientes/ficha/' . $id . '?tab=requisitos';
$cerrado = \Components\LeadFlow\Models\MatterModel::isClosed($matter);

/** Color del distintivo según el estado del requisito. */
$color = static fn (string $s): string => [
    'pending'  => 'badge-secondary',
    'received' => 'badge-info',
    'approved' => 'badge-success',
    'rejected' => 'badge-danger',
    'waived'   => 'badge-light',
][$s] ?? 'badge-light';

// Agrupación por destinatario, conservando el orden de cada grupo.
$grupos = [];
foreach ($requirements as $r) {
    $grupos[(string) $r['applies_to']][] = $r;
}
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-list-check"></i>
            <h6 class="font-semibold"><?= lang('Lfl.mtrChecklist') ?></h6>
            <span class="badge badge-light">
                <?= esc(lang('Lfl.mtrProgress', [(int) $matter['docs_received'], (int) $matter['docs_required']])) ?>
            </span>
        </div>
        <a href="<?= base_url('leadflow/leads/ficha/' . (int) $matter['lead_id'] . '?tab=documentos') ?>"
            class="btn btn-secondary btn-sm">
            <i class="icon icon-paperclip"></i> <?= lang('Lfl.tabDocuments') ?>
        </a>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.mtrChecklistHint') ?></p>

        <?php if ($requirements === []): ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.mtrChecklistNone') ?></p>
                <p class="text-sm text-muted"><?= lang('Lfl.mtrChecklistNoneHint') ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($grupos as $aplica => $filas): ?>
                <h6 class="mb-2 mt-4 text-sm font-semibold text-muted first:mt-0">
                    <?= lang('Lfl.ap_' . $aplica) ?>
                </h6>
                <div class="table-card">
                    <?php /* Desplazamiento propio: `.table-card` recorta, y esta
                             tabla lleva controles que en movil no caben. */ ?>
                    <div class="overflow-x-auto">
                    <table class="table" style="min-width:720px">
                        <thead>
                            <tr>
                                <th><?= lang('Lfl.rqLabel') ?></th>
                                <th><?= lang('Lfl.mtrStatus') ?></th>
                                <th><?= lang('Lfl.rqDue') ?></th>
                                <th class="text-right"><?= lang('Lfl.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filas as $r): ?>
                                <?php $vencido = $r['due_at'] && $r['due_at'] < date('Y-m-d H:i:s')
                                    && ! in_array($r['status'], MatterRequirementModel::SETTLED, true); ?>
                                <tr>
                                    <td>
                                        <span class="font-medium"><?= esc($r['label']) ?></span>
                                        <?php if ((int) $r['is_required'] !== 1): ?>
                                            <span class="badge badge-light"><?= lang('Lfl.rqOptional') ?></span>
                                        <?php endif; ?>
                                        <?php if ($r['note']): ?>
                                            <div class="text-xs text-muted"><?= esc($r['note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $color((string) $r['status']) ?>">
                                            <?= lang('Lfl.rq_' . $r['status']) ?>
                                        </span>
                                        <?php if ($r['document_id']): ?>
                                            <a class="ml-1 text-xs text-primary hover:underline"
                                                href="<?= base_url('leadflow/documentos/' . (int) $r['document_id']) ?>">
                                                <i class="icon icon-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm <?= $vencido ? 'text-danger' : '' ?>">
                                        <?= esc(Meta::date($r['due_at'], false)) ?>
                                        <?php if ($vencido): ?>
                                            <span class="badge badge-danger"><?= lang('Lfl.mtrOverdue') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($canEdit && ! $cerrado): ?>
                                            <?php
                                            /*
                                             * Dos formularios por fila, no cinco.
                                             *
                                             * La primera versión ponía un botón por estado y la columna se
                                             * volvía ilegible: seis controles por fila, repetidos trece veces.
                                             * Aquí el estado es UN desplegable con su nota al lado —la nota
                                             * solo es obligatoria al rechazar, y el servidor lo exige— y la
                                             * subida queda aparte porque es la acción que más se usa.
                                             */
                                            ?>
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                <?php // Subir: el documento es del LEAD, el requisito solo lo enlaza. ?>
                                                <form action="<?= base_url('leadflow/expedientes/requisito/subir/' . (int) $r['id']) ?>"
                                                    method="post" enctype="multipart/form-data" class="flex items-center gap-1">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                                                    <input type="file" name="file" class="form-input text-xs" style="max-width:140px" required>
                                                    <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Lfl.rqUpload'), 'attr') ?>">
                                                        <i class="icon icon-upload"></i>
                                                    </button>
                                                </form>

                                                <form action="<?= base_url('leadflow/expedientes/requisito/estado/' . (int) $r['id']) ?>"
                                                    method="post" class="flex items-center gap-1">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                                                    <label class="sr-only" for="st-<?= (int) $r['id'] ?>"><?= lang('Lfl.mtrStatus') ?></label>
                                                    <select id="st-<?= (int) $r['id'] ?>" name="status" class="form-select text-xs" style="max-width:120px">
                                                        <?php foreach (MatterRequirementModel::STATUSES as $est): ?>
                                                            <option value="<?= esc($est, 'attr') ?>" <?= $r['status'] === $est ? 'selected' : '' ?>>
                                                                <?= lang('Lfl.rq_' . $est) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="text" name="note" class="form-input text-xs" style="max-width:130px"
                                                        placeholder="<?= esc(lang('Lfl.rqNote'), 'attr') ?>" maxlength="255"
                                                        value="<?= esc((string) ($r['note'] ?? ''), 'attr') ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Lfl.save'), 'attr') ?>">
                                                        <i class="icon icon-save"></i>
                                                    </button>
                                                </form>

                                                <?= view('partials/delete_button', [
                                                    'action'  => base_url('leadflow/expedientes/requisito/eliminar/' . (int) $r['id']),
                                                    'confirm' => lang('Lfl.confirmDelete'),
                                                    'label'   => lang('Lfl.delete'),
                                                ]) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php // ── Requisito suelto, fuera de la plantilla del servicio ────────── ?>
        <?php if ($canEdit && ! $cerrado): ?>
            <div class="form-section mt-6">
                <h6 class="form-section-title"><?= lang('Lfl.rqAdd') ?></h6>
                <form action="<?= base_url('leadflow/expedientes/requisito/' . $id) ?>" method="post"
                    class="flex flex-wrap items-end gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">

                    <div class="form-group mb-0 flex-1" style="min-width:200px">
                        <label for="rq-label" class="form-label text-xs"><?= lang('Lfl.rqLabel') ?></label>
                        <input type="text" id="rq-label" name="label" class="form-input" maxlength="120" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="rq-applies" class="form-label text-xs"><?= lang('Lfl.rqAppliesTo') ?></label>
                        <select id="rq-applies" name="applies_to" class="form-select">
                            <?php foreach (ServiceRequirementModel::APPLIES as $a): ?>
                                <option value="<?= esc($a, 'attr') ?>"><?= lang('Lfl.ap_' . $a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label for="rq-type" class="form-label text-xs"><?= lang('Lfl.type') ?></label>
                        <select id="rq-type" name="document_type_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($docTypes as $tid => $nombre): ?>
                                <option value="<?= (int) $tid ?>"><?= esc($nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label for="rq-due" class="form-label text-xs"><?= lang('Lfl.rqDue') ?></label>
                        <input type="date" id="rq-due" name="due_date" class="form-input">
                    </div>
                    <?php /* `.form-check` es un bloque: sin esta fila flexible, la casilla
                             cae debajo del botón en vez de al lado. */ ?>
                    <div class="form-group mb-0">
                        <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                        <label class="form-check">
                            <input type="checkbox" name="is_required" value="1" checked>
                            <span><?= lang('Lfl.rqRequired') ?></span>
                        </label>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                        <button type="submit" class="btn btn-primary"><i class="icon icon-plus-circle"></i></button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
