<?php

namespace Components\Pqrsf\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * HTML → PDF con dompdf (PHP puro, sin llamadas remotas). Sustituye a la TCPDF
 * que usaba la app original; dompdf ya es dependencia del proyecto.
 *
 * Copia propia y no un `use` del PdfService de Contabilidad: un componente no
 * depende de otro (contrato §0.4).
 *
 * Las plantillas deben usar HTML simple + CSS básico (tablas, estilos en línea):
 * dompdf no soporta flexbox ni grid.
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
        $options->set('isRemoteEnabled', false);      // seguridad: nada remoto
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');  // UTF-8 (á, é, í, ó, ú, ñ)
        $options->set('tempDir', $temp);
        $options->set('fontCache', $temp);
        $options->set('chroot', FCPATH);              // solo assets bajo public/

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($size, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Ruta absoluta de una imagen guardada por el núcleo (`/uploads/...`) para que
     * dompdf pueda incrustarla con `isRemoteEnabled=false`. Devuelve null si es
     * remota (S3/R2) o no existe: en ese caso la plantilla simplemente no la pinta.
     */
    public function localImage(?string $path): ?string
    {
        $path = (string) $path;
        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return null;
        }
        $full = FCPATH . ltrim($path, '/\\');

        return is_file($full) ? $full : null;
    }
}
