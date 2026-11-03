<?php
/** Campos de un proceso (alta y edición comparten la misma rejilla). @var array $p */
use Components\LegalCase\Models\ProceedingModel;

$pid = (int) ($p['id'] ?? 0);
?>
<div class="form-group">
    <label class="form-label" for="pr-num-<?= $pid ?>"><?= lang('Lgc.fFilingNumber') ?></label>
    <input id="pr-num-<?= $pid ?>" type="text" name="filing_number" class="form-input font-mono" maxlength="60" value="<?= esc($p['filing_number'] ?? '', 'attr') ?>" placeholder="<?= esc(lang('Lgc.phFiling'), 'attr') ?>">
</div>
<div class="form-group">
    <label class="form-label" for="pr-auth-<?= $pid ?>"><?= lang('Lgc.fAuthority') ?></label>
    <input id="pr-auth-<?= $pid ?>" type="text" name="authority" class="form-input" maxlength="190" value="<?= esc($p['authority'] ?? '', 'attr') ?>" placeholder="<?= esc(lang('Lgc.phAuthority'), 'attr') ?>">
</div>
<div class="form-group">
    <label class="form-label" for="pr-kind-<?= $pid ?>"><?= lang('Lgc.fKind') ?></label>
    <select id="pr-kind-<?= $pid ?>" name="kind" class="form-select">
        <?php foreach (ProceedingModel::KINDS as $k): ?><option value="<?= $k ?>" <?= ($p['kind'] ?? '') === $k ? 'selected' : '' ?>><?= lang('Lgc.pkind_' . $k) ?></option><?php endforeach; ?>
    </select>
</div>
<div class="form-group">
    <label class="form-label" for="pr-st-<?= $pid ?>"><?= lang('Lgc.fStatus') ?></label>
    <select id="pr-st-<?= $pid ?>" name="status" class="form-select">
        <?php foreach (ProceedingModel::STATUSES as $s): ?><option value="<?= $s ?>" <?= ($p['status'] ?? '') === $s ? 'selected' : '' ?>><?= lang('Lgc.proc_' . $s) ?></option><?php endforeach; ?>
    </select>
</div>
<div class="form-group">
    <label class="form-label" for="pr-type-<?= $pid ?>"><?= lang('Lgc.fProcedureType') ?></label>
    <input id="pr-type-<?= $pid ?>" type="text" name="procedure_type" class="form-input" maxlength="120" value="<?= esc($p['procedure_type'] ?? '', 'attr') ?>">
</div>
<div class="form-group">
    <label class="form-label" for="pr-stage-<?= $pid ?>"><?= lang('Lgc.fStage') ?></label>
    <input id="pr-stage-<?= $pid ?>" type="text" name="stage" class="form-input" maxlength="120" value="<?= esc($p['stage'] ?? '', 'attr') ?>">
</div>
<div class="form-group">
    <label class="form-label" for="pr-jur-<?= $pid ?>"><?= lang('Lgc.fJurisdiction') ?></label>
    <input id="pr-jur-<?= $pid ?>" type="text" name="jurisdiction" class="form-input" maxlength="120" value="<?= esc($p['jurisdiction'] ?? '', 'attr') ?>">
</div>
<div class="form-group">
    <label class="form-label" for="pr-start-<?= $pid ?>"><?= lang('Lgc.fStartedOn') ?></label>
    <input id="pr-start-<?= $pid ?>" type="date" name="started_on" class="form-input" value="<?= esc($p['started_on'] ?? '', 'attr') ?>">
</div>
<div class="form-group <?= !empty($wide) ? 'md:col-span-2' : '' ?>">
    <label class="form-label" for="pr-notes-<?= $pid ?>"><?= lang('Lgc.fNotes') ?></label>
    <textarea id="pr-notes-<?= $pid ?>" name="notes" rows="2" class="form-textarea"><?= esc($p['notes'] ?? '') ?></textarea>
</div>
