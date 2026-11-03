<?= $this->extend('layouts/admin') ?>

<?= $this->section('styles') ?>
<style>
    @media print {
        body * { visibility: hidden !important; }
        #printReport, #printReport * { visibility: visible !important; }
        #printReport { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$statusBadge = [
    'pending'    => '<span class="badge badge-secondary">' . lang('Mailer.stPending') . '</span>',
    'sent'       => '<span class="badge badge-success">' . lang('Mailer.stSentOne') . '</span>',
    'failed'     => '<span class="badge badge-danger">' . lang('Mailer.stFailedOne') . '</span>',
    'bounced'    => '<span class="badge badge-warning">' . lang('Mailer.stBounced') . '</span>',
    'complained' => '<span class="badge badge-danger">' . lang('Mailer.stComplained') . '</span>',
    'skipped'    => '<span class="badge badge-secondary">' . lang('Mailer.stSkipped') . '</span>',
];
$e = $engagement;
?>
<div class="mb-4 flex items-center justify-between no-print">
    <div class="flex items-center gap-2">
        <a href="<?= base_url('mailer/campanas/' . $campaign['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Mailer.back') ?></a>
        <h5 class="font-semibold"><?= lang('Mailer.sendReport') ?></h5>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= base_url('mailer/campanas/' . $campaign['id'] . '/reporte/csv') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-download"></i> <?= lang('Mailer.downloadCsv') ?></a>
        <button type="button" onclick="window.print()" class="btn btn-primary btn-sm"><i class="icon icon-printer"></i> <?= lang('Mailer.printPdf') ?></button>
    </div>
</div>

<div id="printReport">
    <div class="card mb-4">
        <div class="card-body">
            <div class="flex items-start justify-between">
                <div>
                    <h4 class="text-lg font-bold"><?= esc($campaign['name']) ?></h4>
                    <p class="text-sm text-muted"><?= esc($campaign['subject']) ?></p>
                    <p class="mt-1 text-xs text-muted"><?= lang('Mailer.listIs', [esc($list['name'] ?? '—')]) ?></p>
                </div>
                <div class="text-right text-xs text-muted">
                    <div><?= lang('Mailer.statusIs') ?> <strong><?= esc($campaign['status']) ?></strong></div>
                    <?php if ($campaign['started_at']): ?><div><?= lang('Mailer.startedAt') ?> <?= esc(substr((string) $campaign['started_at'], 0, 16)) ?></div><?php endif; ?>
                    <?php if ($campaign['finished_at']): ?><div><?= lang('Mailer.finishedAt') ?> <?= esc(substr((string) $campaign['finished_at'], 0, 16)) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="icon icon-bar-chart"></i><h6 class="font-semibold"><?= lang('Mailer.sendSummary') ?></h6></div>
        <div class="card-body">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
                <div class="rounded border border-app p-3 text-center"><div class="text-xl font-bold"><?= (int) $campaign['total'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.inQueue') ?></div></div>
                <div class="rounded border border-app p-3 text-center"><div class="text-xl font-bold text-success"><?= (int) $counts['sent'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.sent') ?></div></div>
                <div class="rounded border border-app p-3 text-center"><div class="text-xl font-bold text-danger"><?= (int) $counts['failed'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.failedPlural') ?></div></div>
                <div class="rounded border border-app p-3 text-center"><div class="text-xl font-bold text-warning"><?= (int) $counts['bounced'] + (int) $counts['complained'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.bouncesComplaints') ?></div></div>
                <div class="rounded border border-app p-3 text-center"><div class="text-xl font-bold"><?= (int) $counts['skipped'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.skipped') ?></div></div>
                <div class="rounded border border-app p-3 text-center"><div class="text-xl font-bold"><?= (int) $counts['pending'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.pending') ?></div></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="icon icon-eye"></i><h6 class="font-semibold"><?= lang('Mailer.engagement') ?></h6></div>
        <div class="card-body">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded border border-app p-3 text-center"><div class="text-2xl font-bold text-primary"><?= (float) $e['open_rate'] ?>%</div><div class="text-xs text-muted"><?= lang('Mailer.opens', [(int) $e['opens_unique']]) ?> <?= lang('Mailer.ofTotal', [(int) $e['opens_total']]) ?></div></div>
                <div class="rounded border border-app p-3 text-center"><div class="text-2xl font-bold text-success"><?= (float) $e['click_rate'] ?>%</div><div class="text-xs text-muted"><?= lang('Mailer.clicks', [(int) $e['clicks_unique']]) ?> <?= lang('Mailer.ofTotal', [(int) $e['clicks_total']]) ?></div></div>
                <div class="rounded border border-app p-3 text-center"><div class="text-2xl font-bold"><?= (float) $e['ctr'] ?>%</div><div class="text-xs text-muted"><?= lang('Mailer.ctr') ?></div></div>
                <div class="rounded border border-app p-3 text-center"><div class="text-2xl font-bold"><?= (int) $e['sent'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.sendBase') ?></div></div>
            </div>

            <?php if (!empty($topLinks)): ?>
                <h6 class="mt-4 mb-2 text-xs font-semibold uppercase tracking-wide text-muted"><?= lang('Mailer.topLinks') ?></h6>
                <div class="overflow-x-auto">
                    <table class="table text-sm">
                        <thead><tr><th><?= lang('Mailer.link') ?></th><th class="text-right"><?= lang('Mailer.clicksCol') ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($topLinks as $l): ?>
                                <tr><td class="max-w-md truncate"><?= esc($l['url']) ?></td><td class="text-right font-semibold"><?= (int) $l['clicks'] ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-people"></i><h6 class="font-semibold"><?= lang('Mailer.perRecipient') ?></h6></div>
        <div class="card-body">
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('mailer/campanas/' . $campaign['id'] . '/reporte'),
                'searchPlaceholder' => lang('Mailer.searchContact'),
                'emptyText'         => lang('Mailer.noRecipients'),
                'columns'           => [
                    ['key' => 'email', 'label' => lang('Mailer.email'), 'sortable' => true,
                        'render' => fn ($r) => esc($r['email']) . ($r['name'] ? '<div class="text-xs text-muted">' . esc($r['name']) . '</div>' : '')],
                    ['key' => 'status', 'label' => lang('Mailer.status'), 'sortable' => true,
                        'render' => fn ($r) => ($statusBadge[$r['status']] ?? esc($r['status'])) . ($r['error'] ? '<div class="text-xs text-danger">' . esc($r['error']) . '</div>' : '')],
                    ['key' => 'sent_at', 'label' => lang('Mailer.sentAt'), 'sortable' => true,
                        'render' => fn ($r) => esc($r['sent_at'] ? substr((string) $r['sent_at'], 0, 16) : '—')],
                    ['key' => 'open_count', 'label' => lang('Mailer.opensCol'), 'sortable' => true, 'class' => 'text-center', 'tdclass' => 'text-center',
                        'render' => fn ($r) => (int) $r['open_count'] > 0 ? '<span class="badge badge-primary">' . (int) $r['open_count'] . '</span>' : '<span class="text-muted">0</span>'],
                    ['key' => 'click_count', 'label' => lang('Mailer.clicksCol'), 'sortable' => true, 'class' => 'text-center', 'tdclass' => 'text-center',
                        'render' => fn ($r) => (int) $r['click_count'] > 0 ? '<span class="badge badge-success">' . (int) $r['click_count'] . '</span>' : '<span class="text-muted">0</span>'],
                ],
            ]) ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
