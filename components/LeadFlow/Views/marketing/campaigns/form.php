<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header"><i class="icon icon-bar-chart"></i><h5 class="font-semibold"><?= esc($title) ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>
        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.campaign') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.campaignHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="120" class="form-input" required value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="source_id" class="form-label"><?= lang('Lfl.source') ?></label>
                        <select id="source_id" name="source_id" class="form-select">
                            <option value=""><?= lang('Lfl.none') ?></option>
                            <?php foreach ($sources as $id => $name): ?>
                                <option value="<?= (int) $id ?>" <?= (int) $v('source_id') === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="utm_campaign" class="form-label">utm_campaign</label>
                        <input type="text" id="utm_campaign" name="utm_campaign" maxlength="120" class="form-input" value="<?= esc($v('utm_campaign'), 'attr') ?>" placeholder="fb_visas_familia">
                        <p class="form-hint"><?= lang('Lfl.utmCampaignHint') ?></p>
                    </div>
                </div>
            </fieldset>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.period') ?> · <?= lang('Lfl.budget') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.campaignBudgetHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="starts_on" class="form-label"><?= lang('Lfl.from') ?></label>
                        <input type="date" id="starts_on" name="starts_on" class="form-input" value="<?= esc((string) $v('starts_on'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="ends_on" class="form-label"><?= lang('Lfl.to') ?></label>
                        <input type="date" id="ends_on" name="ends_on" class="form-input" value="<?= esc((string) $v('ends_on'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="budget" class="form-label"><?= lang('Lfl.budget') ?></label>
                        <input type="number" step="0.01" min="0" id="budget" name="budget" class="form-input" value="<?= esc((string) $v('budget'), 'attr') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="notes" class="form-label"><?= lang('Lfl.notes') ?></label>
                    <textarea id="notes" name="notes" rows="2" class="form-textarea"><?= esc($v('notes')) ?></textarea>
                </div>
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" <?= $row === [] || $v('is_active', 1) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Lfl.active') ?></span>
                </label>
            </fieldset>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                <a href="<?= base_url('leadflow/marketing/campanas') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Lfl.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
