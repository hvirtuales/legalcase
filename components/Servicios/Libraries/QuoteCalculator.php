<?php

namespace Components\Servicios\Libraries;

/**
 * Cálculo de la cotización. Portado de la app original
 * (`C:\xampp\htdocs\cotizar\script.js`, `calcularDescuentoProducto` y
 * `calcularTotales`) para que los importes coincidan exactamente:
 *
 *   línea:      subtotal = precio × cantidad
 *               descuento = tipo '$' ? min(valor, subtotal) : subtotal × valor/100
 *               base      = subtotal − descuento
 *               impuesto  = base × tax%/100
 *               total     = base + impuesto
 *
 *   documento:  base gravable = Σsubtotal − Σdescuento
 *               retención     = base gravable × retention%/100
 *               total neto    = base gravable + Σimpuestos − retención
 *
 * La retención es el único añadido: el original la muestra en el resumen pero la
 * deja fija en 0 (script.js:531).
 *
 * Clase pura y sin estado: recibe arrays y devuelve arrays; es lo que permite
 * probar los importes sin tocar la BD.
 */
class QuoteCalculator
{
    /** Redondeo a 2 decimales, el que usan los importes en BD. */
    private static function money(float $v): float
    {
        return round($v, 2);
    }

    /**
     * Calcula una línea. Devuelve el ítem con line_subtotal/discount/base/tax/total.
     *
     * @param array $item code,name,unit_price,quantity,discount_type,discount_value,tax_percent
     */
    public function line(array $item): array
    {
        $price = (float) ($item['unit_price'] ?? 0);
        $qty   = (float) ($item['quantity'] ?? 0);
        $type  = ($item['discount_type'] ?? '%') === '$' ? '$' : '%';
        $value = (float) ($item['discount_value'] ?? 0);
        $tax   = (float) ($item['tax_percent'] ?? 0);

        $subtotal = self::money($price * $qty);

        // Un descuento en pesos nunca puede superar el subtotal de la línea.
        $discount = $type === '$'
            ? min(max($value, 0), $subtotal)
            : $subtotal * (max($value, 0) / 100);
        $discount = self::money($discount);

        $base     = self::money($subtotal - $discount);
        $taxValue = self::money($base * ($tax / 100));

        return array_merge($item, [
            'discount_type' => $type,
            'line_subtotal' => $subtotal,
            'line_discount' => $discount,
            'line_base'     => $base,
            'line_tax'      => $taxValue,
            'line_total'    => self::money($base + $taxValue),
        ]);
    }

    /**
     * Calcula el documento completo.
     *
     * @param array $items            líneas sin calcular
     * @param float $retentionPercent retención sobre la base gravable
     *
     * @return array{items:array,totals:array}
     */
    public function document(array $items, float $retentionPercent = 0): array
    {
        $calculated = [];
        $subtotal   = 0.0;
        $discount   = 0.0;
        $tax        = 0.0;

        foreach (array_values($items) as $i => $item) {
            $line               = $this->line($item);
            $line['sort_order'] = $i;
            $calculated[]       = $line;

            $subtotal += $line['line_subtotal'];
            $discount += $line['line_discount'];
            $tax      += $line['line_tax'];
        }

        $base      = self::money($subtotal - $discount);
        $retention = self::money($base * (max($retentionPercent, 0) / 100));

        return [
            'items'  => $calculated,
            'totals' => [
                'subtotal'          => self::money($subtotal),
                'discount_total'    => self::money($discount),
                'taxable_base'      => $base,
                'tax_total'         => self::money($tax),
                'retention_percent' => round(max($retentionPercent, 0), 4),
                'retention_total'   => $retention,
                'total'             => self::money($base + $tax - $retention),
            ],
        ];
    }
}
