<?php
/**
 * Cierre del documento: a quién acudir si hay dudas o hace falta un ajuste.
 *
 * Solo se pinta si el vendedor tiene tarjeta digital —externa o la de la
 * plataforma—, porque el botón lleva ahí. El enlace va congelado en el
 * snapshot de la cotización (`seller_card_url`), así que un documento antiguo
 * sigue apuntando a donde apuntaba el día que se emitió.
 *
 * @var array $quote
 */
if (empty($quote['seller_card_url'])) {
    return;
}
?>
<div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-line bg-surface-muted/40 px-4 py-3">
    <p class="text-sm text-muted">
        <?= lang('Quo.advisorNote') ?>
        <?php if (!empty($quote['seller_name'])): ?>
            <span class="font-semibold text-ink"><?= esc($quote['seller_name']) ?></span>
        <?php endif; ?>
    </p>
    <a href="<?= esc($quote['seller_card_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm no-print">
        <i class="icon icon-person-badge"></i> <?= lang('Quo.advisorButton') ?>
    </a>
</div>
