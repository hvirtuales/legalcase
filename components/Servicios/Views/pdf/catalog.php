<?php
/**
 * Catálogo en PDF (dompdf): blanco y negro, tablas simples.
 *
 * dompdf no soporta flex ni grid, así que la rejilla se arma con una tabla de
 * **tres columnas** — por eso NO se reutiliza la vitrina web.
 *
 * @var array   $catalog @var array $company @var array $items @var array $seller
 * @var ?string $logo   ruta local del logo @var ?string $qr @var string $url
 */
$money = static fn ($v) => '$ ' . number_format((float) $v, 0, ',', '.');
$show  = !empty($catalog['show_prices']);
$net   = static function (array $p): float {
    $unit = (float) $p['unit_price'];
    $disc = $p['discount_type'] === '$'
        ? min((float) $p['discount_value'], $unit)
        : $unit * ((float) $p['discount_value'] / 100);

    return max(0, $unit - $disc);
};
// Tres por fila: es el aprovechamiento que pidió la empresa para el impreso.
$rows = array_chunk($items, 3);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 12mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding-bottom: 8px; border-bottom: 1px solid #cccccc; }
        .brand { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #666666; }
        h1 { margin: 14px 0 2px; font-size: 20px; }
        .headline { margin: 0; font-size: 11px; color: #666666; }
        .intro { margin: 8px 0 0; font-size: 10px; color: #666666; }
        .cell { width: 33.33%; padding: 8px 6px 14px; vertical-align: top; }
        .photo { width: 100%; height: 110px; text-align: center; background: #f6f6f6; }
        .photo img { max-height: 110px; max-width: 100%; }
        .code { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #777777; padding-top: 6px; }
        .name { font-size: 10px; font-weight: bold; }
        .desc { font-size: 8px; color: #666666; }
        .price { font-size: 11px; font-weight: bold; padding-top: 3px; }
        .before { font-size: 8px; color: #888888; font-weight: normal; }
        #foot { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8px; color: #999999; }
        .qr { text-align: right; }
    </style>
</head>
<body>
    <div id="foot">
        <?= esc($company['name']) ?><?= !empty($company['nit']) ? ' · NIT ' . esc($company['nit']) : '' ?>
        <?= !empty($company['city']) ? ' · ' . esc($company['city']) : '' ?>
        <?= !empty($seller['name']) ? ' · ' . esc($seller['name']) : '' ?>
        <?= !empty($seller['phone']) ? ' · ' . esc($seller['phone']) : '' ?>
    </div>

    <table class="head">
        <tr>
            <td>
                <?php if ($logo): ?>
                    <img src="<?= esc($logo, 'attr') ?>" style="max-height:42px;max-width:120px">
                <?php endif; ?>
                <div class="brand"><?= esc($company['name']) ?></div>
            </td>
            <td class="qr" style="width:90px">
                <?php if ($qr): ?>
                    <img src="<?= $qr ?>" style="width:70px;height:70px">
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <h1><?= esc($catalog['name']) ?></h1>
    <?php if (!empty($catalog['headline'])): ?>
        <p class="headline"><?= esc($catalog['headline']) ?></p>
    <?php endif; ?>
    <?php if (!empty($catalog['intro'])): ?>
        <p class="intro"><?= esc($catalog['intro']) ?></p>
    <?php endif; ?>

    <table style="margin-top:10px">
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($row as $p): ?>
                    <td class="cell">
                        <div class="photo">
                            <?php if (!empty($p['pdf_photo'])): ?>
                                <img src="<?= esc($p['pdf_photo'], 'attr') ?>">
                            <?php endif; ?>
                        </div>
                        <?php if ($p['code'] !== ''): ?>
                            <div class="code"><?= esc($p['code']) ?></div>
                        <?php endif; ?>
                        <div class="name"><?= esc($p['name']) ?></div>
                        <?php if ($p['description'] !== ''): ?>
                            <div class="desc"><?= esc(mb_strimwidth($p['description'], 0, 110, '…')) ?></div>
                        <?php endif; ?>
                        <?php if ($show): ?>
                            <div class="price">
                                <?= $money($net($p)) ?>
                                <?php if ($net($p) < (float) $p['unit_price']): ?>
                                    <span class="before"><?= lang('Quo.shopBefore') ?> <?= $money($p['unit_price']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <?php for ($i = count($row); $i < 3; $i++): ?>
                    <td class="cell"></td>
                <?php endfor; ?>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
