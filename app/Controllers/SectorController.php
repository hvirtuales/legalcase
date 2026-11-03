<?php

namespace App\Controllers;

use App\Models\SectorModel;

/**
 * Catálogo de **sectores** — administración de plataforma (Super Admin).
 *
 * Las rutas ya van protegidas por `permission:sectors.*` en `Config/Routes.php`,
 * así que aquí no se vuelve a comprobar el permiso: repetirlo en el controlador
 * es la clase de duplicado que un día se queda a medias.
 */
class SectorController extends BaseController
{
    private SectorModel $sectors;

    public function __construct()
    {
        $this->sectors = new SectorModel();
    }

    public function index()
    {
        $dt = (new \App\Libraries\DataTable($this->sectors, $this->request))
            ->searchable(['name', 'description'])
            ->sortable(['id', 'name', 'sort_order', 'is_active'])
            ->defaultSort('sort_order', 'asc')
            ->paginate(20);

        return view('sectors/index', [
            'title'  => lang('Sector.sectors'),
            'dt'     => $dt,
            'counts' => $this->sectors->companyCounts(),
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'POST') {
            return $this->store(null);
        }

        return view('sectors/form', [
            'title'  => lang('Sector.newSector'),
            'sector' => null,
        ]);
    }

    public function edit($id = null)
    {
        $sector = $this->sectors->find((int) $id);
        if (!$sector) {
            return redirect()->to('/sectors')->with('error', lang('Sector.errNotFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store((int) $id);
        }

        return view('sectors/form', [
            'title'  => lang('Sector.editSector'),
            'sector' => $sector,
        ]);
    }

    /** Alta y edición comparten el guardado: es el mismo formulario. */
    private function store(?int $id)
    {
        $r    = $this->request;
        $name = trim((string) $r->getPost('name'));

        // La unicidad se valida AQUÍ con el id real: `is_unique[...,id,{id}]`
        // no rellena el marcador en `update()` y el propio registro contaría
        // como duplicado (gotcha conocido de CI4 4.6 en este proyecto).
        $reglas = [
            'name'  => 'required|min_length[2]|max_length[100]|is_unique[sectors.name,id,' . ($id ?? 0) . ']',
            'color' => 'permit_empty|regex_match[/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/]',
        ];
        if (!$this->validate($reglas)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name'        => $name,
            'slug'        => $this->sectors->uniqueSlug($name, $id),
            'description' => trim((string) $r->getPost('description')) ?: null,
            'icon'        => trim((string) $r->getPost('icon')) ?: null,
            'color'       => trim((string) $r->getPost('color')) ?: null,
            'sort_order'  => (int) $r->getPost('sort_order'),
            'is_active'   => $r->getPost('is_active') ? 1 : 0,
        ];

        $this->sectors->skipValidation(true);
        $ok = $id ? $this->sectors->update($id, $data) : $this->sectors->insert($data);
        $this->sectors->skipValidation(false);

        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->sectors->errors());
        }

        return redirect()->to('/sectors')
            ->with('success', $id ? lang('Sector.okUpdated') : lang('Sector.okCreated'));
    }

    /**
     * Borrar un sector **no borra empresas**: las deja sin clasificar.
     *
     * El soft-delete del sector no dispara ninguna FK —de hecho no hay FK a
     * propósito, ver la migración—, así que las empresas se liberan aquí de
     * forma explícita. La confirmación de la vista avisa de cuántas son.
     */
    public function delete($id = null)
    {
        $sector = $this->sectors->find((int) $id);
        if (!$sector) {
            return redirect()->to('/sectors')->with('error', lang('Sector.errNotFound'));
        }

        $db      = db_connect();
        $sueltas = $db->table('companies')
            ->where('sector_id', (int) $id)
            ->where('deleted_at IS NULL')
            ->countAllResults();

        $db->table('companies')->where('sector_id', (int) $id)->update(['sector_id' => null]);
        $this->sectors->delete((int) $id);

        return redirect()->to('/sectors')->with(
            'success',
            $sueltas > 0
                ? lang('Sector.okDeletedWith', [$sueltas])
                : lang('Sector.okDeleted')
        );
    }
}
