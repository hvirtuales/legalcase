<?php

namespace Components\Mapas\Controllers;

use Components\Mapas\Models\CategoryModel;

/** El lienzo: donde se suben los planos y se colocan los puntos. */
class Editor extends BaseMap
{

    public function index($mapId)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($mapId, $companyId) : null;
        if (!$map) {
            return redirect()->to('mapas')->with('error', lang('Map.mNotFound'));
        }

        return view('Components\Mapas\Views\maps\editor', [
            'title'      => lang('Map.editorTitle', [$map['name']]),
            'map'        => $map,
            // El MISMO payload que consume la página pública: si cada uno
            // armara el suyo acabarían enseñando cosas distintas.
            'payload'    => $this->svc->payload($map),
            'categories' => (new CategoryModel())->forCompany($companyId),
            'link'       => !empty($map['is_published']) && !empty($map['public_token'])
                ? base_url('mapa/' . $map['public_token'])
                : '',
        ]);
    }

    /** El árbol del mapa, para refrescar el lienzo sin recargar la página. */
    public function payload($mapId)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($mapId, $companyId) : null;
        if (!$map) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        return $this->ok(['data' => $this->svc->payload($map)]);
    }

    /**
     * El nombre y la descripción del mapa, guardados **desde el propio editor**.
     *
     * Existe la pantalla `/mapas/editar/{id}` para lo mismo, y se queda: es por
     * donde se entra desde el listado. Pero salir del editor para cambiarle el
     * nombre al mapa que se está montando —y volver a entrar— es un viaje que
     * no lleva a ninguna parte. Solo estos dos campos: el plan, el dueño y el
     * estado siguen siendo del panel, no del lienzo.
     */
    public function settings($mapId)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($mapId, $companyId) : null;
        if (!$map) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        $nombre = trim((string) $this->request->getPost('name'));
        if ($nombre === '') {
            return $this->fail(lang('Map.vrName'));
        }

        $datos = [
            'name'        => $nombre,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            // Ausente = marcado: el interruptor solo viaja cuando está encendido,
            // que es como manda un checkbox.
            'show_header' => $this->request->getPost('show_header') ? 1 : 0,
        ];

        // El logo del mapa: vacío = el de la empresa, que es lo que hacía antes.
        $file = $this->request->getFile('logo');
        if ($file && $file->isValid()) {
            $img = $this->svc->storeImage($file, $companyId, false);
            if (!$img['ok']) {
                return $this->fail($img['error']);
            }
            $this->svc->deleteImage($map['logo'] ?? null);
            $datos['logo'] = $img['path'];
        } elseif ($this->request->getPost('logo_clear')) {
            $this->svc->deleteImage($map['logo'] ?? null);
            $datos['logo'] = null;
        }

        (new \Components\Mapas\Models\MapModel())->update((int) $map['id'], $datos);

        return $this->ok([
            'name' => $nombre,
            'logo' => $datos['logo'] ?? ($map['logo'] ?? null),
        ]);
    }
}
