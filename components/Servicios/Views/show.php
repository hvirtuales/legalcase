<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array $quote @var array $company @var array $cfg */
$meta  = require __DIR__ . '/_meta.php';
$cur   = (string) $quote['currency'];
$money = static fn ($v) => $meta['money']($v, $cur);
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <a href="<?= base_url('servicios/cotizaciones') ?>" class="btn btn-secondary btn-sm">
        <i class="icon icon-chevron-left"></i> <?= lang('Quo.back') ?>
    </a>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= base_url('servicios/cotizaciones/pdf/' . (int) $quote['id']) ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-download"></i> <?= lang('Quo.downloadPdf') ?>
        </a>
        <a href="<?= base_url('servicios/cotizaciones/editar/' . (int) $quote['id']) ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-pencil"></i> <?= lang('Quo.edit') ?>
        </a>
        <form action="<?= base_url('servicios/cotizaciones/estado/' . (int) $quote['id']) ?>" method="post" class="flex items-center gap-1">
            <?= csrf_field() ?>
            <select name="status" class="form-select py-1.5 text-xs">
                <?php foreach ($meta['statuses'] as $k => $label): ?>
                    <option value="<?= $k ?>" <?= $quote['status'] === $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary btn-sm"><?= lang('Quo.changeStatus') ?></button>
        </form>
    </div>
</div>

<?php // La carta va delante del documento, igual que en el PDF ocupa la página anterior. ?>
<?= view('Components\Servicios\Views\_letter', ['quote' => $quote, 'company' => $company, 'cfg' => $cfg]) ?>

<div class="card">
    <div class="card-body">
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
                                <td class="whitespace-nowrap text-right tabular-nums"><?= rtrim(rtrim(number_format((float) $it['quantity'], 2, ',', '.'), '0'), ',') ?></td>
                                <td class="whitespace-nowrap text-right tabular-nums">
                                    <?= (float) $it['line_discount'] > 0 ? '− ' . $money($it['line_discount']) : '—' ?>
                                </td>
                                <td class="whitespace-nowrap text-right tabular-nums">
                                    <?php if ((float) $it['tax_percent'] > 0): ?>
                                        <?= rtrim(rtrim(number_format((float) $it['tax_percent'], 2, ',', '.'), '0'), ',') ?>%
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
                    <div class="flex justify-between"><span class="text-muted"><?= lang('Quo.subtotal') ?></span><span><?= $money($quote['subtotal']) ?></span></div>
                    <div class="flex justify-between"><span class="text-muted">− <?= lang('Quo.discount') ?></span><span><?= $money($quote['discount_total']) ?></span></div>
                    <div class="flex justify-between"><span class="text-muted"><?= lang('Quo.taxableBase') ?></span><span><?= $money($quote['taxable_base']) ?></span></div>
                    <div class="flex justify-between"><span class="text-muted">+ <?= lang('Quo.taxes') ?></span><span><?= $money($quote['tax_total']) ?></span></div>
                    <?php if ((float) $quote['retention_total'] > 0): ?>
                        <div class="flex justify-between"><span class="text-muted">− <?= lang('Quo.retention') ?></span><span><?= $money($quote['retention_total']) ?></span></div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="mt-1 flex justify-between border-t border-line pt-1 text-base font-semibold">
                    <span><?= lang('Quo.netTotal') ?></span><span><?= $money($quote['total']) ?> <?= esc($cur) ?></span>
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

<!-- Enlace para el cliente -->
<div class="card mt-4">
    <div class="card-header">
        <i class="icon icon-link"></i>
        <h5 class="font-semibold"><?= lang('Quo.shareTitle') ?></h5>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Quo.shareHint') ?></p>

        <?php if (!empty($quote['public_enabled']) && $publicUrl): ?>
            <div class="mb-4 rounded-md border border-line bg-surface p-3">
                <label class="form-label text-xs"><?= lang('Quo.shareUrl') ?></label>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" id="publicUrl" class="form-input grow font-mono text-xs" readonly
                        value="<?= esc($publicUrl, 'attr') ?>">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="
                        var i = document.getElementById('publicUrl'); i.select();
                        document.execCommand('copy');
                        this.textContent = <?= json_encode(lang('Quo.shareCopied')) ?>;">
                        <?= lang('Quo.shareCopy') ?>
                    </button>
                    <a href="<?= esc($publicUrl, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                        <i class="icon icon-external-link"></i> <?= lang('Quo.shareOpen') ?>
                    </a>
                </div>
                <p class="mt-2 text-xs text-muted">
                    <?= !empty($quote['public_password']) ? lang('Quo.shareHasPassword') : lang('Quo.shareNoPassword') ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap items-end gap-3">
            <form action="<?= base_url('servicios/cotizaciones/compartir/' . (int) $quote['id']) ?>" method="post"
                class="flex flex-wrap items-end gap-2">
                <?= csrf_field() ?>
                <div class="form-group mb-0">
                    <label for="password" class="form-label text-xs"><?= lang('Quo.sharePassword') ?></label>
                    <input type="text" id="password" name="password" class="form-input" autocomplete="off"
                        placeholder="<?= esc(lang('Quo.sharePasswordHint'), 'attr') ?>">
                </div>
                <?php if (!empty($quote['public_password'])): ?>
                    <label class="form-check mb-2" for="remove_password">
                        <input type="checkbox" class="form-check-input" id="remove_password" name="remove_password" value="1">
                        <span class="form-check-label text-xs"><?= lang('Quo.shareRemovePassword') ?></span>
                    </label>
                <?php endif; ?>
                <button class="btn btn-primary btn-sm mb-1">
                    <i class="icon icon-link"></i>
                    <?= empty($quote['public_enabled']) ? lang('Quo.shareEnable') : lang('Quo.shareUpdate') ?>
                </button>
            </form>

            <?php if (!empty($quote['public_enabled'])): ?>
                <form action="<?= base_url('servicios/cotizaciones/compartir/' . (int) $quote['id'] . '/cerrar') ?>" method="post"
                    onsubmit="return confirm('<?= esc(lang('Quo.shareConfirmDisable'), 'js') ?>')">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary btn-sm mb-1"><i class="icon icon-lock"></i> <?= lang('Quo.shareDisable') ?></button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Lo que hizo el cliente -->
        <?php if (!empty($responses)): ?>
            <?php
            $actionLabels = [
                'viewed'   => lang('Quo.respViewed'),
                'approved' => lang('Quo.respApproved'),
                'rejected' => lang('Quo.respRejected'),
                'changes'  => lang('Quo.respChanges'),
            ];
            $actionBadges = [
                'viewed' => 'badge-secondary', 'approved' => 'badge-success',
                'rejected' => 'badge-danger', 'changes' => 'badge-warning',
            ];
            ?>
            <h6 class="mb-2 mt-6 font-semibold text-muted"><?= lang('Quo.respTitle') ?></h6>
            <div class="space-y-2">
                <?php foreach ($responses as $r): ?>
                    <div class="rounded-md border border-line p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="badge <?= $actionBadges[$r['action']] ?? 'badge-secondary' ?>">
                                <?= esc($actionLabels[$r['action']] ?? $r['action']) ?>
                            </span>
                            <span class="text-xs text-muted">
                                <i class="icon icon-clock"></i> <?= esc(substr((string) $r['created_at'], 0, 16)) ?>
                                <?= $r['ip_address'] ? ' · ' . esc($r['ip_address']) : '' ?>
                            </span>
                        </div>
                        <?php if (trim((string) $r['message']) !== ''): ?>
                            <p class="mt-2 text-sm"><?= nl2br(esc($r['message'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
