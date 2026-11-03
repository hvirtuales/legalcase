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
                                            <?php if ($d['status'] === 'to_validate' && $acc->can('lgc.approve')): ?>
                                                <form method="post" action="<?= base_url('legalcase/plazos/validar/' . (int) $d['id']) ?>"><?= csrf_field() ?>
                                                    <button class="btn btn-secondary btn-sm" title="<?= esc(lang('Lgc.validate'), 'attr') ?>"><i class="icon icon-patch-check"></i></button></form>
                                            <?php endif; ?>
                                            <?php if ($isOpen && $acc->can('lgc.edit')): ?>
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
                                <?php if ($acc->can('lgc.edit')): ?>
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

    <?php if ($acc->can('lgc.create') && $open): ?>
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
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label class="form-label" for="dl-start"><?= lang('Lgc.fStartsOn') ?></label>
                                <input id="dl-start" type="date" name="starts_on" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="dl-due"><?= lang('Lgc.fDueAt') ?> <span class="text-danger">*</span></label>
                                <input id="dl-due" type="datetime-local" name="due_at" class="form-input" required>
                            </div>
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
