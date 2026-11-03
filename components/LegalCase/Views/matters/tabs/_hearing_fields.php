<?php
/**
 * Campos de una audiencia: los usan el alta (columna lateral) y la edición (dentro de cada
 * audiencia). Una sola definición para que reprogramar pida exactamente lo mismo que programar.
 *
 * @var array $h (vacío en el alta) @var string $pfx prefijo de ids @var array $proceedings
 * @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Models\HearingModel;

$h   = $h ?? [];
$val = static fn (string $k, $d = '') => $h[$k] ?? $d;
$at  = $val('scheduled_at') ? date('Y-m-d\TH:i', strtotime((string) $val('scheduled_at'))) : '';
?>
<div class="form-group">
    <label class="form-label" for="<?= $pfx ?>-title"><?= lang('Lgc.fTitle') ?> <span class="text-danger">*</span></label>
    <input id="<?= $pfx ?>-title" type="text" name="title" class="form-input" required maxlength="190" value="<?= esc($val('title'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phHearing'), 'attr') ?>">
</div>
<div class="grid grid-cols-2 gap-3">
    <div class="form-group">
        <label class="form-label" for="<?= $pfx ?>-at"><?= lang('Lgc.fScheduledAt') ?> <span class="text-danger">*</span></label>
        <input id="<?= $pfx ?>-at" type="datetime-local" name="scheduled_at" class="form-input" required value="<?= esc($at, 'attr') ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="<?= $pfx ?>-mode"><?= lang('Lgc.fMode') ?></label>
        <select id="<?= $pfx ?>-mode" name="mode" class="form-select">
            <?php foreach (HearingModel::MODES as $m): ?><option value="<?= $m ?>" <?= $val('mode') === $m ? 'selected' : '' ?>><?= lang('Lgc.hmode_' . $m) ?></option><?php endforeach; ?>
        </select>
    </div>
</div>
<div class="grid grid-cols-2 gap-3">
    <div class="form-group">
        <label class="form-label" for="<?= $pfx ?>-dur"><?= lang('Lgc.fDuration') ?></label>
        <input id="<?= $pfx ?>-dur" type="number" name="duration_min" class="form-input" min="5" max="1440" step="5" value="<?= (int) $val('duration_min', 60) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="<?= $pfx ?>-proc"><?= lang('Lgc.fProceeding') ?></label>
        <select id="<?= $pfx ?>-proc" name="proceeding_id" class="form-select">
            <option value=""><?= lang('Lgc.none') ?></option>
            <?php foreach ($proceedings ?? [] as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) $val('proceeding_id') === (int) $p['id'] ? 'selected' : '' ?>><?= esc($p['filing_number'] ?: ($p['authority'] ?: '#' . (int) $p['id'])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="form-group">
    <label class="form-label" for="<?= $pfx ?>-loc"><?= lang('Lgc.fLocation') ?></label>
    <input id="<?= $pfx ?>-loc" type="text" name="location" class="form-input" maxlength="255" value="<?= esc($val('location'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phLocation'), 'attr') ?>">
</div>
<div class="form-group">
    <label class="form-label" for="<?= $pfx ?>-link"><?= lang('Lgc.fLink') ?></label>
    <input id="<?= $pfx ?>-link" type="url" name="link" class="form-input" maxlength="500" value="<?= esc($val('link'), 'attr') ?>" placeholder="https://">
</div>
<div class="form-group">
    <label class="form-label" for="<?= $pfx ?>-cn"><?= lang('Lgc.fClientNotes') ?></label>
    <textarea id="<?= $pfx ?>-cn" name="client_notes" rows="2" class="form-textarea" placeholder="<?= esc(lang('Lgc.phClientNotes'), 'attr') ?>"><?= esc($val('client_notes')) ?></textarea>
</div>
<div class="form-group">
    <label class="form-label" for="<?= $pfx ?>-in"><?= lang('Lgc.fInternalNotes') ?></label>
    <textarea id="<?= $pfx ?>-in" name="internal_notes" rows="2" class="form-textarea"><?= esc($val('internal_notes')) ?></textarea>
</div>
<div class="form-check">
    <input type="checkbox" id="<?= $pfx ?>-att" name="client_attendance" value="1" class="form-check-input" <?= (int) $val('client_attendance') ? 'checked' : '' ?>>
    <label for="<?= $pfx ?>-att" class="form-check-label"><?= lang('Lgc.clientMustAttend') ?></label>
</div>
<?php if ($acc->can('lgc.share')): ?>
    <div class="form-check">
        <input type="checkbox" id="<?= $pfx ?>-sh" name="is_shared" value="1" class="form-check-input" <?= (int) $val('is_shared') ? 'checked' : '' ?>>
        <label for="<?= $pfx ?>-sh" class="form-check-label"><?= lang('Lgc.shareHearing') ?><span class="block text-xs text-muted"><?= lang('Lgc.shareHearingHint') ?></span></label>
    </div>
<?php endif; ?>
