<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Models\SettingsModel;

?>
<div class="card">
    <div class="card-header"><i class="icon icon-gear"></i><h5 class="font-semibold"><?= lang('Lfl.navSettings') ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_settings_tabs', ['active' => 'general']) ?>
        <form action="<?= base_url('leadflow/configuracion') ?>" method="post">
            <?= csrf_field() ?>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.setGeneral') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.setGeneralHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="currency" class="form-label"><?= lang('Lfl.currency') ?></label>
                        <select id="currency" name="currency" class="form-select">
                            <?php foreach (SettingsModel::CURRENCIES as $c): ?>
                                <option value="<?= esc($c, 'attr') ?>" <?= ($settings['currency'] ?? 'USD') === $c ? 'selected' : '' ?>><?= esc($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="no_response_days" class="form-label"><?= lang('Lfl.noResponseDays') ?></label>
                        <input type="number" min="1" max="90" id="no_response_days" name="no_response_days" class="form-input" value="<?= (int) ($settings['no_response_days'] ?? 7) ?>">
                        <p class="form-hint"><?= lang('Lfl.noResponseDaysHint') ?></p>
                    </div>
                </div>
            </fieldset>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.setPublicForm') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.setPublicFormHint') ?></p>
                <div class="form-group">
                    <label for="public_intro" class="form-label"><?= lang('Lfl.publicIntro') ?></label>
                    <textarea id="public_intro" name="public_intro" rows="2" class="form-textarea"><?= esc($settings['public_intro'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="consent_text" class="form-label"><?= lang('Lfl.consentText') ?></label>
                    <textarea id="consent_text" name="consent_text" rows="4" class="form-textarea" placeholder="<?= esc(lang('Lfl.pubConsentDefault'), 'attr') ?>"><?= esc($settings['consent_text'] ?? '') ?></textarea>
                    <p class="form-hint"><?= lang('Lfl.consentTextHint') ?></p>
                </div>
            </fieldset>
            <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
