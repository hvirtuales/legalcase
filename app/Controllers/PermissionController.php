<?php

namespace App\Controllers;

use App\Models\PermissionModel;
use App\Models\UserModel;

class PermissionController extends BaseController
{
    protected $permissionModel;
    protected $userModel;

    public function __construct()
    {
        $this->permissionModel = new PermissionModel();
        $this->userModel = new UserModel();
    }

    /**
     * List all permissions
     */
    public function index()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'permissions.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $permissions = $this->permissionModel->getGroupedByModule();

        $data = [
            'permissions' => $permissions,
            'title' => lang('App.permissions'),
        ];

        return view('permissions/index', $data);
    }

    /**
     * Show create permission form
     */
    public function create()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'permissions.create')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store();
        }

        $data = [
            'title' => lang('App.createPermission'),
        ];

        return view('permissions/create', $data);
    }

    /**
     * Store new permission
     */
    private function store()
    {
        $rules = [
            'name' => 'required|min_length[3]|max_length[100]|is_unique[permissions.name]',
            'description' => 'permit_empty|max_length[500]',
            'module' => 'required|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'module' => $this->request->getPost('module'),
        ];

        if (!$this->permissionModel->insert($data)) {
            return redirect()->back()->withInput()->with('error', lang('App.createFailed'));
        }

        return redirect()->to('/permissions')->with('success', lang('App.createSuccess'));
    }

    /**
     * Show edit permission form
     */
    public function edit($id)
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'permissions.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $permission = $this->permissionModel->find($id);

        if (!$permission) {
            return redirect()->to('/permissions')->with('error', lang('App.notFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->update($id);
        }

        $data = [
            'permission' => $permission,
            'title' => lang('App.editPermission'),
        ];

        return view('permissions/edit', $data);
    }

    /**
     * Update permission
     */
    private function update($id)
    {
        $rules = [
            'name' => "required|min_length[3]|max_length[100]|is_unique[permissions.name,id,$id]",
            'description' => 'permit_empty|max_length[500]',
            'module' => 'required|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'module' => $this->request->getPost('module'),
        ];

        // Se validó en el controlador; se salta la del modelo (su is_unique con
        // {id} marcaría el propio registro como duplicado en update()).
        $this->permissionModel->skipValidation(true);
        $ok = $this->permissionModel->update($id, $data);
        $this->permissionModel->skipValidation(false);

        if (!$ok) {
            return redirect()->back()->withInput()->with('error', lang('App.updateFailed'));
        }

        return redirect()->to('/permissions')->with('success', lang('App.updateSuccess'));
    }

    /**
     * Delete permission
     */
    public function delete($id)
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'permissions.delete')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        if (!$this->permissionModel->delete($id)) {
            return redirect()->to('/permissions')->with('error', lang('App.deleteFailed'));
        }

        return redirect()->to('/permissions')->with('success', lang('App.deleteSuccess'));
    }
}
