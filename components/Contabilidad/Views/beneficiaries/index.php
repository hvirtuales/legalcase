<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-people"></i>
            <h5 class="font-semibold">Beneficiarios / Terceros</h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('contabilidad/beneficiarios/importar') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-upload"></i> Importar CSV
                </a>
                <a href="<?= base_url('contabilidad/beneficiarios/crear') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> Nuevo beneficiario
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i>
                <span>Selecciona una <strong>empresa activa</strong> en el encabezado para gestionar sus beneficiarios. Los terceros se aíslan por empresa.</span>
            </div>
        <?php else: ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('contabilidad/beneficiarios'),
                'searchPlaceholder' => 'Buscar por nombre, documento, correo…',
                'emptyText'         => 'No hay beneficiarios registrados en esta empresa.',
                'columns'           => [
                    ['key' => 'name', 'label' => 'Nombre / Razón social', 'sortable' => true,
                        'render' => fn ($b) => '<strong>' . esc($b['name']) . '</strong>'
                            . ($b['contact_name'] ? '<div class="text-xs text-muted">' . esc($b['contact_name']) . '</div>' : '')],
                    ['key' => 'id_number', 'label' => 'Documento', 'sortable' => true,
                        'render' => fn ($b) => esc($b['id_type']) . ' ' . esc($b['id_number'])],
                    ['key' => 'email', 'label' => 'Correo', 'sortable' => false,
                        'render' => fn ($b) => esc($b['email'] ?: '—')],
                    ['key' => 'phone', 'label' => 'Teléfono', 'sortable' => false,
                        'render' => fn ($b) => esc($b['phone'] ?: '—')],
                    ['key' => 'city', 'label' => 'Ciudad', 'sortable' => true,
                        'render' => fn ($b) => esc($b['city'] ?: '—')],
                ],
                'actions'           => function ($b) {
                    $html = '<div class="flex justify-end gap-1">';
                    $html .= '<a href="' . base_url('contabilidad/beneficiarios/editar/' . $b['id']) . '" class="btn btn-secondary btn-sm" title="Editar"><i class="icon icon-pencil"></i></a>';
                    $html .= view('partials/delete_button', ['action' => base_url('contabilidad/beneficiarios/eliminar/' . $b['id']), 'confirm' => '¿Eliminar este beneficiario?']);

                    return $html . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
