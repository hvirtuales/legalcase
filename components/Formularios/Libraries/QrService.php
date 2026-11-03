<?php

namespace Components\Formularios\Libraries;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR como data URI PNG (via GD): el enlace del formulario para imprimirlo o
 * pegarlo en una vitrina, y el del comprobante de una respuesta.
 *
 * Va incrustado en el HTML y no como archivo servido a propósito: la vista
 * pública es autosuficiente (contrato 7.3) y un `<img src="data:...">` no añade
 * ninguna petición ni ningún archivo que pueda faltar.
 *
 * Copia propia: un componente no depende de otro (contrato 0.4). La dependencia
 * real, `chillerlan/php-qrcode`, es del proyecto.
 */
class QrService
{
    public function dataUri(?string $text, int $scale = 4): ?string
    {
        $text = trim((string) $text);
        if ($text === '' || !extension_loaded('gd')) {
            return null;
        }

        try {
            $options = new QROptions([
                'outputInterface' => QRGdImagePNG::class,
                'outputBase64'    => true,
                'eccLevel'        => EccLevel::M,
                'scale'           => max(2, min(10, $scale)),
                'quietzoneSize'   => 2,
            ]);

            return (new QRCode($options))->render($text);
        } catch (\Throwable $e) {
            log_message('error', 'Formularios: no se pudo generar el QR: ' . $e->getMessage());

            return null;
        }
    }
}
