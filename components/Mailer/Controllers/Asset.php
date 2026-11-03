<?php

namespace Components\Mailer\Controllers;

use CodeIgniter\Controller;

/**
 * Sirve el CSS y el JS del componente desde su propia carpeta.
 *
 * Antes las vistas enlazaban `public/components/mailer/...`, y **esa carpeta no
 * existe**: la publicación de assets que describe el contrato 2 nunca se
 * implementó en el núcleo (`ComponentManager` no copia nada a `public/`). El
 * resultado era que el editor de plantillas **no cargaba**: se veía un hueco
 * vacío y se guardaba un diseño vacío.
 *
 * La ruta es una LISTA BLANCA FIJA, no un nombre concatenado a un directorio:
 * no hay forma de pedir un archivo que no esté en este mapa.
 */
class Asset extends Controller
{
    private const FILES = [
        'builder.js'  => ['file' => 'js/builder.js', 'type' => 'application/javascript; charset=UTF-8'],
        'builder.css' => ['file' => 'css/builder.css', 'type' => 'text/css; charset=UTF-8'],
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

        $etag = '"' . dechex((int) filemtime($path)) . '-' . dechex((int) filesize($path)) . '"';

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
