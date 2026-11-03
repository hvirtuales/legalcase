<?php

namespace Components\Formularios\Libraries;

/**
 * Iconos SVG EN LINEA, la única fuente del componente.
 *
 * El formulario público es autosuficiente (contrato 7.3): no carga `icons.css`
 * del núcleo ni ninguna fuente de iconos, así que el dibujo viaja dentro del
 * HTML. El editor, que sí es pantalla interna, usa los `icon-*` del núcleo.
 *
 * Trazos de Lucide, redibujados a mano para no depender de un paquete.
 */
class Icons
{
    private const PATHS = [
        'star'   => '<path d="m12 3 2.6 5.6 6 .8-4.4 4.2 1.1 6.1L12 16.8 6.7 19.7l1.1-6.1L3.4 9.4l6-.8Z"/>',
        'heart'  => '<path d="M12 20s-7-4.4-7-9a3.8 3.8 0 0 1 7-2 3.8 3.8 0 0 1 7 2c0 4.6-7 9-7 9Z"/>',
        'thumb'  => '<path d="M7 20V10l4-7 1 .5a2 2 0 0 1 1 2.3L12.4 9H18a2 2 0 0 1 2 2.4l-1.3 6A2 2 0 0 1 16.7 19H7Z"/><path d="M7 10H4v10h3"/>',
        'upload' => '<path d="M12 15V4"/><path d="m8 8 4-4 4 4"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
        'alert'  => '<path d="M12 4 3 19h18Z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
        'check'  => '<path d="m4 12 5 5L20 6"/>',
        'x'      => '<path d="m6 6 12 12"/><path d="m18 6-12 12"/>',
        'left'   => '<path d="m14 6-6 6 6 6"/>',
        'right'  => '<path d="m10 6 6 6-6 6"/>',
        'send'   => '<path d="M4 12 20 4l-6 16-2.5-6.5Z"/>',
        'lock'   => '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'expand' => '<path d="M4 9V4h5"/><path d="M20 15v5h-5"/><path d="M15 4h5v5"/><path d="M9 20H4v-5"/>',
        'shrink' => '<path d="M9 4v5H4"/><path d="M15 20v-5h5"/><path d="M20 9h-5V4"/><path d="M4 15h5v5"/>',
    ];

    /** El SVG de un icono, o cadena vacía si no existe (nunca un error). */
    public static function svg(string $name, string $class = 'ff-ico'): string
    {
        $path = self::PATHS[$name] ?? '';
        if ($path === '') {
            return '';
        }

        return '<svg class="' . esc($class, 'attr') . '" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }

    /** Los que se rellenan al marcarse (la estrella encendida, por ejemplo). */
    public static function has(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }
}
