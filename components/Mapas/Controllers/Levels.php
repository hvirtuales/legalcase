<?php

namespace Components\Mapas\Controllers;

use Components\Mapas\Models\LevelModel;

/**
 * Niveles de un mapa. Responden JSON: los llama el editor sin recargar.
 *
 * Todas las acciones comprueban **las dos puntas** —que el nivel es del mapa y
 * que el mapa es de la empresa activa—: tener el permiso `map.edit` no autoriza
 * a tocar el nivel de otra empresa, y el id llega de un POST.
 */
class Levels extends BaseMap
{
    private LevelModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new LevelModel();
    }

    /** Alta de nivel: nombre y plano. */
    public function store($mapId)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($mapId, $companyId) : null;
        if (!$map) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return $this->fail(lang('Map.vrLevel'));
        }

        $data = [
            'company_id' => $companyId,
            'map_id'     => (int) $mapId,
            'name'       => $name,
            'sort_order' => $this->model->nextOrder((int) $mapId),
        ];

        $file = $this->request->getFile('image');
        if ($file && $file->isValid()) {
            $img = $this->svc->storeImage($file, $companyId, true);
            if (!$img['ok']) {
                return $this->fail($img['error']);
            }
            $data['image']      = $img['path'];
            $data['img_width']  = $img['width'];
            $data['img_height'] = $img['height'];
        }

        if (!$this->model->insert($data)) {
            return $this->fail(implode(' ', $this->model->errors()));
        }

        return $this->ok(['id' => $this->model->getInsertID()]);
    }

    /** Renombrar, activar/desactivar o reemplazar el plano. */
    public function update($id)
    {
        $companyId = $this->companyId();
        $level     = $companyId ? $this->svc->ownedLevel($id, $companyId) : null;
        if (!$level) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        $data = [];
        if ($this->request->getPost('name') !== null) {
            $name = trim((string) $this->request->getPost('name'));
            if ($name === '') {
                return $this->fail(lang('Map.vrLevel'));
            }
            $data['name'] = $name;
        }
        if ($this->request->getPost('is_active') !== null) {
            $data['is_active'] = $this->request->getPost('is_active') ? 1 : 0;
        }

        $file = $this->request->getFile('image');
        if ($file && $file->isValid()) {
            $img = $this->svc->storeImage($file, $companyId, true);
            if (!$img['ok']) {
                return $this->fail($img['error']);
            }
            // El plano viejo se borra: si no, cada reemplazo deja un archivo de
            // varios megas que nadie vuelve a mirar.
            $this->svc->deleteImage($level['image']);
            $data['image']      = $img['path'];
            $data['img_width']  = $img['width'];
            $data['img_height'] = $img['height'];
        }

        if ($data) {
            $this->model->update($id, $data);
        }

        return $this->ok();
    }

    /** Reordenar: llega la lista de ids en el orden nuevo. */
    public function reorder($mapId)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($mapId, $companyId) : null;
        if (!$map) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        // Mismo cuidado que en `Points::move`: `getJSON()` lanza excepción si el
        // cuerpo no es JSON, así que primero se mira el Content-Type.
        $esJson = str_contains((string) $this->request->getHeaderLine('Content-Type'), 'json');
        $cuerpo = $esJson ? (array) $this->request->getJSON(true) : (array) $this->request->getPost();
        $ids    = (array) ($cuerpo['ids'] ?? []);
        $n   = 0;
        foreach ($ids as $id) {
            // Cada id se comprueba contra el mapa: una lista manipulada no puede
            // colar el nivel de otro.
            if ($this->svc->ownedLevel($id, $companyId, (int) $mapId)) {
                $this->model->update((int) $id, ['sort_order' => ++$n]);
            }
        }

        return $this->ok();
    }

    public function delete($id)
    {
        $companyId = $this->companyId();
        $level     = $companyId ? $this->svc->ownedLevel($id, $companyId) : null;
        if (!$level) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }
        $this->svc->deleteLevel((int) $id, $companyId);

        return $this->ok();
    }
}
