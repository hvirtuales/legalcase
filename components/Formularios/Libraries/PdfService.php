<?php

namespace Components\Formularios\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * HTML a PDF con dompdf (PHP puro, sin llamadas remotas).
 *
 * Copia propia y no un `use` del de otro componente (contrato 0.4).
 *
 * Las plantillas usan HTML simple con tablas y estilos en línea: dompdf no
 * soporta flexbox ni grid, así que NO se reutilizan las vistas del formulario.
 */
class PdfService
{
    public function render(string $html, string $size = 'letter', string $orientation = 'portrait'): string
    {
        $temp = WRITEPATH . 'temp';
        if (!is_dir($temp)) {
            @mkdir($temp, 0777, true);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);      // nada remoto
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');  // UTF-8 con tildes y eñes
        $options->set('tempDir', $temp);
        $options->set('fontCache', $temp);
        $options->set('chroot', FCPATH);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($size, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }

    /** Ruta local de una imagen del núcleo para poder incrustarla sin red. */
    public function localImage(?string $path): ?string
    {
        $path = (string) $path;
        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return null;
        }
        $full = FCPATH . ltrim($path, '/');

        return is_file($full) ? $full : null;
    }
}
