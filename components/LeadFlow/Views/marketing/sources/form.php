<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;

$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header"><i class="icon icon-signpost"></i><h5 class="font-semibold"><?= esc($title) ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>
        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.source') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.sourceHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="80" class="form-input" required value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="kind" class="form-label"><?= lang('Lfl.type') ?></label>
                        <select id="kind" name="kind" class="form-select">
                            <?php foreach (Meta::options(Meta::SOURCE_KINDS) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('kind', 'other') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint"><?= lang('Lfl.sourceKindHint') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="utm_source" class="form-label">utm_source</label>
                        <input type="text" id="utm_source" name="utm_source" maxlength="80" class="form-input" value="<?= esc($v('utm_source'), 'attr') ?>" placeholder="facebook">
                        <p class="form-hint"><?= lang('Lfl.utmSourceHint') ?></p>
                    </div>
                </div>
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" <?= $row === [] || $v('is_active', 1) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Lfl.active') ?></span>
                </label>
            </fieldset>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                <a href="<?= base_url('leadflow/marketing/fuentes') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Lfl.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
