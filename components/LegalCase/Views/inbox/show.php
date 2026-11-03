<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Un hilo: la conversación a la izquierda; responsable, estado y respuesta a la derecha.
 * La respuesta es del despacho; el cliente recibe solo un aviso para entrar al portal.
 *
 * @var array $thread @var array $matter @var array $messages @var array $users
 */
use Components\LegalCase\Libraries\Access;
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\ThreadModel;

$id       = (int) $thread['id'];
$acc      = Access::current();
$canWork  = $acc->canWorkOn($matter);
$canEdit  = $canWork && $acc->can('lgc.edit');
$canShare = $canWork && $acc->can('lgc.share');
?>
<div class="mb-4 flex flex-wrap items-center gap-3">
    <a href="<?= base_url('legalcase/mensajes') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i></a>
    <div>
        <h4 class="flex flex-wrap items-center gap-2 font-bold"><?= esc($thread['subject']) ?> <?= Meta::badge('thread', $thread['status']) ?>
            <?php if ($thread['kind'] === 'data_rights'): ?><span class="badge badge-danger"><?= lang('Lgc.dataRights') ?></span><?php endif; ?></h4>
        <p class="text-xs text-muted"><?= lang('Lgc.fRecipient') ?>: <?= (int) $thread['is_shared'] ? lang('Lgc.sharedThread') : esc($accounts[(int) ($thread['account_id'] ?: $thread['opened_by_account'])] ?? '—') ?></p>
        <p class="text-xs text-muted">
            <a class="text-primary" href="<?= base_url('legalcase/asuntos/ficha/' . (int) $matter['id'] . '?tab=mensajes') ?>"><span class="font-mono"><?= esc($matter['number']) ?></span> · <?= esc($matter['title']) ?></a>
        </p>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="card min-w-0 lg:col-span-2">
        <div class="card-body space-y-3">
            <?php if ($thread['kind'] === 'data_rights'): ?>
                <div class="alert alert-warning"><i class="icon icon-shield-check"></i><span><?= lang('Lgc.dataRightsHint') ?></span></div>
            <?php endif; ?>
            <?php foreach ($messages as $m): $in = $m['direction'] === 'in'; $review = ($m['status'] ?? 'sent') === 'pending_review'; ?>
                <div class="rounded-md border p-3 border-line <?= $in ? '' : 'bg-surface-muted' ?>">
                    <?php if ($review): ?>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="badge badge-warning"><?= lang('Lgc.msgInReview') ?></span>
                            <?php if ($canShare && (int) $m['author_user_id'] !== $acc->userId): ?>
                                <form method="post" action="<?= base_url('legalcase/mensajes/revisar/' . (int) $m['id']) ?>" class="flex gap-1"><?= csrf_field() ?>
                                    <button name="decision" value="approve" class="btn btn-success btn-sm"><i class="icon icon-send"></i> <?= lang('Lgc.approveSend') ?></button>
                                    <button name="decision" value="discard" class="btn btn-ghost btn-sm" onclick="return confirm('<?= esc(lang('Lgc.confirmDiscard'), 'js') ?>')"><?= lang('Lgc.discard') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                        <span>
                            <?php if ($in): ?>
                                <i class="icon icon-person-circle"></i> <?= lang('Lgc.fromClient') ?>
                            <?php elseif ((int) $m['is_auto']): ?>
                                <i class="icon icon-robot"></i> <?= lang('Lgc.autoAckLabel') ?>
                            <?php else: ?>
                                <i class="icon icon-person-badge"></i> <?= esc($users[(int) $m['author_user_id']] ?? '#') ?>
                            <?php endif; ?>
                        </span>
                        <span><?= esc(Meta::date($m['created_at'])) ?></span>
                    </div>
                    <p class="whitespace-pre-line text-sm"><?= esc($m['body']) ?></p>
                </div>
            <?php endforeach; ?>

            <?php if ($canEdit): ?>
                <form method="post" action="<?= base_url('legalcase/mensajes/responder/' . $id) ?>" class="space-y-2 border-t border-line pt-3">
                    <?= csrf_field() ?>
                    <label for="body" class="form-label"><?= lang('Lgc.reply') ?></label>
                    <textarea id="body" name="body" rows="5" class="form-textarea" required maxlength="5000"></textarea>
                    <small class="form-hint"><?= lang($canShare ? 'Lgc.replyHint' : 'Lgc.replyReviewHint') ?></small>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <select name="next_status" class="form-select" aria-label="<?= esc(lang('Lgc.fStatus'), 'attr') ?>">
                            <option value="answered"><?= lang('Lgc.thread_answered') ?></option>
                            <option value="waiting_client"><?= lang('Lgc.thread_waiting_client') ?></option>
                            <option value="closed"><?= lang('Lgc.thread_closed') ?></option>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="icon icon-send"></i> <?= lang('Lgc.send') ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-gear"></i><h5 class="font-semibold"><?= lang('Lgc.management') ?></h5></div>
        <div class="card-body space-y-3 text-sm">
            <div>
                <span class="text-xs text-muted"><?= lang('Lgc.fSlaDue') ?></span>
                <div class="<?= !$thread['first_response_at'] && Meta::isPast($thread['first_response_due_at']) ? 'font-semibold text-danger' : '' ?>"><?= esc(Meta::date($thread['first_response_due_at'])) ?></div>
                <?php if ($thread['first_response_at']): ?><div class="text-xs text-muted"><?= esc(lang('Lgc.answeredAt', [Meta::date($thread['first_response_at'])])) ?></div><?php endif; ?>
            </div>
            <?php if ($canEdit): ?>
                <form method="post" action="<?= base_url('legalcase/mensajes/estado/' . $id) ?>" class="space-y-3"><?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label" for="th-as"><?= lang('Lgc.fAssignee') ?></label>
                        <select id="th-as" name="assigned_to" class="form-select">
                            <option value=""><?= lang('Lgc.unassigned') ?></option>
                            <?php foreach ($users as $uid => $name): ?><option value="<?= (int) $uid ?>" <?= (int) $thread['assigned_to'] === (int) $uid ? 'selected' : '' ?>><?= esc($name) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="th-st"><?= lang('Lgc.fStatus') ?></label>
                        <select id="th-st" name="status" class="form-select">
                            <?php foreach (ThreadModel::STATUSES as $s): ?><option value="<?= $s ?>" <?= $thread['status'] === $s ? 'selected' : '' ?>><?= lang('Lgc.thread_' . $s) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="th-pr"><?= lang('Lgc.fPriority') ?></label>
                        <select id="th-pr" name="priority" class="form-select">
                            <?php foreach (ThreadModel::PRIORITIES as $p): ?><option value="<?= $p ?>" <?= $thread['priority'] === $p ? 'selected' : '' ?>><?= lang('Lgc.prio_' . $p) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
