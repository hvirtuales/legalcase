<?php

use Components\LeadFlow\Libraries\Meta;

/*
 * Tareas y plazos del expediente.
 *
 * **Son las actividades del LEAD, no una tabla aparte.** Es deliberado: una segunda
 * lista de pendientes dejaría `/leadflow/actividades` enseñando la mitad del trabajo,
 * obligaría a un segundo disparador de vencidas y a un segundo recordatorio en el
 * tick. Al reutilizarlas, un plazo del caso aparece en la bandeja de siempre y el
 * aviso de `leadflow:tick` funciona sin configurar nada.
 *
 * El tipo `deadline` es lo único nuevo, y existe para poder distinguir «hay que
 * llamar al cliente» de «el 12 de marzo vence la respuesta a USCIS»: lo segundo es lo
 * que alimenta `next_deadline_at` y la columna de plazos del listado.
 *
 * @var array $matter
 * @var array $lead
 * @var array $activities
 * @var array $team
 * @var bool  $canEdit
 */
$lid     = (int) $lead['id'];
$volver  = 'leadflow/expedientes/ficha/' . (int) $matter['id'] . '?tab=tareas';
$ahora   = date('Y-m-d H:i:s');
$cerrado = \Components\LeadFlow\Models\MatterModel::isClosed($matter);
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-list-check"></i>
            <h6 class="font-semibold"><?= lang('Lfl.mtrTabTasks') ?></h6>
            <?php if ($matter['next_deadline_at']): ?>
                <span class="badge <?= $matter['next_deadline_at'] < $ahora ? 'badge-danger' : 'badge-warning' ?>">
                    <?= lang('Lfl.mtrNextDeadline') ?>: <?= esc(Meta::date($matter['next_deadline_at'], false)) ?>
                </span>
            <?php endif; ?>
        </div>
        <a href="<?= base_url('leadflow/actividades?vista=todas') ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-list"></i> <?= lang('Lfl.navActivities') ?>
        </a>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.mtrTasksHint') ?></p>

        <?php if ($activities === []): ?>
            <p class="rounded border border-line p-4 text-sm text-muted"><?= lang('Lfl.noActivities') ?></p>
        <?php else: ?>
            <div class="table-card">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.activity') ?></th>
                            <th><?= lang('Lfl.owner') ?></th>
                            <th><?= lang('Lfl.dueAt') ?></th>
                            <th><?= lang('Lfl.status') ?></th>
                            <?php if ($canEdit): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $a): ?>
                            <?php $vencida = $a['status'] === 'pending' && $a['due_at'] && $a['due_at'] < $ahora; ?>
                            <tr>
                                <td>
                                    <i class="icon <?= esc(Meta::ACTIVITY_ICONS[$a['activity_type']] ?? 'icon-circle', 'attr') ?>"></i>
                                    <span class="font-medium"><?= esc($a['title']) ?></span>
                                    <div class="text-xs text-muted">
                                        <?= esc(lang('Lfl.' . (Meta::ACTIVITY_TYPES[$a['activity_type']] ?? 'actOther'))) ?>
                                    </div>
                                </td>
                                <td class="text-sm"><?= esc($a['assigned_user_id'] ? ($team[(int) $a['assigned_user_id']] ?? '—') : '—') ?></td>
                                <td class="text-sm <?= $vencida ? 'text-danger' : '' ?>">
                                    <?= esc(Meta::date($a['due_at'])) ?>
                                    <?php if ($vencida): ?>
                                        <span class="badge badge-danger"><?= lang('Lfl.mtrOverdue') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $a['status'] === 'done' ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= esc(lang('Lfl.' . (Meta::ACTIVITY_STATUSES[$a['status']] ?? 'asPending'))) ?>
                                    </span>
                                </td>
                                <?php if ($canEdit): ?>
                                    <td class="text-right">
                                        <?php if ($a['status'] === 'pending'): ?>
                                            <form action="<?= base_url('leadflow/actividades/completar/' . (int) $a['id']) ?>" method="post" class="inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Lfl.asDone'), 'attr') ?>">
                                                    <i class="icon icon-check-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php // Se crea contra la ruta del LEAD, que ya existe y ya dispara todo. ?>
        <?php if ($canEdit && ! $cerrado): ?>
            <div class="form-section mt-4">
                <h6 class="form-section-title"><?= lang('Lfl.activityNew') ?></h6>
                <form action="<?= base_url('leadflow/actividades/nueva/' . $lid) ?>" method="post"
                    class="flex flex-wrap items-end gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">

                    <div class="form-group mb-0 flex-1" style="min-width:200px">
                        <label for="act-title" class="form-label text-xs"><?= lang('Lfl.activity') ?></label>
                        <input type="text" id="act-title" name="title" class="form-input" maxlength="150" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="act-type" class="form-label text-xs"><?= lang('Lfl.type') ?></label>
                        <select id="act-type" name="activity_type" class="form-select">
                            <?php foreach (Meta::ACTIVITY_TYPES as $t => $clave): ?>
                                <option value="<?= esc($t, 'attr') ?>" <?= $t === 'deadline' ? 'selected' : '' ?>>
                                    <?= lang('Lfl.' . $clave) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label for="act-due" class="form-label text-xs"><?= lang('Lfl.dueAt') ?></label>
                        <input type="date" id="act-due" name="due_date" class="form-input">
                    </div>
                    <div class="form-group mb-0">
                        <label for="act-user" class="form-label text-xs"><?= lang('Lfl.owner') ?></label>
                        <select id="act-user" name="assigned_user_id" class="form-select">
                            <option value=""><?= lang('Lfl.unassigned') ?></option>
                            <?php foreach ($team as $uid => $nombre): ?>
                                <option value="<?= (int) $uid ?>" <?= (int) ($matter['lawyer_user_id'] ?? 0) === (int) $uid ? 'selected' : '' ?>>
                                    <?= esc($nombre) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
