<?php
/**
 * Coincidencias de conflicto de interés devueltas por el servidor (flash `lgc_conflicts`).
 * Si las hay, el formulario se reenvía con la revisión explícita y una justificación, que
 * quedan como evidencia en cmp_lgc_conflict_checks.
 */
$hits = (array) (session()->getFlashdata('lgc_conflicts') ?? []);
if (!$hits) {
    return;
}
?>
<div class="alert alert-danger mb-4">
    <i class="icon icon-exclamation-circle"></i>
    <div class="min-w-0">
        <strong><?= lang('Lgc.conflictTitle') ?></strong>
        <ul class="mt-2 space-y-1 text-sm">
            <?php foreach ($hits as $h): ?>
                <li>
                    <span class="font-mono"><?= esc($h['number']) ?></span> · <?= esc($h['title']) ?> —
                    <?= esc($h['contact']) ?> <?= lang('Lgc.conflictAs') ?> <strong><?= lang('Lgc.prole_' . $h['role']) ?></strong>
                    <span class="badge badge-light"><?= lang('Lgc.match_' . $h['match']) ?></span>
                    <?php if ($h['status'] === 'closed'): ?><span class="badge badge-secondary"><?= lang('Lgc.matter_closed') ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-2 text-xs"><?= lang('Lgc.conflictHint') ?></p>
    </div>
</div>
<fieldset class="form-section">
    <legend class="form-section-title"><?= lang('Lgc.conflictReview') ?></legend>
    <p class="form-section-hint"><?= lang('Lgc.conflictReviewHint') ?></p>
    <div class="form-check">
        <input type="checkbox" id="conflict_ack" name="conflict_ack" value="1" class="form-check-input" required>
        <label for="conflict_ack" class="form-check-label"><?= lang('Lgc.conflictAck') ?></label>
    </div>
    <div class="form-group mt-2">
        <label for="conflict_note" class="form-label"><?= lang('Lgc.conflictNote') ?> <span class="text-danger">*</span></label>
        <textarea id="conflict_note" name="conflict_note" rows="3" class="form-textarea" required minlength="10" placeholder="<?= esc(lang('Lgc.phConflictNote'), 'attr') ?>"></textarea>
    </div>
</fieldset>
