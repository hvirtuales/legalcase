<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-box-seam"></i>
            <h5 class="font-semibold">Planes</h5>
        </div>
        <a href="<?= base_url('plans/create') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i> Nuevo Plan
        </a>
    </div>
    <div class="card-body">
        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('plans'),
            'searchPlaceholder' => 'Buscar plan…',
            'emptyText'         => 'No hay planes registrados.',
            'columns'           => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => 'Nombre', 'sortable' => true,
                    'render' => fn ($p) => '<strong>' . esc($p['name']) . '</strong>'],
                ['key' => 'price_amount', 'label' => 'Precio', 'sortable' => true,
                    'render' => fn ($p) => esc(number_format((float) $p['price_amount'], 2)) . ' ' . esc($p['price_currency'])],
                ['key' => 'service_days', 'label' => 'Días', 'sortable' => true],
                ['key' => 'max_companies', 'label' => 'Máx. empresas', 'sortable' => true,
                    'render' => fn ($p) => ((int) $p['max_companies'] === 0) ? 'Sin límite' : esc($p['max_companies'])],
                ['key' => 'is_active', 'label' => 'Estado', 'sortable' => true,
                    'render' => fn ($p) => !empty($p['is_active'])
                        ? '<span class="badge badge-success">Activo</span>'
                        : '<span class="badge badge-secondary">Inactivo</span>'],
            ],
            'actions'           => function ($p) {
                $html = '<div class="flex justify-end gap-1">';
                $html .= '<a href="' . base_url('plans/edit/' . $p['id']) . '" class="btn btn-secondary btn-sm" title="Editar"><i class="icon icon-pencil"></i></a>';
                $html .= view('partials/delete_button', ['action' => base_url('plans/delete/' . $p['id']), 'confirm' => '¿Eliminar este plan?']);

                return $html . '</div>';
            },
        ]) ?>
    </div>
</div>

<?= $this->endSection() ?>
