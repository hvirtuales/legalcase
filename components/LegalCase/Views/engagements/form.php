<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Contrato de prestación de servicios / carta de encargo / poder. Orden por decisión:
 * qué se firma → qué se encarga (alcance y exclusiones) → cómo se cobra → vigencia y documento
 * → envío a firma. Firmado, ya no se edita: se termina y se registra uno nuevo.
 *
 * @var array $matter @var array $row @var array $docs @var array $accounts @var ?array $signature
 * @var bool $canWork @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\EngagementModel;

$v      = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$locked = ($row['status'] ?? 'draft') === 'signed' || !$canWork;
$mid    = (int) $matter['id'];
?>
<div class="mb-4 flex items-center gap-3">
    <a href="<?= base_url('legalcase/asuntos/ficha/' . $mid) ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i></a>
    <div>
        <h4 class="font-bold"><?= lang('Lgc.engagement') ?>
            <?php if (!empty($row['id'])): ?><span class="badge badge-light"><?= lang('Lgc.engstatus_' . $row['status']) ?></span><?php endif; ?></h4>
        <p class="text-xs text-muted"><span class="font-mono"><?= esc($matter['number']) ?></span> · <?= esc($matter['title']) ?></p>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="card min-w-0 lg:col-span-2">
        <div class="card-body">
            <?php if ($locked && ($row['status'] ?? '') === 'signed'): ?>
                <div class="alert alert-success mb-4"><i class="icon icon-check-circle"></i><span><?= lang('Lgc.engagementSignedNote') ?></span></div>
            <?php endif; ?>
            <form method="post" action="<?= base_url('legalcase/asuntos/contrato/' . $mid) ?>">
                <?= csrf_field() ?>
                <fieldset class="form-section" <?= $locked ? 'disabled' : '' ?>>
                    <legend class="form-section-title"><?= lang('Lgc.secEngKind') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secEngKindHint') ?></p>
                    <select id="kind" name="kind" class="form-select" aria-label="<?= esc(lang('Lgc.secEngKind'), 'attr') ?>">
                        <?php foreach (EngagementModel::KINDS as $k): ?><option value="<?= $k ?>" <?= $v('kind') === $k ? 'selected' : '' ?>><?= lang('Lgc.engkind_' . $k) ?></option><?php endforeach; ?>
                    </select>
                </fieldset>
                <fieldset class="form-section" <?= $locked ? 'disabled' : '' ?>>
                    <legend class="form-section-title"><?= lang('Lgc.secEngScope') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secEngScopeHint') ?></p>
                    <div class="form-group">
                        <label for="scope" class="form-label"><?= lang('Lgc.fScope') ?> <span class="text-danger">*</span></label>
                        <textarea id="scope" name="scope" rows="4" class="form-textarea" required placeholder="<?= esc(lang('Lgc.phScope'), 'attr') ?>"><?= esc($v('scope')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="exclusions" class="form-label"><?= lang('Lgc.fExclusions') ?></label>
                        <textarea id="exclusions" name="exclusions" rows="2" class="form-textarea" placeholder="<?= esc(lang('Lgc.phExclusions'), 'attr') ?>"><?= esc($v('exclusions')) ?></textarea>
                    </div>
                </fieldset>
                <fieldset class="form-section" <?= $locked ? 'disabled' : '' ?>>
                    <legend class="form-section-title"><?= lang('Lgc.secEngFees') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secEngFeesHint') ?></p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="form-group">
                            <label for="fee_type" class="form-label"><?= lang('Lgc.fFeeType') ?></label>
                            <select id="fee_type" name="fee_type" class="form-select">
                                <?php foreach (EngagementModel::FEE_TYPES as $f): ?><option value="<?= $f ?>" <?= $v('fee_type') === $f ? 'selected' : '' ?>><?= lang('Lgc.fee_' . $f) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label for="amount" class="form-label"><?= lang('Lgc.fAmount') ?></label>
                                <input id="amount" type="number" step="0.01" min="0" name="amount" class="form-input" value="<?= esc((string) $v('amount', '0'), 'attr') ?>">
                            </div>
                            <div class="form-group">
                                <label for="currency" class="form-label"><?= lang('Lgc.fCurrency') ?></label>
                                <input id="currency" type="text" name="currency" maxlength="3" class="form-input uppercase" value="<?= esc((string) $v('currency', 'COP'), 'attr') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="contingency_pct" class="form-label"><?= lang('Lgc.fContingency') ?></label>
                            <input id="contingency_pct" type="number" step="0.01" min="0" max="100" name="contingency_pct" class="form-input" value="<?= esc((string) $v('contingency_pct', '0'), 'attr') ?>">
                        </div>
                    </div>
                </fieldset>
                <fieldset class="form-section" <?= $locked ? 'disabled' : '' ?>>
                    <legend class="form-section-title"><?= lang('Lgc.secEngTerm') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secEngTermHint') ?></p>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="form-group">
                            <label for="starts_on" class="form-label"><?= lang('Lgc.fStartsOnEng') ?></label>
                            <input id="starts_on" type="date" name="starts_on" class="form-input" value="<?= esc((string) $v('starts_on'), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ends_on" class="form-label"><?= lang('Lgc.fEndsOn') ?></label>
                            <input id="ends_on" type="date" name="ends_on" class="form-input" value="<?= esc((string) $v('ends_on'), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label for="document_id" class="form-label"><?= lang('Lgc.fEngDocument') ?></label>
                            <select id="document_id" name="document_id" class="form-select">
                                <option value=""><?= lang('Lgc.none') ?></option>
                                <?php foreach ($docs as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (string) $v('document_id') === (string) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <small class="form-hint"><?= lang('Lgc.engDocHint') ?></small>
                </fieldset>
                <?php if (!$locked): ?>
                    <div class="mt-4 flex justify-end"><button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button></div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="min-w-0 space-y-4">
        <div class="card">
            <div class="card-header"><i class="icon icon-pencil-square"></i><h5 class="font-semibold"><?= lang('Lgc.engSignTitle') ?></h5></div>
            <div class="card-body text-sm">
                <?php if ($signature): ?>
                    <p><?= lang('Lgc.sig_' . $signature['status']) ?> · <?= esc(Meta::date($signature['signed_at'] ?: $signature['created_at'])) ?></p>
                    <?php if ($signature['status'] === 'signed'): ?>
                        <a class="btn btn-ghost btn-sm mt-2" href="<?= base_url('legalcase/firmas/evidencia/' . (int) $signature['id']) ?>"><i class="icon icon-shield-check"></i> <?= lang('Lgc.signEvidence') ?></a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($row['id']) && $canWork && $acc->can('lgc.share') && in_array($row['status'], ['draft', 'sent'], true) && (!$signature || $signature['status'] !== 'pending')): ?>
                    <p class="mb-2 text-xs text-muted"><?= lang('Lgc.engSignHint') ?></p>
                    <?php if (!$accounts): ?>
                        <p class="text-muted"><?= lang('Lgc.noRecipients') ?></p>
                    <?php else: ?>
                        <form method="post" action="<?= base_url('legalcase/asuntos/contrato/firma/' . $mid) ?>" class="space-y-2"><?= csrf_field() ?>
                            <select name="account_id" class="form-select" aria-label="<?= esc(lang('Lgc.fRecipient'), 'attr') ?>">
                                <?php foreach ($accounts as $aid => $mail): ?><option value="<?= (int) $aid ?>"><?= esc($mail) ?></option><?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary btn-sm"><i class="icon icon-send"></i> <?= lang('Lgc.sendToSign') ?></button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($row['id']) && $canWork && $acc->can('lgc.edit')): ?>
                    <form method="post" action="<?= base_url('legalcase/asuntos/contrato/terminar/' . $mid) ?>" class="mt-3" onsubmit="return confirm('<?= esc(lang('Lgc.confirmTerminate'), 'js') ?>')"><?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm"><?= lang('Lgc.terminateEngagement') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
