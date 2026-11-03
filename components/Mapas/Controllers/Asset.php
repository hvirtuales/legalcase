<?php

namespace Components\Mapas\Controllers;

use CodeIgniter\Controller;

/**
 * Sirve los assets del componente desde su carpeta.
 *
 * La publicación a `public/components/<code>/` que describe el contrato §2
 * **nunca se implementó en el núcleo** (`ComponentManager` no copia nada), así
 * que enlazar ahí daría 404. Servirlos desde aquí es lo que mantiene el
 * componente drop-in de verdad.
 *
 * Lista blanca FIJA: el nombre llega de la URL, y sin ella un `../../.env`
 * sería descargable.
 */
class Asset extends Controller
{
    private const FILES = [
        'editor.js'  => ['file' => 'js/editor.js', 'type' => 'application/javascript; charset=UTF-8'],
        'viewer.js'  => ['file' => 'js/viewer.js', 'type' => 'application/javascript; charset=UTF-8'],
        'editor.css' => ['file' => 'css/editor.css', 'type' => 'text/css; charset=UTF-8'],
    ];

    public function serve(?string $name = null)
    {
        $entry = self::FILES[(string) $name] ?? null;
        if (!$entry) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $path = dirname(__DIR__) . '/assets/' . $entry['file'];
        if (!is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $etag = '"' . dechex((int) filemtime($path)) . '-' . dechex((int) filesize($path)) . '"';
        if (trim((string) $this->request->getHeaderLine('If-None-Match')) === $etag) {
            return $this->response->setStatusCode(304);
        }

        return $this->response
            ->setHeader('Content-Type', $entry['type'])
            ->setHeader('Cache-Control', 'public, max-age=604800')
            ->setHeader('ETag', $etag)
            ->setBody((string) file_get_contents($path));
    }
}
