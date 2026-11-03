<?php

namespace Components\Mailer\Libraries;

/**
 * Deja una imagen subida lista para viajar en un correo: acotada al ancho del
 * correo y en un formato que pinten TODOS los clientes.
 *
 * **Por qué no se usa el `ImageOptimizer` del núcleo.** Aquel convierte todo a
 * **WebP**, que es lo correcto para una página web y lo peor posible para un
 * correo: Outlook de escritorio —que sigue siendo media oficina— no lo pinta, y
 * el destinatario ve un hueco. Un correo se maqueta contra el cliente más viejo
 * que lo vaya a abrir, no contra el más nuevo. Aquí solo salen **JPEG, PNG y
 * GIF**, que es lo que entiende cualquiera desde hace veinte años. (Y, por el
 * contrato, un componente tampoco debe depender de una clase del núcleo que
 * podría no existir en otra instalación.)
 *
 * Tres reglas, las mismas que el del núcleo:
 *   1. **Solo se reduce, nunca se amplía.** Agrandar no añade detalle, solo peso.
 *   2. Si algo falla **se conserva el original**: optimizar es una mejora, no un
 *      requisito, y perder la imagen del usuario por un fallo de GD sería peor.
 *   3. Los **GIF se dejan como están**: GD se quedaría con el primer fotograma
 *      de una animación sin avisar.
 *
 * Qué formato sale:
 *   - PNG con transparencia (o paleta con `tRNS`) → **PNG**. Convertirlo a JPEG
 *     le pondría un fondo negro al logo.
 *   - PNG sin transparencia y JPEG → **JPEG**, que para una foto pesa una
 *     fracción.
 *   - WebP → **PNG** si trae alfa, si no **JPEG**. Nunca se queda en WebP.
 */
class ImageOptimizer
{
    /**
     * El ancho del correo. Es el «tamaño del marco de trabajo»: un cuerpo de
     * correo mide 600 px (`EmailRenderer::WIDTH`), así que nada tiene sentido
     * más ancho — se descargaría entero para pintarse encogido.
     */
    public const MAX_WIDTH = EmailRenderer::WIDTH;

    /** Calidad del JPEG: por debajo de 80 se ven los bloques en los degradados. */
    private const QUALITY = 82;

    /**
     * Optimiza en su sitio. Devuelve con qué se quedó, para que quien llama
     * renombre el archivo y pueda decírselo al usuario.
     *
     * @param  string   $path  ruta del archivo ya movido a su destino
     * @param  int|null $ancho ancho al que se va a mostrar (px); se acota al del correo
     * @return array{ok:bool,ext:string,mime:string,width:int,height:int,bytes:int}
     */
    public function optimize(string $path, ?int $ancho = null): array
    {
        $info = @getimagesize($path);
        $sin  = [
            'ok'     => false,
            'ext'    => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'mime'   => $info['mime'] ?? 'application/octet-stream',
            'width'  => (int) ($info[0] ?? 0),
            'height' => (int) ($info[1] ?? 0),
            'bytes'  => (int) @filesize($path),
        ];

        if (!$info || !function_exists('imagecreatetruecolor')) {
            return $sin;
        }

        [$w, $h] = [(int) $info[0], (int) $info[1]];
        $tipo    = $info[2];

        // Un GIF puede estar animado y GD se quedaría con el primer fotograma.
        if ($tipo === IMAGETYPE_GIF) {
            return $sin;
        }

        $tope = self::clampWidth($ancho);
        $alfa = self::hasAlpha($path, $tipo);
        $ext  = $alfa ? 'png' : 'jpg';
        $mime = $alfa ? 'image/png' : 'image/jpeg';

        // Si ya está en el formato bueno y cabe, no se toca: recomprimir un JPEG
        // solo le quita calidad.
        if ($w <= $tope && (($tipo === IMAGETYPE_JPEG && !$alfa) || ($tipo === IMAGETYPE_PNG && $alfa))) {
            return $sin + ['ok' => true];
        }

        $src = self::read($path, $tipo);
        if (!$src) {
            return $sin;
        }

        // Solo se reduce.
        $nw = $w > $tope ? $tope : $w;
        $nh = (int) max(1, round($h * ($nw / $w)));

        $dst = imagecreatetruecolor($nw, $nh);
        if (!$dst) {
            imagedestroy($src);

            return $sin;
        }

        if ($alfa) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            // Sin esto, una imagen con transparencia aplanada a JPEG sale con
            // el fondo NEGRO en vez de blanco.
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $tmp = $path . '.opt';
        $ok  = $alfa
            ? @imagepng($dst, $tmp, 9)
            : @imagejpeg($dst, $tmp, self::QUALITY);
        imagedestroy($dst);

        if (!$ok || !is_file($tmp)) {
            @unlink($tmp);

            return $sin;
        }

        // Si la «optimización» engordó el archivo y no hubo que encogerlo, se
        // queda el original: pesar más no es optimizar.
        if ($nw === $w && filesize($tmp) >= $sin['bytes'] && $sin['ext'] !== 'webp') {
            @unlink($tmp);

            return $sin + ['ok' => true];
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            return $sin;
        }

        return [
            'ok'     => true,
            'ext'    => $ext,
            'mime'   => $mime,
            'width'  => $nw,
            'height' => $nh,
            'bytes'  => (int) @filesize($path),
        ];
    }

    /** El ancho pedido, acotado al del correo. Sin pedir nada, el del correo. */
    public static function clampWidth(?int $ancho): int
    {
        if ($ancho === null || $ancho < 1) {
            return self::MAX_WIDTH;
        }

        return min($ancho, self::MAX_WIDTH);
    }

    /** Abre el archivo con el lector que le toque. */
    private static function read(string $path, int $tipo)
    {
        return match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default        => null,
        } ?: null;
    }

    /**
     * ¿Trae transparencia? Se mira la CABECERA y no los píxeles: recorrer una
     * imagen de 12 megapíxeles en PHP tarda más que todo lo demás junto.
     */
    private static function hasAlpha(string $path, int $tipo): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 64);

        if ($tipo === IMAGETYPE_PNG) {
            // Byte 25: el tipo de color. 4 = gris+alfa, 6 = RGB+alfa.
            $color = ord($head[25] ?? "\0");
            if ($color === 4 || $color === 6) {
                return true;
            }
            // Paleta (3): es transparente solo si trae un trozo `tRNS`.
            return $color === 3 && str_contains((string) @file_get_contents($path), 'tRNS');
        }

        if ($tipo === IMAGETYPE_WEBP) {
            // `VP8L` admite alfa siempre; `VP8X` la declara en un trozo `ALPH`.
            return str_contains($head, 'VP8L') || str_contains($head, 'ALPH');
        }

        return false;
    }
}
