<?php

use Components\LeadFlow\Libraries\Meta;

$id     = (int) $lead['id'];
$volver = 'leadflow/leads/ficha/' . $id . '?tab=actividades';
?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-list-check"></i><h5 class="font-semibold"><?= lang('Lfl.tabActivities') ?></h5></div>
            <div class="card-body">
                <?php if (! $activities): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.noActivities') ?></p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= lang('Lfl.activity') ?></th>
                                    <th><?= lang('Lfl.dueAt') ?></th>
                                    <th><?= lang('Lfl.owner') ?></th>
                                    <th><?= lang('Lfl.status') ?></th>
                                    <th class="text-right"><?= lang('Lfl.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $act): ?>
                                    <?php $late = $act['status'] === 'pending' && $act['due_at'] && $act['due_at'] < date('Y-m-d H:i:s'); ?>
                                    <tr class="<?= $act['status'] === 'done' ? 'text-muted' : '' ?>">
                                        <td>
                                            <div class="font-medium"><i class="icon <?= esc(Meta::ACTIVITY_ICONS[$act['activity_type']] ?? 'icon-circle', 'attr') ?>"></i> <?= esc($act['title']) ?></div>
                                            <div class="text-xs text-muted"><?= esc(Meta::label(Meta::ACTIVITY_TYPES, $act['activity_type'])) ?>
                                                · <span class="badge <?= Meta::color(Meta::PRIORITY_COLORS, $act['priority']) ?>"><?= esc(Meta::label(Meta::PRIORITIES, $act['priority'])) ?></span></div>
                                            <?php if ($act['description']): ?>
                                                <div class="mt-1 whitespace-pre-line text-xs"><?= esc($act['description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?= $late ? 'text-danger' : '' ?>"><?= esc(Meta::date($act['due_at'])) ?></td>
                                        <td><?= esc($team[(int) $act['assigned_user_id']] ?? '—') ?></td>
                                        <td><span class="badge <?= $act['status'] === 'done' ? 'badge-success' : ($late ? 'badge-danger' : 'badge-info') ?>">
                                            <?= esc($late ? lang('Lfl.overdue') : Meta::label(Meta::ACTIVITY_STATUSES, $act['status'])) ?></span></td>
                                        <td>
                                            <?php if ($canEdit): ?>
                                                <div class="flex justify-end gap-1">
                                                    <form action="<?= base_url('leadflow/actividades/' . ($act['status'] === 'done' ? 'reabrir/' : 'completar/') . (int) $act['id']) ?>" method="post">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang($act['status'] === 'done' ? 'Lfl.reopen' : 'Lfl.complete'), 'attr') ?>">
                                                            <i class="icon <?= $act['status'] === 'done' ? 'icon-arrow-counterclockwise' : 'icon-check-circle' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <?php if ($canDelete): ?>
                                                        <form action="<?= base_url('leadflow/actividades/eliminar/' . (int) $act['id']) ?>" method="post"
                                                            onsubmit="return confirm('<?= esc(lang('Lfl.confirmDelete'), 'js') ?>')">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" title="<?= esc(lang('Lfl.delete'), 'attr') ?>"><i class="icon icon-trash"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
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
    </div>

    <div>
        <?php if ($canCreate): ?>
            <form id="nueva" action="<?= base_url('leadflow/actividades/nueva/' . $id) ?>" method="post" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><i class="icon icon-plus-circle"></i><h5 class="font-semibold"><?= lang('Lfl.activityNew') ?></h5></div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="activity_type" class="form-label"><?= lang('Lfl.type') ?></label>
                        <select id="activity_type" name="activity_type" class="form-select">
                            <?php foreach (Meta::options(Meta::ACTIVITY_TYPES) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="title" class="form-label"><?= lang('Lfl.title') ?></label>
                        <input type="text" id="title" name="title" maxlength="150" class="form-input" placeholder="<?= esc(lang('Lfl.activityTitlePh'), 'attr') ?>">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="form-group">
                            <label for="due_date" class="form-label"><?= lang('Lfl.date') ?></label>
                            <input type="date" id="due_date" name="due_date" class="form-input" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label for="due_time" class="form-label"><?= lang('Lfl.time') ?></label>
                            <input type="time" id="due_time" name="due_time" class="form-input" value="<?= date('H:00', time() + 3600) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="a-priority" class="form-label"><?= lang('Lfl.priority') ?></label>
                        <select id="a-priority" name="priority" class="form-select">
                            <?php foreach (Meta::options(Meta::PRIORITIES) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $val === 'medium' ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($access->canAssignOthers()): ?>
                        <div class="form-group">
                            <label for="a-assigned" class="form-label"><?= lang('Lfl.owner') ?></label>
                            <select id="a-assigned" name="assigned_user_id" class="form-select">
                                <?php foreach ($team as $uid => $uname): ?>
                                    <option value="<?= (int) $uid ?>" <?= (int) $uid === (int) $access->userId ? 'selected' : '' ?>><?= esc($uname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="description" class="form-label"><?= lang('Lfl.description') ?></label>
                        <textarea id="description" name="description" rows="2" class="form-textarea"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
