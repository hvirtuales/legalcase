<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Configuración del componente por empresa.
 *
 * Agrupada por decisión: numeración → plazos → cómo puede radicar el ciudadano
 * → textos legales → IA y acuse. Los plazos son por tipo porque no es lo mismo
 * un reclamo que una felicitación.
 *
 * @var array $cfg @var bool $aiReady @var string $next @var int $used
 */
?>

<div class="mx-auto max-w-3xl">
    <div class="card">
        <div class="card-header">
            <i class="icon icon-gear"></i>
            <h5 class="font-semibold"><?= lang('Pqr.settings') ?></h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= base_url('pqrsf/configuracion') ?>">
                <?= csrf_field() ?>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Pqr.setNumbering') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.setNumberingHint') ?></p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="form-group">
                            <label for="prefix" class="form-label"><?= lang('Pqr.setPrefix') ?></label>
                            <input type="text" id="prefix" name="prefix" class="form-input" maxlength="10"
                                value="<?= esc($cfg['prefix'] ?? 'PQR', 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= lang('Pqr.setNext') ?></label>
                            <p class="rounded-md border border-line px-3 py-2 font-mono text-sm"><?= esc($next) ?></p>
                            <small class="form-hint"><?= lang('Pqr.setUsed', [$used]) ?></small>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Pqr.setDays') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.setDaysMoved') ?></p>
                    <a href="<?= base_url('pqrsf/tipos') ?>" class="btn btn-secondary btn-sm">
                        <i class="icon icon-list-check"></i> <?= lang('Pqr.types') ?>
                    </a>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Pqr.setPublic') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.setPublicHint') ?></p>

                    <div class="form-check mb-3">
                        <input type="checkbox" id="allow_anonymous" name="allow_anonymous" value="1" class="form-check-input"
                            <?= !empty($cfg['allow_anonymous']) ? 'checked' : '' ?>>
                        <label for="allow_anonymous" class="form-check-label">
                            <?= lang('Pqr.setAnonymous') ?>
                            <span class="block text-xs text-muted"><?= lang('Pqr.setAnonymousHint') ?></span>
                        </label>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="captcha_enabled" name="captcha_enabled" value="1" class="form-check-input"
                            <?= !empty($cfg['captcha_enabled']) ? 'checked' : '' ?>>
                        <label for="captcha_enabled" class="form-check-label">
                            <?= lang('Pqr.setCaptcha') ?>
                            <span class="block text-xs text-muted"><?= lang('Pqr.setCaptchaHint') ?></span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Pqr.setLegal') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.setLegalHint') ?></p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="form-group">
                            <label for="privacy_url" class="form-label"><?= lang('Pqr.setPrivacyUrl') ?></label>
                            <input type="url" id="privacy_url" name="privacy_url" class="form-input" maxlength="255"
                                value="<?= esc($cfg['privacy_url'] ?? '', 'attr') ?>" placeholder="https://…">
                        </div>
                        <div class="form-group">
                            <label for="terms_url" class="form-label"><?= lang('Pqr.setTermsUrl') ?></label>
                            <input type="url" id="terms_url" name="terms_url" class="form-input" maxlength="255"
                                value="<?= esc($cfg['terms_url'] ?? '', 'attr') ?>" placeholder="https://…">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="privacy_text" class="form-label"><?= lang('Pqr.setPrivacyText') ?></label>
                        <textarea id="privacy_text" name="privacy_text" rows="4" class="form-input"><?= esc($cfg['privacy_text'] ?? '') ?></textarea>
                        <small class="form-hint"><?= lang('Pqr.setTextHint') ?></small>
                    </div>

                    <div class="form-group">
                        <label for="terms_text" class="form-label"><?= lang('Pqr.setTermsText') ?></label>
                        <textarea id="terms_text" name="terms_text" rows="4" class="form-input"><?= esc($cfg['terms_text'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="policy_version" class="form-label"><?= lang('Pqr.setPolicyVersion') ?></label>
                        <input type="text" id="policy_version" name="policy_version" class="form-input" maxlength="40"
                            value="<?= esc($cfg['policy_version'] ?? 'v1', 'attr') ?>">
                        <small class="form-hint"><?= lang('Pqr.setPolicyVersionHint') ?></small>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Pqr.setAi') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.setAiHint') ?></p>

                    <?php if (!$aiReady): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="icon icon-exclamation-circle"></i>
                            <span><?= lang('Pqr.setAiNotConfigured') ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="form-check mb-3">
                        <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" class="form-check-input"
                            <?= !empty($cfg['ai_enabled']) ? 'checked' : '' ?>>
                        <label for="ai_enabled" class="form-check-label">
                            <?= lang('Pqr.setAiEnabled') ?>
                            <span class="block text-xs text-muted"><?= lang('Pqr.setAiEnabledHint') ?></span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="ai_tone" class="form-label"><?= lang('Pqr.setAiTone') ?></label>
                        <select id="ai_tone" name="ai_tone" class="form-select">
                            <?php foreach (['formal', 'cercano', 'breve'] as $tone): ?>
                                <option value="<?= $tone ?>" <?= ($cfg['ai_tone'] ?? 'formal') === $tone ? 'selected' : '' ?>>
                                    <?= lang('Pqr.tone_' . $tone) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint"><?= lang('Pqr.setAiToneHint') ?></small>
                    </div>

                    <!-- El rol, el contexto y las reglas del asistente. -->
                    <div class="form-group mb-0">
                        <label for="ai_instructions" class="form-label"><?= lang('Pqr.setAiInstructions') ?></label>
                        <textarea id="ai_instructions" name="ai_instructions" rows="12" class="form-input font-mono text-xs"
                            placeholder="<?= esc($aiSample, 'attr') ?>"><?= esc($cfg['ai_instructions'] ?? '') ?></textarea>
                        <small class="form-hint"><?= lang('Pqr.setAiInstructionsHint') ?></small>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button type="button" class="btn btn-ghost btn-sm" id="aiSampleBtn">
                                <i class="icon icon-sparkles"></i> <?= lang('Pqr.setAiSample') ?>
                            </button>
                            <span class="text-xs text-muted">
                                <?= lang('Pqr.setAiVars') ?>
                                <?php foreach ($aiVars as $v): ?>
                                    <code class="mx-0.5">{{<?= $v ?>}}</code>
                                <?php endforeach; ?>
                            </span>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="icon icon-info-circle"></i>
                            <span><?= lang('Pqr.setAiJsonNote') ?></span>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Pqr.setAck') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.setAckHint') ?></p>

                    <div class="form-group">
                        <label for="ack_template" class="form-label"><?= lang('Pqr.setAckTemplate') ?></label>
                        <textarea id="ack_template" name="ack_template" rows="5" class="form-input"
                            placeholder="<?= esc(lang('Pqr.ackDefault'), 'attr') ?>"><?= esc($cfg['ack_template'] ?? '') ?></textarea>
                        <small class="form-hint"><?= lang('Pqr.setAckVars') ?></small>
                    </div>
                </fieldset>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Pqr.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    // «Usar la de ejemplo»: rellena el cuadro, sin guardar y sin pisar lo escrito
    // por accidente.
    var btn = document.getElementById('aiSampleBtn');
    var box = document.getElementById('ai_instructions');
    var sample = <?= json_encode($aiSample, JSON_UNESCAPED_UNICODE) ?>;

    btn.addEventListener('click', function () {
        if (box.value.trim() !== '' && !confirm(<?= json_encode(lang('Pqr.setAiSampleConfirm'), JSON_UNESCAPED_UNICODE) ?>)) {
            return;
        }
        box.value = sample;
        box.focus();
    });
}());
</script>
<?= $this->endSection() ?>
