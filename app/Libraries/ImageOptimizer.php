<?php

namespace App\Libraries;

use CodeIgniter\Images\Exceptions\ImageException;
use Config\Services;

/**
 * Deja toda imagen subida en **WebP** y acotada a un ancho máximo.
 *
 * Es una capacidad de plataforma, no de un componente: la usa `UploadService`,
 * así que vale igual para el logo de una empresa, la foto de un producto o una
 * imagen de una página. Una foto de móvil son 4000 px y 4 MB; servida tal cual
 * en una página pública es lo que más pesa con diferencia, y ningún diseño la
 * enseña por encima de ~1300 px.
 *
 * Dos decisiones:
 *
 *  - **Solo se reduce, nunca se amplía.** Estirar una imagen pequeña la
 *    empeora y engorda el archivo sin ganar nada.
 *  - **Si algo falla, se conserva el original.** La optimización es una mejora,
 *    no un requisito: que GD no tenga WebP o que la imagen esté corrupta no
 *    puede tumbar una subida que por lo demás es válida.
 *
 * Los GIF **animados** se dejan como están: convertirlos con GD se queda con el
 * primer fotograma y se pierde la animación sin avisar.
 */
class ImageOptimizer
{
    /** Ancho máximo por defecto. Ninguna maquetación del sistema pasa de aquí. */
    public const MAX_WIDTH = 1300;

    /** Calidad de WebP. 82 es el punto donde deja de notarse a simple vista. */
    private const QUALITY = 82;

    /**
     * Optimiza el archivo **en el sitio** y devuelve la ruta resultante.
     *
     * La extensión cambia a `.webp`, así que la ruta de vuelta NO tiene por qué
     * ser la de entrada: quien llama debe usar la devuelta.
     *
     * @param string $path     ruta absoluta del archivo ya movido
     * @param int    $maxWidth ancho máximo; 0 = no redimensionar
     *
     * @return string ruta final (la nueva si se optimizó, la original si no)
     */
    public function optimize(string $path, int $maxWidth = self::MAX_WIDTH): string
    {
        if (!is_file($path) || !function_exists('imagewebp')) {
            return $path;
        }

        $info = @getimagesize($path);
        if (!$info) {
            return $path;
        }
        [$ancho, , $tipo] = $info;

        // Ya está como tiene que estar: volver a codificarla solo perdería
        // calidad. Pasa cuando dos capas optimizan la misma imagen (el núcleo
        // al subirla y el componente después).
        if ($tipo === IMAGETYPE_WEBP && ($maxWidth <= 0 || $ancho <= $maxWidth)) {
            return $path;
        }

        // Un GIF animado perdería la animación en la conversión.
        if ($tipo === IMAGETYPE_GIF && $this->isAnimatedGif($path)) {
            return $path;
        }

        $destino = preg_replace('/\.[^.\/\\\\]+$/', '', $path) . '.webp';

        try {
            $image = Services::image()->withFile($path);

            // Solo hacia abajo: ampliar empeora la imagen y engorda el archivo.
            if ($maxWidth > 0 && $ancho > $maxWidth) {
                $image->resize($maxWidth, 0, true, 'width');
            }

            $image->convert(IMAGETYPE_WEBP)->save($destino, self::QUALITY);
        } catch (ImageException | \Throwable $e) {
            log_message('warning', 'No se pudo optimizar la imagen: ' . $e->getMessage());

            return $path;
        }

        if (!is_file($destino) || filesize($destino) === 0) {
            return $path;
        }

        // El original solo se borra cuando de verdad hay un sustituto, y solo si
        // no es el mismo archivo (una imagen que ya era .webp).
        if (realpath($destino) !== realpath($path)) {
            @unlink($path);
        }

        return $destino;
    }

    /**
     * Medidas de una imagen: `['width' => int, 'height' => int]`, o ceros.
     *
     * Se guardan junto al archivo para poder pintar la biblioteca sin abrir
     * cada imagen y para reservar el hueco antes de que cargue.
     */
    public function size(string $path): array
    {
        $info = @getimagesize($path);

        return ['width' => (int) ($info[0] ?? 0), 'height' => (int) ($info[1] ?? 0)];
    }

    /**
     * ¿El GIF tiene más de un fotograma?
     *
     * Se cuenta el separador de bloque gráfico (`00 21 F9 04`). Es la forma
     * barata y estándar de saberlo sin decodificar el archivo entero.
     */
    private function isAnimatedGif(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return false;
        }

        $frames = 0;
        $prev   = '';

        while (!feof($fh) && $frames < 2) {
            $chunk = $prev . (string) fread($fh, 8192);
            $frames += preg_match_all('/\x00\x21\xF9\x04/', $chunk);
            $prev = substr($chunk, -4);
        }
        fclose($fh);

        return $frames > 1;
    }
}
