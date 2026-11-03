<?php

namespace App\Controllers;

use App\Models\PlanModel;
use App\Models\UserModel;

class PlanController extends BaseController
{
    protected $planModel;
    protected $userModel;

    public function __construct()
    {
        $this->planModel = new PlanModel();
        $this->userModel = new UserModel();
    }

    public function index()
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'plans.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $dt = (new \App\Libraries\DataTable($this->planModel, $this->request))
            ->searchable(['name', 'description'])
            ->sortable(['id', 'name', 'price_amount', 'service_days', 'max_companies', 'is_active'])
            ->defaultSort('price_amount', 'asc')
            ->paginate(15);

        return view('plans/index', ['dt' => $dt, 'title' => 'Planes']);
    }

    public function create()
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'plans.create')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store();
        }

        return view('plans/create', ['title' => 'Crear Plan']);
    }

    private function store()
    {
        $data = $this->collect();

        if (!$this->planModel->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->planModel->errors());
        }

        return redirect()->to('/plans')->with('success', 'Plan creado correctamente.');
    }

    public function edit($id)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'plans.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $plan = $this->planModel->find($id);
        if (!$plan) {
            return redirect()->to('/plans')->with('error', 'Plan no encontrado.');
        }

        if ($this->request->getMethod() === 'POST') {
            if (!$this->planModel->update($id, $this->collect())) {
                return redirect()->back()->withInput()->with('errors', $this->planModel->errors());
            }

            return redirect()->to('/plans')->with('success', 'Plan actualizado correctamente.');
        }

        return view('plans/edit', ['plan' => $plan, 'title' => 'Editar Plan']);
    }

    public function delete($id)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'plans.delete')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        // Evitar borrar un plan con empresas asociadas
        $inUse = (new \App\Models\CompanyModel())->where('plan_id', $id)->countAllResults();
        if ($inUse > 0) {
            return redirect()->to('/plans')->with('error', 'No se puede eliminar: hay empresas usando este plan.');
        }

        if (!$this->planModel->delete($id)) {
            return redirect()->to('/plans')->with('error', 'No se pudo eliminar el plan.');
        }

        return redirect()->to('/plans')->with('success', 'Plan eliminado.');
    }

    /**
     * Normaliza el POST a los campos del modelo.
     *
     * Los campos de los componentes **no** están listados aquí: salen de
     * `ComponentManager::planFormGroups()`, el mismo sitio del que salen los
     * recuadros del formulario. Dos consecuencias buscadas:
     *
     *  1. Un componente nuevo se configura en el plan sin tocar el núcleo.
     *  2. Los nombres de columna los pone el **Manifest, no el navegador**: un
     *     POST con campos inventados no puede escribir en columnas ajenas.
     *
     * Solo se tocan las columnas del **ámbito** del plan. Las del otro ámbito
     * (y las de los componentes desactivados) se dejan como estaban en vez de
     * ponerlas a 0, que apagaría servicios en silencio.
     */
    private function collect(): array
    {
        $scope = \App\Models\PlanModel::scopeOf(['scope' => $this->request->getPost('scope')]);

        $data = [
            'name'           => $this->request->getPost('name'),
            'description'    => $this->request->getPost('description'),
            'scope'          => $scope,
            'service_days'   => (int) $this->request->getPost('service_days'),
            'price_amount'   => $this->request->getPost('price_amount') ?: 0,
            'price_currency' => strtoupper((string) ($this->request->getPost('price_currency') ?: 'USD')),
            'features'       => $this->request->getPost('features'),
            'is_active'      => $this->request->getPost('is_active') ? 1 : 0,
        ];

        // El cupo de empresas es del plan de tenant: cuántas puede agrupar.
        if ($scope === 'tenant') {
            $data['max_companies'] = (int) $this->request->getPost('max_companies');
        }

        foreach ((new \App\Libraries\ComponentManager())->planFormGroups($scope) as $group) {
            if ($group['flag']) {
                $data[$group['flag']] = $this->request->getPost($group['flag']) ? 1 : 0;
            }
            foreach ($group['fields'] as $field) {
                // Una casilla sin marcar no viaja en el POST: hay que escribir
                // el 0 explícitamente o desactivar un servicio no se guardaría.
                $data[$field['column']] = ($field['type'] ?? 'number') === 'bool'
                    ? ($this->request->getPost($field['column']) ? 1 : 0)
                    : (int) $this->request->getPost($field['column']);
            }
        }

        return $data;
    }
}
