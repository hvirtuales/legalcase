<?php
/**
 * Plantilla del PDF. Mismo formato que el detalle interno y la vista pública.
 *
 * Estética **minimalista en blanco y negro**: el documento NO usa el color de
 * marca de la empresa (solo su logo). Toda la jerarquía se resuelve con negros,
 * grises y grosores de línea, que es lo que mejor imprime y lo que menos envejece.
 *
 * dompdf no soporta flexbox ni grid: la maquetación va con tablas y estilos en
 * línea, y los anchos de columna con `table-layout: fixed` + `width`.
 *
 * @var array       $quote   cotización con sus items
 * @var array       $company empresa emisora (núcleo)
 * @var array       $cfg     ajustes del documento
 * @var string|null $logo    ruta ABSOLUTA del logo local (o null)
 */
$cur   = esc($quote['currency'] ?? 'COP');
$items = $quote['items'] ?? [];

// Con el símbolo delante, igual que en la web (`_meta.php`).
$money = static function ($v) use ($quote) {
    $dec = ($quote['currency'] ?? 'COP') === 'COP' ? 0 : 2;

    return '$ ' . number_format((float) $v, $dec, ',', '.');
};
$qty = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

$statusLabels = [
    'pendiente' => lang('Quo.statusPendiente'), 'aprobada' => lang('Quo.statusAprobada'),
    'facturada' => lang('Quo.statusFacturada'), 'rechazada' => lang('Quo.statusRechazada'),
];
$status = (string) ($quote['status'] ?? 'pendiente');

/**
 * Color de marca, para los enlaces de la carta y el estado «facturada» (el
 * resto del documento sigue en blanco y negro a propósito).
 *
 * El hex se emite CRUDO tras validarlo: `esc($hex, 'css')` devuelve `\23 …` y
 * `esc($hex, 'attr')` devuelve `&#x23;…`, que no son colores válidos.
 */
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) ($company['brand_color'] ?? ''))
    ? $company['brand_color']
    : '#1a1a1a';

// Tinta legible sobre ese fondo: blanco salvo que el color sea muy claro.
$lum      = (0.299 * hexdec(substr($brand, 1, 2)) + 0.587 * hexdec(substr($brand, 3, 2)) + 0.114 * hexdec(substr($brand, 5, 2))) / 255;
$brandInk = $lum > 0.65 ? '#1a1a1a' : '#ffffff';

/**
 * Color del estado, el mismo semáforo que la web (`_meta.php` → `app.css`):
 * pendiente ámbar, aprobada verde, rechazada roja. «Facturada» usa allí
 * `badge-primary`, que toma el color de la plataforma; aquí su equivalente es
 * el color de marca. Van en literal porque dompdf no resuelve variables CSS.
 */
$statusColors = [
    'pendiente' => '#f59e0b',
    'aprobada'  => '#10b981',
    'rechazada' => '#ef4444',
    'facturada' => $brand,
];
$statusColor = $statusColors[$status] ?? '#666666';

