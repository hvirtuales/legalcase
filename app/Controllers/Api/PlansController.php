<?php

namespace App\Controllers\Api;

use App\Models\PlanModel;

class PlansController extends BaseApiController
{
    private function model(): PlanModel
    {
        return new PlanModel();
    }

    public function index()
    {
        if ($r = $this->guard('plans.view')) {
            return $r;
        }

        return $this->respond(['data' => $this->model()->findAll()]);
    }

    public function show($id = null)
    {
        if ($r = $this->guard('plans.view')) {
            return $r;
        }
        $plan = $this->model()->find($id);

        return $plan ? $this->respond(['data' => $plan]) : $this->failNotFound('Plan no encontrado.');
    }

    public function create()
    {
        if ($r = $this->guard('plans.create')) {
            return $r;
        }
        $model = $this->model();
        $data  = $this->body();
        $data['is_active'] ??= 1;

        $id = $model->insert($data);
        if (!$id) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated(['data' => $model->find($id)]);
    }

    public function update($id = null)
    {
        if ($r = $this->guard('plans.edit')) {
            return $r;
        }
        $model = $this->model();
        if (!$model->find($id)) {
            return $this->failNotFound('Plan no encontrado.');
        }
        if (!$model->update($id, $this->body())) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respond(['data' => $model->find($id)]);
    }

    public function delete($id = null)
    {
        if ($r = $this->guard('plans.delete')) {
            return $r;
        }
        $model = $this->model();
        if (!$model->find($id)) {
            return $this->failNotFound('Plan no encontrado.');
        }
        $model->delete($id);

        return $this->respondDeleted(['id' => (int) $id]);
    }
}
