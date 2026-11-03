<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Usuarios de las empresas del grupo.
 *
 * Mismo listado que `/users` (`DataTable` + `partials/datatable`), acotado a
 * los miembros de las empresas del grupo. Un usuario que esté en dos de ellas
 * sale una vez por empresa: aquí lo que importa es dónde está.
 *
 * Los roles que se pueden repartir salen de `TenantGuard::assignableRoles()`
 * —de «Admin» hacia abajo—. A quien ya tenga un rol que el administrador del
 * grupo no puede otorgar se le muestra, pero no se le ofrece cambiarlo.
 *
 * @var array $tenant @var array $dt @var array $children @var array $rolesMap @var array $roles
 */
$assignableIds = array_map(static fn ($r) => (int) $r['id'], $roles);

/** ¿Puedo tocarle el rol? Solo si todos los que tiene son míos. */
$manageable = static function (int $userId) use ($rolesMap, $assignableIds): bool {
    foreach ($rolesMap[$userId] ?? [] as $r) {
        if (!in_array((int) $r['id'], $assignableIds, true)) {
            return false;
        }
    }

    return true;
};
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-people"></i>
            <h5 class="font-semibold"><?= lang('Tenant.groupUsers') ?></h5>
            <span class="badge badge-light"><i class="icon icon-diagram-3"></i> <?= esc($tenant['name']) ?></span>
        </div>
        <?php if ($children): ?>
            <a href="<?= base_url('tenant/usuarios/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Tenant.newUser') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$children): ?>
            <p class="text-sm text-muted"><?= lang('Tenant.noCompanies') ?></p>
        <?php else: ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('tenant/usuarios'),
                'searchPlaceholder' => lang('Tenant.searchUsers'),
                'emptyText'         => lang('Tenant.noMembers'),
                'columns'           => [
                    ['key' => 'username', 'label' => lang('Tenant.username'), 'sortable' => true, 'sortKey' => 'users.username',
                        'render' => fn ($u) => '<strong>' . esc($u['username']) . '</strong>'],
                    ['key' => 'first_name', 'label' => lang('Tenant.firstName'), 'sortable' => true, 'sortKey' => 'users.first_name',
                        'render' => fn ($u) => esc(trim($u['first_name'] . ' ' . $u['last_name']))],
                    ['key' => 'email', 'label' => lang('Tenant.email'), 'sortable' => true, 'sortKey' => 'users.email'],
                    ['key' => 'company_name', 'label' => lang('Tenant.company'),
                        'render' => fn ($u) => esc($u['company_name'] ?? '—')],
                    ['key' => '_member', 'label' => lang('Tenant.inCompany'),
                        'render' => fn ($u) => !empty($u['is_admin'])
                            ? '<span class="badge badge-primary">' . lang('Tenant.badgeAdmin') . '</span>'
                            : '<span class="badge badge-secondary">' . lang('Tenant.badgeMember') . '</span>'],
                    ['key' => '_role', 'label' => lang('Tenant.role'),
                        'render' => function ($u) use ($rolesMap, $manageable) {
                            $names = array_map(static fn ($r) => esc($r['name']), $rolesMap[(int) $u['id']] ?? []);
                            if (!$names) {
                                return '<span class="text-muted">—</span>';
                            }

                            return implode(', ', $names)
                                . ($manageable((int) $u['id']) ? '' : ' <span class="badge badge-light">' . lang('Tenant.platformRole') . '</span>');
                        }],
                    ['key' => 'is_active', 'label' => lang('App.status'), 'sortable' => true, 'sortKey' => 'users.is_active',
                        'render' => fn ($u) => !empty($u['is_active'])
                            ? '<span class="badge badge-success">' . lang('App.active') . '</span>'
                            : '<span class="badge badge-danger">' . lang('App.inactive') . '</span>'],
                ],
                'actions'           => function ($u) use ($manageable) {
                    $html = '<div class="flex justify-end gap-1">';

                    if ($manageable((int) $u['id'])) {
                        $html .= '<a href="' . base_url('tenant/usuarios/' . (int) $u['id'] . '/rol') . '" class="btn btn-secondary btn-sm" title="'
                            . esc(lang('Tenant.assignRole'), 'attr') . '"><i class="icon icon-person-badge"></i></a>';
                    }

                    // Al dueño de la empresa no se le quita la membresía.
                    if ((int) ($u['owner_id'] ?? 0) !== (int) $u['id']) {
                        $html .= view('partials/delete_button', [
                            'action'  => base_url('tenant/usuarios/' . (int) $u['company_id'] . '/quitar/' . (int) $u['id']),
                            'confirm' => lang('Tenant.confirmRemoveUser'),
                            'icon'    => 'icon-x-lg',
                            'label'   => lang('Tenant.removeUser'),
                        ]);
                    }

                    return $html . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
