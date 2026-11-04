<?php

namespace Components\LegalCase\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use Components\LegalCase\Libraries\HelpGuide;

/**
 * Ayuda de LegalCase: `GET legalcase/ayuda` y sus capturas de referencia.
 *
 * Pasa por `BaseLgc::_remap()` como cualquier pantalla interna (empresa activa y plan),
 * y las imágenes se sirven por aquí y no desde `public/` porque el núcleo no publica
 * assets de componentes: viajan en `Assets/help` con la carpeta.
 */
class Help extends BaseLgc
{
    public function index()
    {
        $acc   = $this->acc();
        $perms = [];
        foreach (HelpGuide::PERMS as $p) {
            $perms['lgc.' . $p] = $acc->can('lgc.' . $p);
        }

        return view('Components\LegalCase\Views\help', [
            'title'  => lang('Lgc.navHelp'),
            'perms'  => $perms,
            'locale' => service('request')->getLocale(),
        ]);
    }

    /** Una captura de la guía. Lista blanca: ningún nombre de la URL toca el disco si no está en `HelpGuide`. */
    public function image(string $name = '')
    {
        if (! isset(HelpGuide::allowedImages()[$name])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $path = dirname(__DIR__) . '/Assets/help/' . $name;
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        // `setCache()` retira el `no-store` con que nace la respuesta de CI4. `private`:
        // está detrás de la sesión, que ningún proxy compartido la guarde.
        return $this->response
            ->setHeader('Content-Type', 'image/webp')
            ->setCache(['private', 'max-age' => 86400])
            ->setBody((string) file_get_contents($path));
    }
}
