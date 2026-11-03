<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-clipboard"></i><h5 class="font-semibold"><?= lang('Mailer.templates') ?></h5></div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('mailer/plantillas/crear') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Mailer.newTemplate') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Mailer.pickCompanyTpl') ?></span></div>
        <?php else: ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('mailer/plantillas'),
                'searchPlaceholder' => lang('Mailer.searchTemplate'),
                'emptyText'         => lang('Mailer.noTemplates'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Mailer.name'), 'sortable' => true, 'render' => fn ($t) => '<strong>' . esc($t['name']) . '</strong>'],
                    ['key' => 'subject', 'label' => lang('Mailer.subject'), 'sortable' => false, 'render' => fn ($t) => esc($t['subject'] ?: '—')],
                    ['key' => 'created_at', 'label' => lang('Mailer.createdF'), 'sortable' => true, 'render' => fn ($t) => esc(substr((string) $t['created_at'], 0, 10))],
                ],
                'actions'           => function ($t) {
                    $h = '<div class="flex justify-end gap-1">';
                    $h .= '<a href="' . base_url('mailer/plantillas/' . $t['id'] . '/preview') . '" target="_blank" class="btn btn-secondary btn-sm" title="' . esc(lang('Mailer.preview'), 'attr') . '"><i class="icon icon-eye"></i></a>';
                    $h .= '<a href="' . base_url('mailer/plantillas/editar/' . $t['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Mailer.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                    $h .= view('partials/delete_button', ['action' => base_url('mailer/plantillas/eliminar/' . $t['id']), 'confirm' => lang('Mailer.delTemplate')]);

                    return $h . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
