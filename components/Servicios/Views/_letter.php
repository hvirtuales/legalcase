<?php
/**
 * Carta de presentación, para las vistas WEB (detalle interno y vista del
 * cliente). En el PDF vive maquetada aparte —dompdf no entiende las clases de
 * Tailwind y necesita tablas—, pero el contenido y el orden son los mismos:
 * membrete · fecha · destinatario · asunto · saludo · cuerpo · firma.
 *
 * No se pinta nada si la empresa no activó la carta o la dejó vacía.
 *
 * @var array $quote @var array $company @var array $cfg
 */
if (empty($cfg['cover_enabled']) || trim((string) ($cfg['cover_html'] ?? '')) === '') {
    return;
}

// Fecha en largo y en el idioma activo («2 de agosto de 2026»).
$letterDate = $quote['issue_date'] ?: date('Y-m-d');
if (class_exists(\IntlDateFormatter::class)) {
    $fmt        = new \IntlDateFormatter(service('request')->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
    $letterDate = $fmt->format(new \DateTime($letterDate));
}
$letterPlace = trim((string) ($company['city'] ?? ''));
?>
<div class="card mb-4">
    <div class="card-body letter-web">
        <!-- Membrete -->
        <div class="flex items-start gap-4 border-b-2 border-ink pb-4">
            <?php if (!empty($company['logo'])): ?>
                <img src="<?= media_url($company['logo']) ?>" alt="" class="max-h-16 shrink-0 rounded bg-canvas p-1">
            <?php endif; ?>
            <div class="min-w-0">
                <div class="text-base font-semibold text-ink"><?= esc($company['name']) ?></div>
                <div class="text-xs leading-relaxed text-muted">
                    <?= $company['nit'] ? 'NIT ' . esc($company['nit']) . '<br>' : '' ?>
                    <?= esc($company['address'] ?? '') ?><?= !empty($company['city']) ? ' · ' . esc($company['city']) : '' ?><br>
                    <?= esc($company['contact_phone'] ?? '') ?><?= !empty($company['contact_email']) ? ' · ' . esc($company['contact_email']) : '' ?>
                </div>
            </div>
        </div>

        <!-- Fecha y destinatario -->
        <div class="mt-7">
            <p class="mb-4"><?= $letterPlace ? esc($letterPlace) . ', ' : '' ?><?= esc($letterDate) ?></p>

            <p class="text-[11px] uppercase tracking-widest text-muted"><?= lang('Quo.letterTo') ?></p>
            <p class="text-base font-semibold text-ink"><?= esc($quote['client_name'] ?: '—') ?></p>
            <p class="text-xs leading-relaxed text-muted">
                <?= $quote['client_nit'] ? 'NIT ' . esc($quote['client_nit']) . '<br>' : '' ?>
                <?= $quote['client_address'] ? esc($quote['client_address']) . '<br>' : '' ?>
                <?= esc($quote['client_city']) ?>
            </p>
        </div>

        <!-- Asunto -->
        <p class="mt-6">
            <strong><?= lang('Quo.letterSubject') ?>:</strong>
            <?= esc($quote['doc_type']) ?> <?= esc($quote['number']) ?>
        </p>

        <?php if (!empty($quote['client_contact'])): ?>
            <p class="mt-4"><?= lang('Quo.letterGreeting') ?> <?= esc($quote['client_contact']) ?>:</p>
        <?php endif; ?>

        <!-- Cuerpo: lo escribe el editor, así que sus etiquetas llevan medidas propias -->
        <div class="letter-body mt-4 text-justify"><?= $cfg['cover_html'] ?></div>

        <!-- Despedida y firma -->
        <div class="mt-10">
            <p><?= lang('Quo.letterClosing') ?>,</p>

            <div class="mt-2 max-w-md">
                <?php if (!empty($quote['seller_signature'])): ?>
                    <img src="<?= media_url($quote['seller_signature']) ?>" alt="" class="max-h-16">
                <?php else: ?>
                    <div class="h-12"></div>
                <?php endif; ?>
                <div class="border-t border-faint pt-2">
                    <div class="font-semibold text-ink"><?= esc($quote['seller_name'] ?: $company['name']) ?></div>
                    <?php if (!empty($quote['seller_position'])): ?>
                        <div class="text-xs"><?= esc($quote['seller_position']) ?></div>
                    <?php endif; ?>
                    <div class="text-xs text-muted">
                        <?= esc($quote['seller_email']) ?><br>
                        <?php if (!empty($quote['seller_phone'])): ?>
                            <?= lang('Quo.phone') ?>: <?= esc($quote['seller_phone']) ?><br>
                        <?php endif; ?>
                        <?= esc($company['name']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
