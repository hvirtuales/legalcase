<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$reasonBadge = [
    'bounce'      => '<span class="badge badge-warning">' . lang('Mailer.stBounced') . '</span>',
    'complaint'   => '<span class="badge badge-danger">' . lang('Mailer.stComplained') . '</span>',
    'unsubscribe' => '<span class="badge badge-secondary">' . lang('Mailer.stUnsubscribed') . '</span>',
    'manual'      => '<span class="badge badge-secondary">' . lang('Mailer.stManual') . '</span>',
];
?>
<?php if ($dt !== null): ?>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 mb-4">
        <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold text-warning"><?= (int) $stats['bounce'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.bounces') ?></div></div></div>
        <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold text-danger"><?= (int) $stats['complaint'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.complaints') ?></div></div></div>
        <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold"><?= (int) $stats['unsubscribe'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.unsubscribed') ?></div></div></div>
        <div class="card"><div class="card-body text-center"><div class="text-2xl font-bold"><?= (int) $stats['manual'] ?></div><div class="text-xs text-muted"><?= lang('Mailer.manuals') ?></div></div></div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-shield"></i><h5 class="font-semibold"><?= lang('Mailer.suppTitle') ?></h5></div>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Mailer.noCompany') ?></span></div>
        <?php else: ?>
            <div class="alert alert-info text-sm"><i class="icon icon-info-circle"></i>
                <span><?= lang('Mailer.suppNote') ?></span></div>

            <form method="post" action="<?= base_url('mailer/supresiones/agregar') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <?= csrf_field() ?>
                <div class="form-group mb-0 flex-1 min-w-[220px]">
                    <label class="form-label" for="email"><?= lang('Mailer.suppAdd') ?></label>
                    <input type="email" name="email" id="email" class="form-input" required placeholder="<?= esc(lang('Mailer.mailPh'), 'attr') ?>">
                </div>
                <div class="form-group mb-0 flex-1 min-w-[200px]">
                    <label class="form-label" for="notes"><?= lang('Mailer.suppNote2') ?></label>
                    <input type="text" name="notes" id="notes" class="form-input">
                </div>
                <button type="submit" class="btn btn-primary"><i class="icon icon-plus-circle"></i> <?= lang('Mailer.suppress') ?></button>
            </form>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('mailer/supresiones'),
                'searchPlaceholder' => lang('Mailer.searchEmail'),
                'emptyText'         => lang('Mailer.noSuppressed'),
                'columns'           => [
                    ['key' => 'email', 'label' => lang('Mailer.email'), 'sortable' => true],
                    ['key' => 'reason', 'label' => lang('Mailer.reason'), 'sortable' => true, 'render' => fn ($s) => $reasonBadge[$s['reason']] ?? esc($s['reason'])],
                    ['key' => 'notes', 'label' => lang('Mailer.note'), 'sortable' => false, 'render' => fn ($s) => esc($s['notes'] ?: '—')],
                    ['key' => 'created_at', 'label' => lang('Mailer.date'), 'sortable' => true, 'render' => fn ($s) => esc(substr((string) $s['created_at'], 0, 10))],
                ],
                'actions'           => fn ($s) => '<div class="flex justify-end">' . view('partials/delete_button', [
                    'action' => base_url('mailer/supresiones/eliminar/' . $s['id']),
                    'confirm' => lang('Mailer.reactivateConfirm'),
                    'icon' => 'arrow-repeat', 'label' => lang('Mailer.reactivate'), 'text' => lang('Mailer.reactivate'), 'class' => 'btn btn-secondary btn-sm',
                ]) . '</div>',
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
