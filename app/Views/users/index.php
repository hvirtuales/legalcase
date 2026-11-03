<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$rolesMap = $rolesMap ?? [];
$adminMap = $adminMap ?? [];
$scoped   = $scoped ?? false;

$columns = [
    ['key' => 'id', 'label' => 'ID', 'sortable' => true],
    ['key' => 'username', 'label' => lang('App.username'), 'sortable' => true,
        'render' => fn ($u) => '<strong>' . esc($u['username']) . '</strong>'],
    ['key' => 'first_name', 'label' => lang('App.firstName'), 'sortable' => true,
        'render' => fn ($u) => esc(trim($u['first_name'] . ' ' . $u['last_name']))],
    ['key' => 'email', 'label' => lang('App.email'), 'sortable' => true],
    ['key' => 'roles', 'label' => 'Rol', 'sortable' => false,
        'render' => fn ($u) => !empty($rolesMap[$u['id']])
            ? esc(implode(', ', $rolesMap[$u['id']]))
            : '<span class="text-muted">—</span>'],
];

// Estado dentro de la empresa (solo en la vista acotada por empresa)
if ($scoped) {
    $columns[] = ['key' => 'company_role', 'label' => 'En la empresa', 'sortable' => false,
        'render' => static function ($u) use ($adminMap) {
            if (!array_key_exists($u['id'], $adminMap)) {
                return '<span class="text-muted">—</span>';
            }

            return $adminMap[$u['id']]
                ? '<span class="badge badge-primary">Admin</span>'
                : '<span class="badge badge-secondary">Miembro</span>';
        }];
}

$columns[] = ['key' => 'is_active', 'label' => lang('App.status'), 'sortable' => true,
    'render' => fn ($u) => !empty($u['is_active'])
        ? '<span class="badge badge-success">' . lang('App.active') . '</span>'
        : '<span class="badge badge-danger">' . lang('App.inactive') . '</span>'];
$columns[] = ['key' => 'last_login_at', 'label' => lang('App.lastAccess'), 'sortable' => true,
    'render' => fn ($u) => !empty($u['last_login_at'])
        ? date('d/m/Y H:i', strtotime($u['last_login_at']))
        : '<span class="text-muted">' . lang('App.never') . '</span>'];
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-people"></i>
            <h5 class="font-semibold"><?= lang('App.userList') ?></h5>
            <?php if ($scoped && !empty($companyName)): ?>
                <span class="badge badge-light"><i class="icon icon-buildings"></i> <?= esc($companyName) ?></span>
            <?php endif; ?>
        </div>
        <a href="<?= base_url('users/create') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i>
            <?= $scoped ? 'Registrar miembro' : lang('App.newUser') ?>
        </a>
    </div>
    <div class="card-body">
        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('users'),
            'searchPlaceholder' => 'Buscar por usuario, email o nombre…',
            'emptyText'         => $scoped ? 'Esta empresa no tiene miembros aún.' : lang('App.noUsersRegistered'),
            'columns'           => $columns,
            'actions'           => function ($u) use ($scoped) {
                $html = '<div class="flex justify-end gap-1">';
                $html .= '<a href="' . base_url('users/edit/' . $u['id']) . '" class="btn btn-secondary btn-sm" title="' . lang('App.edit') . '"><i class="icon icon-pencil"></i></a>';
                $html .= '<a href="' . base_url('users/assign-role/' . $u['id']) . '" class="btn btn-secondary btn-sm" title="' . lang('App.assignRoles') . '"><i class="icon icon-person-badge"></i></a>';
                if ($u['id'] != session()->get('user_id')) {
                    $html .= view('partials/delete_button', [
                        'action'  => base_url('users/delete/' . $u['id']),
                        'confirm' => $scoped ? '¿Quitar a este usuario de la empresa?' : lang('App.confirmDelete'),
                        'label'   => $scoped ? 'Quitar' : lang('App.delete'),
                    ]);
                }

                return $html . '</div>';
            },
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
