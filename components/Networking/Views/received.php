<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php /** @var \Components\Networking\Libraries\NetworkService $net */ ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-box-arrow-in-right"></i>
        <h5 class="font-semibold"><?= lang('Net.receivedTitle') ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Networking\Views\_tabs', ['counts' => $counts, 'current' => 'recibidas']) ?>

        <?php if (empty($rows)): ?>
            <p class="py-8 text-center text-muted"><?= lang('Net.noPending') ?></p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($rows as $r): ?>
                    <?php $uid = (int) $r['requester_id']; ?>
                    <div class="rounded-lg border border-line bg-surface p-4">
                        <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <a href="<?= base_url('networking/perfil/' . $uid) ?>" class="font-semibold text-primary">
                                    <?= esc($net->displayName($r['other'])) ?>
                                </a>
                                <div class="text-xs text-muted">
                                    <i class="icon icon-clock"></i> <?= esc($r['created_at'] ?? '') ?>
                                </div>
                            </div>
                            <span class="badge badge-warning"><?= lang('Net.statusPending') ?></span>
                        </div>

                        <div class="mb-3 rounded border border-line bg-body p-3 text-sm">
                            <div class="mb-1 text-xs font-semibold text-muted"><?= lang('Net.reason') ?></div>
                            <?= nl2br(esc($r['message'] ?? '')) ?>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <form action="<?= base_url('networking/responder/' . (int) $r['id']) ?>" method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="accepted">
                                <button class="btn btn-success btn-sm"><i class="icon icon-check-circle"></i> <?= lang('Net.accept') ?></button>
                            </form>
                            <form action="<?= base_url('networking/responder/' . (int) $r['id']) ?>" method="post" class="inline"
                                onsubmit="return confirm('<?= esc(lang('Net.confirmReject'), 'js') ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="rejected">
                                <button class="btn btn-secondary btn-sm"><i class="icon icon-x-circle"></i> <?= lang('Net.reject') ?></button>
                            </form>
                            <a href="<?= base_url('networking/perfil/' . $uid) ?>" class="btn btn-ghost btn-sm">
                                <i class="icon icon-eye"></i> <?= lang('Net.viewProfile') ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
