<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Redacción de una actualización al cliente con la estructura de lenguaje claro (§6.6).
 * A la derecha, las últimas actuaciones como referencia (son internas: se resumen, no se
 * copian). Guardar deja el texto en BORRADOR; publicarlo es otro paso, con aprobación.
 *
 * @var array $matter @var array $row @var string $action @var array $events
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\MatterModel;

$v      = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$kind   = (string) ($row['kind'] ?? 'regular');
$noNews = $kind === 'no_news';
?>
<div class="mb-4 flex items-center gap-3">
    <a href="<?= base_url('legalcase/asuntos/ficha/' . (int) $matter['id'] . '?tab=comunicacion') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i></a>
    <div>
        <h4 class="font-bold"><?= esc($title) ?> <span class="badge badge-light"><?= lang('Lgc.ukind_' . $kind) ?></span></h4>
        <p class="text-xs text-muted"><span class="font-mono"><?= esc($matter['number']) ?></span> · <?= esc(MatterModel::clientTitle($matter)) ?></p>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="card min-w-0 lg:col-span-2">
        <div class="card-body">
            <div class="alert alert-warning mb-4"><i class="icon icon-exclamation-circle"></i><span><?= lang('Lgc.updWritingRules') ?></span></div>
            <form method="post" action="<?= esc($action, 'attr') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="kind" value="<?= esc($kind, 'attr') ?>">

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secUpdHeader') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secUpdHeaderHint') ?></p>
                    <div class="form-group">
                        <label for="title" class="form-label"><?= lang('Lgc.fTitle') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="title" name="title" class="form-input" required maxlength="190" value="<?= esc($v('title'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="stage_label" class="form-label"><?= lang('Lgc.fStageLabel') ?></label>
                        <input type="text" id="stage_label" name="stage_label" class="form-input" maxlength="120" value="<?= esc($v('stage_label'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phPublicStage'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Lgc.fStageLabelHint') ?></small>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secUpdBody') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secUpdBodyHint') ?></p>
                    <?php foreach (['what_happened' => true, 'what_it_means' => false, 'what_we_do' => false, 'client_action' => false] as $f => $req): ?>
                        <div class="form-group">
                            <label for="<?= $f ?>" class="form-label"><?= lang('Lgc.f_' . $f) ?><?= $req ? ' <span class="text-danger">*</span>' : '' ?></label>
                            <textarea id="<?= $f ?>" name="<?= $f ?>" rows="3" class="form-textarea" <?= $req ? 'required' : '' ?> placeholder="<?= esc(lang('Lgc.ph_' . $f), 'attr') ?>"><?= esc($v($f)) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <?php if ($noNews): ?>
                    <fieldset class="form-section">
                        <legend class="form-section-title"><i class="icon icon-shield-check"></i> <?= lang('Lgc.secVerification') ?></legend>
                        <p class="form-section-hint"><?= lang('Lgc.secVerificationHint') ?></p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="form-group">
                                <label for="verified_source" class="form-label"><?= lang('Lgc.fVerifiedSource') ?> <span class="text-danger">*</span></label>
                                <input type="text" id="verified_source" name="verified_source" class="form-input" required maxlength="190" value="<?= esc($v('verified_source'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phVerifiedSource'), 'attr') ?>">
                            </div>
                            <div class="form-group">
                                <label for="verified_at" class="form-label"><?= lang('Lgc.fVerifiedAt') ?> <span class="text-danger">*</span></label>
                                <input type="datetime-local" id="verified_at" name="verified_at" class="form-input" required value="<?= esc(Meta::inputDt($v('verified_at')), 'attr') ?>">
                            </div>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secNextContact') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secNextContactHint') ?></p>
                    <div class="form-group">
                        <label for="next_update_at" class="form-label"><?= lang('Lgc.fNextUpdate') ?></label>
                        <input type="datetime-local" id="next_update_at" name="next_update_at" class="form-input" value="<?= esc(Meta::inputDt($v('next_update_at')), 'attr') ?>">
                    </div>
                </fieldset>

                <div class="mt-4 flex justify-end gap-2">
                    <a href="<?= base_url('legalcase/asuntos/ficha/' . (int) $matter['id'] . '?tab=comunicacion') ?>" class="btn btn-ghost"><?= lang('Lgc.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lgc.saveDraft') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-journal-text"></i><h5 class="font-semibold"><?= lang('Lgc.recentEvents') ?></h5></div>
        <div class="card-body">
            <p class="mb-2 text-xs text-muted"><?= lang('Lgc.recentEventsHint') ?></p>
            <?php if (!$events): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noEvents') ?></p>
            <?php else: ?>
                <ul class="space-y-2 text-sm">
                    <?php foreach ($events as $e): ?>
                        <li>
                            <div class="font-semibold"><?= esc($e['title']) ?></div>
                            <div class="text-xs text-muted"><?= esc(Meta::date($e['occurred_at'])) ?> · <?= esc($e['source'] ?: '—') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
