<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-diagram-3"></i>
            <h5 class="font-semibold"><?= lang('Tenant.tenants') ?></h5>
        </div>
        <a href="<?= base_url('tenants/crear') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i> <?= lang('Tenant.newTenant') ?>
        </a>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Tenant.tenantsIntro') ?></p>

        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('tenants'),
            'searchPlaceholder' => lang('Tenant.searchTenants'),
            'emptyText'         => lang('Tenant.noTenants'),
            'columns'           => [
                ['key' => 'name', 'label' => lang('Tenant.tenant'), 'sortable' => true, 'sortKey' => 'companies.name',
                    'render' => fn ($t) => '<strong>' . esc($t['name']) . '</strong>'
                        . (empty($t['nit']) ? '' : '<div class="text-xs text-muted">' . esc($t['nit']) . '</div>')],
                ['key' => 'plan_name', 'label' => lang('Tenant.plan'),
                    'render' => fn ($t) => esc($t['plan_name'] ?? lang('Tenant.noPlan'))],
                ['key' => '_quota', 'label' => lang('Tenant.companiesCount'),
                    'render' => function ($t) use ($counts) {
                        $c    = $counts[(int) $t['id']] ?? ['used' => 0, 'left' => null];
                        $used = (int) $c['used'];
                        if ($c['left'] === null) {
                            return esc($used) . ' <span class="text-xs text-muted">· ' . lang('Tenant.unlimited') . '</span>';
                        }
                        $max   = $used + (int) $c['left'];
                        $badge = $c['left'] <= 0 ? 'badge-danger' : 'badge-secondary';

                        return esc(lang('Tenant.quotaUsed', [$used, $max]))
                            . ' <span class="badge ' . $badge . '">' . esc(lang('Tenant.quotaLeft', [(int) $c['left']])) . '</span>';
                    }],
                ['key' => '_sub', 'label' => lang('Tenant.subscription'),
                    'render' => function ($t) use ($subMap, $subModel) {
                        $sub = $subMap[(int) $t['id']] ?? null;
                        if ($sub && $subModel->isValid($sub)) {
                            return '<span class="badge badge-success">' . lang('Tenant.valid') . '</span>';
                        }

                        return '<span class="badge badge-danger">' . lang('Tenant.expired') . '</span>';
                    }],
                ['key' => 'owner_name', 'label' => lang('Tenant.admin'),
                    'render' => fn ($t) => esc(trim((string) ($t['owner_name'] ?? '')) ?: '—')],
            ],
            'actions'           => function ($t) use ($counts) {
                $html = '<div class="flex justify-end gap-1">';
                $html .= '<a href="' . base_url('tenants/empresas/' . $t['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Tenant.manageCompanies'), 'attr') . '"><i class="icon icon-buildings"></i></a>';
                $html .= '<a href="' . base_url('tenants/editar/' . $t['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('App.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                $html .= view('partials/delete_button', [
                    'action'  => base_url('tenants/eliminar/' . $t['id']),
                    'confirm' => lang('Tenant.confirmDelete', [(int) ($counts[(int) $t['id']]['used'] ?? 0)]),
                ]);

                return $html . '</div>';
            },
        ]) ?>
    </div>
</div>

<?= $this->endSection() ?>
