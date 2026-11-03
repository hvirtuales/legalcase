<?php
/**
 * El afiche que se imprime y se pega en el área.
 *
 * Pensado para leerse a un metro de distancia y para fotocopiarse en blanco y
 * negro sin perder el QR. Maquetado con tablas y estilos en línea porque dompdf
 * no soporta flexbox ni grid.
 *
 * @var array   $point @var array $company @var string $url @var string $qr
 * @var ?string $logo  ruta absoluta o null (remota / inexistente)
 */
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) ($company['brand_color'] ?? '')) ? $company['brand_color'] : '#111827';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #111827; }
        .band { background: <?= $brand ?>; height: 14px; }
        .wrap { padding: 36px 46px; text-align: center; }
        .logo { max-height: 78px; max-width: 260px; }
        .company { font-size: 15px; font-weight: bold; letter-spacing: .04em; text-transform: uppercase; }
        h1 { font-size: 46px; margin: 26px 0 6px; letter-spacing: .02em; }
        .sub { font-size: 15px; color: #4b5563; margin: 0 0 4px; }
        .area { display: inline-block; margin-top: 12px; padding: 7px 20px; font-size: 17px; font-weight: bold;
                border: 2px solid <?= $brand ?>; border-radius: 8px; }
        .qr { margin-top: 30px; }
        .qr img { width: 268px; height: 268px; }
        .steps { width: 100%; margin-top: 26px; font-size: 12.5px; color: #374151; }
        .steps td { padding: 0 10px; text-align: center; vertical-align: top; }
        .step-n { display: inline-block; width: 26px; height: 26px; line-height: 26px; border-radius: 50%;
                  background: <?= $brand ?>; color: #fff; font-weight: bold; font-size: 13px; margin-bottom: 6px; }
        .url { margin-top: 22px; font-size: 10.5px; color: #6b7280; word-wrap: break-word; }
        .foot { margin-top: 26px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="band"></div>

    <div class="wrap">
        <?php if ($logo): ?>
            <img class="logo" src="<?= esc($logo, 'attr') ?>" alt="">
        <?php else: ?>
            <div class="company"><?= esc($company['name']) ?></div>
        <?php endif; ?>

        <h1><?= lang('Pqr.posterTitle') ?></h1>
        <p class="sub"><?= lang('Pqr.posterSub') ?></p>

        <?php if (!empty($point['area'])): ?>
            <div class="area"><?= esc($point['area']) ?></div>
        <?php endif; ?>

        <div class="qr"><img src="<?= $qr ?>" alt="QR"></div>

        <table class="steps">
            <tr>
                <td width="33%"><span class="step-n">1</span><br><?= lang('Pqr.posterStep1') ?></td>
                <td width="33%"><span class="step-n">2</span><br><?= lang('Pqr.posterStep2') ?></td>
                <td width="34%"><span class="step-n">3</span><br><?= lang('Pqr.posterStep3') ?></td>
            </tr>
        </table>

        <p class="url"><?= esc($url) ?></p>

        <div class="foot">
            <?= esc($company['name']) ?><?= !empty($company['nit']) ? ' · NIT ' . esc($company['nit']) : '' ?>
            · <?= esc($point['name']) ?>
        </div>
    </div>
</body>
</html>
