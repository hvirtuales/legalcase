<?php

namespace App\Controllers\Api;

use App\Models\CompanyModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;

class CompaniesController extends BaseApiController
{
    private function model(): CompanyModel
    {
        return new CompanyModel();
    }

    /**
     * Si el token está acotado a una empresa, sólo puede operar sobre ESA.
     * Devuelve una respuesta 403 cuando el id pedido no es el del token.
     */
    private function outOfScope($id)
    {
        $scope = $this->companyId();
        if ($scope !== null && (int) $id !== (int) $scope) {
            return $this->failForbidden('El token sólo puede operar sobre su empresa (#' . $scope . ').');
        }

        return null;
    }

    public function index()
    {
        if ($r = $this->guard('companies.view')) {
            return $r;
        }
        // Token por empresa: sólo su empresa
        if ($scope = $this->companyId()) {
            $company = $this->model()->find($scope);

            return $this->respond(['data' => $company ? [$company] : []]);
        }

        return $this->respond(['data' => $this->model()->findAll()]);
    }

    /** GET /company — la empresa del token (con plan y suscripción). */
    public function current()
    {
        $scope = $this->companyId();
        if (!$scope) {
            return $this->failForbidden('Este token no está acotado a una empresa.');
        }
        $company = $this->model()->find($scope);
        if (!$company) {
            return $this->failNotFound('Empresa no encontrada.');
        }
        $sub  = (new SubscriptionModel())->getActiveForCompany((int) $scope);
        $plan = !empty($company['plan_id']) ? (new PlanModel())->find($company['plan_id']) : null;

        return $this->respond(['data' => ['company' => $company, 'plan' => $plan, 'subscription' => $sub]]);
    }

    public function show($id = null)
    {
        if ($r = $this->guard('companies.view')) {
            return $r;
        }
        if ($r = $this->outOfScope($id)) {
            return $r;
        }
        $company = $this->model()->find($id);

        return $company ? $this->respond(['data' => $company]) : $this->failNotFound('Empresa no encontrada.');
    }

    public function create()
    {
        if ($r = $this->guard('companies.create')) {
            return $r;
        }
        // Un token acotado a una empresa no crea nuevas empresas
        if ($this->companyId() !== null) {
            return $this->failForbidden('Un token de empresa no puede crear empresas.');
        }
        $model = $this->model();
        $data  = $this->body();
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = $model->makeSlug($data['name']);
        }
        $data['is_active'] ??= 1;

        $id = $model->insert($data);
        if (!$id) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated(['data' => $model->find($id)]);
    }

    public function update($id = null)
    {
        if ($r = $this->guard('companies.edit')) {
            return $r;
        }
        if ($r = $this->outOfScope($id)) {
            return $r;
        }
        $model = $this->model();
        if (!$model->find($id)) {
            return $this->failNotFound('Empresa no encontrada.');
        }
        if (!$model->update($id, $this->body())) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respond(['data' => $model->find($id)]);
    }

    public function delete($id = null)
    {
        if ($r = $this->guard('companies.delete')) {
            return $r;
        }
        // Un token acotado no elimina su propia empresa vía API
        if ($this->companyId() !== null) {
            return $this->failForbidden('Un token de empresa no puede eliminar empresas.');
        }
        $model = $this->model();
        if (!$model->find($id)) {
            return $this->failNotFound('Empresa no encontrada.');
        }
        $model->delete($id);

        return $this->respondDeleted(['id' => (int) $id]);
    }
}
