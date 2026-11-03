<?= $this->extend('Components\Servicios\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * La cotización que ve el cliente. Replica el formato del detalle interno
 * (`Views/show.php`) para que empresa y cliente vean exactamente el mismo
 * documento; aquí solo cambian las acciones: en vez de editar/estado, el cliente
 * descarga, imprime y responde.
 *
 * @var array       $quote @var array $company @var string $token
 * @var string|null $decision decisión firme del cliente (ver PublicLinkService)
 */
$meta  = require __DIR__ . '/../_meta.php';
$cur   = (string) $quote['currency'];
$money = static fn ($v) => $meta['money']($v, $cur);
$qty   = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

// La decisión firme sale del histórico: pedir ajustes después de aprobar no debe
// reabrir los botones.
$decision = $decision ?? null;
$answered = in_array((string) $decision, ['approved', 'rejected'], true);
$expired  = !empty($quote['issue_date']) && (int) $quote['validity_days'] > 0
    && strtotime($quote['issue_date'] . ' +' . (int) $quote['validity_days'] . ' days') < strtotime('today');
?>

<?php // La carta va delante del documento, igual que en el PDF ocupa la página anterior. ?>
<?= view('Components\Servicios\Views\_letter', ['quote' => $quote, 'company' => $company, 'cfg' => $cfg]) ?>

<div class="card">
    <div class="card-body">
        <!-- La descarga vive dentro del marco del documento; no se imprime. -->
        <div class="no-print mb-3 flex justify-end">
            <a href="<?= base_url('servicios/p/' . $token . '/pdf') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-download"></i> <?= lang('Quo.downloadPdf') ?>
            </a>
        </div>

        <!-- Encabezado -->
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line pb-4">
            <div class="flex items-start gap-3">
                <?php if (!empty($company['logo'])): ?>
                    <img src="<?= media_url($company['logo']) ?>" alt="" class="max-h-16 rounded bg-canvas p-1">
                <?php endif; ?>
                <div>
                    <div class="font-semibold text-ink"><?= esc($company['name']) ?></div>
                    <div class="text-xs text-muted">
                        <?= $company['nit'] ? 'NIT ' . esc($company['nit']) . '<br>' : '' ?>
                        <?= esc($company['address'] ?? '') ?><?= !empty($company['city']) ? ' · ' . esc($company['city']) : '' ?><br>
                        <?= esc($company['contact_phone'] ?? '') ?> <?= esc($company['contact_email'] ?? '') ?>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <h5 class="text-lg font-semibold uppercase tracking-wide text-ink"><?= esc($quote['doc_type']) ?></h5>
                <div class="text-xl font-bold text-ink"><?= esc($quote['number']) ?></div>
                <span class="badge <?= $meta['statusBadge']($quote['status']) ?>"><?= esc($meta['statusLabel']($quote['status'])) ?></span>
                <?php if ($expired && !$answered): ?>
                    <span class="badge badge-warning"><?= lang('Quo.docExpired') ?></span>
                <?php endif; ?>
                <div class="mt-1 text-xs text-muted">
                    <?= lang('Quo.date') ?>: <?= esc(substr((string) $quote['issue_date'], 0, 10)) ?><br>
                    <?php if ((int) $quote['validity_days'] > 0): ?>
                        <?= lang('Quo.validityDays') ?>: <?= (int) $quote['validity_days'] ?><br>
                    <?php endif; ?>
                    <?= esc($quote['payment_form']) ?> · <?= esc($quote['payment_method']) ?>
                </div>
            </div>
        </div>

        <!-- Partes -->
        <div class="grid grid-cols-1 gap-4 py-4 md:grid-cols-2">
            <div class="rounded-md border border-line p-3">
                <h6 class="mb-1 font-semibold text-muted"><?= lang('Quo.pdfFor') ?></h6>
                <div class="font-semibold text-ink"><?= esc($quote['client_name'] ?: '—') ?></div>
                <div class="text-xs text-muted">
                    <?= $quote['client_nit'] ? 'NIT ' . esc($quote['client_nit']) . '<br>' : '' ?>
                    <?= esc($quote['client_address']) ?><br>
                    <?= esc(trim($quote['client_city'] . ' ' . $quote['client_phone'])) ?><br>
                    <?= esc($quote['client_email']) ?>
                </div>
            </div>
            <div class="rounded-md border border-line p-3">
                <h6 class="mb-1 font-semibold text-muted"><?= lang('Quo.pdfPrepared') ?></h6>
                <div class="font-semibold text-ink"><?= esc($quote['seller_name'] ?: '—') ?></div>
                <?php if (!empty($quote['seller_position'])): ?>
                    <div class="text-xs text-ink"><?= esc($quote['seller_position']) ?></div>
                <?php endif; ?>
                <div class="text-xs text-muted">
                    <?= esc($quote['seller_email']) ?><br><?= esc($quote['seller_phone']) ?>
                </div>
                <?php // Sin botón de tarjeta: el «Contactar» del cierre ya lleva ahí. ?>
            </div>
        </div>

        <?php if (trim((string) $quote['intro_html']) !== ''): ?>
            <div class="prose mb-4 text-sm"><?= $quote['intro_html'] ?></div>
        <?php endif; ?>

        <?php if (!empty($quote['show_detail'])): ?>
            <!-- Detalle: las columnas de datos nunca se parten (whitespace-nowrap)
                 y toman solo lo que necesitan; «Producto» lleva w-full, así absorbe
                 el espacio sobrante y es el único que hace salto de línea. -->
            <div class="overflow-x-auto">
                <table class="table w-full text-xs">
                    <thead>
                        <tr class="whitespace-nowrap text-[10px]">
                            <th><?= lang('Quo.colRef') ?></th>
                            <th class="w-full"><?= lang('Quo.colProduct') ?></th>
                            <th class="text-right"><?= lang('Quo.colUnitPrice') ?></th>
                            <th class="text-right"><?= lang('Quo.colQuantity') ?></th>
                            <th class="text-right"><?= lang('Quo.colDiscount') ?></th>
                            <th class="text-right"><?= lang('Quo.colTax') ?></th>
                            <th class="text-right"><?= lang('Quo.colTotal') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quote['items'] as $it): ?>
                            <tr class="align-top">
                                <td class="whitespace-nowrap font-mono text-muted"><?= esc($it['code'] ?: '—') ?></td>
                                <td>
                                    <span class="font-semibold">
                                        <?php if (!empty($it['details_url'])): ?>
                                            <a href="<?= esc($it['details_url'], 'attr') ?>" target="_blank" rel="noopener" class="text-ink underline decoration-line-strong underline-offset-2"><?= esc($it['name']) ?></a>
                                        <?php else: ?>
                                            <?= esc($it['name']) ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php if (!empty($it['description'])): ?>
                                        <div class="text-[11px] leading-snug text-muted"><?= esc($it['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap text-right tabular-nums"><?= $money($it['unit_price']) ?></td>
                                <td class="whitespace-nowrap text-right tabular-nums"><?= $qty($it['quantity']) ?></td>
                                <td class="whitespace-nowrap text-right tabular-nums">
                                    <?= (float) $it['line_discount'] > 0 ? '− ' . $money($it['line_discount']) : '—' ?>
                                </td>
                                <td class="whitespace-nowrap text-right tabular-nums">
                                    <?php if ((float) $it['tax_percent'] > 0): ?>
                                        <?= $qty($it['tax_percent']) ?>%
                                        <div class="text-[11px] text-faint"><?= $money($it['line_tax']) ?></div>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap text-right font-semibold tabular-nums"><?= $money($it['line_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Resumen -->
        <div class="mt-4 flex justify-end">
            <div class="w-full max-w-sm rounded-md border border-line p-3 text-sm">
                <?php if (!empty($quote['show_detail'])): ?>
                    <div class="flex justify-between"><span class="text-muted"><?= lang('Quo.subtotal') ?></span><span class="tabular-nums"><?= $money($quote['subtotal']) ?></span></div>
                    <div class="flex justify-between"><span class="text-muted">− <?= lang('Quo.discount') ?></span><span class="tabular-nums"><?= $money($quote['discount_total']) ?></span></div>
                    <div class="flex justify-between"><span class="text-muted"><?= lang('Quo.taxableBase') ?></span><span class="tabular-nums"><?= $money($quote['taxable_base']) ?></span></div>
                    <div class="flex justify-between"><span class="text-muted">+ <?= lang('Quo.taxes') ?></span><span class="tabular-nums"><?= $money($quote['tax_total']) ?></span></div>
                    <?php if ((float) $quote['retention_total'] > 0): ?>
                        <div class="flex justify-between"><span class="text-muted">− <?= lang('Quo.retention') ?></span><span class="tabular-nums"><?= $money($quote['retention_total']) ?></span></div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="mt-1 flex justify-between border-t border-line pt-1 text-base font-semibold">
                    <span><?= lang('Quo.netTotal') ?></span><span class="tabular-nums"><?= $money($quote['total']) ?> <?= esc($cur) ?></span>
                </div>
            </div>
        </div>

        <?php if (trim((string) $quote['terms_html']) !== ''): ?>
            <div class="mt-6 border-t border-line pt-4 text-xs text-muted"><?= $quote['terms_html'] ?></div>
        <?php endif; ?>

        <?= view('Components\Servicios\Views\_advisor', ['quote' => $quote]) ?>

        <?php
        // Pie identificativo, en gris claro (equivale al pie repetido del PDF).
        $footerParts = array_filter([
            $company['name'] ?? '',
            !empty($company['nit']) ? 'NIT ' . $company['nit'] : '',
            $company['address'] ?? '',
            $company['city'] ?? '',
        ]);
        ?>
        <div class="mt-6 border-t border-line pt-3 text-center text-[11px] text-faint">
            <?= esc(implode(' · ', $footerParts)) ?>
            <div class="mt-1"><?= lang('Quo.docFooterNote') ?></div>
        </div>
    </div>
</div>

<!-- Respuesta del cliente -->
<div class="card no-print mt-4">
    <div class="card-header">
        <i class="icon icon-check-circle"></i>
        <h5 class="font-semibold"><?= lang('Quo.pubDecision') ?></h5>
    </div>
    <div class="card-body">
        <?php if ($answered): ?>
            <div class="alert <?= $decision === 'approved' ? 'alert-success' : 'alert-danger' ?>">
                <i class="icon <?= $decision === 'approved' ? 'icon-check-circle' : 'icon-x-circle' ?>"></i>
                <span>
                    <?= $decision === 'approved' ? lang('Quo.pubAlreadyApproved') : lang('Quo.pubAlreadyRejected') ?>
                    <?= esc(substr((string) $quote['client_responded_at'], 0, 16)) ?>
                </span>
            </div>
            <p class="mb-4 text-sm text-muted"><?= lang('Quo.pubStillChanges') ?></p>
        <?php else: ?>
            <?php if ($expired): ?>
                <div class="alert alert-warning"><i class="icon icon-clock"></i><span><?= lang('Quo.pubExpired') ?></span></div>
            <?php endif; ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Quo.pubIntro') ?></p>
        <?php endif; ?>

        <form action="<?= base_url('servicios/p/' . $token . '/responder') ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="message" class="form-label"><?= lang('Quo.pubMessage') ?></label>
                <textarea id="message" name="message" rows="3" maxlength="2000" class="form-textarea"
                    placeholder="<?= esc(lang('Quo.pubMessageHint'), 'attr') ?>"></textarea>
                <small class="form-hint"><?= lang('Quo.pubChangesHint') ?></small>
            </div>

            <!-- Jerarquía por contraste: aprobar sólido, ajustes sólido de marca y
                 rechazar con borde rojo (legible, sin competir con los otros dos). -->
            <div class="flex flex-wrap gap-2">
                <?php if (!$answered): ?>
                    <button name="action" value="approved" class="btn btn-success"
                        onclick="return confirm('<?= esc(lang('Quo.pubConfirmApprove'), 'js') ?>')">
                        <i class="icon icon-check-circle"></i> <?= lang('Quo.pubApprove') ?>
                    </button>
                    <button name="action" value="changes" class="btn btn-primary">
                        <i class="icon icon-chat"></i> <?= lang('Quo.pubRequestChanges') ?>
                    </button>
                    <button name="action" value="rejected"
                        class="btn btn-secondary border-danger font-semibold text-danger hover:bg-danger hover:text-white"
                        onclick="return confirm('<?= esc(lang('Quo.pubConfirmReject'), 'js') ?>')">
                        <i class="icon icon-x-circle"></i> <?= lang('Quo.pubReject') ?>
                    </button>
                <?php else: ?>
                    <button name="action" value="changes" class="btn btn-primary">
                        <i class="icon icon-chat"></i> <?= lang('Quo.pubRequestChanges') ?>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
