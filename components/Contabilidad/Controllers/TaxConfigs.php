<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Contabilidad\Libraries\CatalogService;
use Components\Contabilidad\Models\TaxConfigModel;

class TaxConfigs extends BaseController
{
    private TaxConfigModel $model;

    public function __construct()
    {
        $this->model = new TaxConfigModel();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Contabilidad\Views\taxes\index', ['title' => 'Impuestos', 'dt' => null]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'code'])
            ->sortable(['name', 'code', 'category', 'rate', 'sort_order', 'is_active'])
            ->defaultSort('sort_order', 'asc')
            ->query(fn ($m) => $m->where('company_id', $companyId))
            ->paginate(20);

        return view('Components\Contabilidad\Views\taxes\index', ['title' => 'Impuestos', 'dt' => $dt]);
    }

    public function seed()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('contabilidad/impuestos')->with('error', 'Selecciona una empresa activa.');
        }
        $n = (new CatalogService())->seedTaxes($companyId);

        return redirect()->to('contabilidad/impuestos')->with('success', $n > 0 ? "Se agregaron {$n} impuestos por defecto." : 'Los impuestos por defecto ya estaban cargados.');
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('contabilidad/impuestos')->with('error', 'Selecciona una empresa activa.');
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save(null, $companyId);
        }

        return view('Components\Contabilidad\Views\taxes\form', ['title' => 'Nuevo impuesto', 'tax' => []]);
    }

    public function edit($id)
    {
        $companyId = $this->companyId();
        $tax       = $this->model->find($id);
        if (!$tax || (int) $tax['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/impuestos')->with('error', 'Impuesto no encontrado.');
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save((int) $id, $companyId);
        }

        return view('Components\Contabilidad\Views\taxes\form', ['title' => 'Editar impuesto', 'tax' => $tax]);
    }

    private function save(?int $id, int $companyId)
    {
        $code = trim((string) $this->request->getPost('code'));
        $dup  = $this->model->where('company_id', $companyId)->where('code', $code);
        if ($id) {
            $dup->where('id !=', $id);
        }
        if ($code !== '' && $dup->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'Ya existe un impuesto con ese código en esta empresa.');
        }

        $data = [
            'company_id'  => $companyId,
            'name'        => trim((string) $this->request->getPost('name')),
            'code'        => $code,
            'category'    => $this->request->getPost('category') ?: 'iva',
            'rate'        => $this->request->getPost('rate') ?: 0,
            'is_additive' => $this->request->getPost('is_additive') ? 1 : 0,
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
            'sort_order'  => (int) $this->request->getPost('sort_order'),
        ];

        $ok = $id ? $this->model->update($id, $data) : $this->model->insert($data);
        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('contabilidad/impuestos')->with('success', 'Impuesto guardado.');
    }

    public function delete($id)
    {
        $companyId = $this->companyId();
        $tax       = $this->model->find($id);
        if (!$tax || (int) $tax['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/impuestos')->with('error', 'Impuesto no encontrado.');
        }
        $this->model->delete($id);

        return redirect()->to('contabilidad/impuestos')->with('success', 'Impuesto eliminado.');
    }
}
