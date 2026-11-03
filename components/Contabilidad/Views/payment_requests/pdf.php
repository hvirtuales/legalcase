<?php
$f = static fn ($n) => number_format((float) $n, 2);
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #222; margin: 0; }
        .title { font-size: 15px; font-weight: bold; }
        .num { font-size: 16px; font-weight: bold; color: #b00; }
        .muted { color: #777; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; vertical-align: top; }
        .items th { background: #f0f0f0; border-bottom: 1px solid #bbb; text-align: left; font-size: 10px; }
        .items td { border-bottom: 1px solid #eee; }
        .r { text-align: right; }
        h4 { margin: 12px 0 4px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width:65%">
                <div class="title"><?= esc($company['name'] ?? '') ?></div>
                <?php if (!empty($company['nit'])): ?><div class="muted">NIT: <?= esc($company['nit']) ?></div><?php endif; ?>
            </td>
            <td class="r">
                <div class="muted">CUENTA DE COBRO</div>
                <div class="num"><?= esc($pr['reference']) ?></div>
                <div class="muted"><?= esc(substr((string) $pr['created_at'], 0, 10)) ?></div>
            </td>
        </tr>
    </table>
    <hr style="border:none;border-top:1px solid #ccc;margin:6px 0">

    <h4>Datos del colaborador</h4>
    <table>
        <tr>
            <td style="width:50%"><span class="muted">Nombre:</span> <?= esc($pr['collaborator_name']) ?></td>
            <td><span class="muted">Documento:</span> <?= esc($pr['collaborator_id_type'] . ' ' . $pr['collaborator_id']) ?></td>
        </tr>
        <tr>
            <td><span class="muted">Correo:</span> <?= esc($pr['collaborator_email'] ?: '—') ?></td>
            <td><span class="muted">Teléfono:</span> <?= esc($pr['collaborator_phone'] ?: '—') ?></td>
        </tr>
        <tr>
            <td><span class="muted">Ciudad:</span> <?= esc($pr['collaborator_city'] ?: '—') ?></td>
            <td><span class="muted">Dirección:</span> <?= esc($pr['collaborator_address'] ?: '—') ?></td>
        </tr>
        <tr>
            <td colspan="2"><span class="muted">Banco:</span> <?= esc(trim($pr['collaborator_bank'] . ' · ' . $pr['collaborator_account_type'] . ' · ' . $pr['collaborator_account'], ' ·') ?: '—') ?></td>
        </tr>
    </table>

    <h4>Conceptos<?= $pr['period'] ? ' (' . esc($pr['period']) . ')' : '' ?></h4>
    <table class="items">
        <thead><tr><th>Descripción</th><th class="r">Valor</th><th class="r">IVA</th><th class="r">Rte.</th></tr></thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= esc($it['description'] ?? '') ?></td>
                    <td class="r"><?= $f($it['amount'] ?? 0) ?></td>
                    <td class="r"><?= $f($it['iva_amount'] ?? 0) ?></td>
                    <td class="r"><?= $f($it['retencion_amount'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table style="margin-top:6px">
        <tr>
            <td style="width:60%"></td>
            <td>
                <table>
                    <tr><td class="muted">Subtotal</td><td class="r"><?= $f($pr['amount']) ?></td></tr>
                    <tr><td class="muted">IVA</td><td class="r"><?= $f($pr['iva_amount']) ?></td></tr>
                    <tr><td class="muted">Retención (<?= rtrim(rtrim(number_format((float) $pr['retencion_rate'], 2), '0'), '.') ?>%)</td><td class="r">-<?= $f($pr['retencion_amount']) ?></td></tr>
                    <tr><td style="font-weight:bold;border-top:1px solid #999">Total</td><td class="r" style="font-weight:bold;border-top:1px solid #999"><?= $f($pr['total_amount']) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <?php if ($pr['cert_no_iva'] || $pr['cert_art383']): ?>
        <p class="muted" style="margin-top:10px;font-size:10px">
            <?php if ($pr['cert_no_iva']): ?>• Declara no ser responsable de IVA. <?php endif; ?>
            <?php if ($pr['cert_art383']): ?>• Solicita depuración de base (Art. 383 E.T.).<?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($pr['signature_data']) && strncmp($pr['signature_data'], 'data:image', 10) === 0): ?>
        <div style="margin-top:20px">
            <div class="muted" style="font-size:10px">Firma</div>
            <img src="<?= $pr['signature_data'] ?>" style="max-height:80px;border-bottom:1px solid #333">
        </div>
    <?php endif; ?>
</body>
</html>
