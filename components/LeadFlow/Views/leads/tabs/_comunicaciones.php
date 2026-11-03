<?php

use Components\LeadFlow\Libraries\Meta;

$id = (int) $lead['id'];
?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-chat"></i><h5 class="font-semibold"><?= lang('Lfl.tabComms') ?></h5></div>
            <div class="card-body">
                <?php if (! $communications): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.noComms') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($communications as $c): ?>
                            <li class="py-2">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="font-medium">
                                        <i class="icon <?= esc(Meta::TIMELINE_ICONS[$c['channel']] ?? 'icon-chat', 'attr') ?>"></i>
                                        <?= esc(Meta::label(Meta::CHANNELS, $c['channel'])) ?>
                                        · <?= esc(Meta::label(Meta::DIRECTIONS, $c['direction'])) ?>
                                        <?= $c['outcome'] ? ' · ' . esc(Meta::label(Meta::OUTCOMES, $c['outcome'])) : '' ?>
                                        <?= $c['duration_sec'] ? ' · ' . (int) round($c['duration_sec'] / 60) . ' min' : '' ?>
                                    </span>
                                    <span class="text-xs text-muted">
                                        <?= esc(Meta::date($c['created_at'])) ?>
                                        <?= $c['created_by'] ? ' · ' . esc($team[(int) $c['created_by']] ?? '') : '' ?>
                                    </span>
                                </div>
                                <?php if ($c['subject']): ?><div class="text-sm font-semibold"><?= esc($c['subject']) ?></div><?php endif; ?>
                                <?php if ($c['body']): ?><p class="whitespace-pre-line text-sm text-muted"><?= esc($c['body']) ?></p><?php endif; ?>
                                <?php if ($c['status'] === 'failed'): ?>
                                    <p class="text-xs text-danger"><?= esc(lang('Lfl.commFailed', [$c['error'] ?? ''])) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-journal-text"></i><h5 class="font-semibold"><?= lang('Lfl.notes') ?></h5></div>
            <div class="card-body">
                <?php if ($canCreate): ?>
                    <form action="<?= base_url('leadflow/leads/nota/' . $id) ?>" method="post" class="mb-4">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="note-body" class="form-label"><?= lang('Lfl.noteNew') ?></label>
                            <textarea id="note-body" name="body" rows="3" class="form-textarea" required maxlength="5000"></textarea>
                        </div>
                        <label class="form-check mb-1">
                            <input type="checkbox" name="is_transferable" value="1" class="form-check-input" checked>
                            <span class="form-check-label"><?= lang('Lfl.noteTransferable') ?></span>
                        </label>
                        <p class="form-hint mb-2"><?= lang('Lfl.noteTransferableHint') ?></p>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                    </form>
                <?php endif; ?>
                <?php if (! $notes): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.noNotes') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($notes as $n): ?>
                            <li class="flex items-start justify-between gap-2 py-2">
                                <div class="min-w-0">
                                    <p class="whitespace-pre-line text-sm"><?= esc($n['body']) ?></p>
                                    <p class="text-xs text-muted">
                                        <?= esc(Meta::date($n['created_at'])) ?>
                                        <?= $n['created_by'] ? ' · ' . esc($team[(int) $n['created_by']] ?? '') : '' ?>
                                        · <?= lang($n['is_transferable'] ? 'Lfl.transferable' : 'Lfl.internalOnly') ?>
                                    </p>
                                </div>
                                <?php if ($canDelete): ?>
                                    <?= view('partials/delete_button', [
                                        'action'  => base_url('leadflow/leads/nota/eliminar/' . (int) $n['id']),
                                        'confirm' => lang('Lfl.confirmDelete'),
                                        'label'   => lang('Lfl.delete'),
                                    ]) ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <?php if ($canCreate): ?>
            <form action="<?= base_url('leadflow/leads/comunicacion/' . $id) ?>" method="post" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><i class="icon icon-telephone"></i><h5 class="font-semibold"><?= lang('Lfl.commLog') ?></h5></div>
                <div class="card-body">
                    <p class="mb-3 text-xs text-muted"><?= lang('Lfl.commLogHint') ?></p>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="form-group">
                            <label for="c-channel" class="form-label"><?= lang('Lfl.channel') ?></label>
                            <select id="c-channel" name="channel" class="form-select">
                                <?php foreach (Meta::options(Meta::CHANNELS) as $val => $lbl): ?>
                                    <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="c-direction" class="form-label"><?= lang('Lfl.direction') ?></label>
                            <select id="c-direction" name="direction" class="form-select">
                                <?php foreach (Meta::options(Meta::DIRECTIONS) as $val => $lbl): ?>
                                    <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="form-group">
                            <label for="c-outcome" class="form-label"><?= lang('Lfl.outcome') ?></label>
                            <select id="c-outcome" name="outcome" class="form-select">
                                <option value=""><?= lang('Lfl.none') ?></option>
                                <?php foreach (Meta::options(Meta::OUTCOMES) as $val => $lbl): ?>
                                    <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="c-duration" class="form-label"><?= lang('Lfl.durationMin') ?></label>
                            <input type="number" id="c-duration" name="duration_min" min="0" max="600" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="c-body" class="form-label"><?= lang('Lfl.notes') ?></label>
                        <textarea id="c-body" name="body" rows="2" class="form-textarea"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                </div>
            </form>

            <form id="email" action="<?= base_url('leadflow/leads/email/' . $id) ?>" method="post" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><i class="icon icon-envelope"></i><h5 class="font-semibold"><?= lang('Lfl.emailSend') ?></h5></div>
                <div class="card-body">
                    <?php if (! $lead['email']): ?>
                        <p class="text-sm text-muted"><?= lang('Lfl.errNoEmail') ?></p>
                    <?php else: ?>
                        <p class="mb-3 text-xs text-muted"><?= esc(lang('Lfl.emailTo', [$lead['email']])) ?></p>
                        <div class="form-group">
                            <label for="e-subject" class="form-label"><?= lang('Lfl.subject') ?> <span class="text-danger">*</span></label>
                            <input type="text" id="e-subject" name="subject" maxlength="200" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="e-body" class="form-label"><?= lang('Lfl.message') ?> <span class="text-danger">*</span></label>
                            <textarea id="e-body" name="body" rows="5" class="form-textarea" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-send"></i> <?= lang('Lfl.send') ?></button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
