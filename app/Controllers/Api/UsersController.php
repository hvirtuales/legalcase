<?php

namespace App\Controllers\Api;

use App\Models\CompanyUserModel;
use App\Models\UserModel;

class UsersController extends BaseApiController
{
    private function model(): UserModel
    {
        return new UserModel();
    }

    private function members(): CompanyUserModel
    {
        return new CompanyUserModel();
    }

    /** Quita campos sensibles de la salida. */
    private function clean(array $u): array
    {
        unset($u['password'], $u['reset_hash'], $u['reset_expires_at']);

        return $u;
    }

    /** 403 si el token está acotado a una empresa y el usuario no es miembro. */
    private function notMember($id)
    {
        $scope = $this->companyId();
        if ($scope !== null && !$this->members()->isMember((int) $scope, (int) $id)) {
            return $this->failForbidden('El usuario no pertenece a la empresa del token.');
        }

        return null;
    }

    public function index()
    {
        if ($r = $this->guard('users.view')) {
            return $r;
        }
        $model = $this->model();

        // Token por empresa: sólo los usuarios miembros de esa empresa
        if ($scope = $this->companyId()) {
            $ids   = $this->members()->getUserIds((int) $scope);
            $users = $ids ? $model->whereIn('id', $ids)->findAll() : [];
        } else {
            $users = $model->findAll();
        }

        return $this->respond(['data' => array_map([$this, 'clean'], $users)]);
    }

    public function show($id = null)
    {
        if ($r = $this->guard('users.view')) {
            return $r;
        }
        if ($r = $this->notMember($id)) {
            return $r;
        }
        $user = $this->model()->find($id);

        return $user ? $this->respond(['data' => $this->clean($user)]) : $this->failNotFound('Usuario no encontrado.');
    }

    public function create()
    {
        if ($r = $this->guard('users.create')) {
            return $r;
        }
        $model = $this->model();
        $data  = $this->body();
        $data['auth_method'] ??= 'password';
        $data['is_active']   ??= 1;

        $id = $model->insert($data);
        if (!$id) {
            return $this->failValidationErrors($model->errors());
        }

        // Token por empresa: el nuevo usuario queda vinculado a esa empresa
        if ($scope = $this->companyId()) {
            $this->members()->addUser((int) $scope, (int) $id, false);
        }

        return $this->respondCreated(['data' => $this->clean($model->find($id))]);
    }

    public function update($id = null)
    {
        if ($r = $this->guard('users.edit')) {
            return $r;
        }
        if ($r = $this->notMember($id)) {
            return $r;
        }
        $model = $this->model();
        if (!$model->find($id)) {
            return $this->failNotFound('Usuario no encontrado.');
        }
        if (!$model->update($id, $this->body())) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respond(['data' => $this->clean($model->find($id))]);
    }

    public function delete($id = null)
    {
        if ($r = $this->guard('users.delete')) {
            return $r;
        }

        // Token por empresa: "eliminar" = desvincular de la empresa (no borra el usuario global)
        if ($scope = $this->companyId()) {
            if (!$this->members()->isMember((int) $scope, (int) $id)) {
                return $this->failForbidden('El usuario no pertenece a la empresa del token.');
            }
            $this->members()->removeUser((int) $scope, (int) $id);

            return $this->respond(['data' => ['detached' => true, 'user_id' => (int) $id, 'company_id' => (int) $scope]]);
        }

        $model = $this->model();
        if (!$model->find($id)) {
            return $this->failNotFound('Usuario no encontrado.');
        }
        $model->delete($id);

        return $this->respondDeleted(['id' => (int) $id]);
    }
}
