<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$statusBadge = static function (array $r): string {
    $s = $r['status'];
    if ($s === 'anulado') {
        return '<span class="badge badge-danger">Anulado</span>';
    }
    if ($s === 'pagado') {
        $label = (($r['type_name'] ?? '') === 'Ingreso') ? 'Recibido' : 'Pagado';

        return '<span class="badge badge-success">' . $label . '</span>';
    }

    return '<span class="badge badge-secondary">Emitido</span>';
};
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-receipt"></i>
            <h5 class="font-semibold">Comprobantes</h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted">Balance: <strong class="<?= $balance >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($balance, 2) ?></strong></span>
                <?php if (($quota ?? null) !== null): ?><span class="text-xs text-muted">Cupo: <strong><?= (int) $quota ?></strong></span><?php endif; ?>
                <?php if (($serviceEnabled ?? true) && (($quota ?? null) === null || $quota > 0)): ?>
                    <a href="<?= base_url('contabilidad/comprobantes/nuevo') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> Nuevo</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt !== null && !($serviceEnabled ?? true)): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span>El plan de la empresa <strong>no incluye</strong> el servicio de contabilidad.</span></div>
        <?php elseif ($dt !== null && ($quota ?? null) !== null && $quota <= 0): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span>Alcanzaste el <strong>límite de comprobantes</strong> de tu plan.</span></div>
        <?php endif; ?>
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i>
                <span>Selecciona una <strong>empresa activa</strong> en el encabezado para gestionar comprobantes.</span></div>
        <?php else: ?>
            <!-- Buscador + filtros en una sola fila -->
            <form method="get" action="<?= base_url('contabilidad/comprobantes') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input" placeholder="N° o beneficiario…">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Tipo</label>
                    <select name="type_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= (string) ($extraParams['type_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Estado</label>
                    <select name="status" class="form-select">
                        <?php foreach (['' => 'Todos', 'emitido' => 'Emitido', 'pagado' => 'Pagado', 'anulado' => 'Anulado'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= (string) ($extraParams['status'] ?? '') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Desde</label>
                    <input type="date" name="from" value="<?= esc($extraParams['from'] ?? '', 'attr') ?>" class="form-input">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Hasta</label>
                    <input type="date" name="to" value="<?= esc($extraParams['to'] ?? '', 'attr') ?>" class="form-input">
                </div>
                <?php if (!empty($dt['sort'])): ?>
                    <input type="hidden" name="sort" value="<?= esc($dt['sort'], 'attr') ?>">
                    <input type="hidden" name="dir" value="<?= esc($dt['dir'] ?? 'asc', 'attr') ?>">
                <?php endif; ?>
                <div class="form-group mb-0">
                    <label class="form-label text-xs invisible select-none" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="Buscar y filtrar"><i class="icon icon-funnel"></i></button>
                        <a href="<?= base_url('contabilidad/comprobantes') ?>" class="btn btn-ghost" title="Limpiar"><i class="icon icon-x-lg"></i></a>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'showSearch'        => false,
                'baseUrl'           => base_url('contabilidad/comprobantes'),
                'extraParams'       => $extraParams,
                'searchPlaceholder' => 'Buscar por número o beneficiario…',
                'emptyText'         => 'No hay comprobantes con esos criterios.',
                'columns'           => [
                    ['key' => 'number', 'label' => 'Número', 'sortable' => true,
                        'render' => fn ($r) => '<a href="' . base_url('contabilidad/comprobantes/' . $r['id']) . '" class="font-mono font-semibold text-primary">' . esc($r['number']) . '</a>'
                            . '<div class="text-xs text-muted">' . esc($r['type_name'] ?? '') . '</div>'],
                    ['key' => 'date', 'label' => 'Fecha', 'sortable' => true],
                    ['key' => 'beneficiary_name', 'label' => 'Beneficiario', 'sortable' => false,
                        'render' => fn ($r) => esc($r['beneficiary_name'] ?: '—')],
                    ['key' => 'total_amount', 'label' => 'Total', 'sortable' => true, 'class' => 'text-right', 'tdclass' => 'text-right',
                        'render' => fn ($r) => '<strong>' . number_format((float) $r['total_amount'], 2) . '</strong>'],
                    ['key' => 'status', 'label' => 'Estado', 'sortable' => true, 'render' => $statusBadge],
                ],
                'actions'           => function ($r) {
                    $html = '<div class="flex justify-end gap-1">';
                    $html .= '<a href="' . base_url('contabilidad/comprobantes/' . $r['id']) . '" class="btn btn-secondary btn-sm" title="Ver"><i class="icon icon-eye"></i></a>';
                    if ($r['status'] === 'emitido') {
                        $html .= '<a href="' . base_url('contabilidad/comprobantes/editar/' . $r['id']) . '" class="btn btn-secondary btn-sm" title="Editar"><i class="icon icon-pencil"></i></a>';
                    }
                    $html .= view('partials/delete_button', ['action' => base_url('contabilidad/comprobantes/eliminar/' . $r['id']), 'confirm' => '¿Eliminar este comprobante?']);

                    return $html . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
