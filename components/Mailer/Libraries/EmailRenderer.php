<?php

namespace Components\Mailer\Libraries;

/**
 * Diseño (JSON) => HTML de correo. Es la ÚNICA autoridad sobre lo que se envía.
 *
 * Antes el HTML lo generaba el NAVEGADOR y el servidor guardaba lo que le
 * llegara: si el JavaScript no cargaba —y no cargaba, porque sus assets daban
 * 404— se guardaba un correo vacío, y nadie validaba lo que salía. Ahora el
 * constructor solo edita el árbol; el HTML lo produce aquí el servidor, en cada
 * guardado y en cada vista previa.
 *
 * Un correo NO es una página: se maqueta con TABLAS y estilos EN LINEA, ancho
 * fijo de 600 px y sin hojas externas, porque es lo único que pintan igual
 * Outlook, Gmail y el resto. Nada de flexbox, grid ni `<style>`.
 *
 * Todo lo que viene de datos pasa por `esc()`; los colores se validan y se
 * emiten crudos (escaparlos daría un color inválido), y el HTML enriquecido ya
 * viene limpio del saneador que corre al guardar.
 */
class EmailRenderer
{
    public const WIDTH = 600;

    private const BTN_SIZES = [
        'sm' => ['pad' => '8px 16px', 'fs' => 13],
        'md' => ['pad' => '12px 24px', 'fs' => 14],
        'lg' => ['pad' => '16px 32px', 'fs' => 16],
    ];

    private const FONT = "Arial,Helvetica,sans-serif";

    private array $company;

    /** @param array $company la ficha de `companies`, para los bloques de empresa */
    public function __construct(array $company = [])
    {
        $this->company = $company;
    }

