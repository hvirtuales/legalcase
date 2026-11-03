<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$badge = static function (array $r): string {
    return [
        'pending'  => '<span class="badge badge-warning">Pendiente</span>',
        'approved' => '<span class="badge badge-success">Aprobada</span>',
        'rejected' => '<span class="badge badge-danger">Rechazada</span>',
    ][$r['status']] ?? esc($r['status']);
};
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-cash-stack"></i><h5 class="font-semibold">Cuentas de cobro</h5></div>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span>Selecciona una <strong>empresa activa</strong> para gestionar sus cuentas de cobro.</span></div>
        <?php else: ?>
            <?php if (!($serviceEnabled ?? true)): ?>
                <div class="alert alert-warning mb-4"><i class="icon icon-exclamation-circle"></i><span>El plan de la empresa <strong>no incluye</strong> el servicio; el formulario público está deshabilitado.</span></div>
            <?php elseif (($quota ?? null) !== null): ?>
                <div class="mb-4 text-sm text-muted">Cupo del plan: <strong><?= (int) $quota ?></strong> cuentas de cobro restantes.</div>
            <?php endif; ?>

            <!-- Reporte (métricas) + enlace público en la misma fila -->
            <div class="mb-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:col-span-2">
                    <?php foreach ([['Total', 'total', 'text-ink'], ['Pendientes', 'pending', 'text-warning'], ['Aprobadas', 'approved', 'text-success'], ['Rechazadas', 'rejected', 'text-danger']] as [$lbl, $k, $cls]): ?>
                        <div class="rounded-lg border border-line p-3 text-center">
                            <div class="text-2xl font-bold <?= $cls ?>"><?= (int) $counts[$k] ?></div>
                            <div class="text-xs text-muted"><?= $lbl ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex flex-col justify-center gap-2 rounded-lg border border-line bg-surface-muted p-3">
                    <div class="flex items-center gap-2 text-sm text-muted"><i class="icon icon-link text-primary"></i> Enlace público para colaboradores</div>
                    <input type="text" readonly value="<?= esc($publicUrl, 'attr') ?>" class="form-input font-mono text-xs" onclick="this.select()">
                    <a href="<?= esc($publicUrl, 'attr') ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="icon icon-box-arrow-up-right"></i> Abrir formulario</a>
                </div>
            </div>

            <!-- Buscador + filtro + botón en una sola fila -->
            <form method="get" action="<?= base_url('contabilidad/cuentas-cobro') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input" placeholder="Referencia o colaborador…">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Estado</label>
                    <select name="status" class="form-select">
                        <?php foreach (['' => 'Todas', 'pending' => 'Pendientes', 'approved' => 'Aprobadas', 'rejected' => 'Rechazadas'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= (string) ($extraParams['status'] ?? '') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($dt['sort'])): ?>
                    <input type="hidden" name="sort" value="<?= esc($dt['sort'], 'attr') ?>">
                    <input type="hidden" name="dir" value="<?= esc($dt['dir'] ?? 'asc', 'attr') ?>">
                <?php endif; ?>
                <div class="form-group mb-0">
                    <label class="form-label text-xs invisible select-none" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="Buscar y filtrar"><i class="icon icon-funnel"></i></button>
                        <a href="<?= base_url('contabilidad/cuentas-cobro') ?>" class="btn btn-ghost" title="Limpiar"><i class="icon icon-x-lg"></i></a>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'showSearch'        => false,
                'baseUrl'           => base_url('contabilidad/cuentas-cobro'),
                'extraParams'       => $extraParams,
                'searchPlaceholder' => 'Buscar por referencia o colaborador…',
                'emptyText'         => 'No hay cuentas de cobro.',
                'columns'           => [
                    ['key' => 'reference', 'label' => 'Referencia', 'sortable' => true,
                        'render' => fn ($r) => '<a href="' . base_url('contabilidad/cuentas-cobro/' . $r['id']) . '" class="font-mono font-semibold text-primary">' . esc($r['reference']) . '</a>'],
                    ['key' => 'collaborator_name', 'label' => 'Colaborador', 'sortable' => false,
                        'render' => fn ($r) => esc($r['collaborator_name']) . '<div class="text-xs text-muted">' . esc($r['collaborator_id_type'] . ' ' . $r['collaborator_id']) . '</div>'],
                    ['key' => 'created_at', 'label' => 'Fecha', 'sortable' => true, 'render' => fn ($r) => esc(substr((string) $r['created_at'], 0, 10))],
                    ['key' => 'total_amount', 'label' => 'Total', 'sortable' => true, 'class' => 'text-right', 'tdclass' => 'text-right', 'render' => fn ($r) => '<strong>' . number_format((float) $r['total_amount'], 2) . '</strong>'],
                    ['key' => 'status', 'label' => 'Estado', 'sortable' => true, 'render' => $badge],
                ],
                'actions'           => fn ($r) => '<a href="' . base_url('contabilidad/cuentas-cobro/' . $r['id']) . '" class="btn btn-secondary btn-sm" title="Ver"><i class="icon icon-eye"></i></a>',
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
