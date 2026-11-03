<?php

namespace Components\Mapas\Controllers;

use Components\Mapas\Libraries\MapService;
use Components\Mapas\Models\CategoryModel;
use Components\Mapas\Models\PointModel;

/**
 * Puntos de interés. Responden JSON: el lienzo los crea, los mueve y los edita
 * sin recargar la página.
 *
 * Las coordenadas llegan del navegador y **el servidor no se las cree**: las
 * acota a 0–100 (`PointModel::clamp`). Un punto en 300 % quedaría fuera del
 * plano, invisible y sin forma de recuperarlo desde la interfaz.
 */
class Points extends BaseMap
{
    private PointModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new PointModel();
    }

    /**
     * La categoría que llega del formulario, validada contra la empresa.
     * Sin esto se podría clasificar un punto con la categoría de otra empresa
     * cambiando el `<select>` en el navegador.
     */
    private function categoryFor($raw, int $companyId): ?int
    {
        $id = (int) $raw;
        if ($id <= 0) {
            return null;
        }
        $cat = (new CategoryModel())->find($id);

        return ($cat && (int) $cat['company_id'] === $companyId) ? $id : null;
    }

    /**
     * Los campos descriptivos, los que se guardan al crear y al editar.
     *
     * **`kind` y `geometry` NO salen de aquí, a propósito.** `update()` reescribe
     * lo que le entrega este método, y el editor manda el formulario ENTERO al
     * ocultar o mostrar una forma (`alternarVisible`): si la geometría viajara
     * por aquí, pulsar el ojo sobre un polígono lo convertiría en un pin. El
     * tipo se fija al crear y la geometría cambia por su propia ruta, igual que
     * ya se hizo con `move()`.
     */
    private function fields(int $companyId): array
    {
        return [
            'title'       => trim((string) $this->request->getPost('title')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            // El enlace se filtra AL GUARDAR además de al pintar: un
            // `javascript:` no tiene por qué llegar siquiera a la base.
            'url'         => MapService::safeUrl($this->request->getPost('url')) ?: null,
            'category_id' => $this->categoryFor($this->request->getPost('category_id'), $companyId),
            'is_active'   => $this->request->getPost('is_active') === null ? 1 : ($this->request->getPost('is_active') ? 1 : 0),
        ];
    }

    /**
     * La presentación (relleno, transparencia, color y tipo de borde…), **solo
     * si el formulario la manda**.
     *
     * Es la misma regla que la geometría, por la misma razón: el editor manda
     * el formulario entero al ocultar o mostrar un elemento, y ese formulario no
     * lleva los colores. Si `fields()` los incluyera, pulsar el ojo se los
     * llevaría por delante. Se sanea contra la lista blanca del modelo: lo que
     * no esté ahí no entra, y lo que se salga de rango se acota.
     */
    private function styleIfSent(array $data): array
    {
        $raw = $this->request->getPost('style');
        if ($raw !== null) {
            $data['style'] = PointModel::styleProps($raw);
        }

        return $data;
    }

    /**
     * De quién cuelga el elemento, **solo si el formulario lo manda**.
     *
     * Misma regla que la geometría y la presentación, y por la misma razón: el
     * editor manda el formulario ENTERO al ocultar o mostrar un elemento, y ese
     * formulario no lleva el padre. Si `fields()` lo incluyera, pulsar el ojo
     * sobre una ruta la desvincularía de su local y la dejaría suelta en el
     * plano —donde ni siquiera se dibuja—, sin ningún aviso.
     *
     * Cuatro comprobaciones, todas en el servidor porque el id llega de un POST:
     * que el padre exista y sea de esta empresa, que sea **del mismo mapa** —una
     * ruta de otro centro comercial no significa nada—, que no sea el propio
     * elemento, y que **no sea ya un hijo**. Lo último es lo que mantiene el
     * árbol en dos alturas, que es lo que asumen el pintado, la ficha y la
     * cascada del directorio.
     */
    private function parentIfSent(array $data, int $companyId, ?array $point, int $mapId): array
    {
        $raw = $this->request->getPost('parent_id');
        if ($raw === null) {
            return $data;
        }

        $id = (int) $raw;
        if ($id <= 0) {
            $data['parent_id'] = null;   // «ninguno»: se suelta del padre

            return $data;
        }

        // Un elemento que YA tiene hijos no puede pasar a ser hijo: sería un
        // tercer nivel por la puerta de atrás.
        if ($point && $this->model->where('parent_id', (int) $point['id'])->countAllResults() > 0) {
            return $data;
        }

        $padre = $this->model->find($id);
        $vale  = $padre
            && (int) $padre['company_id'] === $companyId
            && (int) $padre['map_id'] === $mapId
            && (!$point || (int) $padre['id'] !== (int) $point['id'])
            && empty($padre['parent_id']);

        $data['parent_id'] = $vale ? $id : null;

        return $data;
    }

    public function store($levelId)
    {
        $companyId = $this->companyId();
        $level     = $companyId ? $this->svc->ownedLevel($levelId, $companyId) : null;
        if (!$level) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        // El tipo se fija AL CREAR y no se vuelve a tocar: cambiar un polígono a
        // pin dejaría una geometría huérfana, y al revés, un pin sin vértices
        // convertido en polígono no se podría pintar.
        $kind = PointModel::kind($this->request->getPost('kind'));
        $geom = PointModel::geometry($this->request->getPost('geometry'), $kind, $error);
        if ($error !== null) {
            return $this->fail(lang($error));
        }

        $data = $this->parentIfSent($this->styleIfSent($this->fields($companyId)), $companyId, null, (int) $level['map_id']) + [
            'company_id' => $companyId,
            'map_id'     => (int) $level['map_id'],
            'level_id'   => (int) $levelId,
            'kind'       => $kind,
            'geometry'   => $geom,
        ];

        // En una forma, `x`/`y` son su ANCLA y las calcula el servidor: la del
        // navegador no se usa. En un pin o un rótulo son el punto que se marcó.
        [$data['x'], $data['y']] = $geom
            ? PointModel::anchor($geom, $kind)
            : [PointModel::clamp($this->request->getPost('x')), PointModel::clamp($this->request->getPost('y'))];

        if ($data['title'] === '') {
            return $this->fail(lang('Map.vrTitle'));
        }

        $file = $this->request->getFile('image');
        if ($file && $file->isValid()) {
            $img = $this->svc->storeImage($file, $companyId, false);
            if (!$img['ok']) {
                return $this->fail($img['error']);
            }
            $data['image'] = $img['path'];
        }

        if (!$this->model->insert($data)) {
            return $this->fail(implode(' ', $this->model->errors()));
        }

        return $this->ok(['id' => $this->model->getInsertID()]);
    }

    public function update($id)
    {
        $companyId = $this->companyId();
        $point     = $companyId ? $this->mine($this->model, $id, $companyId) : null;
        if (!$point) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        $data = $this->parentIfSent(
            $this->styleIfSent($this->fields($companyId)),
            $companyId,
            $point,
            (int) $point['map_id']
        );
        if ($data['title'] === '') {
            return $this->fail(lang('Map.vrTitle'));
        }

        $file = $this->request->getFile('image');
        if ($file && $file->isValid()) {
            $img = $this->svc->storeImage($file, $companyId, false);
            if (!$img['ok']) {
                return $this->fail($img['error']);
            }
            $this->svc->deleteImage($point['image']);
            $data['image'] = $img['path'];
        }

        $this->model->update($id, $data);

        return $this->ok();
    }

    /**
     * Mover un elemento. Va aparte del guardado completo porque se llama al
     * soltar y solo toca dos números: mandar el formulario entero en cada
     * arrastre sería absurdo.
     *
     * En una **forma** el destino es su ancla nueva, y lo que se guarda es la
     * geometría **trasladada entera** por ese desplazamiento: si solo se moviera
     * el ancla, la ficha se separaría del dibujo.
     */
    public function move($id)
    {
        $companyId = $this->companyId();
        $point     = $companyId ? $this->mine($this->model, $id, $companyId) : null;
        if (!$point) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        // OJO: `getJSON()` **lanza** `HTTPException` si el cuerpo no es JSON;
        // no devuelve null, así que `getJSON() ?: getPost()` da un 500 con
        // «Failed to parse JSON string» en cuanto llega un formulario normal.
        // Se mira el Content-Type antes de intentarlo.
        $in = str_contains((string) $this->request->getHeaderLine('Content-Type'), 'json')
            ? (array) $this->request->getJSON(true)
            : (array) $this->request->getPost();

        $x = PointModel::clamp($in['x'] ?? 0);
        $y = PointModel::clamp($in['y'] ?? 0);

        $geom = is_array($point['geometry'] ?? null) ? $point['geometry'] : [];
        if ($geom) {
            $movida = PointModel::translate($geom, $x - (float) $point['x'], $y - (float) $point['y']);
            // El ancla se recalcula de la geometría ya trasladada y no del punto
            // pedido: `translate()` recorta el desplazamiento si la forma se
            // saldría del plano, y el ancla tiene que seguir a la forma.
            [$x, $y] = PointModel::anchor($movida, PointModel::kind($point['kind'] ?? 'pin'));
            $this->model->update($id, ['x' => $x, 'y' => $y, 'geometry' => $movida]);

            return $this->ok(['geom' => $movida, 'x' => $x, 'y' => $y]);
        }

        $this->model->update($id, ['x' => $x, 'y' => $y]);

        return $this->ok();
    }

    /**
     * Reescribir la geometría de una forma: mover un vértice, añadirlo, quitarlo.
     *
     * Tiene ruta propia y no pasa por `update()` por el mismo motivo que `move()`:
     * el editor manda el formulario ENTERO al ocultar o mostrar un elemento, y
     * si la geometría viajara con él, el ojo de «ocultar» borraría el dibujo.
     *
     * El **tipo no se puede cambiar aquí**: la geometría se valida contra el que
     * ya tiene guardado la fila.
     */
    public function geometry($id)
    {
        $companyId = $this->companyId();
        $point     = $companyId ? $this->mine($this->model, $id, $companyId) : null;
        if (!$point) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        $kind = PointModel::kind($point['kind'] ?? 'pin');
        if (in_array($kind, PointModel::ANCHORED, true)) {
            return $this->fail(lang('Map.vrKind'));   // un pin no tiene vértices
        }

        // `getPost()` a secas: el editor manda `FormData` siempre, y meter aquí
        // un `getJSON()` reabriría el 500 que ya costó una vez (ver `move()`).
        $geom = PointModel::geometry($this->request->getPost('geometry'), $kind, $error);
        if ($error !== null || !$geom) {
            return $this->fail(lang($error ?? 'Map.vrGeometry'));
        }

        [$x, $y] = PointModel::anchor($geom, $kind);
        $this->model->update($id, ['geometry' => $geom, 'x' => $x, 'y' => $y]);

        return $this->ok(['geom' => $geom, 'x' => $x, 'y' => $y]);
    }

    /**
     * Duplicar un elemento: la copia nace al lado, en el mismo nivel.
     *
     * Lleva `map.create` y no `map.edit` por lo mismo que el duplicado de un
     * mapa: duplicar es crear. Las reglas —el desplazamiento, la copia del
     * archivo, el ancla— viven en el servicio, no aquí.
     */
    public function duplicate($id)
    {
        $companyId = $this->companyId();
        $point     = $companyId ? $this->mine($this->model, $id, $companyId) : null;
        if (!$point) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }

        $nuevo = $this->svc->duplicatePoint((int) $id, $companyId);
        if (!$nuevo) {
            return $this->fail(lang('Map.pCopyFailed'));
        }

        return $this->ok(['id' => $nuevo]);
    }

    public function delete($id)
    {
        $companyId = $this->companyId();
        $point     = $companyId ? $this->mine($this->model, $id, $companyId) : null;
        if (!$point) {
            return $this->fail(lang('Map.mNotFound'), 404);
        }
        // Se lleva a sus hijos: un sub-elemento solo se alcanza desde la ficha
        // de su padre, así que sin él quedaría invisible para siempre.
        $n = $this->svc->deletePoint((int) $id, $companyId);

        return $this->ok(['removed' => $n]);
    }
}