    /** El cuerpo completo del correo, listo para enviar. */
    public function render($design): string
    {
        $design = (new DesignSanitizer())->clean($design);

        $body = '';
        foreach ($design['sections'] as $section) {
            $body .= $this->section($section);
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;background:#f4f4f5;margin:0;padding:0">'
            . '<tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="' . self::WIDTH . '" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:' . self::WIDTH . 'px;max-width:100%;background:#ffffff;border-radius:8px">'
            . $body
            . '</table></td></tr></table>';
    }

    /** Una sección: una fila de la tabla, con sus columnas dentro. */
    private function section(array $s): string
    {
        $bg  = $s['bg'] !== '' ? 'background:' . $s['bg'] . ';' : '';
        $pad = (int) $s['pad'];

        $cells = '';
        $width = (int) floor(self::WIDTH / max(1, (int) $s['cols']));

        foreach ($s['blocks'] as $blocks) {
            $inner = '';
            foreach ($blocks as $b) {
                $inner .= $this->block($b);
            }
            if ($inner === '') {
                $inner = '&nbsp;';
            }
            // `valign=top` o las columnas de distinto alto se centran solas y el
            // correo queda descuadrado en Outlook.
            $cells .= '<td valign="top" width="' . $width . '" style="width:' . $width . 'px;padding:0 8px">'
                . $inner . '</td>';
        }

        return '<tr><td style="' . $bg . 'padding:' . $pad . 'px ' . max(8, $pad - 8) . 'px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . '<tr>' . $cells . '</tr></table></td></tr>';
    }

    /**
     * Un bloque. Añadir un tipo al registro obliga a añadir su `case` aquí: si
     * falta, el bloque no se pinta y se ve en la primera prueba.
     */
    private function block(array $b): string
    {
        return match ((string) $b['type']) {
            'title'   => $this->title($b),
            'text'    => $this->text($b),
            'button'  => $this->button($b),
            'image'   => $this->image($b),
            'video'   => $this->video($b),
            'logo'    => $this->logo($b),
            'social'  => $this->social($b),
            'company' => $this->companyBlock($b),
            'divider' => $this->divider($b),
            'spacer'  => $this->spacer($b),
            'html'    => (string) $b['code'],
            default   => '',
        };
    }

    private function title(array $b): string
    {
        $tag = in_array($b['level'], ['h1', 'h2', 'h3'], true) ? $b['level'] : 'h2';

        return '<' . $tag . ' style="margin:0 0 12px 0;font-family:' . self::FONT
            . ';font-size:' . (int) $b['size'] . 'px;line-height:1.25;text-align:' . $b['align']
            . ';color:' . $b['color'] . ';font-weight:bold">' . esc($b['text']) . '</' . $tag . '>';
    }

    private function text(array $b): string
    {
        $lh = max(100, min(250, (int) $b['lineHeight'])) / 100;

        // `html` ya viene limpio del saneador: se emite sin volver a escapar,
        // porque es marcado a propósito (negritas, enlaces, listas).
        return '<div style="margin:0 0 12px 0;font-family:' . self::FONT
            . ';font-size:' . (int) $b['size'] . 'px;line-height:' . $lh
            . ';text-align:' . $b['align'] . ';color:' . $b['color'] . '">'
            . $b['html'] . '</div>';
    }

    private function button(array $b): string
    {
        $size = self::BTN_SIZES[$b['size']] ?? self::BTN_SIZES['md'];
        $href = $b['href'] !== '' ? $b['href'] : '#';
        $wide = !empty($b['fullWidth']);

        // El botón es una TABLA y no un `<a>` con relleno: Outlook ignora el
        // padding de un enlace y el botón se queda sin caja.
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'
            . ' style="margin:0 0 12px 0;' . ($wide ? 'width:100%;' : '')
            . ($b['align'] === 'center' ? 'margin-left:auto;margin-right:auto;' : '')
            . ($b['align'] === 'right' ? 'margin-left:auto;' : '') . '">'
            . '<tr><td align="center" style="background:' . $b['bg'] . ';border-radius:' . (int) $b['radius'] . 'px">'
            . '<a href="' . esc($href, 'attr') . '" target="_blank" rel="noopener"'
            . ' style="display:inline-block;padding:' . $size['pad'] . ';font-family:' . self::FONT
            . ';font-size:' . $size['fs'] . 'px;font-weight:bold;color:' . $b['color']
            . ';text-decoration:none;border-radius:' . (int) $b['radius'] . 'px'
            . ($wide ? ';width:100%;text-align:center' : '') . '">'
            . esc($b['text']) . '</a></td></tr></table>';
    }

    private function image(array $b): string
    {
        if ($b['src'] === '') {
            return $this->placeholder(lang('Mailer.phImage'));
        }

        $w   = (int) $b['width'];
        $sty = ($w > 0 ? 'width:' . $w . 'px;' : 'width:100%;')
            . 'max-width:100%;height:auto;display:inline-block;border:0;outline:none;text-decoration:none'
            . ((int) $b['radius'] > 0 ? ';border-radius:' . (int) $b['radius'] . 'px' : '');

        $img = '<img src="' . esc($b['src'], 'attr') . '" alt="' . esc($b['alt'], 'attr') . '" style="' . $sty . '">';

        if ($b['href'] !== '') {
            $img = '<a href="' . esc($b['href'], 'attr') . '" target="_blank" rel="noopener">' . $img . '</a>';
        }

        return '<div style="text-align:' . $b['align'] . ';margin:0 0 12px 0">' . $img . '</div>';
    }

    /**
     * Vídeo: una MINIATURA ENLAZADA con un triángulo encima. Ningún cliente de
     * correo reproduce vídeo incrustado, así que fingirlo sería mentir.
     */
    private function video(array $b): string
    {
        if ($b['src'] === '') {
            return $this->placeholder(lang('Mailer.phVideo'));
        }

        $w = (int) $b['width'];

        return '<div style="text-align:' . $b['align'] . ';margin:0 0 12px 0">'
            . '<a href="' . esc($b['href'] !== '' ? $b['href'] : '#', 'attr') . '" target="_blank" rel="noopener"'
            . ' style="display:inline-block;position:relative;text-decoration:none">'
            . '<img src="' . esc($b['src'], 'attr') . '" alt="' . esc($b['alt'], 'attr') . '"'
            . ' style="' . ($w > 0 ? 'width:' . $w . 'px;' : 'width:100%;')
            . 'max-width:100%;height:auto;display:block;border:0">'
            . '<span style="display:inline-block;margin-top:8px;font-family:' . self::FONT
            . ';font-size:13px;color:#4f46e5">&#9658; ' . esc($b['alt']) . '</span>'
            . '</a></div>';
    }

    private function divider(array $b): string
    {
        $gap = (int) $b['gap'];

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . '<tr><td style="padding:' . $gap . 'px 0"><div style="border-top:' . (int) $b['thickness']
            . 'px solid ' . $b['color'] . ';font-size:0;line-height:0">&nbsp;</div></td></tr></table>';
    }

    private function spacer(array $b): string
    {
        $h = max(1, (int) $b['height']);

        return '<div style="height:' . $h . 'px;font-size:0;line-height:0">&nbsp;</div>';
    }

    private function placeholder(string $text): string
    {
        return '<div style="padding:20px;text-align:center;color:#9ca3af;border:1px dashed #d1d5db;'
            . 'font-family:' . self::FONT . ';font-size:13px">' . esc($text) . '</div>';
    }

    /* ------------------------------------------- bloques que leen la empresa */

    /** URL absoluta de una imagen del núcleo: en un correo no valen las relativas. */
    private function absolute(string $path): string
    {
        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return $path;
        }

        helper('url');

        return base_url(ltrim($path, '/'));
    }

