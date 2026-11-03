<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-people"></i>
            <h5 class="font-semibold"><?= lang('Mailer.lists') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('mailer/listas/crear') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Mailer.newList') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i>
                <span><?= lang('Mailer.pickCompanyLists') ?></span></div>
        <?php else: ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('mailer/listas'),
                'searchPlaceholder' => lang('Mailer.searchList'),
                'emptyText'         => lang('Mailer.noLists'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Mailer.name'), 'sortable' => true,
                        'render' => fn ($l) => '<a href="' . base_url('mailer/listas/' . $l['id']) . '" class="text-primary font-semibold">' . esc($l['name']) . '</a>'
                            . ($l['description'] ? '<div class="text-xs text-muted">' . esc($l['description']) . '</div>' : '')],
                    ['key' => 'contacts', 'label' => lang('Mailer.contacts'), 'sortable' => false,
                        'render' => fn ($l) => '<span class="badge badge-secondary">' . (int) ($counts[$l['id']] ?? 0) . '</span>'],
                    ['key' => 'created_at', 'label' => lang('Mailer.createdF'), 'sortable' => true,
                        'render' => fn ($l) => esc(substr((string) $l['created_at'], 0, 10))],
                ],
                'actions'           => function ($l) {
                    $h = '<div class="flex justify-end gap-1">';
                    $h .= '<a href="' . base_url('mailer/listas/' . $l['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Mailer.viewContacts'), 'attr') . '"><i class="icon icon-eye"></i></a>';
                    $h .= '<a href="' . base_url('mailer/listas/editar/' . $l['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Mailer.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                    $h .= view('partials/delete_button', ['action' => base_url('mailer/listas/eliminar/' . $l['id']), 'confirm' => lang('Mailer.delList')]);

                    return $h . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
