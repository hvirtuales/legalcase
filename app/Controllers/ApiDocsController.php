<?php

namespace App\Controllers;

use App\Models\ApiTokenModel;
use App\Models\UserModel;

class ApiDocsController extends BaseController
{
    public function index()
    {
        $userId = session()->get('user_id');
        if (!(new UserModel())->hasPermission($userId, 'api.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        return view('apidocs/index', [
            'title'   => 'Documentación de la API',
            'baseUrl' => rtrim(base_url('api/v1'), '/'),
            'tokens'  => (new ApiTokenModel())->getUserTokens($userId),
        ]);
    }
}
