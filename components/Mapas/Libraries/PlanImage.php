<?php

namespace Components\Mapas\Libraries;

/**
 * Prepara el plano de un nivel para que se pueda mirar en un navegador.
 *
 * **Por qué no vale el `ImageOptimizer` del núcleo.** Aquel recorta a 1300 px,
 * que está bien para el logo de una empresa o la foto de un producto y es
 * inservible para un plano: a 1300 px el nombre de los locales deja de leerse
 * al ampliar, que es justo para lo que sirve un mapa. Aquí el tope es mucho
 * mayor y el formato es **WebP**, que en una página web sí se puede usar
 * —a diferencia del correo, donde Outlook no lo pinta.
 *
 * **Por qué hay tope.** Un plano de 6000 × 3000 son 18 megapíxeles; el
 * navegador lo decodifica a RGBA en memoria, o sea unos **72 MB**, y en un
 * teléfono de gama media eso es una pestaña que se cierra sola. Además hay
 * límites duros de tamaño de canvas por navegador (Safari en iOS ronda los 16
 * millones de píxeles y devuelve un lienzo en blanco al pasarse, sin avisar).
 * Se acota a `MAX_WIDTH` y a `MAX_PIXELS`, lo que pinche antes.
 *
 * Tres reglas, las mismas que el optimizador del núcleo:
 *   1. **Solo se reduce, nunca se amplía.**
 *   2. Si algo falla **se conserva el original**: optimizar es una mejora.
 *   3. Los **GIF se dejan intactos**: GD se quedaría con el primer fotograma.
 */
class PlanImage
{
    /** Ancho máximo del plano ya servido. Suficiente para leer un local al ampliar. */
    public const MAX_WIDTH = 4000;

    /**
     * Y un tope por área, que es el que de verdad protege: un plano muy alto y
     * estrecho pasaría el filtro de ancho y seguiría siendo inmanejable.
     * 12 Mpx deja holgura frente al límite de canvas de iOS.
     */
    public const MAX_PIXELS = 12000000;

    private const QUALITY = 82;

    /**
     * Optimiza en su sitio y devuelve con qué quedó, para que quien llama
     * renombre el archivo y guarde las medidas.
     *
     * @return array{ok:bool,ext:string,width:int,height:int,bytes:int,scaled:bool}
     */
    public function process(string $path): array
    {
        $info = @getimagesize($path);
        $sin  = [
            'ok'     => false,
            'ext'    => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'width'  => (int) ($info[0] ?? 0),
            'height' => (int) ($info[1] ?? 0),
            'bytes'  => (int) @filesize($path),
            'scaled' => false,
        ];

        if (!$info || !function_exists('imagecreatetruecolor')) {
            return $sin;
        }

        [$w, $h] = [(int) $info[0], (int) $info[1]];
        $tipo    = $info[2];

        if ($tipo === IMAGETYPE_GIF) {
            return $sin;
        }

        $factor = $this->scaleFactor($w, $h);
        $webp   = function_exists('imagewebp');

        // Ya cabe y ya es WebP: no se toca. Recomprimir solo quita calidad.
        if ($factor >= 1.0 && $tipo === IMAGETYPE_WEBP) {
            return ['ok' => true] + $sin;
        }

        $src = $this->read($path, $tipo);
        if (!$src) {
            return $sin;
        }

        $nw = max(1, (int) round($w * min(1.0, $factor)));
        $nh = max(1, (int) round($h * min(1.0, $factor)));

        $dst = imagecreatetruecolor($nw, $nh);
        if (!$dst) {
            imagedestroy($src);

            return $sin;
        }
        // Un plano puede traer transparencia (fondos recortados); si se aplana
        // sin esto sale con fondo negro.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $ext = $webp ? 'webp' : 'png';
        $tmp = $path . '.opt';
        $ok  = $webp ? @imagewebp($dst, $tmp, self::QUALITY) : @imagepng($dst, $tmp, 6);
        imagedestroy($dst);

        if (!$ok || !is_file($tmp) || !@rename($tmp, $path)) {
            @unlink($tmp);

            return $sin;
        }

        return [
            'ok'     => true,
            'ext'    => $ext,
            'width'  => $nw,
            'height' => $nh,
            'bytes'  => (int) @filesize($path),
            'scaled' => $factor < 1.0,
        ];
    }

    /**
     * Cuánto hay que encoger para respetar los dos topes. Devuelve 1 o más
     * cuando ya cabe —nunca se amplía.
     */
    private function scaleFactor(int $w, int $h): float
    {
        $porAncho = $w > self::MAX_WIDTH ? self::MAX_WIDTH / $w : 1.0;
        $area     = $w * $h;
        $porArea  = $area > self::MAX_PIXELS ? sqrt(self::MAX_PIXELS / $area) : 1.0;

        return min($porAncho, $porArea);
    }

    private static function read(string $path, int $tipo)
    {
        return match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default        => null,
        } ?: null;
    }
}
