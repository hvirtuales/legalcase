<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$isPending = $pr['status'] === 'pending';
$statusBadge = [
    'pending'  => '<span class="badge badge-warning">Pendiente</span>',
    'approved' => '<span class="badge badge-success">Aprobada</span>',
    'rejected' => '<span class="badge badge-danger">Rechazada</span>',
][$pr['status']] ?? esc($pr['status']);
?>
<div class="no-print mb-4 flex items-center justify-between">
    <a href="<?= base_url('contabilidad/cuentas-cobro') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-chevron-left"></i> Volver</a>
    <div class="flex gap-1">
        <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="icon icon-printer"></i> Imprimir</button>
        <a href="<?= base_url('contabilidad/cuentas-cobro/' . $pr['id'] . '/pdf') ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="icon icon-download"></i> PDF</a>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-body">
                <div class="flex items-start justify-between border-b border-line pb-3">
                    <div>
                        <div class="text-xs text-muted">Cuenta de cobro</div>
                        <div class="font-mono text-xl font-bold text-primary"><?= esc($pr['reference']) ?></div>
                    </div>
                    <div><?= $statusBadge ?></div>
                </div>

                <div class="grid grid-cols-2 gap-2 py-3 text-sm md:grid-cols-3">
                    <div><div class="text-xs text-muted">Colaborador</div><?= esc($pr['collaborator_name']) ?></div>
                    <div><div class="text-xs text-muted">Documento</div><?= esc($pr['collaborator_id_type'] . ' ' . $pr['collaborator_id']) ?></div>
                    <div><div class="text-xs text-muted">Fecha</div><?= esc(substr((string) $pr['created_at'], 0, 10)) ?></div>
                    <div><div class="text-xs text-muted">Correo</div><?= esc($pr['collaborator_email'] ?: '—') ?></div>
                    <div><div class="text-xs text-muted">Teléfono</div><?= esc($pr['collaborator_phone'] ?: '—') ?></div>
                    <div><div class="text-xs text-muted">Ciudad</div><?= esc($pr['collaborator_city'] ?: '—') ?></div>
                    <div class="md:col-span-2"><div class="text-xs text-muted">Banco / cuenta</div><?= esc(trim($pr['collaborator_bank'] . ' ' . $pr['collaborator_account_type'] . ' ' . $pr['collaborator_account']) ?: '—') ?></div>
                    <?php if ($pr['period']): ?><div><div class="text-xs text-muted">Periodo</div><?= esc($pr['period']) ?></div><?php endif; ?>
                </div>

                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Concepto</th><th class="text-right">Valor</th><th class="text-right">IVA</th><th class="text-right">Rte.</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?= esc($it['description']) ?></td>
                                    <td class="text-right font-mono"><?= number_format((float) $it['amount'], 2) ?></td>
                                    <td class="text-right font-mono"><?= number_format((float) ($it['iva_amount'] ?? 0), 2) ?></td>
                                    <td class="text-right font-mono"><?= number_format((float) ($it['retencion_amount'] ?? 0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 flex justify-end">
                    <table class="text-sm">
                        <tr><td class="pr-6 text-muted">Subtotal</td><td class="text-right font-mono"><?= number_format((float) $pr['amount'], 2) ?></td></tr>
                        <tr><td class="pr-6 text-muted">IVA</td><td class="text-right font-mono"><?= number_format((float) $pr['iva_amount'], 2) ?></td></tr>
                        <tr><td class="pr-6 text-muted">Retención (<?= rtrim(rtrim(number_format((float) $pr['retencion_rate'], 2), '0'), '.') ?>%)</td><td class="text-right font-mono">-<?= number_format((float) $pr['retencion_amount'], 2) ?></td></tr>
                        <tr class="border-t border-line"><td class="pr-6 font-semibold">Total</td><td class="text-right font-mono text-lg font-bold"><?= number_format((float) $pr['total_amount'], 2) ?></td></tr>
                    </table>
                </div>

                <div class="mt-3 flex flex-wrap gap-3 text-xs text-muted">
                    <?php if ($pr['cert_no_iva']): ?><span class="badge badge-secondary">No responsable de IVA</span><?php endif; ?>
                    <?php if ($pr['cert_art383']): ?><span class="badge badge-secondary">Art. 383 E.T.</span><?php endif; ?>
                </div>

                <?php if (!empty($pr['signature_data']) && strncmp($pr['signature_data'], 'data:image', 10) === 0): ?>
                    <div class="mt-3">
                        <div class="text-xs text-muted">Firma</div>
                        <img src="<?= esc($pr['signature_data'], 'attr') ?>" alt="firma" class="max-h-24 border border-line bg-white">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Acciones (no imprimir) -->
    <div class="no-print">
        <?php if ($isPending): ?>
            <div class="card mb-4">
                <div class="card-header"><i class="icon icon-percent"></i><h6 class="font-semibold">Definir retención</h6></div>
                <div class="card-body">
                    <form action="<?= base_url('contabilidad/cuentas-cobro/retencion/' . $pr['id']) ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label">Tasa de retención %</label>
                            <input type="number" step="0.01" id="retInput" name="retencion_rate" class="form-input" value="<?= esc($pr['retencion_rate'], 'attr') ?>">
                            <div class="mt-1 flex flex-wrap gap-1">
                                <?php foreach ([1, 2.5, 3.5, 4, 6, 11] as $r): ?>
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('retInput').value=<?= $r ?>"><?= $r ?>%</button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm"><i class="icon icon-save"></i> Aplicar</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="icon icon-check-circle"></i><h6 class="font-semibold">Revisión</h6></div>
                <div class="card-body">
                    <form action="<?= base_url('contabilidad/cuentas-cobro/aprobar/' . $pr['id']) ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="form-group"><label class="form-label">Notas</label><textarea name="admin_notes" class="form-textarea" rows="2"></textarea></div>
                        <div class="flex gap-2">
                            <button class="btn btn-success btn-sm"><i class="icon icon-check-circle"></i> Aprobar</button>
                            <button formaction="<?= base_url('contabilidad/cuentas-cobro/rechazar/' . $pr['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Rechazar esta solicitud?')"><i class="icon icon-x-circle"></i> Rechazar</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-sm">
                    <div class="mb-1 text-muted">Revisada por</div>
                    <div class="font-semibold"><?= esc($pr['reviewed_by'] ?: '—') ?></div>
                    <div class="text-xs text-muted"><?= esc($pr['reviewed_at'] ?: '') ?></div>
                    <?php if ($pr['admin_notes']): ?><div class="mt-2 rounded bg-surface-muted p-2 text-xs"><?= esc($pr['admin_notes']) ?></div><?php endif; ?>
                    <?php if (!empty($pr['voucher_id'])): ?>
                        <a href="<?= base_url('contabilidad/comprobantes/' . $pr['voucher_id']) ?>" class="btn btn-secondary btn-sm mt-3"><i class="icon icon-receipt"></i> Ver Egreso generado</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <?= view('Components\Contabilidad\Views\partials\attachments', [
                'ownerType' => 'payment_request', 'ownerId' => $pr['id'], 'attachments' => $attachments ?? [],
                'canEdit' => true, 'return' => base_url('contabilidad/cuentas-cobro/' . $pr['id']),
            ]) ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
