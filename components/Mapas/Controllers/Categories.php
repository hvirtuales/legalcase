<?php

namespace Components\Mapas\Controllers;

use Components\Mapas\Libraries\MapService;
use Components\Mapas\Models\CategoryModel;
use Components\Mapas\Models\PointModel;

/** Categorías de punto: su nombre y el color del pin. Por empresa. */
class Categories extends BaseMap
{
    private CategoryModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CategoryModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Mapas\Views\categories\index', ['title' => lang('Map.categories'), 'rows' => null]);
        }

        $rows = $this->model->where('company_id', $companyId)
            ->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->findAll();

        // Cuántos puntos usa cada una: es lo que hace falta saber antes de
        // borrarla, y es la pregunta que se hace cualquiera al mirar la lista.
        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['id']] = (new PointModel())->where('category_id', $r['id'])->countAllResults();
        }

        return view('Components\Mapas\Views\categories\index', [
            'title'  => lang('Map.categories'),
            'rows'   => $rows,
            'counts' => $counts,
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('mapas/categorias')->with('error', lang('Map.mNoCompany'));
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save(null, $companyId);
        }

        return view('Components\Mapas\Views\categories\form', ['title' => lang('Map.newCategory'), 'row' => []]);
    }

    public function edit($id)
    {
        $companyId = $this->companyId();
        $row       = $companyId ? $this->mine($this->model, $id, $companyId) : null;
        if (!$row) {
            return redirect()->to('mapas/categorias')->with('error', lang('Map.mNotFound'));
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save((int) $id, $companyId);
        }

        return view('Components\Mapas\Views\categories\form', ['title' => lang('Map.editCategory'), 'row' => $row]);
    }

    private function save(?int $id, int $companyId)
    {
        $name = trim((string) $this->request->getPost('name'));
        if ($this->model->nameTaken($companyId, $name, $id)) {
            return redirect()->back()->withInput()->with('errors', [lang('Map.mCategoryTaken')]);
        }

        $data = [
            'company_id' => $companyId,
            'name'       => $name,
            // El color se valida aquí: acaba en un `style` de la página pública
            // y en el pin del lienzo.
            'color'      => MapService::color($this->request->getPost('color')),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'is_active'  => $this->request->getPost('is_active') ? 1 : 0,
        ];

        $ok = $id ? $this->model->update($id, $data) : $this->model->insert($data);
        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('mapas/categorias')->with('success', lang('Map.mSaved'));
    }

    /**
     * Borrar una categoría **no borra sus puntos**: los deja sin clasificar.
     *
     * La FK es `SET NULL`, pero el soft-delete de la categoría no la dispara,
     * así que hay que soltarlos a mano. Es el mismo caso que los sectores del
     * núcleo con las empresas.
     */
    public function delete($id)
    {
        $companyId = $this->companyId();
        $row       = $companyId ? $this->mine($this->model, $id, $companyId) : null;
        if (!$row) {
            return redirect()->to('mapas/categorias')->with('error', lang('Map.mNotFound'));
        }

        (new PointModel())->where('category_id', $id)->set(['category_id' => null])->update();
        $this->model->delete($id);

        return redirect()->to('mapas/categorias')->with('success', lang('Map.mCategoryDeleted'));
    }
}
