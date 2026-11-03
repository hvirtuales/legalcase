<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\FormModel;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-clipboard"></i>
            <h5 class="font-semibold"><?= lang('Lfl.navForms') ?></h5>
        </div>
        <?php if ($forms !== null): ?>
            <a href="<?= base_url('leadflow/formularios/nuevo') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Lfl.formNew') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($forms === null): ?>
            <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Lfl.formsIntro') ?></p>
            <?php if (! $forms): ?>
                <p class="text-sm text-muted"><?= lang('Lfl.noForms') ?></p>
            <?php else: ?>
                <div class="table-card overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Lfl.name') ?></th>
                                <th><?= lang('Lfl.service') ?> / <?= lang('Lfl.campaign') ?></th>
                                <th><?= lang('Lfl.fields') ?></th>
                                <th><?= lang('Lfl.status') ?></th>
                                <th class="text-right"><?= lang('Lfl.formViews') ?> / <?= lang('Lfl.formSubmissions') ?></th>
                                <th class="text-right"><?= lang('Lfl.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forms as $f): ?>
                                <?php $live = $f['status'] === 'published' && (int) $f['is_public'] === 1; ?>
                                <tr>
                                    <td>
                                        <a class="font-semibold text-primary hover:underline" href="<?= base_url('leadflow/formularios/editar/' . (int) $f['id']) ?>"><?= esc($f['name']) ?></a>
                                        <?php if ($live): ?>
                                            <div class="text-xs"><a class="text-muted hover:underline" href="<?= base_url('leadflow-publico/f/' . $f['token']) ?>" target="_blank" rel="noopener">
                                                <i class="icon icon-box-arrow-up-right"></i> <?= lang('Lfl.openPublic') ?></a></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm"><?= esc($f['service_name'] ?: '—') ?><div class="text-xs text-muted"><?= esc($f['campaign_name'] ?: '—') ?></div></td>
                                    <td><?= count(FormModel::fields($f)) ?></td>
                                    <td>
                                        <span class="badge <?= Meta::color(Meta::FORM_COLORS, $f['status']) ?>"><?= esc(Meta::label(Meta::FORM_STATUSES, $f['status'])) ?></span>
                                        <span class="badge <?= (int) $f['is_public'] === 1 ? 'badge-info' : 'badge-light' ?>"><?= lang((int) $f['is_public'] === 1 ? 'Lfl.formPublic' : 'Lfl.formInternal') ?></span>
                                    </td>
                                    <td class="text-right"><?= (int) $f['views'] ?> / <?= (int) $f['submissions'] ?></td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <a href="<?= base_url('leadflow/formularios/editar/' . (int) $f['id']) ?>" class="btn btn-secondary btn-sm" title="<?= esc(lang('Lfl.edit'), 'attr') ?>"><i class="icon icon-pencil"></i></a>
                                            <?php if ($canDelete): ?>
                                                <?= view('partials/delete_button', [
                                                    'action'  => base_url('leadflow/formularios/eliminar/' . (int) $f['id']),
                                                    'confirm' => lang('Lfl.formDelConfirm'),
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
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
