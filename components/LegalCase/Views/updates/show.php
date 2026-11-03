<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Vista previa EXACTA de lo que verá el cliente + acciones del ciclo de aprobación.
 * Publicar exige confirmar los destinatarios (HC-C03).
 *
 * @var array $u @var array $matter @var array $warnings @var string $policy @var array $recipients
 * @var array $versions @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Libraries\Team;
use Components\LegalCase\Models\MatterModel;

$id       = (int) $u['id'];
$editable = in_array($u['status'], ['draft', 'pending_review'], true);
$cid      = (int) $matter['company_id'];
$isAuthor = (int) $u['author_id'] === $acc->userId;
$canPub   = $acc->can('lgc.approve') && (
    $u['status'] === 'approved' || ($policy === 'self' && $editable)
);
$canApprove = $acc->can('lgc.approve') && $u['status'] === 'pending_review' && !($policy === 'reviewer' && $isAuthor);
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <div class="flex items-center gap-3">
        <a href="<?= base_url('legalcase/asuntos/ficha/' . (int) $matter['id'] . '?tab=comunicacion') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i></a>
        <div>
            <h4 class="flex flex-wrap items-center gap-2 font-bold"><?= esc($u['title']) ?> <?= Meta::badge('upd', $u['status']) ?></h4>
            <p class="text-xs text-muted">
                <span class="font-mono"><?= esc($matter['number']) ?></span> · <?= lang('Lgc.ukind_' . $u['kind']) ?> · v<?= (int) $u['version'] ?>
                · <?= lang('Lgc.author') ?>: <?= esc(Team::name($cid, (int) $u['author_id'] ?: null)) ?>
            </p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ($editable && $acc->can('lgc.edit')): ?>
            <a href="<?= base_url('legalcase/actualizaciones/editar/' . $id) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i> <?= lang('Lgc.edit') ?></a>
        <?php endif; ?>
        <?php if ($u['status'] === 'draft' && $acc->can('lgc.edit')): ?>
            <form method="post" action="<?= base_url('legalcase/actualizaciones/enviar/' . $id) ?>"><?= csrf_field() ?>
                <button class="btn btn-secondary btn-sm"><i class="icon icon-send"></i> <?= lang('Lgc.submitReview') ?></button></form>
        <?php endif; ?>
        <?php if ($canApprove): ?>
            <form method="post" action="<?= base_url('legalcase/actualizaciones/aprobar/' . $id) ?>"><?= csrf_field() ?>
                <button class="btn btn-success btn-sm"><i class="icon icon-check-circle"></i> <?= lang('Lgc.approve') ?></button></form>
        <?php endif; ?>
        <?php if ($u['status'] === 'published' && $acc->can('lgc.edit')): ?>
            <form method="post" action="<?= base_url('legalcase/actualizaciones/revisar/' . $id) ?>"><?= csrf_field() ?>
                <button class="btn btn-secondary btn-sm"><i class="icon icon-pencil-square"></i> <?= lang('Lgc.newVersion') ?></button></form>
        <?php endif; ?>
        <?php if ($editable && $acc->can('lgc.delete')): ?>
            <?= view('partials/delete_button', ['action' => base_url('legalcase/actualizaciones/eliminar/' . $id), 'confirm' => lang('Lgc.confirmDelete'), 'label' => lang('Lgc.delete'), 'class' => 'btn btn-danger btn-sm', 'icon' => 'trash', 'text' => '']) ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="min-w-0 space-y-4 lg:col-span-2">
        <?php if ($warnings): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i>
                <span><?= lang('Lgc.updWarnings') ?> <strong><?= esc(implode(', ', $warnings)) ?></strong></span></div>
        <?php endif; ?>
        <?php if ($u['review_note']): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Lgc.reviewNote') ?>: <?= esc($u['review_note']) ?></span></div>
        <?php endif; ?>
        <?php if ($u['status'] === 'revoked'): ?>
            <div class="alert alert-danger"><i class="icon icon-x-circle"></i><span><?= lang('Lgc.revokedNote', [Meta::date($u['revoked_at']), esc($u['revoke_reason'])]) ?></span></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header flex-wrap justify-between gap-2">
                <div class="flex items-center gap-2"><i class="icon icon-eye"></i><h5 class="font-semibold"><?= lang('Lgc.clientPreview') ?></h5></div>
                <span class="text-xs text-muted"><?= esc(MatterModel::clientTitle($matter)) ?></span>
            </div>
            <div class="card-body space-y-4 text-sm">
                <?php if ($u['stage_label']): ?>
                    <div><span class="badge badge-primary"><?= esc($u['stage_label']) ?></span></div>
                <?php endif; ?>
                <?php foreach (['what_happened', 'what_it_means', 'what_we_do', 'client_action'] as $f): ?>
                    <?php if (trim((string) $u[$f]) !== ''): ?>
                        <div>
                            <div class="text-xs font-semibold uppercase text-muted"><?= lang('Lgc.pt_' . $f) ?></div>
                            <p class="mt-1 whitespace-pre-line"><?= esc($u[$f]) ?></p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($u['kind'] === 'no_news'): ?>
                    <p class="text-xs text-muted"><?= lang('Lgc.verifiedLine', [esc($u['verified_source'] ?: '—'), esc(Meta::date($u['verified_at']))]) ?></p>
                <?php endif; ?>
                <div class="rounded-md border border-line p-3">
                    <div class="text-xs font-semibold uppercase text-muted"><?= lang('Lgc.pt_next') ?></div>
                    <p class="mt-1"><?= $u['next_update_at'] ? esc(Meta::date($u['next_update_at'], false)) : lang('Lgc.ptNextByPlan') ?></p>
                    <p class="mt-1 text-xs text-muted"><?= lang('Lgc.ptNextDisclaimer') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="min-w-0 space-y-4">
        <?php if ($canPub): ?>
            <div class="card">
                <div class="card-header"><i class="icon icon-send"></i><h5 class="font-semibold"><?= lang('Lgc.publish') ?></h5></div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('legalcase/actualizaciones/publicar/' . $id) ?>" class="space-y-3">
                        <?= csrf_field() ?>
                        <p class="text-xs text-muted"><?= lang($policy === 'self' && $u['status'] !== 'approved' ? 'Lgc.publishSelfHint' : 'Lgc.publishHint') ?></p>
                        <div class="text-sm">
                            <div class="mb-1 text-xs font-semibold uppercase text-muted"><?= lang('Lgc.recipients') ?></div>
                            <?php if (!$recipients): ?>
                                <p class="text-muted"><?= lang('Lgc.noRecipientsPublish') ?></p>
                            <?php else: ?>
                                <ul class="space-y-1">
                                    <?php foreach ($recipients as $r): ?>
                                        <li><i class="icon icon-person-circle"></i> <?= esc($r['email']) ?> <?= (int) $r['notify_email'] ? '' : '<span class="badge badge-secondary">' . lang('Lgc.optedOut') . '</span>' ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="confirm_recipients" name="confirm_recipients" value="1" class="form-check-input" required>
                            <label for="confirm_recipients" class="form-check-label"><?= lang('Lgc.confirmRecipients') ?></label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="icon icon-send"></i> <?= lang('Lgc.publish') ?></button>
                    </form>
                </div>
            </div>
        <?php elseif ($policy === 'reviewer' && $isAuthor && $u['status'] === 'pending_review'): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Lgc.waitingReviewer') ?></span></div>
        <?php endif; ?>

        <?php if (in_array($u['status'], ['pending_review', 'approved'], true) && $acc->can('lgc.approve')): ?>
            <div class="card">
                <div class="card-header"><i class="icon icon-arrow-counterclockwise"></i><h5 class="font-semibold"><?= lang('Lgc.sendBack') ?></h5></div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('legalcase/actualizaciones/devolver/' . $id) ?>" class="space-y-2"><?= csrf_field() ?>
                        <textarea name="note" rows="2" class="form-textarea" required maxlength="255" placeholder="<?= esc(lang('Lgc.phReturnNote'), 'attr') ?>" aria-label="<?= esc(lang('Lgc.reviewNote'), 'attr') ?>"></textarea>
                        <button class="btn btn-ghost btn-sm"><?= lang('Lgc.sendBack') ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($u['status'] === 'published' && $acc->can('lgc.approve')): ?>
            <div class="card">
                <div class="card-header"><i class="icon icon-x-circle"></i><h5 class="font-semibold"><?= lang('Lgc.revoke') ?></h5></div>
                <div class="card-body">
                    <p class="mb-2 text-xs text-muted"><?= lang('Lgc.revokeHint') ?></p>
                    <form method="post" action="<?= base_url('legalcase/actualizaciones/revocar/' . $id) ?>" class="space-y-2"
                        onsubmit="return confirm('<?= esc(lang('Lgc.confirmRevoke'), 'js') ?>')"><?= csrf_field() ?>
                        <textarea name="reason" rows="2" class="form-textarea" required maxlength="255" placeholder="<?= esc(lang('Lgc.phRevokeReason'), 'attr') ?>" aria-label="<?= esc(lang('Lgc.revoke'), 'attr') ?>"></textarea>
                        <button class="btn btn-danger btn-sm"><?= lang('Lgc.revoke') ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="icon icon-history"></i><h5 class="font-semibold"><?= lang('Lgc.trace') ?></h5></div>
            <div class="card-body text-xs">
                <dl class="space-y-1">
                    <div><dt class="inline text-muted"><?= lang('Lgc.created') ?>:</dt> <dd class="inline"><?= esc(Meta::date($u['created_at'])) ?></dd></div>
                    <div><dt class="inline text-muted"><?= lang('Lgc.submitted') ?>:</dt> <dd class="inline"><?= esc(Meta::date($u['submitted_at'])) ?></dd></div>
                    <div><dt class="inline text-muted"><?= lang('Lgc.reviewed') ?>:</dt> <dd class="inline"><?= esc(Meta::date($u['reviewed_at'])) ?><?= $u['reviewed_by'] ? ' · ' . esc(Team::name($cid, (int) $u['reviewed_by'])) : '' ?></dd></div>
                    <div><dt class="inline text-muted"><?= lang('Lgc.published') ?>:</dt> <dd class="inline"><?= esc(Meta::date($u['published_at'])) ?><?= $u['published_by'] ? ' · ' . esc(Team::name($cid, (int) $u['published_by'])) : '' ?></dd></div>
                </dl>
                <?php if ($versions): ?>
                    <div class="mt-3 font-semibold text-muted"><?= lang('Lgc.versions') ?></div>
                    <ul class="mt-1 space-y-1">
                        <?php foreach ($versions as $ver): ?>
                            <li><a class="text-primary" href="<?= base_url('legalcase/actualizaciones/ver/' . (int) $ver['id']) ?>">v<?= (int) $ver['version'] ?></a> <?= Meta::badge('upd', $ver['status']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
