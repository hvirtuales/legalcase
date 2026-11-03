<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$debe  = array_sum(array_map(static fn ($e) => (float) $e['debit'], $entries));
$haber = array_sum(array_map(static fn ($e) => (float) $e['credit'], $entries));
$cuadra = round($debe, 2) === round($haber, 2);
$isIngreso = ($type['name'] ?? '') === 'Ingreso';
$statusLabel = $v['status'] === 'pagado' ? ($isIngreso ? 'Recibido' : 'Pagado') : ucfirst($v['status']);
$verifyUrl = base_url('contabilidad/verificar/' . $v['id']);
?>
<style>
    @media print {
        #sidebar, #sidebarOverlay, .no-print, header, .sticky { display: none !important; }
        main { margin: 0 !important; }
        .card { border: 0 !important; box-shadow: none !important; }
    }
</style>

<!-- Barra de acciones (no se imprime) -->
<div class="no-print mb-4 flex flex-wrap items-center justify-between gap-2">
    <a href="<?= base_url('contabilidad/comprobantes') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-chevron-left"></i> Volver</a>
    <div class="flex flex-wrap gap-1">
        <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="icon icon-printer"></i> Imprimir</button>
        <a href="<?= base_url('contabilidad/comprobantes/' . $v['id'] . '/pdf') ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="icon icon-download"></i> PDF</a>
        <?php if ($v['status'] === 'emitido'): ?>
            <a href="<?= base_url('contabilidad/comprobantes/editar/' . $v['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i> Editar</a>
            <form action="<?= base_url('contabilidad/comprobantes/estado/' . $v['id']) ?>" method="post" class="inline">
                <?= csrf_field() ?><input type="hidden" name="status" value="pagado">
                <button class="btn btn-success btn-sm"><i class="icon icon-check-circle"></i> <?= $isIngreso ? 'Marcar recibido' : 'Marcar pagado' ?></button>
            </form>
        <?php endif; ?>
        <?php if ($v['status'] !== 'anulado'): ?>
            <form action="<?= base_url('contabilidad/comprobantes/estado/' . $v['id']) ?>" method="post" class="inline" onsubmit="return confirm('¿Anular este comprobante?')">
                <?= csrf_field() ?><input type="hidden" name="status" value="anulado">
                <button class="btn btn-danger btn-sm"><i class="icon icon-x-circle"></i> Anular</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Cabecera del documento -->
        <div class="flex items-start justify-between border-b border-line pb-4">
            <div>
                <div class="text-lg font-bold"><?= esc($company['name'] ?? '') ?></div>
                <?php if (!empty($company['nit'])): ?><div class="text-sm text-muted">NIT: <?= esc($company['nit']) ?></div><?php endif; ?>
            </div>
            <div class="text-right">
                <div class="text-sm text-muted"><?= esc($type['name'] ?? '') ?></div>
                <div class="font-mono text-xl font-bold text-primary"><?= esc($v['number']) ?></div>
                <div class="mt-1">
                    <?php if ($v['status'] === 'anulado'): ?><span class="badge badge-danger"><?= $statusLabel ?></span>
                    <?php elseif ($v['status'] === 'pagado'): ?><span class="badge badge-success"><?= $statusLabel ?></span>
                    <?php else: ?><span class="badge badge-secondary"><?= $statusLabel ?></span><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 py-4 text-sm md:grid-cols-4">
            <div><div class="text-xs text-muted">Fecha</div><?= esc($v['date']) ?></div>
            <div><div class="text-xs text-muted">Beneficiario</div><?= esc($v['beneficiary_name'] ?: '—') ?></div>
            <div><div class="text-xs text-muted">Documento</div><?= esc($v['beneficiary_id'] ?: '—') ?></div>
            <div><div class="text-xs text-muted">Medio de pago</div><?= esc($v['payment_method']) ?></div>
            <?php if (!empty($v['source_account_label'])): ?><div><div class="text-xs text-muted">Cuenta / Caja</div><?= esc($v['source_account_label']) ?></div><?php endif; ?>
        </div>
        <?php if (!empty($v['description'])): ?>
            <div class="mb-3 text-sm"><span class="text-xs text-muted">Concepto:</span> <?= esc($v['description']) ?></div>
        <?php endif; ?>

        <!-- Conceptos -->
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr>
                    <th>Descripción</th><th class="text-right">Base</th><th class="text-right">IVA</th><th class="text-right">Rte.</th><th class="text-right">Neto</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= esc($it['description']) ?>
                                <?php if (!empty($it['taxes'])): ?>
                                    <div class="text-xs text-muted">
                                        <?php foreach ($it['taxes'] as $tx): ?>
                                            <span class="mr-2"><?= esc($tx['name']) ?>: <?= !empty($tx['is_additive']) ? '+' : '−' ?><?= number_format((float) $tx['amount'], 2) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-mono"><?= number_format((float) $it['amount'], 2) ?></td>
                            <td class="text-right font-mono"><?= number_format((float) $it['iva_amount'], 2) ?></td>
                            <td class="text-right font-mono"><?= number_format((float) $it['retencion_amount'], 2) ?></td>
                            <td class="text-right font-mono"><?= number_format((float) $it['amount'] + (float) $it['iva_amount'] - (float) $it['retencion_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-2 flex justify-end">
            <table class="text-sm">
                <tr><td class="pr-6 text-muted">Subtotal</td><td class="text-right font-mono"><?= number_format((float) $v['subtotal'], 2) ?></td></tr>
                <tr><td class="pr-6 text-muted">IVA</td><td class="text-right font-mono"><?= number_format((float) $v['total_iva'], 2) ?></td></tr>
                <tr><td class="pr-6 text-muted">Retención</td><td class="text-right font-mono">-<?= number_format((float) $v['total_retencion'], 2) ?></td></tr>
                <?php if ((float) ($v['total_other_taxes'] ?? 0) != 0.0): ?>
                    <tr><td class="pr-6 text-muted">Otros impuestos</td><td class="text-right font-mono"><?= number_format((float) $v['total_other_taxes'], 2) ?></td></tr>
                <?php endif; ?>
                <tr class="border-t border-line"><td class="pr-6 font-semibold">Total</td><td class="text-right font-mono text-lg font-bold"><?= number_format((float) $v['total_amount'], 2) ?></td></tr>
            </table>
        </div>

        <!-- Asiento contable (partida doble) -->
        <h6 class="mb-2 mt-4 font-semibold text-muted">Asiento contable (partida doble)</h6>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Cuenta</th><th>Descripción</th><th class="text-right">Débito</th><th class="text-right">Crédito</th></tr></thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td class="font-mono"><?= esc($e['account_code']) ?> <span class="text-muted"><?= esc($e['account_name']) ?></span></td>
                            <td><?= esc($e['description']) ?></td>
                            <td class="text-right font-mono"><?= (float) $e['debit'] ? number_format((float) $e['debit'], 2) : '' ?></td>
                            <td class="text-right font-mono"><?= (float) $e['credit'] ? number_format((float) $e['credit'], 2) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="border-t border-line font-semibold">
                        <td colspan="2" class="text-right">Totales</td>
                        <td class="text-right font-mono"><?= number_format($debe, 2) ?></td>
                        <td class="text-right font-mono"><?= number_format($haber, 2) ?></td>
                    </tr>
                    <tr><td colspan="4" class="text-right text-xs <?= $cuadra ? 'text-success' : 'text-danger' ?>">
                        <?= $cuadra ? '✔ Debe = Haber (cuadrado)' : '✗ Descuadrado' ?>
                    </td></tr>
                </tfoot>
            </table>
        </div>

        <!-- Verificación pública -->
        <div class="mt-4 flex items-center gap-3 border-t border-line pt-3">
            <img src="<?= $qr ?>" alt="QR de verificación" class="border border-line bg-white" style="width:96px;height:96px">
            <div class="text-xs text-muted">
                <i class="icon icon-shield-check"></i> Escanea el código para verificar este comprobante.<br>
                <a href="<?= $verifyUrl ?>" class="text-primary" target="_blank"><?= $verifyUrl ?></a>
            </div>
        </div>
    </div>
</div>

<div class="no-print mt-4">
    <?= view('Components\Contabilidad\Views\partials\attachments', [
        'ownerType' => 'voucher', 'ownerId' => $v['id'], 'attachments' => $attachments ?? [],
        'canEdit' => $v['status'] === 'emitido', 'return' => base_url('contabilidad/comprobantes/' . $v['id']),
    ]) ?>
</div>
<?= $this->endSection() ?>
