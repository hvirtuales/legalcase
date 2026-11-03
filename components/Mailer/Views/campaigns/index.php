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
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-send"></i><h5 class="font-semibold"><?= lang('Mailer.campaigns') ?></h5></div>
        <?php if ($dt !== null && ($serviceEnabled ?? true)): ?>
            <a href="<?= base_url('mailer/campanas/crear') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Mailer.newCampaign') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Mailer.pickCompany') ?></span></div>
        <?php else: ?>
            <?php if (!($serviceEnabled ?? true)): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Mailer.planOff') ?></span></div>
            <?php elseif (($quota ?? null) !== null): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Mailer.quotaLeft', [(int) $quota]) ?></span></div>
            <?php endif; ?>
            <?php if (($reputation['level'] ?? 'ok') === 'block'): ?>
                <div class="alert alert-danger"><i class="icon icon-x-circle"></i><span><strong><?= lang('Mailer.repBlocked') ?></strong> <?= esc($reputation['reason']) ?> <?= lang('Mailer.repClean') ?> <?= lang('Mailer.repRates', [(float) $reputation['bounce_rate'], (float) $reputation['complaint_rate']]) ?></span></div>
            <?php elseif (($reputation['level'] ?? 'ok') === 'warn'): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><strong><?= lang('Mailer.repWatch') ?></strong> <?= esc($reputation['reason']) ?> <?= lang('Mailer.repRates', [(float) $reputation['bounce_rate'], (float) $reputation['complaint_rate']]) ?></span></div>
            <?php endif; ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('mailer/campanas'),
                'searchPlaceholder' => lang('Mailer.searchCampaign'),
                'emptyText'         => lang('Mailer.noCampaigns'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Mailer.campaign'), 'sortable' => true,
                        'render' => fn ($c) => '<a href="' . base_url('mailer/campanas/' . $c['id']) . '" class="text-primary font-semibold">' . esc($c['name']) . '</a>'
                            . '<div class="text-xs text-muted">' . esc($c['subject']) . '</div>'],
                    ['key' => 'status', 'label' => lang('Mailer.status'), 'sortable' => true, 'render' => fn ($c) => $statusBadge[$c['status']] ?? esc($c['status'])],
                    ['key' => 'sent_count', 'label' => lang('Mailer.sent'), 'sortable' => true,
                        'render' => fn ($c) => (int) $c['sent_count'] . ' / ' . (int) $c['total']
                            . ((int) $c['failed_count'] > 0 ? ' <span class="text-danger text-xs">(' . (int) $c['failed_count'] . ' ' . lang('Mailer.stFailed') . ')</span>' : '')],
                    ['key' => 'created_at', 'label' => lang('Mailer.createdF'), 'sortable' => true, 'render' => fn ($c) => esc(substr((string) $c['created_at'], 0, 10))],
                ],
                'actions'           => function ($c) {
                    $h = '<div class="flex justify-end gap-1">';
                    $h .= '<a href="' . base_url('mailer/campanas/' . $c['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Mailer.view'), 'attr') . '"><i class="icon icon-eye"></i></a>';
                    $h .= view('partials/delete_button', ['action' => base_url('mailer/campanas/eliminar/' . $c['id']), 'confirm' => lang('Mailer.delCampaign')]);

                    return $h . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
