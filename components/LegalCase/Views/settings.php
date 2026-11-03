<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Configuración del despacho. Orden por decisión: cómo se aprueba lo que sale al cliente
 * → cómo se validan los plazos → compromisos por defecto → portal → numeración.
 *
 * @var array $row @var string $portalUrl
 */
$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="mx-auto max-w-3xl">
    <div class="card">
        <div class="card-header"><i class="icon icon-gear"></i><h5 class="font-semibold"><?= lang('Lgc.settings') ?></h5></div>
        <div class="card-body">
            <form method="post" action="<?= base_url('legalcase/configuracion') ?>">
                <?= csrf_field() ?>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secApproval') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secApprovalHint') ?></p>
                    <?php foreach (['self', 'reviewer'] as $p): ?>
                        <div class="form-check">
                            <input type="radio" id="ap-<?= $p ?>" name="approval_policy" value="<?= $p ?>" class="form-check-input" <?= $v('approval_policy', 'self') === $p ? 'checked' : '' ?>>
                            <label for="ap-<?= $p ?>" class="form-check-label"><?= lang('Lgc.policy_' . $p) ?><span class="block text-xs text-muted"><?= lang('Lgc.policy_' . $p . 'Hint') ?></span></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secDeadlinePolicy') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secDeadlinePolicyHint') ?></p>
                    <?php foreach (['single', 'dual'] as $p): ?>
                        <div class="form-check">
                            <input type="radio" id="dp-<?= $p ?>" name="deadline_policy" value="<?= $p ?>" class="form-check-input" <?= $v('deadline_policy', 'single') === $p ? 'checked' : '' ?>>
                            <label for="dp-<?= $p ?>" class="form-check-label"><?= lang('Lgc.dpolicy_' . $p) ?><span class="block text-xs text-muted"><?= lang('Lgc.dpolicy_' . $p . 'Hint') ?></span></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secDefaults') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secDefaultsHint') ?></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="form-group">
                            <label for="default_cadence_days" class="form-label"><?= lang('Lgc.fCadence') ?></label>
                            <input type="number" id="default_cadence_days" name="default_cadence_days" class="form-input" min="1" max="365" value="<?= (int) $v('default_cadence_days', 15) ?>">
                        </div>
                        <div class="form-group">
                            <label for="response_sla_hours" class="form-label"><?= lang('Lgc.fSla') ?></label>
                            <input type="number" id="response_sla_hours" name="response_sla_hours" class="form-input" min="1" max="720" value="<?= (int) $v('response_sla_hours', 48) ?>">
                        </div>
                    </div>
                    <small class="form-hint"><?= lang('Lgc.defaultsNote') ?></small>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secPortal') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secPortalHint') ?> <code class="text-xs"><?= esc($portalUrl) ?></code></p>
                    <div class="form-check">
                        <input type="checkbox" id="portal_enabled" name="portal_enabled" value="1" class="form-check-input" <?= (int) $v('portal_enabled', 1) ? 'checked' : '' ?>>
                        <label for="portal_enabled" class="form-check-label"><?= lang('Lgc.fPortalEnabled') ?><span class="block text-xs text-muted"><?= lang('Lgc.fPortalEnabledHint') ?></span></label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="notify_email" name="notify_email" value="1" class="form-check-input" <?= (int) $v('notify_email', 1) ? 'checked' : '' ?>>
                        <label for="notify_email" class="form-check-label"><?= lang('Lgc.fNotifyEmail') ?><span class="block text-xs text-muted"><?= lang('Lgc.fNotifyEmailHint') ?></span></label>
                    </div>
                    <div class="form-group mt-3">
                        <label for="portal_help" class="form-label"><?= lang('Lgc.fPortalHelp') ?></label>
                        <textarea id="portal_help" name="portal_help" rows="4" class="form-textarea" placeholder="<?= esc(lang('Lgc.phPortalHelp'), 'attr') ?>"><?= esc($v('portal_help')) ?></textarea>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secNumbering') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secNumberingHint') ?></p>
                    <div class="form-group">
                        <label for="matter_prefix" class="form-label"><?= lang('Lgc.fPrefix') ?></label>
                        <input type="text" id="matter_prefix" name="matter_prefix" class="form-input uppercase" maxlength="10" value="<?= esc($v('matter_prefix', 'LC'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Lgc.fPrefixHint', [esc($v('matter_prefix', 'LC')) . '-' . str_pad((string) ((int) ($row['matter_sequence'] ?? 0) + 1), 6, '0', STR_PAD_LEFT)]) ?></small>
                    </div>
                </fieldset>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
