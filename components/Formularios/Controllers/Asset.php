<?php

namespace Components\Formularios\Controllers;

use CodeIgniter\Controller;

/**
 * Sirve el CSS y el JS del componente desde su propia carpeta.
 *
 * ¿Por qué un controlador y no `public/components/frm/`? Porque la publicación
 * de assets que describe el contrato 2 NUNCA se implementó en el núcleo:
 * `ComponentManager` no copia nada a `public/` y ningún componente usa esa
 * carpeta. Servirlos desde aquí mantiene el componente drop-in de verdad: se
 * copia la carpeta y funciona, sin tocar `app/` ni `public/`.
 *
 * La ruta es una LISTA BLANCA FIJA, no un nombre concatenado a un directorio:
 * no hay forma de pedir un archivo que no esté en este mapa.
 */
class Asset extends Controller
{
    private const FILES = [
        'editor.js'  => ['file' => 'js/editor.js', 'type' => 'application/javascript; charset=UTF-8'],
        'editor.css' => ['file' => 'css/editor.css', 'type' => 'text/css; charset=UTF-8'],
        'form.js'    => ['file' => 'js/form.js', 'type' => 'application/javascript; charset=UTF-8'],
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
