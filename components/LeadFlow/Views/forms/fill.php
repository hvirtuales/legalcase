<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Models\LeadModel;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-clipboard"></i>
            <h5 class="font-semibold"><?= esc($form['name']) ?></h5>
        </div>
        <a href="<?= base_url('leadflow/leads/ficha/' . (int) $lead['id'] . '?tab=precalificacion') ?>" class="btn btn-ghost btn-sm">
            <i class="icon icon-arrow-left"></i> <?= esc(LeadModel::fullName($lead)) ?>
        </a>
    </div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>
        <?php if ($form['description']): ?>
            <p class="mb-4 whitespace-pre-line text-sm text-muted"><?= esc($form['description']) ?></p>
        <?php endif; ?>

        <?php if (! $fields): ?>
            <p class="text-sm text-muted"><?= lang('Lfl.fbEmpty') ?></p>
        <?php else: ?>
            <form action="<?= base_url('leadflow/leads/precalificar/' . (int) $lead['id'] . '/' . (int) $form['id']) ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lfl.tabPrequal') ?></legend>
                    <p class="form-section-hint"><?= lang('Lfl.fillIntro') ?></p>
                    <?= view('Components\LeadFlow\Views\forms\_fields', ['fields' => $fields]) ?>
                </fieldset>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                    <a href="<?= base_url('leadflow/leads/ficha/' . (int) $lead['id'] . '?tab=precalificacion') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Lfl.cancel') ?></a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
