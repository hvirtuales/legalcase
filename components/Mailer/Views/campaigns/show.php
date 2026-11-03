<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$statusBadge = [
    'draft'   => '<span class="badge badge-secondary">' . lang('Mailer.stDraft') . '</span>',
    'sending' => '<span class="badge badge-warning">' . lang('Mailer.stSending') . '</span>',
    'paused'  => '<span class="badge badge-secondary">' . lang('Mailer.stPaused') . '</span>',
    'sent'    => '<span class="badge badge-success">' . lang('Mailer.stSent') . '</span>',
    'failed'  => '<span class="badge badge-danger">' . lang('Mailer.stFailed') . '</span>',
];
$isDraft   = in_array($campaign['status'], ['draft', 'failed'], true);
$isSending = $campaign['status'] === 'sending';
$canSend   = $campaign['status'] !== 'sent';
?>
<div class="mb-4 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <a href="<?= base_url('mailer/campanas') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Mailer.back') ?></a>
        <h5 class="font-semibold"><?= esc($campaign['name']) ?></h5>
        <?= $statusBadge[$campaign['status']] ?? '' ?>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($isDraft): ?>
            <a href="<?= base_url('mailer/campanas/editar/' . $campaign['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i> <?= lang('Mailer.edit') ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card mb-4">
            <div class="card-header"><i class="icon icon-envelope"></i><h6 class="font-semibold"><?= lang('Mailer.detail') ?></h6></div>
            <div class="card-body">
                <dl class="grid grid-cols-3 gap-2 text-sm">
                    <dt class="text-muted"><?= lang('Mailer.subject') ?></dt><dd class="col-span-2"><?= esc($campaign['subject']) ?></dd>
                    <dt class="text-muted"><?= lang('Mailer.list') ?></dt><dd class="col-span-2"><?= esc($list['name'] ?? '—') ?> <span class="text-muted"><?= lang('Mailer.nSubscribed', [(int) $sendable]) ?></span></dd>
                    <dt class="text-muted"><?= lang('Mailer.sender') ?></dt><dd class="col-span-2"><?= esc(trim(($campaign['from_name'] ?? '') . ' <' . ($campaign['from_email'] ?: lang('Mailer.defaultSender')) . '>', ' ')) ?></dd>
                    <?php if ($campaign['reply_to']): ?><dt class="text-muted"><?= lang('Mailer.replyTo') ?></dt><dd class="col-span-2"><?= esc($campaign['reply_to']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2"><i class="icon icon-bar-chart"></i><h6 class="font-semibold"><?= lang('Mailer.opensClicks') ?></h6></div>
                <a href="<?= base_url('mailer/campanas/' . $campaign['id'] . '/reporte') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-clipboard"></i> <?= lang('Mailer.sendReport') ?></a>
            </div>
            <div class="card-body">
                <?php $e = $engagement; ?>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded border border-app p-3 text-center">
                        <div class="text-2xl font-bold"><?= (int) $e['sent'] ?></div>
                        <div class="text-xs text-muted"><?= lang('Mailer.sent') ?></div>
                    </div>
                    <div class="rounded border border-app p-3 text-center">
                        <div class="text-2xl font-bold text-primary"><?= (float) $e['open_rate'] ?>%</div>
                        <div class="text-xs text-muted"><?= lang('Mailer.opens', [(int) $e['opens_unique']]) ?> <span class="opacity-70"><?= lang('Mailer.ofTotal', [(int) $e['opens_total']]) ?></span></div>
                    </div>
                    <div class="rounded border border-app p-3 text-center">
                        <div class="text-2xl font-bold text-success"><?= (float) $e['click_rate'] ?>%</div>
                        <div class="text-xs text-muted"><?= lang('Mailer.clicks', [(int) $e['clicks_unique']]) ?> <span class="opacity-70"><?= lang('Mailer.ofTotal', [(int) $e['clicks_total']]) ?></span></div>
                    </div>
                    <div class="rounded border border-app p-3 text-center">
                        <div class="text-2xl font-bold"><?= (float) $e['ctr'] ?>%</div>
                        <div class="text-xs text-muted"><?= lang('Mailer.ctr') ?></div>
                    </div>
                </div>

                <?php if (!empty($topLinks)): ?>
                    <h6 class="mt-4 mb-2 text-xs font-semibold uppercase tracking-wide text-muted"><?= lang('Mailer.topLinks') ?></h6>
                    <div class="overflow-x-auto">
                        <table class="table text-sm">
                            <thead><tr><th><?= lang('Mailer.link') ?></th><th class="text-right"><?= lang('Mailer.clicksCol') ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($topLinks as $l): ?>
                                    <tr>
                                        <td class="max-w-md truncate"><a href="<?= esc($l['url'], 'attr') ?>" target="_blank" class="text-primary"><?= esc($l['url']) ?></a></td>
                                        <td class="text-right font-semibold"><?= (int) $l['clicks'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <p class="mt-2 text-xs text-muted"><?= lang('Mailer.pixelNote') ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2"><i class="icon icon-eye"></i><h6 class="font-semibold"><?= lang('Mailer.mailPreview') ?></h6></div>
                <a href="<?= base_url('mailer/campanas/' . $campaign['id'] . '/preview') ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="icon icon-box-arrow-up-right"></i> <?= lang('Mailer.openTab') ?></a>
            </div>
            <div class="card-body">
                <?php if (trim((string) $campaign['body_html']) === ''): ?>
                    <div class="rounded border border-app p-4 bg-surface text-sm text-muted"><?= lang('Mailer.emptyBody') ?></div>
                <?php else: ?>
                    <iframe src="<?= base_url('mailer/campanas/' . $campaign['id'] . '/preview') ?>" title="<?= esc(lang('Mailer.previewAlt2'), 'attr') ?>"
                        style="width:100%;height:520px;border:1px solid var(--color-border,#e5e7eb);border-radius:8px;background:#fff"></iframe>
                    <p class="mt-2 text-xs text-muted"><?= lang('Mailer.previewNote') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="card mb-4">
            <div class="card-header"><i class="icon icon-bar-chart"></i><h6 class="font-semibold"><?= lang('Mailer.progress') ?></h6></div>
            <div class="card-body">
                <?php
                $total = (int) $campaign['total'];
                $done  = (int) $counts['sent'] + (int) $counts['failed'] + (int) $counts['skipped'];
                $pct   = $total > 0 ? round($done / $total * 100) : 0;
                ?>
                <div class="mb-2 h-2 w-full overflow-hidden rounded bg-app-muted">
                    <div class="h-2 bg-primary" style="width: <?= $pct ?>%"></div>
                </div>
                <ul class="space-y-1 text-sm">
                    <li class="flex justify-between"><span class="text-muted"><?= lang('Mailer.inQueue') ?></span><strong><?= $total ?></strong></li>
                    <li class="flex justify-between"><span class="text-success"><?= lang('Mailer.sent') ?></span><strong><?= (int) $counts['sent'] ?></strong></li>
                    <li class="flex justify-between"><span class="text-danger"><?= lang('Mailer.failedPlural') ?></span><strong><?= (int) $counts['failed'] ?></strong></li>
                    <li class="flex justify-between"><span class="text-warning"><?= lang('Mailer.bouncesComplaints') ?></span><strong><?= (int) $counts['bounced'] + (int) $counts['complained'] ?></strong></li>
                    <li class="flex justify-between"><span class="text-muted"><?= lang('Mailer.skippedSupp') ?></span><strong><?= (int) $counts['skipped'] ?></strong></li>
                    <li class="flex justify-between"><span class="text-muted"><?= lang('Mailer.pending') ?></span><strong><?= (int) $counts['pending'] ?></strong></li>
                </ul>
                <?php if (($quota ?? null) !== null): ?>
                    <p class="mt-2 text-xs text-muted"><?= lang('Mailer.quotaLeftShort', [(int) $quota]) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($reputation['level'] ?? 'ok') === 'block'): ?>
            <div class="alert alert-danger mb-4"><i class="icon icon-x-circle"></i><span><strong><?= lang('Mailer.repBlocked') ?></strong> <?= esc($reputation['reason']) ?></span></div>
        <?php elseif (($reputation['level'] ?? 'ok') === 'warn'): ?>
            <div class="alert alert-warning mb-4"><i class="icon icon-exclamation-circle"></i><span><?= esc($reputation['reason']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($missingAddress)): ?>
            <div class="alert alert-warning mb-4"><i class="icon icon-exclamation-circle"></i><span><?= lang('Mailer.needAddress', [base_url('mi-empresa')]) ?></span></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header"><i class="icon icon-send"></i><h6 class="font-semibold"><?= lang('Mailer.sendTest') ?></h6></div>
            <div class="card-body">
                <form method="post" action="<?= base_url('mailer/campanas/prueba/' . $campaign['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label" for="test_email"><?= lang('Mailer.testEmail') ?></label>
                        <input type="email" name="test_email" id="test_email" class="form-input" required placeholder="<?= esc(lang('Mailer.testPh'), 'attr') ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary w-full"><i class="icon icon-envelope"></i> <?= lang('Mailer.sendTest') ?></button>
                    <p class="mt-2 text-xs text-muted"><?= lang('Mailer.testNote2') ?></p>
                </form>
            </div>
        </div>

        <?php if ($canSend): ?>
            <?php $hasQueue = (int) $counts['pending'] > 0 || $isSending; ?>
            <div class="card">
                <div class="card-body">
                    <?php if ((int) $sendable === 0 && (int) $counts['pending'] === 0): ?>
                        <div class="alert alert-warning text-sm"><i class="icon icon-exclamation-circle"></i><span><?= lang('Mailer.noSendable') ?></span></div>
                    <?php endif; ?>

                    <?php if (!$hasQueue): ?>
                        <!-- Envío automático (cron) -->
                        <form method="post" action="<?= base_url('mailer/campanas/encolar/' . $campaign['id']) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary w-full" onclick="return confirm('<?= esc(lang('Mailer.scheduleConfirm'), 'js') ?>')">
                                <i class="icon icon-clock"></i> <?= lang('Mailer.scheduleSend') ?>
                            </button>
                        </form>
                        <p class="mt-1 mb-3 text-xs text-muted"><?= lang('Mailer.scheduleNote') ?></p>
                    <?php else: ?>
                        <div class="alert alert-info text-sm"><i class="icon icon-clock"></i>
                            <span><?= lang('Mailer.queued', [(int) $counts['pending']]) ?></span></div>
                    <?php endif; ?>

                    <!-- Envío manual inmediato (un lote) -->
                    <form method="post" action="<?= base_url('mailer/campanas/enviar/' . $campaign['id']) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn <?= $hasQueue ? 'btn-primary' : 'btn-secondary' ?> w-full"
                            onclick="return confirm('<?= esc($hasQueue ? lang('Mailer.confirmNext') : lang('Mailer.confirmFirst'), 'js') ?>')">
                            <i class="icon icon-send"></i>
                            <?= $hasQueue ? lang('Mailer.sendNextBatch') : lang('Mailer.sendFirstBatch') ?>
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-muted"><?= lang('Mailer.batchNote', [\Components\Mailer\Libraries\MailerService::BATCH]) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
