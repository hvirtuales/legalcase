<?php

namespace App\Controllers\Api;

class MeController extends BaseApiController
{
    /** Identidad y permisos del token actual. */
    public function index()
    {
        $user = $this->apiAuth->user();

        $company = $this->apiAuth->company();

        return $this->respond(['data' => [
            'user' => [
                'id'       => $user['id'] ?? null,
                'username' => $user['username'] ?? null,
                'email'    => $user['email'] ?? null,
                'name'     => trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))),
            ],
            'company' => [
                'id'   => $this->apiAuth->companyId(),
                'name' => $company['name'] ?? null,
            ],
            'role_ids'    => $this->apiAuth->roleIds(),
            'permissions' => $this->apiAuth->permissions(),
        ]]);
    }

    /** Comprobación de vida (requiere token válido). */
    public function ping()
    {
        return $this->respond(['data' => ['ok' => true, 'time' => date('c')]]);
    }
}
