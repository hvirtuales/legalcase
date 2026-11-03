<?php

use Components\Copropiedad\Libraries\DocumentService;
use Components\Copropiedad\Libraries\Meta;

/**
 * Bloque de documentos adjuntos, reutilizable.
 *
 * Lo usan la ficha del inmueble y la de la persona; sirve igual para vehículos,
 * mascotas y autorizaciones porque la tabla es polimórfica.
 *
 * Espera: `$documents`, `$ownerType`, `$ownerId`, `$returnTo`.
 *
 * `return_to` viaja en el formulario para volver a la ficha de donde salió, y el
 * controlador lo **valida contra un patrón cerrado** antes de redirigir: un destino
 * libre sería una redirección abierta.
 */
$documents = $documents ?? [];
$mb        = (int) round(DocumentService::MAX_BYTES / 1024 / 1024);
$hoy       = date('Y-m-d');
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-paperclip"></i>
            <h5 class="font-semibold"><?= lang('Cop.documents') ?></h5>
        </div>
    </div>
    <div class="card-body">
        <?php if (! $documents): ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Cop.noDocuments') ?></p>
        <?php else: ?>
            <div class="mb-4 overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Cop.docTitle') ?></th>
                            <th><?= lang('Cop.docKind') ?></th>
                            <th><?= lang('Cop.docExpires') ?></th>
                            <th class="text-right"><?= lang('Cop.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $d): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($d['title']) ?></strong>
                                    <div class="text-xs text-muted"><?= esc($d['original_name']) ?></div>
                                </td>
                                <td><?= esc(Meta::label(Meta::DOC_KINDS, $d['kind'])) ?></td>
                                <td>
                                    <?php if (empty($d['expires_at'])): ?>
                                        <span class="text-muted"><?= lang('Cop.none') ?></span>
                                    <?php else: ?>
                                        <?php // Lo vencido en rojo: es la razón de guardar la fecha. ?>
                                        <span class="badge <?= $d['expires_at'] < $hoy ? 'badge-danger' : 'badge-secondary' ?>">
                                            <?= esc($d['expires_at'] < $hoy
                                                ? lang('Cop.docExpiredOn', [$d['expires_at']])
                                                : lang('Cop.docExpiresOn', [$d['expires_at']])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="<?= base_url('copropiedad/documentos/' . (int) $d['id']) ?>"
                                            class="btn btn-secondary btn-sm" title="<?= esc(lang('Cop.download'), 'attr') ?>">
                                            <i class="icon icon-download"></i>
                                        </a>
                                        <?= view('partials/delete_button', [
                                            'action'  => base_url('copropiedad/documentos/eliminar/' . (int) $d['id']),
                                            'confirm' => lang('Cop.docDelConfirm'),
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

        <form action="<?= base_url('copropiedad/documentos/subir') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="owner_type" value="<?= esc($ownerType, 'attr') ?>">
            <input type="hidden" name="owner_id" value="<?= (int) $ownerId ?>">
            <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.docAdd') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.docFileHint', [$mb]) ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group md:col-span-2">
                        <label for="doc-title" class="form-label"><?= lang('Cop.docTitle') ?></label>
                        <input type="text" id="doc-title" name="title" class="form-input" maxlength="140">
                    </div>
                    <div class="form-group">
                        <label for="doc-kind" class="form-label"><?= lang('Cop.docKind') ?></label>
                        <select id="doc-kind" name="kind" class="form-select">
                            <?php foreach (Meta::options(Meta::DOC_KINDS) as $v => $l): ?>
                                <option value="<?= esc($v, 'attr') ?>" <?= $v === 'otro' ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="doc-file" class="form-label"><?= lang('Cop.docFile') ?> <span class="text-danger">*</span></label>
                        <input type="file" id="doc-file" name="document" class="form-input" required
                            accept="<?= esc(implode(',', DocumentService::ALLOWED_MIMES), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="doc-issued" class="form-label"><?= lang('Cop.docIssued') ?></label>
                        <input type="date" id="doc-issued" name="issued_at" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="doc-expires" class="form-label"><?= lang('Cop.docExpires') ?></label>
                        <input type="date" id="doc-expires" name="expires_at" class="form-input">
                        <small class="form-hint"><?= lang('Cop.docExpiresHint') ?></small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="icon icon-upload"></i> <?= lang('Cop.upload') ?>
                </button>
            </fieldset>
        </form>
    </div>
</div>
