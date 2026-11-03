<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-landmark"></i><h5 class="font-semibold">Cuentas bancarias</h5></div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('contabilidad/cuentas-bancarias/nueva') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> Nueva</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span>Selecciona una <strong>empresa activa</strong> para gestionar sus cuentas bancarias.</span></div>
        <?php else: ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('contabilidad/cuentas-bancarias'),
                'searchPlaceholder' => 'Buscar por banco, número o titular…',
                'emptyText'         => 'No hay cuentas bancarias. Crea la primera.',
                'columns'           => [
                    ['key' => 'bank_name', 'label' => 'Banco', 'sortable' => true,
                        'render' => fn ($r) => esc($r['bank_name']) . '<div class="text-xs text-muted">' . esc($r['account_type']) . '</div>'],
                    ['key' => 'account_number', 'label' => 'Número', 'sortable' => true, 'render' => fn ($r) => '<span class="font-mono">' . esc($r['account_number'] ?: '—') . '</span>'],
                    ['key' => 'account_holder', 'label' => 'Titular', 'sortable' => false, 'render' => fn ($r) => esc($r['account_holder'] ?: '—')],
                    ['key' => 'initial_balance', 'label' => 'Saldo inicial', 'sortable' => true, 'class' => 'text-right', 'tdclass' => 'text-right',
                        'render' => fn ($r) => '<span class="font-mono">' . number_format((float) $r['initial_balance'], 2) . '</span>'],
                    ['key' => 'is_active', 'label' => 'Estado', 'sortable' => true,
                        'render' => fn ($r) => $r['is_active'] ? '<span class="badge badge-success">Activa</span>' : '<span class="badge badge-secondary">Inactiva</span>'],
                ],
                'actions'           => function ($r) {
                    $h = '<div class="flex justify-end gap-1">';
                    $h .= '<a href="' . base_url('contabilidad/cuentas-bancarias/editar/' . $r['id']) . '" class="btn btn-secondary btn-sm" title="Editar"><i class="icon icon-pencil"></i></a>';
                    $h .= view('partials/delete_button', ['action' => base_url('contabilidad/cuentas-bancarias/eliminar/' . $r['id']), 'confirm' => '¿Eliminar esta cuenta?']);

                    return $h . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
