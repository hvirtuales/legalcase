<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-diagram-3"></i>
            <h5 class="font-semibold">Plan Único de Cuentas <span class="badge badge-secondary">Global</span></h5>
        </div>
        <div class="flex items-center gap-1">
            <form action="<?= base_url('contabilidad/puc/seed') ?>" method="post" class="inline">
                <?= csrf_field() ?>
                <button class="btn btn-secondary btn-sm" title="Cargar el PUC de arranque"><i class="icon icon-arrow-repeat"></i> Sembrar PUC estándar</button>
            </form>
            <a href="<?= base_url('contabilidad/puc/importar') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-upload"></i> Importar CSV</a>
            <a href="<?= base_url('contabilidad/puc/nueva') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> Nueva cuenta</a>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3 text-sm"><i class="icon icon-info-circle"></i>
            <span>El PUC es <strong>compartido por todas las empresas</strong>. Los cambios afectan a toda la plataforma.</span></div>
        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('contabilidad/puc'),
            'searchPlaceholder' => 'Buscar por código o nombre…',
            'emptyText'         => 'No hay cuentas. Usa «Sembrar PUC estándar» para cargar el catálogo de arranque.',
            'columns'           => [
                ['key' => 'code', 'label' => 'Código', 'sortable' => true, 'render' => fn ($a) => '<span class="font-mono font-semibold" style="padding-left:' . (((int) $a['level'] - 1) * 12) . 'px">' . esc($a['code']) . '</span>'],
                ['key' => 'name', 'label' => 'Nombre', 'sortable' => true],
                ['key' => 'type', 'label' => 'Tipo', 'sortable' => true, 'render' => fn ($a) => '<span class="capitalize">' . esc($a['type']) . '</span>'],
                ['key' => 'nature', 'label' => 'Naturaleza', 'sortable' => false, 'render' => fn ($a) => '<span class="capitalize text-muted">' . esc($a['nature']) . '</span>'],
                ['key' => 'level', 'label' => 'Nivel', 'sortable' => true, 'class' => 'text-center', 'tdclass' => 'text-center'],
                ['key' => 'is_active', 'label' => 'Activo', 'sortable' => true, 'render' => fn ($a) => !empty($a['is_active']) ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-secondary">No</span>'],
            ],
            'actions'           => function ($a) {
                $h = '<div class="flex justify-end gap-1">';
                $h .= '<a href="' . base_url('contabilidad/puc/editar/' . $a['id']) . '" class="btn btn-secondary btn-sm" title="Editar"><i class="icon icon-pencil"></i></a>';
                $h .= view('partials/delete_button', ['action' => base_url('contabilidad/puc/eliminar/' . $a['id']), 'confirm' => '¿Eliminar esta cuenta?']);

                return $h . '</div>';
            },
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
