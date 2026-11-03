<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-buildings"></i>
            <h5 class="font-semibold">Empresas</h5>
        </div>
        <a href="<?= base_url('companies/create') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i> Nueva Empresa
        </a>
    </div>
    <div class="card-body">
        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('companies'),
            'searchPlaceholder' => 'Buscar por nombre, NIT o email…',
            'emptyText'         => 'No hay empresas registradas.',
            'columns'           => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'sortKey' => 'companies.id'],
                ['key' => 'name', 'label' => 'Empresa', 'sortable' => true, 'sortKey' => 'companies.name',
                    'render' => fn ($c) => '<strong>' . esc($c['name']) . '</strong>'],
                ['key' => 'nit', 'label' => 'NIT', 'sortable' => true, 'sortKey' => 'companies.nit',
                    'render' => fn ($c) => esc($c['nit'] ?? '-')],
                ['key' => 'plan_name', 'label' => 'Plan',
                    'render' => fn ($c) => esc($c['plan_name'] ?? '—')],
                ['key' => '_sub', 'label' => 'Suscripción',
                    'render' => function ($c) use ($subMap, $subModel) {
                        $sub = $subMap[(int) $c['id']] ?? null;
                        if ($sub && $subModel->isValid($sub)) {
                            return '<span class="badge badge-success">Vigente</span>';
                        }
                        return $sub ? '<span class="badge badge-danger">Vencida</span>' : '<span class="badge badge-secondary">Sin suscripción</span>';
                    }],
                ['key' => 'owner_name', 'label' => 'Administrador',
                    'render' => fn ($c) => esc(trim((string) ($c['owner_name'] ?? '')) ?: '—')],
                ['key' => 'is_active', 'label' => 'Estado', 'sortable' => true, 'sortKey' => 'companies.is_active',
                    'render' => fn ($c) => !empty($c['is_active'])
                        ? '<span class="badge badge-success">Activa</span>'
                        : '<span class="badge badge-secondary">Inactiva</span>'],
            ],
            'actions'           => function ($c) {
                $html = '<div class="flex justify-end gap-1">';
                $html .= '<a href="' . base_url('companies/members/' . $c['id']) . '" class="btn btn-secondary btn-sm" title="Miembros"><i class="icon icon-people"></i></a>';
                $html .= '<a href="' . base_url('companies/edit/' . $c['id']) . '" class="btn btn-secondary btn-sm" title="Editar"><i class="icon icon-pencil"></i></a>';
                $html .= view('partials/delete_button', ['action' => base_url('companies/delete/' . $c['id']), 'confirm' => '¿Eliminar esta empresa?']);

                return $html . '</div>';
            },
        ]) ?>
    </div>
</div>

<?= $this->endSection() ?>
