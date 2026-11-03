<?php
$f = static fn ($n) => number_format((float) $n, 2);
$statusLabel = $v['status'] === 'pagado' ? (($type['name'] ?? '') === 'Ingreso' ? 'RECIBIDO' : 'PAGADO') : strtoupper($v['status']);
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #222; margin: 0; }
        .title { font-size: 15px; font-weight: bold; }
        .num { font-size: 18px; font-weight: bold; color: #b00; }
        .muted { color: #777; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; vertical-align: top; }
        .items th { background: #f0f0f0; border-bottom: 1px solid #bbb; text-align: left; font-size: 10px; }
        .items td { border-bottom: 1px solid #eee; }
        .r { text-align: right; }
        .box { border: 1px solid #ccc; padding: 6px 8px; }
        .status { display: inline-block; border: 1px solid #888; padding: 2px 8px; font-size: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width:60%">
                <div class="title"><?= esc($company['name'] ?? '') ?></div>
                <?php if (!empty($company['nit'])): ?><div class="muted">NIT: <?= esc($company['nit']) ?></div><?php endif; ?>
                <?php if (!empty($company['address'])): ?><div class="muted"><?= esc($company['address']) ?></div><?php endif; ?>
            </td>
            <td class="r" style="width:40%">
                <div class="muted"><?= esc($type['name'] ?? '') ?></div>
                <div class="num"><?= esc($v['number']) ?></div>
                <div><span class="status"><?= $statusLabel ?></span></div>
            </td>
        </tr>
    </table>
    <hr style="border:none;border-top:1px solid #ccc;margin:6px 0">

    <table>
        <tr>
            <td style="width:25%"><span class="muted">Fecha:</span> <?= esc($v['date']) ?></td>
            <td style="width:40%"><span class="muted">Beneficiario:</span> <?= esc($v['beneficiary_name'] ?: '—') ?></td>
            <td><span class="muted">Documento:</span> <?= esc($v['beneficiary_id'] ?: '—') ?></td>
        </tr>
        <tr>
            <td><span class="muted">Medio de pago:</span> <?= esc($v['payment_method']) ?><?php if (!empty($v['source_account_label'])): ?> <span class="muted">·</span> <?= esc($v['source_account_label']) ?><?php endif; ?></td>
            <td colspan="2"><span class="muted">Concepto:</span> <?= esc($v['description'] ?: '—') ?></td>
        </tr>
    </table>

    <p style="font-weight:bold;margin:10px 0 4px">Conceptos</p>
    <table class="items">
        <thead><tr><th>Descripción</th><th class="r">Base</th><th class="r">IVA</th><th class="r">Rte.</th><th class="r">Neto</th></tr></thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= esc($it['description']) ?><?php if (!empty($it['taxes'])): ?><br><span class="muted" style="font-size:9px"><?php foreach ($it['taxes'] as $tx): ?><?= esc($tx['name']) ?>: <?= !empty($tx['is_additive']) ? '+' : '-' ?><?= $f($tx['amount']) ?>&nbsp;&nbsp;<?php endforeach; ?></span><?php endif; ?></td>
                    <td class="r"><?= $f($it['amount']) ?></td>
                    <td class="r"><?= $f($it['iva_amount']) ?></td>
                    <td class="r"><?= $f($it['retencion_amount']) ?></td>
                    <td class="r"><?= $f((float) $it['amount'] + (float) $it['iva_amount'] - (float) $it['retencion_amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table style="margin-top:6px">
        <tr>
            <td style="width:60%"></td>
            <td>
                <table>
                    <tr><td class="muted">Subtotal</td><td class="r"><?= $f($v['subtotal']) ?></td></tr>
                    <tr><td class="muted">IVA</td><td class="r"><?= $f($v['total_iva']) ?></td></tr>
                    <tr><td class="muted">Retención</td><td class="r">-<?= $f($v['total_retencion']) ?></td></tr>
                    <?php if ((float) ($v['total_other_taxes'] ?? 0) != 0.0): ?>
                        <tr><td class="muted">Otros impuestos</td><td class="r"><?= $f($v['total_other_taxes']) ?></td></tr>
                    <?php endif; ?>
                    <tr><td style="font-weight:bold;border-top:1px solid #999">Total</td><td class="r" style="font-weight:bold;border-top:1px solid #999"><?= $f($v['total_amount']) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="font-weight:bold;margin:12px 0 4px">Asiento contable (partida doble)</p>
    <table class="items">
        <thead><tr><th>Cuenta</th><th>Descripción</th><th class="r">Débito</th><th class="r">Crédito</th></tr></thead>
        <tbody>
            <?php foreach ($entries as $e): ?>
                <tr>
                    <td><?= esc($e['account_code']) ?> <span class="muted"><?= esc($e['account_name']) ?></span></td>
                    <td><?= esc($e['description']) ?></td>
                    <td class="r"><?= (float) $e['debit'] ? $f($e['debit']) : '' ?></td>
                    <td class="r"><?= (float) $e['credit'] ? $f($e['credit']) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="2" class="r" style="font-weight:bold;border-top:1px solid #999">Totales</td>
                <td class="r" style="font-weight:bold;border-top:1px solid #999"><?= $f($debe) ?></td>
                <td class="r" style="font-weight:bold;border-top:1px solid #999"><?= $f($haber) ?></td>
            </tr>
        </tbody>
    </table>

    <table style="margin-top:18px">
        <tr>
            <td style="width:90px"><img src="<?= $qr ?>" style="width:80px;height:80px"></td>
            <td class="muted" style="font-size:10px;vertical-align:middle">Escanea el código para verificar este comprobante:<br><?= esc($verifyUrl) ?></td>
        </tr>
    </table>
</body>
</html>
