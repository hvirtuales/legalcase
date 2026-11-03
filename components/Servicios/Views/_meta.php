<?php
/**
 * Etiquetas y estilos compartidos por las vistas: estados y formato de moneda.
 * Devuelve closures listas para usar.
 */
$labels = [
    'pendiente' => lang('Quo.statusPendiente'),
    'aprobada'  => lang('Quo.statusAprobada'),
    'facturada' => lang('Quo.statusFacturada'),
    'rechazada' => lang('Quo.statusRechazada'),
];
$badges = [
    'pendiente' => 'badge-warning',
    'aprobada'  => 'badge-success',
    'facturada' => 'badge-primary',
    'rechazada' => 'badge-danger',
];

return [
    'statusLabel' => static fn (?string $s) => $labels[$s] ?? (string) $s,
    'statusBadge' => static fn (?string $s) => $badges[$s] ?? 'badge-secondary',
    'statuses'    => $labels,
    // COP sin decimales (como la app original), el resto con 2.
    'money'       => static function ($v, string $currency = 'COP'): string {
        $dec = $currency === 'COP' ? 0 : 2;

        return '$ ' . number_format((float) $v, $dec, ',', '.');
    },
];
