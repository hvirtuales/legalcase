<?php

namespace Components\Contabilidad\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * HTML → PDF con dompdf (pura PHP, sin llamadas remotas). Usa DejaVu Sans
 * (soporta acentos/ñ). Las plantillas deben usar HTML simple + CSS básico
 * (tablas, inline styles); dompdf no soporta flexbox/grid.
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
        $options->set('isRemoteEnabled', false);   // seguridad: nada de recursos remotos
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // UTF-8 (á, é, í, ó, ú, ñ)
        $options->set('tempDir', $temp);
        $options->set('fontCache', $temp);
        $options->set('chroot', FCPATH);            // solo assets bajo public/

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($size, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }
}
