<?php

namespace Components\Pqrsf\Libraries;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Genera un QR como data URI PNG (vía GD). Vale igual para HTML y para el PDF:
 * dompdf incrusta data URIs sin necesitar acceso remoto.
 *
 * Copia propia del componente (no se usa la de otro): un componente no
 * depende de otro (contrato §0.4). La dependencia real es `chillerlan/php-qrcode`,
 * que es del proyecto.
 */
class QrService
{
    /** Devuelve el data URI, o null si el texto está vacío o GD no está disponible. */
    public function dataUri(?string $text, int $scale = 3): ?string
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
                'scale'           => $scale,
                'quietzoneSize'   => 1,
            ]);

            return (new QRCode($options))->render($text);
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo generar el QR: ' . $e->getMessage());

            return null;
        }
    }
}
