<?php
/**
 * Iconos SVG en línea para las vistas públicas (sin la fuente de iconos del núcleo).
 * Trazos de Lucide.
 */
if (! function_exists('lfl_icon')) {
    function lfl_icon(string $name): string
    {
        $paths = [
            'check'   => '<path d="M20 6 9 17l-5-5"/>',
            'refresh' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M8 16H3v5"/>',
            'send'    => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
            'alert'   => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
        ];

        return '<span class="lf-i" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . ($paths[$name] ?? '') . '</svg></span>';
    }
}
