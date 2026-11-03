<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$catBadge = [
    'iva'       => '<span class="badge badge-info">IVA</span>',
    'retencion' => '<span class="badge badge-warning">Retención</span>',
    'other'     => '<span class="badge badge-secondary">Otro</span>',
];
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-percent"></i>
            <h5 class="font-semibold">Impuestos</h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex items-center gap-1">
                <form action="<?= base_url('contabilidad/impuestos/seed') ?>" method="post" class="inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary btn-sm" title="Cargar los 14 impuestos colombianos por defecto"><i class="icon icon-arrow-repeat"></i> Restaurar por defecto</button>
                </form>
                <a href="<?= base_url('contabilidad/impuestos/nuevo') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> Nuevo</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i>
                <span>Selecciona una <strong>empresa activa</strong> para configurar sus impuestos.</span></div>
        <?php else: ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('contabilidad/impuestos'),
                'searchPlaceholder' => 'Buscar impuesto…',
                'emptyText'         => 'No hay impuestos. Usa «Restaurar por defecto» para cargar los estándar.',
                'columns'           => [
                    ['key' => 'code', 'label' => 'Código', 'sortable' => true, 'render' => fn ($t) => '<span class="font-mono">' . esc($t['code']) . '</span>'],
                    ['key' => 'name', 'label' => 'Nombre', 'sortable' => true],
                    ['key' => 'category', 'label' => 'Categoría', 'sortable' => true, 'render' => fn ($t) => $catBadge[$t['category']] ?? esc($t['category'])],
                    ['key' => 'rate', 'label' => 'Tasa %', 'sortable' => true, 'class' => 'text-right', 'tdclass' => 'text-right font-mono', 'render' => fn ($t) => rtrim(rtrim(number_format((float) $t['rate'], 4), '0'), '.')],
                    ['key' => 'is_active', 'label' => 'Activo', 'sortable' => true, 'render' => fn ($t) => !empty($t['is_active']) ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-secondary">No</span>'],
                ],
                'actions'           => function ($t) {
                    $h = '<div class="flex justify-end gap-1">';
                    $h .= '<a href="' . base_url('contabilidad/impuestos/editar/' . $t['id']) . '" class="btn btn-secondary btn-sm" title="Editar"><i class="icon icon-pencil"></i></a>';
                    $h .= view('partials/delete_button', ['action' => base_url('contabilidad/impuestos/eliminar/' . $t['id']), 'confirm' => '¿Eliminar este impuesto?']);

                    return $h . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