// Pie de página: identificación legal de la empresa, en gris claro.
$footerParts = array_filter([
    $company['name'] ?? '',
    !empty($company['nit']) ? 'NIT ' . $company['nit'] : '',
    $company['address'] ?? '',
    $company['city'] ?? '',
]);
?>
<style>
    /* Paleta del documento: solo negros y grises. */
    body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1a1a1a; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #666666; }
    .faint { color: #999999; }
    .right { text-align: right; }
    .center { text-align: center; }
    .box { border: 1px solid #d4d4d4; padding: 8px; }
    h1 { font-size: 18px; margin: 0; color: #1a1a1a; text-transform: uppercase; letter-spacing: 1px; white-space: nowrap; }
    h2 { font-size: 10.5px; margin: 0 0 4px; color: #666666; text-transform: uppercase; letter-spacing: .5px; }
    .doc-number { font-size: 21px; font-weight: bold; color: #000000; }
    .badge {
        display: inline-block; padding: 2px 7px; font-size: 9px;
        border: 1px solid #666666; color: #444444; text-transform: uppercase; letter-spacing: .5px;
        vertical-align: middle;
    }
    /* Bajo el encabezado: un filete gris, no la regla negra del resto. */
    .rule { border: none; border-top: 1px solid #d4d4d4; margin: 8px 0 10px; }

    /* Detalle: solo «Producto» parte línea; el resto en una sola línea. */
    .items { table-layout: fixed; font-size: 10px; margin-top: 10px; }
    .items th {
        background: #1a1a1a; color: #ffffff; padding: 5px 3px;
        font-size: 8.5px; text-transform: uppercase; letter-spacing: .3px;
        text-align: left; white-space: nowrap;
    }
    .items td { border-bottom: 1px solid #e0e0e0; padding: 5px 3px; vertical-align: top; white-space: nowrap; }
    .items td.product { white-space: normal; word-wrap: break-word; }
    .items .desc { color: #666666; font-size: 9px; }

    .totals td { padding: 4px 7px; font-size: 11px; }
    .total-row td { border-top: 1.5px solid #1a1a1a; font-size: 13px; font-weight: bold; }
    .page-break { page-break-after: always; }

    /* ------------------------------------------------------------------
       Carta de presentación. Va en su propia página y respira distinto al
       resto del documento: cuerpo más grande, interlínea más abierta y
       márgenes laterales propios (no se toca `@page`, que rige también las
       páginas de ítems, donde el ancho hace falta para la tabla).
       ------------------------------------------------------------------ */
    .letter { padding: 10px 38px 0; font-size: 13.5px; line-height: 1.45; }
    .letter .lt-head td { padding-bottom: 0; }
    .letter .lt-company { font-size: 15px; }
    .letter .lt-nit { font-size: 12px; color: #666666; }
    .letter .lt-rule { border: none; border-top: 1.5px solid #1a1a1a; margin: 12px 0 22px; }

    .letter .lt-block { margin-bottom: 18px; }
    .letter .lt-label {
        font-size: 9.5px; color: #666666; text-transform: uppercase; letter-spacing: 1px;
    }
    .letter .lt-to { font-size: 15px; }
    .letter .lt-meta { font-size: 11.5px; line-height: 1.35; }
    .letter .lt-subject { margin-bottom: 16px; font-size: 13.5px; }
    .letter .lt-greeting { margin-bottom: 12px; }

    /* El cuerpo lo escribe el editor: sus etiquetas necesitan medidas
       propias o dompdf las junta todas sin separación. */
    .letter .lt-body { text-align: justify; }
    .letter .lt-body p { margin: 0 0 9px; }
    .letter .lt-body p:last-child { margin-bottom: 0; }
    .letter .lt-body ul, .letter .lt-body ol { margin: 0 0 9px; padding-left: 22px; }
    .letter .lt-body li { margin-bottom: 3px; }
    .letter .lt-body h3, .letter .lt-body h4 { font-size: 14px; margin: 14px 0 6px; }

    /* Los enlaces del cuerpo, como botón con el color de la empresa: en papel
       una URL subrayada no se puede pulsar, pero un botón invita a escanear o
       a hacer clic en el PDF. */
    .letter .lt-body a {
        display: inline-block; background: <?= $brand ?>; color: <?= $brandInk ?>;
        padding: 4px 12px; margin: 1px 0; font-size: 12px; font-weight: bold;
        text-decoration: none;
    }

    .letter .lt-sign { margin-top: 30px; font-size: 13.5px; }
    .letter .lt-sign-name { font-size: 14px; }
    .letter .lt-sign-meta { font-size: 11.5px; line-height: 1.35; }

    /* Pie repetido en TODAS las páginas: dompdf mantiene el bloque fijo. */
    #page-footer {
        position: fixed; bottom: -18px; left: 0; right: 0;
        text-align: center; font-size: 8.5px; color: #b0b0b0;
        border-top: 1px solid #e8e8e8; padding-top: 4px;
    }
</style>

<div id="page-footer"><?= esc(implode(' · ', $footerParts)) ?></div>

<?php if (!empty($cfg['cover_enabled']) && trim((string) $cfg['cover_html']) !== ''): ?>
    <?php
    // Fecha en largo y en el idioma activo («2 de agosto de 2026»).
    $letterDate = $quote['issue_date'] ?: date('Y-m-d');
    if (class_exists(\IntlDateFormatter::class)) {
        $fmt        = new \IntlDateFormatter(service('request')->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        $letterDate = $fmt->format(new \DateTime($letterDate));
    }
    $letterPlace = trim((string) ($company['city'] ?? ''));
    ?>
    <!-- Carta de presentación: maquetada como carta comercial, en su propia página -->
    <div class="page-break letter">
        <!-- Membrete: logo y, a su derecha, solo el nombre y el NIT. La dirección
             y los contactos ya van en el pie de todas las páginas; repetirlos
             aquí recargaba la carta. La fecha baja a encabezar al destinatario. -->
        <table class="lt-head">
            <tr>
                <?php if ($logo): ?>
                    <td style="width:175px; vertical-align:middle">
                        <img src="<?= esc($logo, 'attr') ?>" style="max-height:87px; max-width:165px">
                    </td>
                <?php endif; ?>
                <td style="vertical-align:middle; <?= $logo ? 'border-left:2px solid ' . $brand . '; padding-left:14px' : '' ?>">
                    <strong class="lt-company"><?= esc($company['name']) ?></strong>
                    <?php // Lo que es, no el NIT: eso ya va en el pie de todas las páginas. ?>
                    <br><span class="lt-nit"><?= lang('Quo.secCover') ?></span>
                </td>
            </tr>
        </table>
        <hr class="lt-rule">

        <!-- Fecha y destinatario -->
        <div class="lt-block">
            <div style="margin-bottom:18px"><?= $letterPlace ? esc($letterPlace) . ', ' : '' ?><?= esc($letterDate) ?></div>

            <span class="lt-label"><?= lang('Quo.letterTo') ?></span><br>
            <strong class="lt-to"><?= esc($quote['client_name'] ?: '—') ?></strong><br>
            <span class="muted lt-meta">
                <?= $quote['client_nit'] ? 'NIT ' . esc($quote['client_nit']) . '<br>' : '' ?>
                <?= $quote['client_address'] ? esc($quote['client_address']) . '<br>' : '' ?>
                <?= esc($quote['client_city']) ?>
            </span>
        </div>

        <!-- Asunto -->
        <div class="lt-subject">
            <strong><?= lang('Quo.letterSubject') ?>:</strong>
            <?= esc($quote['doc_type']) ?> <?= esc($quote['number']) ?>
        </div>

        <?php if (!empty($quote['client_contact'])): ?>
            <div class="lt-greeting"><?= lang('Quo.letterGreeting') ?> <?= esc($quote['client_contact']) ?>:</div>
        <?php endif; ?>

        <div class="lt-body"><?= $cfg['cover_html'] ?></div>

        <!-- Despedida y firma -->
        <?php
        $pdfSvc    = new \Components\Servicios\Libraries\PdfService();
        $signature = $pdfSvc->localImage($quote['seller_signature'] ?? null);
        // QR de la tarjeta digital: se escanea desde el papel y lleva al mismo
        // destino que el botón que va justo debajo.
        $cardQr = !empty($quote['seller_card_url'])
            ? (new \Components\Servicios\Libraries\QrService())->dataUri($quote['seller_card_url'])
            : null;
        ?>
        <div class="lt-sign">
            <?= lang('Quo.letterClosing') ?>,

            <table style="margin-top:8px">
                <tr>
                <!-- Bloque de firma: el doble de ancho que antes -->
                <td style="width:460px; vertical-align:bottom">
                    <?php if ($signature): ?>
                        <!-- Firma escaneada del vendedor, justo sobre la línea -->
                        <img src="<?= esc($signature, 'attr') ?>" style="max-height:60px; max-width:400px"><br>
                    <?php else: ?>
                        <div style="height:48px"></div>
                    <?php endif; ?>
                    <div style="border-top:1px solid #999999;width:440px;padding-top:6px">
                        <strong class="lt-sign-name"><?= esc($quote['seller_name'] ?: $company['name']) ?></strong><br>
                        <?php if (!empty($quote['seller_position'])): ?>
                            <span class="lt-sign-meta"><?= esc($quote['seller_position']) ?></span><br>
                        <?php endif; ?>
                        <span class="muted lt-sign-meta">
                            <?= esc($quote['seller_email']) ?><br>
                            <?php if (!empty($quote['seller_phone'])): ?>
                                <?= lang('Quo.phone') ?>: <?= esc($quote['seller_phone']) ?><br>
                            <?php endif; ?>
                            <?= esc($company['name']) ?>
                        </span>
                    </div>
                </td>

                <!-- A la derecha: QR arriba y su botón debajo -->
                <td class="center" style="vertical-align:bottom; padding-left:16px">
                    <?php if (!empty($quote['seller_card_url'])): ?>
                        <?php if ($cardQr): ?>
                            <img src="<?= $cardQr ?>" style="width:80px; height:80px"><br>
                        <?php endif; ?>
                        <!-- Botón con el color de la empresa, como el «Contactar» del cierre -->
                        <a href="<?= esc($quote['seller_card_url'], 'attr') ?>"
                            style="display:inline-block;margin-top:6px;background:<?= $brand ?>;color:<?= $brandInk ?>;
                                   padding:5px 12px;font-size:10px;font-weight:bold;text-decoration:none">
                            <?= lang('Quo.letterCardButton') ?>
                        </a>
                    <?php endif; ?>
                </td>
                </tr>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Encabezado: datos de la empresa FRENTE al logo + identificación del documento -->
<table>
    <tr>
        <?php if ($logo): ?>
            <td style="width:160px; vertical-align:top">
                <img src="<?= esc($logo, 'attr') ?>" style="max-height:78px; max-width:150px">
            </td>
        <?php endif; ?>
        <?php // Filete vertical del color de la empresa separando el logo de sus datos. ?>
        <td style="vertical-align:top; <?= $logo ? 'border-left:2px solid ' . $brand . '; padding-left:14px' : '' ?>">
            <strong style="font-size:13px"><?= esc($company['name']) ?></strong><br>
            <span class="muted" style="font-size:11px">
                <?= $company['nit'] ? 'NIT ' . esc($company['nit']) . '<br>' : '' ?>
                <?= $company['address'] ? esc($company['address']) : '' ?>
                <?= !empty($company['city']) ? ' · ' . esc($company['city']) : '' ?><br>
                <?= esc($company['contact_phone'] ?? '') ?>
                <?= !empty($company['contact_email']) ? ' · ' . esc($company['contact_email']) : '' ?><br>
                <?= esc($company['website'] ?? '') ?>
            </span>
        </td>
        <?php // Más ancha: los datos del documento no deben partirse en dos renglones. ?>
        <td class="right" style="vertical-align:top; width:250px">
            <!-- El estado va en la misma línea del tipo de documento -->
            <h1><?= esc($quote['doc_type']) ?>
                <span class="badge" style="background:<?= $statusColor ?>; border-color:<?= $statusColor ?>; color:#ffffff">
                    <?= esc($statusLabels[$status] ?? $status) ?>
                </span>
            </h1>
            <div class="doc-number"><?= esc($quote['number']) ?></div>
            <div class="muted" style="font-size:11px; white-space:nowrap">
                <?= lang('Quo.date') ?>: <?= esc(substr((string) $quote['issue_date'], 0, 10)) ?><br>
                <?php if ((int) $quote['validity_days'] > 0): ?>
                    <?= lang('Quo.validityDays') ?>: <?= (int) $quote['validity_days'] ?><br>
                <?php endif; ?>
                <?= esc($quote['payment_form']) ?> · <?= esc($quote['payment_method']) ?>
            </div>
        </td>
    </tr>
</table>

<hr class="rule">

<!-- Partes -->
<table>
    <tr>
        <td style="width:49%" class="box">
            <h2><?= lang('Quo.pdfFor') ?></h2>
            <strong><?= esc($quote['client_name'] ?: '—') ?></strong><br>
            <span class="muted" style="font-size:11px">
                <?= $quote['client_nit'] ? 'NIT ' . esc($quote['client_nit']) . '<br>' : '' ?>
                <?= $quote['client_address'] ? esc($quote['client_address']) . '<br>' : '' ?>
                <?= esc(trim($quote['client_city'] . ' ' . $quote['client_phone'])) ?><br>
                <?= esc($quote['client_email']) ?>
            </span>
        </td>
        <td style="width:2%"></td>
        <td class="box">
            <h2><?= lang('Quo.pdfPrepared') ?></h2>
            <strong><?= esc($quote['seller_name'] ?: $company['name']) ?></strong><br>
            <?php if (!empty($quote['seller_position'])): ?>
                <span style="font-size:11px"><?= esc($quote['seller_position']) ?></span><br>
            <?php endif; ?>
            <?php // Sin enlace a la tarjeta: el botón «Contactar» del cierre ya lleva ahí. ?>
            <span class="muted" style="font-size:11px">
                <?= esc($quote['seller_email']) ?><br>
                <?= esc($quote['seller_phone']) ?>
            </span>
        </td>
    </tr>
</table>

<?php if (trim((string) $quote['intro_html']) !== ''): ?>
    <div style="margin-top:10px;line-height:1.5;font-size:11px"><?= $quote['intro_html'] ?></div>
<?php endif; ?>

<?php if (!empty($quote['show_detail'])): ?>
    <!-- Detalle -->
    <table class="items">
        <thead>
            <tr>
                <th style="width:10%"><?= lang('Quo.colRef') ?></th>
                <th style="width:33%"><?= lang('Quo.colProduct') ?></th>
                <th style="width:14%" class="right"><?= lang('Quo.colUnitPrice') ?></th>
                <th style="width:9%" class="center"><?= lang('Quo.colQuantity') ?></th>
                <th style="width:10%" class="right"><?= lang('Quo.colDiscount') ?></th>
                <th style="width:11%" class="right"><?= lang('Quo.colTax') ?></th>
                <th style="width:13%" class="right"><?= lang('Quo.colTotal') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td class="muted"><?= esc($it['code'] ?: '—') ?></td>
                    <td class="product">
                        <strong>
                            <?php if (!empty($it['details_url'])): ?>
                                <a href="<?= esc($it['details_url'], 'attr') ?>" style="color:#1a1a1a"><?= esc($it['name']) ?></a>
                            <?php else: ?>
                                <?= esc($it['name']) ?>
                            <?php endif; ?>
                        </strong>
                        <?php if (!empty($it['description'])): ?>
                            <div class="desc"><?= esc($it['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="right"><?= $money($it['unit_price']) ?></td>
                    <td class="center"><?= $qty($it['quantity']) ?></td>
                    <td class="right"><?= (float) $it['line_discount'] > 0 ? '−' . $money($it['line_discount']) : '—' ?></td>
                    <td class="right">
                        <?php if ((float) $it['tax_percent'] > 0): ?>
                            <?= $qty($it['tax_percent']) ?>%
                            <div class="faint"><?= $money($it['line_tax']) ?></div>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="right"><strong><?= $money($it['line_total']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Resumen -->
    <table style="margin-top:10px">
        <tr>
            <td style="width:62%"></td>
            <td class="box">
                <table class="totals">
                    <tr><td class="muted"><?= lang('Quo.subtotal') ?></td><td class="right"><?= $money($quote['subtotal']) ?> <?= $cur ?></td></tr>
                    <tr><td class="muted">− <?= lang('Quo.discount') ?></td><td class="right"><?= $money($quote['discount_total']) ?></td></tr>
                    <tr><td class="muted"><?= lang('Quo.taxableBase') ?></td><td class="right"><?= $money($quote['taxable_base']) ?></td></tr>
                    <tr><td class="muted">+ <?= lang('Quo.taxes') ?></td><td class="right"><?= $money($quote['tax_total']) ?></td></tr>
                    <?php if ((float) $quote['retention_total'] > 0): ?>
                        <tr><td class="muted">− <?= lang('Quo.retention') ?></td><td class="right"><?= $money($quote['retention_total']) ?></td></tr>
                    <?php endif; ?>
                    <tr class="total-row"><td><?= lang('Quo.netTotal') ?></td><td class="right"><?= $money($quote['total']) ?> <?= $cur ?></td></tr>
                </table>
            </td>
        </tr>
    </table>
<?php else: ?>
    <table style="margin-top:14px">
        <tr>
            <td style="width:62%"></td>
            <td class="box">
                <table class="totals">
                    <tr class="total-row"><td><?= lang('Quo.netTotal') ?></td><td class="right"><?= $money($quote['total']) ?> <?= $cur ?></td></tr>
                </table>
            </td>
        </tr>
    </table>
<?php endif; ?>

<?php if (trim((string) $quote['terms_html']) !== ''): ?>
    <div style="margin-top:16px;border-top:1px solid #e0e0e0;padding-top:8px;font-size:10px;line-height:1.5" class="muted">
        <?= $quote['terms_html'] ?>
    </div>
<?php endif; ?>

<?php if (!empty($quote['seller_card_url'])): ?>
    <!-- Cierre: a quién acudir por dudas o ajustes. El botón lleva a la tarjeta
         digital del vendedor y sigue siendo pulsable en el PDF. -->
    <table style="margin-top:16px; border:1px solid #e0e0e0">
        <tr>
            <td style="padding:10px 12px; vertical-align:middle; font-size:11px">
                <?= lang('Quo.advisorNote') ?>
                <?php if (!empty($quote['seller_name'])): ?>
                    <strong><?= esc($quote['seller_name']) ?></strong>
                <?php endif; ?>
            </td>
            <td class="right" style="padding:10px 12px; vertical-align:middle; width:120px">
                <a href="<?= esc($quote['seller_card_url'], 'attr') ?>"
                    style="display:inline-block; background:<?= $brand ?>; color:<?= $brandInk ?>;
                           padding:5px 14px; font-size:11px; font-weight:bold; text-decoration:none">
                    <?= lang('Quo.advisorButton') ?>
                </a>
            </td>
        </tr>
    </table>
<?php endif; ?>

<div style="margin-top:12px;text-align:center;font-size:9px" class="faint">
    <?= lang('Quo.docFooterNote') ?>
</div>
