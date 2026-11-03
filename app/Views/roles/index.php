<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-person-badge"></i>
            <h5 class="font-semibold"><?= lang('App.roleList') ?></h5>
        </div>
        <a href="<?= base_url('roles/create') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i>
            <?= lang('App.newRole') ?>
        </a>
    </div>
    <div class="card-body">
        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('roles'),
            'searchPlaceholder' => 'Buscar rol…',
            'emptyText'         => lang('App.noRolesRegistered'),
            'columns'           => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => lang('App.name'), 'sortable' => true,
                    'render' => fn ($r) => '<strong>' . esc($r['name']) . '</strong>'],
                ['key' => 'description', 'label' => lang('App.description'),
                    'render' => fn ($r) => esc($r['description'] ?? '-')],
                ['key' => 'created_at', 'label' => lang('App.created'), 'sortable' => true,
                    'render' => fn ($r) => !empty($r['created_at']) ? date('d/m/Y', strtotime($r['created_at'])) : '-'],
            ],
            'actions'           => function ($r) {
                $html = '<div class="flex justify-end gap-1">';
                $html .= '<a href="' . base_url('roles/permissions/' . $r['id']) . '" class="btn btn-secondary btn-sm" title="' . lang('App.managePermissions') . '"><i class="icon icon-key"></i></a>';
                $html .= '<a href="' . base_url('roles/edit/' . $r['id']) . '" class="btn btn-secondary btn-sm" title="' . lang('App.edit') . '"><i class="icon icon-pencil"></i></a>';
                $html .= view('partials/delete_button', [
                    'action'  => base_url('roles/delete/' . $r['id']),
                    'confirm' => lang('App.confirmDeleteRole'),
                    'label'   => lang('App.delete'),
                ]);

                return $html . '</div>';
            },
        ]) ?>
    </div>
</div>

<?= $this->endSection() ?>
