<?php

namespace Components\Paginas\Libraries;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Genera un QR como data URI PNG (vía GD).
 *
 * Va incrustado en el HTML y no como archivo servido a propósito: la página
 * pública es autosuficiente (§7.3) y un `<img src="data:…">` no añade ninguna
 * petición ni ningún archivo que pueda faltar.
 *
 * Copia propia del componente: uno no depende de otro (contrato §0.4). La
 * dependencia real, `chillerlan/php-qrcode`, es del proyecto.
 */
class QrService
{
    /** Devuelve el data URI, o null si el texto está vacío o GD no está disponible. */
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
                // M aguanta ~15 % de daño: suficiente para un QR impreso en un
                // folleto o pegado en una vitrina, sin engordar el módulo.
                'eccLevel'        => EccLevel::M,
                'scale'           => max(2, min(10, $scale)),
                'quietzoneSize'   => 2,
            ]);

            return (new QRCode($options))->render($text);
        } catch (\Throwable $e) {
            log_message('error', 'Páginas: no se pudo generar el QR: ' . $e->getMessage());

            return null;
        }
    }
}
