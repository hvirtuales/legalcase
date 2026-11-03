<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Configuración del componente para la empresa activa.
 *
 * Los campos van agrupados en `.form-section` (fieldset con borde, título y
 * ayuda), la clase del núcleo, y ordenados por decisión: avisos, luego defensa
 * contra el correo basura, luego datos personales.
 *
 * @var array $conf
 * @var bool  $hasGd
 */
?>
<form method="post" action="<?= base_url('formularios/configuracion') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header justify-between">
            <div class="flex items-center gap-2">
                <i class="icon icon-gear"></i>
                <h5 class="font-semibold"><?= lang('Frm.settings') ?></h5>
            </div>
            <button class="btn btn-primary btn-sm"><?= lang('Frm.save') ?></button>
        </div>

        <div class="card-body space-y-6">
            <?php if (empty($company)): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Frm.noCompanyHint') ?></span></div>
            <?php else: ?>

                <fieldset class="form-section">
                    <legend class="font-semibold"><?= lang('Frm.cfgNotify') ?></legend>
                    <p class="mb-3 text-xs text-muted"><?= lang('Frm.cfgNotifyHint') ?></p>
                    <input class="form-input" type="email" name="notify_email" maxlength="190"
                           value="<?= esc($conf['notify_email'] ?? '', 'attr') ?>">
                </fieldset>

                <fieldset class="form-section">
                    <legend class="font-semibold"><?= lang('Frm.cfgCaptcha') ?></legend>
                    <p class="mb-3 text-xs text-muted"><?= lang('Frm.cfgCaptchaHint') ?></p>
                    <?php if (!$hasGd): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="icon icon-exclamation-circle"></i><span><?= lang('Frm.cfgNoGd') ?></span>
                        </div>
                    <?php endif; ?>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="captcha_enabled" value="1"
                               <?= !empty($conf['captcha_enabled']) ? 'checked' : '' ?>>
                        <span><?= lang('Frm.cfgCaptcha') ?></span>
                    </label>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="font-semibold"><?= lang('Frm.cfgConsent') ?></legend>
                    <label class="mb-3 flex items-center gap-2">
                        <input type="checkbox" name="require_consent" value="1"
                               <?= !empty($conf['require_consent']) ? 'checked' : '' ?>>
                        <span><?= lang('Frm.cfgConsent') ?></span>
                    </label>

                    <label class="form-label" for="policy"><?= lang('Frm.cfgPolicy') ?></label>
                    <textarea class="form-input mb-3" id="policy" name="policy_text" rows="4"
                              maxlength="4000"><?= esc($conf['policy_text'] ?? '') ?></textarea>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="form-label" for="pver"><?= lang('Frm.cfgVersion') ?></label>
                            <input class="form-input" id="pver" name="policy_version" maxlength="20"
                                   value="<?= esc($conf['policy_version'] ?? '1.0', 'attr') ?>">
                            <p class="mt-1 text-xs text-muted"><?= lang('Frm.cfgVersionHint') ?></p>
                        </div>
                        <div>
                            <label class="form-label" for="ret"><?= lang('Frm.cfgRetention') ?></label>
                            <input class="form-input" id="ret" type="number" min="0" name="retention_days"
                                   value="<?= (int) ($conf['retention_days'] ?? 0) ?>">
                        </div>
                    </div>
                </fieldset>

            <?php endif; ?>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
