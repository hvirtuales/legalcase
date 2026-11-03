<?php
/**
 * Hilos del asunto. La respuesta se escribe en la ficha del hilo.
 *
 * @var array $matter @var array $threads @var array $users @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\Meta;

$id = (int) $matter['id'];
?>
<div class="grid gap-4 lg:grid-cols-3">
    <div class="card min-w-0 lg:col-span-2">
        <div class="card-header"><i class="icon icon-chat"></i><h5 class="font-semibold"><?= lang('Lgc.threads') ?></h5></div>
        <div class="card-body">
            <?php if (!$threads): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noThreads') ?></p>
            <?php else: ?>
                <ul class="divide-y divide-line">
                    <?php foreach ($threads as $t): $late = !$t['first_response_at'] && Meta::isPast($t['first_response_due_at']); ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <div>
                                <a class="font-semibold text-primary" href="<?= base_url('legalcase/mensajes/hilo/' . (int) $t['id']) ?>"><?= esc($t['subject']) ?></a>
                                <?php if ($t['kind'] === 'data_rights'): ?><span class="badge badge-danger"><?= lang('Lgc.dataRights') ?></span><?php endif; ?>
                                <span class="text-xs text-muted"><?= (int) $t['is_shared'] ? lang('Lgc.sharedThread') : esc($accounts[(int) ($t['account_id'] ?: $t['opened_by_account'])] ?? '') ?></span>
                                <div class="text-xs text-muted">
                                    <?= esc(Meta::date($t['last_message_at'])) ?> · <?= esc($users[(int) $t['assigned_to']] ?? lang('Lgc.unassigned')) ?>
                                    <?php if ($late): ?><span class="font-semibold text-danger"> · <?= lang('Lgc.slaLate') ?></span><?php endif; ?>
                                </div>
                            </div>
                            <?= Meta::badge('thread', $t['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php if (($canWork && $acc->can('lgc.edit')) && $matter['status'] !== 'closed'): ?>
        <div class="card">
            <div class="card-header"><i class="icon icon-send"></i><h5 class="font-semibold"><?= lang('Lgc.threadNew') ?></h5></div>
            <div class="card-body">
                <form method="post" action="<?= base_url('legalcase/asuntos/mensaje/' . $id) ?>" class="space-y-3">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label" for="th-to"><?= lang('Lgc.fRecipient') ?> <span class="text-danger">*</span></label>
                        <select id="th-to" name="recipient" class="form-select" required>
                            <?php foreach ($accounts as $aid => $mail): ?><option value="<?= (int) $aid ?>"><?= esc($mail) ?></option><?php endforeach; ?>
                            <?php if (count($accounts) > 1): ?><option value="shared"><?= lang('Lgc.sharedThreadOption') ?></option><?php endif; ?>
                        </select>
                        <small class="form-hint"><?= lang($accounts ? 'Lgc.sharedThreadHint' : 'Lgc.noRecipients') ?></small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="th-subj"><?= lang('Lgc.fSubject') ?></label>
                        <input id="th-subj" type="text" name="subject" class="form-input" maxlength="190">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="th-body"><?= lang('Lgc.fMessage') ?> <span class="text-danger">*</span></label>
                        <textarea id="th-body" name="body" rows="5" class="form-textarea" required maxlength="5000"></textarea>
                        <small class="form-hint"><?= lang('Lgc.threadNewHint') ?></small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-send"></i> <?= lang('Lgc.send') ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
