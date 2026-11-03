<?php

namespace Components\Contabilidad\Libraries;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Genera un QR como data URI PNG (vía GD). Sirve igual en HTML y en el PDF
 * (dompdf soporta data URIs PNG). Sin CDN ni llamadas remotas.
 */
class QrService
{
    public function dataUri(string $text): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64'    => true,   // devuelve "data:image/png;base64,…"
            'eccLevel'        => EccLevel::M,
            'scale'           => 4,
            'quietzoneSize'   => 2,
        ]);

        return (new QRCode($options))->render($text);
    }
}
