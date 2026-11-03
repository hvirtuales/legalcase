<?php

namespace Components\Mapas\Controllers;

use App\Libraries\DataTable;
use Components\Mapas\Libraries\PlanGuard;
use Components\Mapas\Models\LevelModel;
use Components\Mapas\Models\MapModel;
use Components\Mapas\Models\PointModel;

/** Alta, baja y publicación de mapas. Todo aislado por la empresa activa. */
class Maps extends BaseMap
{
    private MapModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new MapModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Mapas\Views\maps\index', ['title' => lang('Map.maps'), 'dt' => null]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'description'])
            ->sortable(['name', 'is_published', 'created_at'])
            ->defaultSort('created_at', 'desc')
            ->query(fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        $guard = new PlanGuard();

        // Cuántos niveles y puntos tiene cada mapa: es lo primero que se mira en
        // un listado de mapas, y sin esto todos parecen iguales.
        $counts = [];
        foreach ($dt['rows'] as $row) {
            $counts[$row['id']] = [
                'levels' => (new LevelModel())->where('map_id', $row['id'])->countAllResults(),
                'points' => (new PointModel())->where('map_id', $row['id'])->countAllResults(),
            ];
        }

        return view('Components\Mapas\Views\maps\index', [
            'title'          => lang('Map.maps'),
            'dt'             => $dt,
            'counts'         => $counts,
            'serviceEnabled' => $guard->serviceEnabled($companyId),
            'left'           => $guard->mapsLeft($companyId),
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('mapas')->with('error', lang('Map.mNoCompany'));
        }
        // El cupo se comprueba AQUÍ y no solo escondiendo el botón: un POST
        // directo se salta la vista.
        if (!(new PlanGuard())->canCreateMap($companyId)) {
            return redirect()->to('mapas')->with('error', lang('Map.mNoQuota'));
        }

        if ($this->request->getMethod() === 'POST') {
            $data = [
                'company_id'  => $companyId,
                'name'        => trim((string) $this->request->getPost('name')),
                'description' => trim((string) $this->request->getPost('description')) ?: null,
            ];
            if (!$this->model->insert($data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }

            // Al editor directamente: un mapa sin niveles no sirve de nada, y
            // volver al listado obligaría a buscarlo para empezar a trabajar.
            return redirect()->to('mapas/editor/' . $this->model->getInsertID())
                ->with('success', lang('Map.mCreated'));
        }

        return view('Components\Mapas\Views\maps\form', ['title' => lang('Map.newMap'), 'map' => []]);
    }

    public function edit($id)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($id, $companyId) : null;
        if (!$map) {
            return redirect()->to('mapas')->with('error', lang('Map.mNotFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            $data = [
                'name'        => trim((string) $this->request->getPost('name')),
                'description' => trim((string) $this->request->getPost('description')) ?: null,
            ];
            if (!$this->model->update($id, $data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }

            return redirect()->to('mapas')->with('success', lang('Map.mSaved'));
        }

        return view('Components\Mapas\Views\maps\form', ['title' => lang('Map.editMap'), 'map' => $map]);
    }

    /** Publica o despublica. El token se crea la primera vez y no cambia nunca. */
    public function publish($id)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($id, $companyId) : null;
        if (!$map) {
            return redirect()->to('mapas')->with('error', lang('Map.mNotFound'));
        }

        $publicar = empty($map['is_published']);
        if ($publicar) {
            $this->svc->tokenFor((int) $id);
        }
        $this->model->update($id, ['is_published' => $publicar ? 1 : 0]);

        return redirect()->back()->with('success', $publicar ? lang('Map.mPublished') : lang('Map.mUnpublished'));
    }

    public function delete($id)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($id, $companyId) : null;
        if (!$map) {
            return redirect()->to('mapas')->with('error', lang('Map.mNotFound'));
        }
        $this->svc->deleteMap((int) $id, $companyId);

        return redirect()->to('mapas')->with('success', lang('Map.mDeleted'));
    }

    /**
     * Copia el mapa entero y entra a editarlo.
     *
     * El cupo del plan se comprueba **aquí también**: una copia es un mapa más,
     * y el botón escondido no es control —un POST directo se salta la vista—.
     */
    public function duplicate($id)
    {
        $companyId = $this->companyId();
        $map       = $companyId ? $this->svc->owned($id, $companyId) : null;
        if (!$map) {
            return redirect()->to('mapas')->with('error', lang('Map.mNotFound'));
        }
        if (!(new PlanGuard())->canCreateMap($companyId)) {
            return redirect()->to('mapas')->with('error', lang('Map.mNoQuota'));
        }

        $nuevo = $this->svc->duplicateMap((int) $id, $companyId);
        if (!$nuevo) {
            return redirect()->to('mapas')->with('error', lang('Map.mCopyFailed'));
        }

        // Al editor de la copia, como al crear: lo que se quiere hacer con una
        // copia es cambiarle algo.
        return redirect()->to('mapas/editor/' . $nuevo)->with('success', lang('Map.mCopied'));
    }
}
