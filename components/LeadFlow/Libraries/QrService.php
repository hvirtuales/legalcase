<?php

namespace Components\LeadFlow\Libraries;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR como data URI PNG (vía GD), para compartir el enlace de un formulario en un
 * cartel o una pieza impresa. Copia propia (§0.4): un componente no depende de
 * otro. La dependencia real, `chillerlan/php-qrcode`, es del proyecto.
 */
class QrService
{
    public function dataUri(?string $text, int $scale = 4): ?string
    {
        $text = trim((string) $text);
        if ($text === '' || ! extension_loaded('gd') || ! class_exists(QRCode::class)) {
            return null;
        }

        try {
            $options = new QROptions([
                'outputInterface' => QRGdImagePNG::class,
                'outputBase64'    => true,
                'eccLevel'        => EccLevel::M,
                'scale'           => $scale,
                'quietzoneSize'   => 1,
            ]);

            return (new QRCode($options))->render($text);
        } catch (\Throwable $e) {
            log_message('error', 'LeadFlow: no se pudo generar el QR: ' . $e->getMessage());

            return null;
        }
    }
}
