<?php
/**
 * Plazos (registro manual y validación) y tareas. Ninguna fecha se presenta como
 * confirmada mientras esté «por validar» (§6.13-8).
 *
 * @var array $matter @var array $deadlines @var array $tasks @var array $proceedings @var array $users
 * @var array $settings @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\Meta;

$id   = (int) $matter['id'];
$open = $matter['status'] !== 'closed';
$dual = ($settings['deadline_policy'] ?? 'single') === 'dual';
?>
<div class="grid gap-4 lg:grid-cols-3">
    <div class="min-w-0 space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header flex-wrap justify-between gap-2">
                <div class="flex items-center gap-2"><i class="icon icon-clock"></i><h5 class="font-semibold"><?= lang('Lgc.deadlines') ?></h5></div>
                <span class="text-xs text-muted"><?= lang($dual ? 'Lgc.policyDualShort' : 'Lgc.policySingleShort') ?></span>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3"><i class="icon icon-info-circle"></i><span><?= lang('Lgc.deadlineDisclaimer') ?></span></div>
                <?php if (!$deadlines): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noDeadlines') ?></p>
                <?php else: ?>
                    <div class="table-card overflow-x-auto">
                        <table class="table">
                            <thead><tr>
                                <th><?= lang('Lgc.fTitle') ?></th><th><?= lang('Lgc.fDueAt') ?></th><th><?= lang('Lgc.fAssignee') ?></th><th><?= lang('Lgc.fStatus') ?></th><th></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($deadlines as $d): $isOpen = in_array($d['status'], ['to_validate', 'validated'], true); ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold"><?= esc($d['title']) ?> <?= (int) $d['is_critical'] ? '<span class="badge badge-danger">' . lang('Lgc.critical') . '</span>' : '' ?></div>
                                        <?php if ($d['basis']): ?><div class="max-w-xs truncate text-xs text-muted" title="<?= esc($d['basis'], 'attr') ?>"><?= esc($d['basis']) ?></div><?php endif; ?>
                                        <?php if ($d['validated_by']): ?>
                                            <div class="text-xs text-muted"><?= lang('Lgc.validatedBy', [esc($users[(int) $d['validated_by']] ?? '#'), esc(Meta::date($d['validated_at']))]) ?>
                                            <?= $d['second_validated_by'] ? ' · ' . lang('Lgc.validatedBy', [esc($users[(int) $d['second_validated_by']] ?? '#'), esc(Meta::date($d['second_validated_at']))]) : '' ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap <?= $isOpen && Meta::isPast($d['due_at']) ? 'font-semibold text-danger' : '' ?>">
                                        <?= esc(Meta::date($d['due_at'])) ?>
                                        <?= $d['status'] === 'to_validate' ? '<div class="text-xs text-warning">' . lang('Lgc.unconfirmedDate') . '</div>' : '' ?>
                                    </td>
                                    <td><?= esc($users[(int) $d['assigned_to']] ?? '—') ?></td>
                                    <td><?= Meta::badge('deadline', $d['status']) ?></td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <?php if ($d['status'] === 'to_validate' && ($canWork && $acc->can('lgc.approve'))): ?>
                                                <form method="post" action="<?= base_url('legalcase/plazos/validar/' . (int) $d['id']) ?>"><?= csrf_field() ?>
                                                    <button class="btn btn-secondary btn-sm" title="<?= esc(lang('Lgc.validate'), 'attr') ?>"><i class="icon icon-patch-check"></i></button></form>
                                            <?php endif; ?>
                                            <?php if ($isOpen && ($canWork && $acc->can('lgc.edit'))): ?>
                                                <form method="post" action="<?= base_url('legalcase/plazos/estado/' . (int) $d['id']) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="done">
                                                    <button class="btn btn-success btn-sm" title="<?= esc(lang('Lgc.markDone'), 'attr') ?>"><i class="icon icon-check-circle"></i></button></form>
                                                <form method="post" action="<?= base_url('legalcase/plazos/estado/' . (int) $d['id']) ?>" onsubmit="return confirm('<?= esc(lang('Lgc.confirmCancel'), 'js') ?>')"><?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
                                                    <button class="btn btn-ghost btn-sm" title="<?= esc(lang('Lgc.cancelItem'), 'attr') ?>"><i class="icon icon-x-circle"></i></button></form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-landmark"></i><h5 class="font-semibold"><?= lang('Lgc.hearings') ?></h5></div>
            <div class="card-body">
                <?php if (!$hearings): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noHearings') ?></p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($hearings as $h): ?>
                            <li class="rounded-md border border-line p-3 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="font-semibold"><?= esc($h['title']) ?>
                                        <span class="badge badge-light"><?= lang('Lgc.hmode_' . $h['mode']) ?></span>
                                        <span class="badge <?= ['scheduled' => 'badge-info', 'held' => 'badge-success', 'postponed' => 'badge-warning', 'cancelled' => 'badge-secondary'][$h['status']] ?? 'badge-light' ?>"><?= lang('Lgc.hstatus_' . $h['status']) ?></span>
                                        <?php if ((int) $h['is_shared']): ?><span class="badge badge-success"><i class="icon icon-eye"></i> <?= lang('Lgc.visibleToClient') ?></span><?php endif; ?>
                                    </div>
                                    <div class="text-xs <?= $h['status'] === 'scheduled' && Meta::isPast($h['scheduled_at']) ? 'text-danger' : 'text-muted' ?>"><?= esc(Meta::date($h['scheduled_at'])) ?> · <?= (int) $h['duration_min'] ?> min</div>
                                </div>
                                <div class="mt-1 text-xs text-muted">
                                    <?= esc($h['location'] ?: '') ?><?= $h['link'] ? ' · <a class="text-primary" href="' . esc($h['link'], 'attr') . '" target="_blank" rel="noopener noreferrer">' . esc(lang('Lgc.hearingLink')) . '</a>' : '' ?>
                                    <?= (int) $h['client_attendance'] ? ' · ' . lang('Lgc.clientMustAttend') : '' ?>
                                </div>
                                <?php if ($h['internal_notes']): ?><p class="mt-1 whitespace-pre-line text-xs"><i class="icon icon-lock"></i> <?= esc($h['internal_notes']) ?></p><?php endif; ?>
                                <?php if (($canWork && $acc->can('lgc.edit')) && $h['status'] === 'scheduled'): ?>
                                    <form method="post" action="<?= base_url('legalcase/audiencias/estado/' . (int) $h['id']) ?>" class="mt-2 flex flex-wrap gap-1"><?= csrf_field() ?>
                                        <button name="status" value="held" class="btn btn-success btn-sm"><i class="icon icon-check-circle"></i> <?= lang('Lgc.hstatus_held') ?></button>
                                        <button name="status" value="postponed" class="btn btn-ghost btn-sm"><?= lang('Lgc.hstatus_postponed') ?></button>
                                        <button name="status" value="cancelled" class="btn btn-ghost btn-sm" onclick="return confirm('<?= esc(lang('Lgc.confirmCancel'), 'js') ?>')"><?= lang('Lgc.hstatus_cancelled') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canWork && $acc->can('lgc.edit') && in_array($h['status'], ['scheduled', 'postponed'], true)): ?>
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs text-primary"><i class="icon icon-pencil"></i> <?= lang('Lgc.hearingEdit') ?></summary>
                                        <form method="post" action="<?= base_url('legalcase/audiencias/editar/' . (int) $h['id']) ?>" class="mt-3 space-y-3"><?= csrf_field() ?>
                                            <?php if ((int) $h['is_shared']): ?><div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Lgc.hearingRescheduleHint') ?></span></div><?php endif; ?>
                                            <?= view('Components\LegalCase\Views\matters\tabs\_hearing_fields', ['h' => $h, 'pfx' => 'he' . (int) $h['id'], 'proceedings' => $proceedings, 'acc' => $acc]) ?>
                                            <div class="flex justify-end"><button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button></div>
                                        </form>
                                    </details>
                                <?php endif; ?>
                                <?php if ($canWork && $acc->can('lgc.delete') && !((int) $h['is_shared'] && in_array($h['status'], ['scheduled', 'postponed'], true))): ?>
                                    <div class="mt-2 flex justify-end">
                                        <?= view('partials/delete_button', ['action' => base_url('legalcase/audiencias/eliminar/' . (int) $h['id']), 'confirm' => lang('Lgc.confirmDelete'), 'label' => lang('Lgc.delete'), 'class' => 'btn btn-ghost btn-sm', 'icon' => 'trash', 'text' => lang('Lgc.delete')]) ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-list-check"></i><h5 class="font-semibold"><?= lang('Lgc.tasks') ?></h5></div>
            <div class="card-body">
                <?php if (!$tasks): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noTasks') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($tasks as $t): ?>
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <div class="<?= $t['status'] !== 'open' ? 'text-muted line-through' : '' ?>">
                                    <span class="font-semibold"><?= esc($t['title']) ?></span>
                                    <?php if ($t['kind'] !== 'task'): ?><span class="badge badge-info"><?= lang('Lgc.tkind_' . $t['kind']) ?></span><?php endif; ?>
                                    <div class="text-xs text-muted"><?= esc(Meta::date($t['due_at'])) ?> · <?= esc($users[(int) $t['assigned_to']] ?? '—') ?></div>
                                </div>
                                <?php if (($canWork && $acc->can('lgc.edit'))): ?>
                                    <form method="post" action="<?= base_url('legalcase/tareas/estado/' . (int) $t['id']) ?>"><?= csrf_field() ?>
                                        <input type="hidden" name="status" value="<?= $t['status'] === 'open' ? 'done' : 'open' ?>">
                                        <button class="btn btn-ghost btn-sm"><i class="icon icon-<?= $t['status'] === 'open' ? 'check-circle' : 'arrow-counterclockwise' ?>"></i>
                                            <?= lang($t['status'] === 'open' ? 'Lgc.markDone' : 'Lgc.reopen') ?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (($canWork && $acc->can('lgc.create')) && $open): ?>
        <div class="min-w-0 space-y-4">
            <div class="card">
                <div class="card-header"><i class="icon icon-clock"></i><h5 class="font-semibold"><?= lang('Lgc.deadlineNew') ?></h5></div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('legalcase/asuntos/plazo/' . $id) ?>" class="space-y-3">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label" for="dl-title"><?= lang('Lgc.fTitle') ?> <span class="text-danger">*</span></label>
                            <input id="dl-title" type="text" name="title" class="form-input" required maxlength="190" placeholder="<?= esc(lang('Lgc.phDeadline'), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="dl-start"><?= lang('Lgc.fStartsOn') ?></label>
                            <input id="dl-start" type="date" name="starts_on" class="form-input">
                            <small class="form-hint"><?= lang('Lgc.fStartsOnHint') ?></small>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label class="form-label" for="dl-days"><?= lang('Lgc.fTermDays') ?></label>
                                <input id="dl-days" type="number" name="term_days" min="1" max="3650" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="dl-unit"><?= lang('Lgc.fTermUnit') ?></label>
                                <select id="dl-unit" name="term_unit" class="form-select">
                                    <option value="business"><?= lang('Lgc.unit_business') ?></option>
                                    <option value="calendar"><?= lang('Lgc.unit_calendar') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="dl-due"><?= lang('Lgc.fDueAt') ?></label>
                            <input id="dl-due" type="datetime-local" name="due_at" class="form-input">
                            <small class="form-hint"><?= lang('Lgc.fDueAtCalcHint') ?></small>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="dl-basis"><?= lang('Lgc.fBasis') ?></label>
                            <textarea id="dl-basis" name="basis" rows="2" class="form-textarea" placeholder="<?= esc(lang('Lgc.phBasis'), 'attr') ?>"></textarea>
                        </div>
                        <?php if ($proceedings): ?>
                            <div class="form-group">
                                <label class="form-label" for="dl-proc"><?= lang('Lgc.proceeding') ?></label>
                                <select id="dl-proc" name="proceeding_id" class="form-select">
                                    <option value=""><?= lang('Lgc.none') ?></option>
                                    <?php foreach ($proceedings as $p): ?><option value="<?= (int) $p['id'] ?>"><?= esc($p['filing_number'] ?: $p['authority']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label class="form-label" for="dl-who"><?= lang('Lgc.fAssignee') ?></label>
                            <select id="dl-who" name="assigned_to" class="form-select">
                                <?php foreach ($users as $uid => $name): ?><option value="<?= (int) $uid ?>" <?= (int) $uid === (int) $matter['lead_lawyer_id'] ? 'selected' : '' ?>><?= esc($name) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="dl-crit" name="is_critical" value="1" class="form-check-input">
                            <label for="dl-crit" class="form-check-label"><?= lang('Lgc.fCritical') ?><span class="block text-xs text-muted"><?= lang($dual ? 'Lgc.fCriticalHintDual' : 'Lgc.fCriticalHint') ?></span></label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.add') ?></button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="icon icon-landmark"></i><h5 class="font-semibold"><?= lang('Lgc.hearingNew') ?></h5></div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('legalcase/asuntos/audiencia/' . $id) ?>" class="space-y-3">
                        <?= csrf_field() ?>
                        <?= view('Components\LegalCase\Views\matters\tabs\_hearing_fields', ['h' => [], 'pfx' => 'hr', 'proceedings' => $proceedings, 'acc' => $acc]) ?>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.add') ?></button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="icon icon-list-check"></i><h5 class="font-semibold"><?= lang('Lgc.taskNew') ?></h5></div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('legalcase/asuntos/tarea/' . $id) ?>" class="space-y-3">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label" for="tk-title"><?= lang('Lgc.fTitle') ?> <span class="text-danger">*</span></label>
                            <input id="tk-title" type="text" name="title" class="form-input" required maxlength="190">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label class="form-label" for="tk-due"><?= lang('Lgc.fDueAt') ?></label>
                                <input id="tk-due" type="datetime-local" name="due_at" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="tk-who"><?= lang('Lgc.fAssignee') ?></label>
                                <select id="tk-who" name="assigned_to" class="form-select">
                                    <?php foreach ($users as $uid => $name): ?><option value="<?= (int) $uid ?>" <?= (int) $uid === $acc->userId ? 'selected' : '' ?>><?= esc($name) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="tk-detail"><?= lang('Lgc.fDetail') ?></label>
                            <textarea id="tk-detail" name="detail" rows="2" class="form-textarea"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.add') ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
