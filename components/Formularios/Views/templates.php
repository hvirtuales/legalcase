<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Plantillas: las de arranque (globales, `company_id` NULL) y las que la
 * empresa guarda desde sus propios formularios.
 *
 * @var array $templates
 * @var array $forms
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-list-columns-reverse"></i>
            <h5 class="font-semibold"><?= lang('Frm.templates') ?></h5>
        </div>
        <form method="post" action="<?= base_url('formularios/plantillas/sembrar') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-secondary btn-sm"><i class="icon icon-sparkles"></i> <?= lang('Frm.tplSeed') ?></button>
        </form>
    </div>

    <div class="card-body">
        <?php if (!$templates): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Frm.tplNone') ?></span></div>
        <?php else: ?>
            <div class="grid gap-4 md:grid-cols-2">
                <?php foreach ($templates as $tpl): ?>
                    <div class="rounded-xl border border-line p-4">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <h6 class="font-semibold"><?= esc($tpl['name']) ?></h6>
                            <span class="badge <?= $tpl['company_id'] === null ? 'badge-secondary' : 'badge-primary' ?>">
                                <?= $tpl['company_id'] === null ? lang('Frm.tplStarter') : lang('Frm.tplOwn') ?>
                            </span>
                        </div>
                        <p class="mb-3 text-sm text-muted"><?= esc($tpl['description']) ?></p>

                        <div class="flex flex-wrap items-center gap-2">
                            <form method="post" action="<?= base_url('formularios/plantillas/usar/' . $tpl['id']) ?>"
                                  class="flex items-center gap-2">
                                <?= csrf_field() ?>
                                <select name="display" class="form-input form-input-sm">
                                    <option value="card"><?= lang('Frm.displayCard') ?></option>
                                    <option value="fullscreen"><?= lang('Frm.displayFull') ?></option>
                                </select>
                                <button class="btn btn-primary btn-sm"><?= lang('Frm.tplUse') ?></button>
                            </form>

                            <?php if ($tpl['company_id'] !== null): ?>
                                <?= view('partials/delete_button', [
                                    'action'  => base_url('formularios/plantillas/eliminar/' . $tpl['id']),
                                    'confirm' => lang('Frm.confirmDeleteTpl'),
                                    'class'   => 'btn btn-ghost btn-sm text-danger',
                                    'label'   => lang('Frm.delete'),
                                ]) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($forms): ?>
<div class="card mt-6">
    <div class="card-header"><h5 class="font-semibold"><?= lang('Frm.tplSaveFrom') ?></h5></div>
    <div class="card-body">
        <?php foreach ($forms as $f): ?>
            <form method="post" action="<?= base_url('formularios/plantillas/desde/' . $f['id']) ?>"
                  class="mb-2 flex flex-wrap items-center gap-2">
                <?= csrf_field() ?>
                <span class="min-w-48 flex-1 font-medium"><?= esc($f['title']) ?></span>
                <input class="form-input flex-1" name="description" maxlength="255"
                       placeholder="<?= esc(lang('Frm.fDescription'), 'attr') ?>">
                <button class="btn btn-secondary btn-sm"><?= lang('Frm.save') ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
