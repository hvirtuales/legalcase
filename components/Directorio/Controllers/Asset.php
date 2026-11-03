<?php

namespace Components\Directorio\Controllers;

use CodeIgniter\Controller;

/**
 * Sirve los recursos del componente: **Leaflet auto-hospedado**.
 *
 * La página pública del directorio no puede pedirle nada a un CDN (contrato
 * base §7.3), y un mapa necesita una biblioteca. La solución es la misma que en
 * el núcleo con Inter y con intl-tel-input: **traerse el archivo**. Leaflet
 * 1.9.4 vive en `assets/vendor/leaflet/` dentro de la carpeta del componente,
 * así que llevarse la carpeta a otra plataforma sigue funcionando.
 *
 * ¿Por qué un controlador y no `public/components/dir/`? Porque la publicación
 * de assets que describe el contrato §2 **nunca se implementó en el núcleo**:
 * `ComponentManager` no copia nada a `public/`. Servirlos desde aquí es lo que
 * mantiene el componente drop-in de verdad.
 *
 * La ruta es una **lista blanca fija**, no un nombre concatenado a un
 * directorio: no hay forma de pedir `../../.env` (contrato §12).
 */
class Asset extends Controller
{
    /** Único mapa de recursos servibles. Lo que no esté aquí, no existe. */
    private const FILES = [
        'leaflet.css' => ['file' => 'vendor/leaflet/leaflet.css', 'type' => 'text/css; charset=UTF-8'],
        'leaflet.js'  => ['file' => 'vendor/leaflet/leaflet.js', 'type' => 'application/javascript; charset=UTF-8'],
    ];

    /**
     * Las imágenes de Leaflet (el marcador y su sombra).
     *
     * Van aparte porque su nombre lo compone la propia biblioteca a partir de
     * `L.Icon.Default.imagePath`, así que la lista blanca tiene que cubrir
     * exactamente esos cinco nombres y ninguno más.
     */
    private const IMAGES = [
        'marker-icon.png', 'marker-icon-2x.png', 'marker-shadow.png',
        'layers.png', 'layers-2x.png',
    ];

    public function show(?string $name = null)
    {
        $entry = self::FILES[(string) $name] ?? null;
        if (!$entry) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        return $this->send(dirname(__DIR__) . '/assets/' . $entry['file'], $entry['type']);
    }

    public function image(?string $name = null)
    {
        if (!in_array((string) $name, self::IMAGES, true)) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        return $this->send(dirname(__DIR__) . '/assets/vendor/leaflet/images/' . $name, 'image/png');
    }

    private function send(string $path, string $type)
    {
        if (!is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        $etag = '"' . dechex((int) filemtime($path)) . '-' . dechex((int) filesize($path)) . '"';

        // Si el navegador ya lo tiene, no se reenvía.
        if (trim((string) $this->request->getHeaderLine('If-None-Match')) === $etag) {
            return $this->response->setStatusCode(304);
        }

        return $this->response
            ->setHeader('Content-Type', $type)
            // Un año: la versión de Leaflet está congelada en la carpeta, así
            // que el archivo servido en esta ruta no cambia nunca.
            ->setHeader('Cache-Control', 'public, max-age=31536000')
            ->setHeader('ETag', $etag)
            ->setBody((string) file_get_contents($path));
    }
}
