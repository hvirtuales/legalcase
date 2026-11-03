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
                    <legend class="form-section-title"><i class="icon icon-shield-check"></i> <?= lang('Lgc.secPrivacy') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secPrivacyHint', [esc((string) ($row['privacy_version'] ?? '1'))]) ?></p>
                    <textarea id="privacy_text" name="privacy_text" rows="8" class="form-textarea" aria-label="<?= esc(lang('Lgc.secPrivacy'), 'attr') ?>"
                        placeholder="<?= esc(lang('Lgc.phPrivacy'), 'attr') ?>"><?= esc($v('privacy_text')) ?></textarea>
                    <small class="form-hint"><?= lang('Lgc.privacyTextHint') ?></small>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secRetention') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secRetentionHint') ?></p>
                    <div class="form-group">
                        <label for="retention_years" class="form-label"><?= lang('Lgc.fRetentionYears') ?></label>
                        <input type="number" id="retention_years" name="retention_years" class="form-input" min="1" max="50" value="<?= (int) $v('retention_years', 10) ?>">
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
<div class="mx-auto mt-4 grid max-w-3xl gap-4">
    <div class="card" id="soporte">
        <div class="card-header"><i class="icon icon-life-preserver"></i><h5 class="font-semibold"><?= lang('Lgc.supportTitle') ?></h5></div>
        <div class="card-body">
            <p class="mb-3 text-sm text-muted"><?= lang('Lgc.supportHint') ?></p>
            <form method="post" action="<?= base_url('legalcase/configuracion/soporte') ?>" class="grid gap-3 md:grid-cols-3">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="sp-who" class="form-label"><?= lang('Lgc.fSupportWho') ?></label>
                    <input id="sp-who" type="text" name="who" class="form-input" required maxlength="100" placeholder="<?= esc(lang('Lgc.phSupportWho'), 'attr') ?>">
                </div>
                <div class="form-group">
                    <label for="sp-h" class="form-label"><?= lang('Lgc.fSupportHours') ?></label>
                    <input id="sp-h" type="number" name="hours" class="form-input" min="1" max="72" value="4">
                </div>
                <div class="form-group md:col-span-3">
                    <label for="sp-r" class="form-label"><?= lang('Lgc.fSupportReason') ?> <span class="text-danger">*</span></label>
                    <input id="sp-r" type="text" name="reason" class="form-input" required minlength="10" maxlength="255" placeholder="<?= esc(lang('Lgc.phSupportReason'), 'attr') ?>">
                </div>
                <div class="md:col-span-3"><button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-key"></i> <?= lang('Lgc.grantSupport') ?></button></div>
            </form>
            <?php if ($grants): ?>
                <div class="table-card mt-4 overflow-x-auto">
                    <table class="table">
                        <thead><tr><th><?= lang('Lgc.fUser') ?></th><th><?= lang('Lgc.fSupportReason') ?></th><th><?= lang('Lgc.fExpires') ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($grants as $g): $live = !$g['revoked_at'] && strtotime((string) $g['expires_at']) > time(); ?>
                            <tr>
                                <td><?= esc($g['username']) ?></td>
                                <td class="max-w-xs truncate text-xs"><?= esc($g['reason']) ?></td>
                                <td class="text-xs"><?= esc((string) $g['expires_at']) ?> <?= $live ? '<span class="badge badge-success">' . lang('Lgc.active') . '</span>' : '<span class="badge badge-secondary">' . lang('Lgc.inactive') . '</span>' ?></td>
                                <td class="text-right">
                                    <?php if ($live): ?>
                                        <form method="post" action="<?= base_url('legalcase/configuracion/soporte/revocar/' . (int) $g['id']) ?>"><?= csrf_field() ?>
                                            <button class="btn btn-ghost btn-sm"><?= lang('Lgc.revokeAccess') ?></button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" id="reasignar">
        <div class="card-header"><i class="icon icon-arrow-repeat"></i><h5 class="font-semibold"><?= lang('Lgc.reassignTitle') ?></h5></div>
        <div class="card-body">
            <p class="mb-3 text-sm text-muted"><?= lang('Lgc.reassignHint') ?></p>
            <form method="post" action="<?= base_url('legalcase/configuracion/reasignar') ?>" class="grid gap-3 md:grid-cols-2"
                onsubmit="return confirm('<?= esc(lang('Lgc.confirmReassign'), 'js') ?>')">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="ra-from" class="form-label"><?= lang('Lgc.fFromUser') ?></label>
                    <select id="ra-from" name="from_user" class="form-select" required>
                        <?php foreach ($holders as $uid => $name): ?><option value="<?= (int) $uid ?>"><?= esc($name) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ra-to" class="form-label"><?= lang('Lgc.fToUser') ?></label>
                    <select id="ra-to" name="to_user" class="form-select" required>
                        <?php foreach ($users as $uid => $name): ?><option value="<?= (int) $uid ?>"><?= esc($name) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2"><button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-repeat"></i> <?= lang('Lgc.reassign') ?></button></div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
