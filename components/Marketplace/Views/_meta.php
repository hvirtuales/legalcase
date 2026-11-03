<?php
/**
 * Etiquetas y estilos compartidos por las vistas del componente: cómo se llama y
 * se pinta cada tipo de publicación y cada estado. Un solo sitio que cambiar.
 *
 * Devuelve closures listas para usar: $typeLabel($t), $typeIcon($t),
 * $typeBadge($t), $statusLabel($s), $statusBadge($s), $priceText($listing).
 */

$typeLabels = [
    'product'     => lang('Mkt.product'),
    'service'     => lang('Mkt.service'),
    'need'        => lang('Mkt.need'),
    'opportunity' => lang('Mkt.opportunity'),
];
$typeIcons = [
    'product'     => 'icon-box-seam',
    'service'     => 'icon-headset',
    'need'        => 'icon-search',
    'opportunity' => 'icon-sparkles',
];
// La oferta se distingue de la demanda por color: azul/verde ofrece, ámbar busca.
$typeBadges = [
    'product'     => 'badge-info',
    'service'     => 'badge-success',
    'need'        => 'badge-warning',
    'opportunity' => 'badge-primary',
];
$statusLabels = [
    'draft'     => lang('Mkt.statusDraft'),
    'published' => lang('Mkt.statusPublished'),
    'paused'    => lang('Mkt.statusPaused'),
    'closed'    => lang('Mkt.statusClosed'),
];
$statusBadges = [
    'draft'     => 'badge-secondary',
    'published' => 'badge-success',
    'paused'    => 'badge-warning',
    'closed'    => 'badge-light',
];

return [
    'typeLabel'   => static fn (?string $t) => $typeLabels[$t] ?? (string) $t,
    'typeIcon'    => static fn (?string $t) => $typeIcons[$t] ?? 'icon-box-seam',
    'typeBadge'   => static fn (?string $t) => $typeBadges[$t] ?? 'badge-secondary',
    'statusLabel' => static fn (?string $s) => $statusLabels[$s] ?? (string) $s,
    'statusBadge' => static fn (?string $s) => $statusBadges[$s] ?? 'badge-secondary',
    'priceText'   => static function (array $l): string {
        $type = $l['price_type'] ?? 'quote';
        $amt  = (float) ($l['price_amount'] ?? 0);
        if ($type === 'quote' || $amt <= 0) {
            return lang('Mkt.priceQuote');
        }
        $money = number_format($amt, 0, ',', '.') . ' ' . esc($l['price_currency'] ?? '');

        return $type === 'from' ? lang('Mkt.priceFrom') . ' ' . $money : $money;
    },
];
