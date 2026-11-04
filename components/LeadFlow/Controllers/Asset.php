<?php

namespace Components\LeadFlow\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Archivos estáticos propios del componente: `GET /lfl-recursos/{archivo}`.
 *
 * Las tipografías del sitio público (Cormorant Garamond y Manrope, licencia SIL OFL 1.1
 * junto a los archivos) y las fotografías de la portada, en 1200 y 640 px.
 *
 * **Viajan dentro de la carpeta del componente** y no en `public/`: el núcleo no
 * publica assets de componentes (CLAUDE.md), y un componente que se suelta en otra
 * instalación tiene que verse igual. Tampoco se cargan de un CDN.
 *
 * **Lista blanca fija**: ningún nombre que llegue de la URL se resuelve contra el disco
 * si no está aquí, así que no hay recorrido de directorios posible.
 */
class Asset extends BaseController
{
    private const FONTS = [
        'cormorant-garamond-500-700.woff2',
        'manrope-400-800.woff2',
    ];

    /** Fotografías de la portada; cada una existe en `-1200.webp` y `-640.webp`. */
    public const PHOTOS = ['hero', 'presencia', 'captacion', 'gestion', 'portal', 'verificacion', 'directorio', 'cierre'];

    /** [archivo => [subcarpeta, tipo]] */
    private static function files(): array
    {
        $out = [];
        foreach (self::FONTS as $f) {
            $out[$f] = ['fonts', 'font/woff2'];
        }
        foreach (self::PHOTOS as $p) {
            foreach ([1200, 640] as $w) {
                $out[$p . '-' . $w . '.webp'] = ['img', 'image/webp'];
            }
        }
        // El logo, sin fondo; la variante oscura lleva el navy pasado a crema.
        $out['logo.png']      = ['img', 'image/png'];
        $out['logo-dark.png'] = ['img', 'image/png'];

        return $out;
    }

    public function file(string $name = '')
    {
        $files = self::files();
        if (! isset($files[$name])) {
            throw PageNotFoundException::forPageNotFound();
        }
        [$dir, $mime] = $files[$name];
        $path = dirname(__DIR__) . '/Assets/' . $dir . '/' . $name;
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        // `setCache()` y no `setHeader('Cache-Control')`: es lo único que retira el
        // `no-store` con el que nace la respuesta de CI4 (ver `Directory::cover`).
        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setCache(['public', 'immutable', 'max-age' => 31536000])
            ->setBody((string) file_get_contents($path));
    }
}
