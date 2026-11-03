<?php
/**
 * Procesos (0..N por asunto) y actuaciones. Las actuaciones son INTERNAS: al cliente le
 * llega la actualización que se redacte a partir de ellas.
 *
 * @var array $matter @var array $proceedings @var array $events
 * @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\EventModel;
use Components\LegalCase\Models\ProceedingModel;

$id     = (int) $matter['id'];
$open   = $matter['status'] !== 'closed';
$procs  = [];
foreach ($proceedings as $p) {
    $procs[(int) $p['id']] = $p['filing_number'] ?: ($p['authority'] ?: '#' . $p['id']);
}
?>
<div class="grid gap-4 lg:grid-cols-3">
    <div class="min-w-0 space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-landmark"></i><h5 class="font-semibold"><?= lang('Lgc.proceedings') ?></h5></div>
            <div class="card-body">
                <?php if (!$proceedings): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noProceedingsHint') ?></p>
                <?php endif; ?>
                <div class="space-y-3">
                    <?php foreach ($proceedings as $p): ?>
                        <details class="rounded-md border border-line p-3">
                            <summary class="cursor-pointer text-sm">
                                <span class="font-mono font-semibold"><?= esc($p['filing_number'] ?: lang('Lgc.noFiling')) ?></span>
                                · <?= esc($p['authority'] ?: '—') ?>
                                <span class="badge badge-light"><?= lang('Lgc.pkind_' . $p['kind']) ?></span>
                                <span class="badge badge-info"><?= lang('Lgc.proc_' . $p['status']) ?></span>
                            </summary>
                            <?php if ($acc->can('lgc.edit')): ?>
                                <form method="post" action="<?= base_url('legalcase/procesos/editar/' . (int) $p['id']) ?>" class="mt-3 grid gap-3 md:grid-cols-2">
                                    <?= csrf_field() ?>
                                    <?= view('Components\LegalCase\Views\matters\tabs\_proceeding_fields', ['p' => $p, 'wide' => true]) ?>
                                    <div class="flex justify-end gap-2 md:col-span-2">
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            <?php if ($acc->can('lgc.delete')): ?>
                                <div class="mt-2 flex justify-end">
                                    <?= view('partials/delete_button', ['action' => base_url('legalcase/procesos/eliminar/' . (int) $p['id']), 'confirm' => lang('Lgc.confirmDelete'), 'label' => lang('Lgc.delete'), 'text' => lang('Lgc.delete'), 'class' => 'btn btn-danger btn-sm', 'icon' => 'trash']) ?>
                                </div>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-journal-text"></i><h5 class="font-semibold"><?= lang('Lgc.events') ?></h5></div>
            <div class="card-body">
                <?php if (!$events): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noEvents') ?></p>
                <?php else: ?>
                    <ol class="space-y-3">
                        <?php foreach ($events as $e): ?>
                            <li class="rounded-md border border-line p-3 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="font-semibold">
                                        <?= esc($e['title']) ?>
                                        <?php if ((int) $e['is_relevant']): ?><span class="badge badge-warning"><?= lang('Lgc.relevant') ?></span><?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted">
                                        <?= esc(Meta::date($e['occurred_at'])) ?> · <?= lang('Lgc.ekind_' . $e['kind']) ?>
                                        <?php if ($acc->can('lgc.delete')): ?>
                                            <?= view('partials/delete_button', ['action' => base_url('legalcase/actuaciones/eliminar/' . (int) $e['id']), 'confirm' => lang('Lgc.confirmDelete'), 'label' => lang('Lgc.delete'), 'class' => 'btn btn-ghost btn-sm', 'icon' => 'trash', 'text' => '']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($e['detail']): ?><p class="mt-1 whitespace-pre-line text-muted"><?= esc($e['detail']) ?></p><?php endif; ?>
                                <div class="mt-1 text-xs text-muted">
                                    <?= lang('Lgc.fSource') ?>: <?= esc($e['source'] ?: '—') ?><?= $e['source_ref'] ? ' · ' . esc($e['source_ref']) : '' ?>
                                    <?= $e['proceeding_id'] ? ' · ' . esc($procs[(int) $e['proceeding_id']] ?? '') : '' ?>
                                </div>
                                <?php if ((int) $e['is_relevant'] && $open && $acc->can('lgc.create')): ?>
                                    <a class="mt-2 inline-block text-xs text-primary" href="<?= base_url('legalcase/asuntos/actualizacion/' . $id) ?>"><i class="icon icon-send"></i> <?= lang('Lgc.updFromEvent') ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($acc->can('lgc.create')): ?>
        <div class="min-w-0 space-y-4">
            <?php if ($open): ?>
                <div class="card">
                    <div class="card-header"><i class="icon icon-plus-circle"></i><h5 class="font-semibold"><?= lang('Lgc.proceedingNew') ?></h5></div>
                    <div class="card-body">
                        <form method="post" action="<?= base_url('legalcase/asuntos/proceso/' . $id) ?>" class="grid gap-3">
                            <?= csrf_field() ?>
                            <?= view('Components\LegalCase\Views\matters\tabs\_proceeding_fields', ['p' => ['kind' => 'judicial', 'status' => 'active'], 'wide' => false]) ?>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.add') ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header"><i class="icon icon-journal-text"></i><h5 class="font-semibold"><?= lang('Lgc.eventNew') ?></h5></div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('legalcase/asuntos/actuacion/' . $id) ?>" class="space-y-3">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label" for="ev-title"><?= lang('Lgc.fTitle') ?> <span class="text-danger">*</span></label>
                            <input id="ev-title" type="text" name="title" class="form-input" required maxlength="190" placeholder="<?= esc(lang('Lgc.phEventTitle'), 'attr') ?>">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label class="form-label" for="ev-kind"><?= lang('Lgc.fKind') ?></label>
                                <select id="ev-kind" name="kind" class="form-select">
                                    <?php foreach (EventModel::KINDS as $k): ?><option value="<?= $k ?>"><?= lang('Lgc.ekind_' . $k) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="ev-at"><?= lang('Lgc.fOccurredAt') ?></label>
                                <input id="ev-at" type="datetime-local" name="occurred_at" class="form-input" value="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                        <?php if ($procs): ?>
                            <div class="form-group">
                                <label class="form-label" for="ev-proc"><?= lang('Lgc.proceeding') ?></label>
                                <select id="ev-proc" name="proceeding_id" class="form-select">
                                    <option value=""><?= lang('Lgc.none') ?></option>
                                    <?php foreach ($procs as $pid => $pl): ?><option value="<?= $pid ?>"><?= esc($pl) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label class="form-label" for="ev-detail"><?= lang('Lgc.fDetail') ?></label>
                            <textarea id="ev-detail" name="detail" rows="3" class="form-textarea"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label class="form-label" for="ev-src"><?= lang('Lgc.fSource') ?></label>
                                <input id="ev-src" type="text" name="source" class="form-input" maxlength="120" placeholder="<?= esc(lang('Lgc.phSource'), 'attr') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="ev-ref"><?= lang('Lgc.fSourceRef') ?></label>
                                <input id="ev-ref" type="text" name="source_ref" class="form-input" maxlength="190">
                            </div>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="ev-rel" name="is_relevant" value="1" class="form-check-input">
                            <label for="ev-rel" class="form-check-label"><?= lang('Lgc.fRelevant') ?><span class="block text-xs text-muted"><?= lang('Lgc.fRelevantHint') ?></span></label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.add') ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
