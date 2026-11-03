<?php

namespace Components\Paginas\Controllers;

use CodeIgniter\Controller;

/**
 * Sirve el CSS y el JS del editor desde la carpeta del componente.
 *
 * ¿Por qué un controlador y no `public/components/pag/`? Porque la publicación
 * de assets que describe el contrato §2 **nunca se implementó en el núcleo**:
 * `ComponentManager` no copia nada a `public/`, y ningún componente usa esa
 * carpeta. Servirlos desde aquí mantiene el componente drop-in de verdad —se
 * copia la carpeta y funciona— sin tocar `app/`.
 *
 * La ruta es una **lista blanca fija**, no un nombre de archivo concatenado a un
 * directorio: no hay forma de pedir `../../.env` (contrato §12).
 */
class Asset extends Controller
{
    /** Único mapa de recursos servibles. Lo que no esté aquí, no existe. */
    private const FILES = [
        'editor.js'  => ['file' => 'js/editor.js', 'type' => 'application/javascript; charset=UTF-8'],
        'editor.css' => ['file' => 'css/editor.css', 'type' => 'text/css; charset=UTF-8'],
    ];

    public function serve(?string $name = null)
    {
        $entry = self::FILES[(string) $name] ?? null;
        if (!$entry) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        $path = dirname(__DIR__) . '/assets/' . $entry['file'];
        if (!is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        $mtime = (int) filemtime($path);
        $etag  = '"' . dechex($mtime) . '-' . dechex((int) filesize($path)) . '"';

        // Si el navegador ya lo tiene, no se reenvía.
        if (trim((string) $this->request->getHeaderLine('If-None-Match')) === $etag) {
            return $this->response->setStatusCode(304);
        }

        return $this->response
            ->setHeader('Content-Type', $entry['type'])
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setHeader('ETag', $etag)
            ->setBody((string) file_get_contents($path));
    }
}