    private function logo(array $b): string
    {
        $logo = (string) ($this->company['logo'] ?? '');
        if ($logo === '') {
            return $this->placeholder(lang('Mailer.phNoLogo'));
        }

        $href = $b['href'] !== '' ? $b['href'] : (string) ($this->company['website'] ?? '');
        $img  = '<img src="' . esc($this->absolute($logo), 'attr') . '"'
            . ' alt="' . esc((string) ($this->company['name'] ?? ''), 'attr') . '"'
            . ' style="width:' . (int) $b['width'] . 'px;max-width:100%;height:auto;display:inline-block;border:0">';

        if ($href !== '') {
            $img = '<a href="' . esc($href, 'attr') . '" target="_blank" rel="noopener">' . $img . '</a>';
        }

        return '<div style="text-align:' . $b['align'] . ';margin:0 0 12px 0">' . $img . '</div>';
    }

    /**
     * Redes de la empresa. Van como TEXTO enlazado y no como iconos: un icono
     * exige una imagen alojada, y la mitad de los clientes de correo bloquean
     * las imágenes por defecto —el lector vería una fila de huecos—.
     */
    private function social(array $b): string
    {
        $redes = [
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin'  => 'LinkedIn',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
        ];

        $links = [];
        foreach ($redes as $key => $label) {
            $url = trim((string) ($this->company[$key] ?? ''));
            if ($url === '') {
                continue;
            }
            $links[] = '<a href="' . esc($url, 'attr') . '" target="_blank" rel="noopener"'
                . ' style="color:' . $b['color'] . ';text-decoration:none;font-weight:bold;padding:0 8px">'
                . esc($label) . '</a>';
        }

        if (!$links) {
            return $this->placeholder(lang('Mailer.phNoSocial'));
        }

        return '<div style="text-align:' . $b['align'] . ';margin:0 0 12px 0;font-family:' . self::FONT
            . ';font-size:' . (int) $b['size'] . 'px;color:' . $b['color'] . '">'
            . implode('<span style="color:#d1d5db">·</span>', $links) . '</div>';
    }

    /** Pie con los datos de la empresa: lo que exige un correo comercial. */
    private function companyBlock(array $b): string
    {
        $lines = [];

        $name = trim((string) ($this->company['name'] ?? ''));
        if ($name !== '') {
            $lines[] = '<strong>' . esc($name) . '</strong>';
        }
        if (!empty($b['showNit']) && trim((string) ($this->company['nit'] ?? '')) !== '') {
            $lines[] = esc('NIT ' . $this->company['nit']);
        }
        if (!empty($b['showAddress'])) {
            $dir = trim(implode(', ', array_filter([
                (string) ($this->company['address'] ?? ''),
                (string) ($this->company['city'] ?? ''),
            ])));
            if ($dir !== '') {
                $lines[] = esc($dir);
            }
        }
        if (!empty($b['showContact'])) {
            $con = array_filter([
                (string) ($this->company['phone'] ?? $this->company['contact_phone'] ?? ''),
                (string) ($this->company['email'] ?? $this->company['contact_email'] ?? ''),
            ]);
            if ($con) {
                $lines[] = esc(implode(' · ', $con));
            }
        }

        if (!$lines) {
            return $this->placeholder(lang('Mailer.phNoCompany'));
        }

        return '<div style="text-align:' . $b['align'] . ';margin:0 0 12px 0;font-family:' . self::FONT
            . ';font-size:' . (int) $b['size'] . 'px;line-height:1.6;color:' . $b['color'] . '">'
            . implode('<br>', $lines) . '</div>';
    }
}
