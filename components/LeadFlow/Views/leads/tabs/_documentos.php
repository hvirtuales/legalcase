<?php

use Components\LeadFlow\Libraries\Meta;

$id = (int) $lead['id'];
?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-paperclip"></i><h5 class="font-semibold"><?= lang('Lfl.tabDocuments') ?></h5></div>
            <div class="card-body">
                <p class="mb-3 text-xs text-muted"><?= lang('Lfl.docsHint') ?></p>
                <?php if (! $documents): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.noDocuments') ?></p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= lang('Lfl.document') ?></th>
                                    <th><?= lang('Lfl.type') ?></th>
                                    <th><?= lang('Lfl.transfer') ?></th>
                                    <th class="text-right"><?= lang('Lfl.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $d): ?>
                                    <tr>
                                        <td>
                                            <a class="font-medium text-primary hover:underline" href="<?= base_url('leadflow/documentos/' . (int) $d['id']) ?>"><?= esc($d['original_name']) ?></a>
                                            <div class="text-xs text-muted">
                                                <?= esc(number_format($d['size'] / 1024, 0)) ?> KB · <?= esc(Meta::date($d['created_at'])) ?>
                                                · <?= lang($d['source'] === 'public' ? 'Lfl.docFromProspect' : 'Lfl.docInternal') ?>
                                            </div>
                                            <div class="font-mono text-[10px] text-faint" title="SHA-256"><?= esc(substr($d['hash'], 0, 16)) ?>…</div>
                                        </td>
                                        <td><?= esc($docTypes[(int) $d['document_type_id']] ?? '—') ?></td>
                                        <td>
                                            <span class="badge <?= $d['is_authorized'] ? 'badge-success' : 'badge-light' ?>">
                                                <?= lang($d['is_authorized'] ? 'Lfl.docAuthorized' : 'Lfl.docNotAuthorized') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex justify-end gap-1">
                                                <a href="<?= base_url('leadflow/documentos/' . (int) $d['id']) ?>" class="btn btn-secondary btn-sm" title="<?= esc(lang('Lfl.download'), 'attr') ?>"><i class="icon icon-download"></i></a>
                                                <?php if ($canEdit): ?>
                                                    <form action="<?= base_url('leadflow/documentos/autorizar/' . (int) $d['id']) ?>" method="post">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang($d['is_authorized'] ? 'Lfl.docRevoke' : 'Lfl.docAuthorize'), 'attr') ?>">
                                                            <i class="icon <?= $d['is_authorized'] ? 'icon-lock' : 'icon-shield-check' ?>"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($canDelete): ?>
                                                    <?= view('partials/delete_button', [
                                                        'action'  => base_url('leadflow/documentos/eliminar/' . (int) $d['id']),
                                                        'confirm' => lang('Lfl.confirmDelete'),
                                                        'label'   => lang('Lfl.delete'),
                                                    ]) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <?php if ($canCreate): ?>
            <form action="<?= base_url('leadflow/documentos/subir/' . $id) ?>" method="post" enctype="multipart/form-data" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><i class="icon icon-upload"></i><h5 class="font-semibold"><?= lang('Lfl.docUpload') ?></h5></div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="document_type_id" class="form-label"><?= lang('Lfl.type') ?></label>
                        <select id="document_type_id" name="document_type_id" class="form-select">
                            <option value=""><?= lang('Lfl.none') ?></option>
                            <?php foreach ($docTypes as $tid => $tname): ?>
                                <option value="<?= (int) $tid ?>"><?= esc($tname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="document" class="form-label"><?= lang('Lfl.file') ?> <span class="text-danger">*</span></label>
                        <input type="file" id="document" name="document" class="form-input" required accept="application/pdf,image/*">
                        <p class="form-hint"><?= lang('Lfl.docLimits') ?></p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-upload"></i> <?= lang('Lfl.upload') ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
