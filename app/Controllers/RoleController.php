<?php

namespace App\Controllers;

use App\Models\RoleModel;
use App\Models\PermissionModel;
use App\Models\UserModel;

class RoleController extends BaseController
{
    protected $roleModel;
    protected $permissionModel;
    protected $userModel;

    public function __construct()
    {
        $this->roleModel = new RoleModel();
        $this->permissionModel = new PermissionModel();
        $this->userModel = new UserModel();
    }

    /**
     * List all roles
     */
    public function index()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'roles.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $dt = (new \App\Libraries\DataTable($this->roleModel, $this->request))
            ->searchable(['name', 'description'])
            ->sortable(['id', 'name', 'created_at'])
            ->defaultSort('id', 'asc')
            ->paginate(15);

        return view('roles/index', [
            'dt'    => $dt,
            'title' => lang('App.roles'),
        ]);
    }

    /**
     * Show create role form
     */
    public function create()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'roles.create')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store();
        }

        $data = [
            'title' => lang('App.createRole'),
        ];

        return view('roles/create', $data);
    }

    /**
     * Store new role
     */
    private function store()
    {
        $rules = [
            'name' => 'required|min_length[3]|max_length[50]|is_unique[roles.name]',
            'description' => 'permit_empty|max_length[500]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
        ];

        if (!$this->roleModel->insert($data)) {
            return redirect()->back()->withInput()->with('error', lang('App.createFailed'));
        }

        return redirect()->to('/roles')->with('success', lang('App.createSuccess'));
    }

    /**
     * Show edit role form
     */
    public function edit($id)
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'roles.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $role = $this->roleModel->find($id);

        if (!$role) {
            return redirect()->to('/roles')->with('error', lang('App.notFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->update($id);
        }

        $data = [
            'role' => $role,
            'title' => lang('App.editRole'),
        ];

        return view('roles/edit', $data);
    }

    /**
     * Update role
     */
    private function update($id)
    {
        $rules = [
            'name' => "required|min_length[3]|max_length[50]|is_unique[roles.name,id,$id]",
            'description' => 'permit_empty|max_length[500]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
        ];

        // Se validó en el controlador; se salta la del modelo (su is_unique con
        // {id} marcaría el propio registro como duplicado en update()).
        $this->roleModel->skipValidation(true);
        $ok = $this->roleModel->update($id, $data);
        $this->roleModel->skipValidation(false);

        if (!$ok) {
            return redirect()->back()->withInput()->with('error', lang('App.updateFailed'));
        }

        return redirect()->to('/roles')->with('success', lang('App.updateSuccess'));
    }

    /**
     * Delete role
     */
    public function delete($id)
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'roles.delete')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        // Check if role has users
        $usersCount = $this->roleModel->getUsersCount($id);
        if ($usersCount > 0) {
            return redirect()->to('/roles')->with('error', lang('App.roleHasUsers'));
        }

        if (!$this->roleModel->delete($id)) {
            return redirect()->to('/roles')->with('error', lang('App.deleteFailed'));
        }

        return redirect()->to('/roles')->with('success', lang('App.deleteSuccess'));
    }

    /**
     * Manage role permissions
     */
    public function permissions($roleId)
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'roles.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $role = $this->roleModel->getRoleWithPermissions($roleId);

        if (!$role) {
            return redirect()->to('/roles')->with('error', lang('App.notFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            $permissionIds = $this->request->getPost('permissions') ?? [];

            $this->roleModel->syncPermissions($roleId, $permissionIds);

            return redirect()->to('/roles')->with('success', lang('App.permissionsUpdated'));
        }

        $data = [
            'role' => $role,
            'allPermissions' => $this->permissionModel->getGroupedByModule(),
            'title' => lang('App.rolePermissions'),
        ];

        return view('roles/permissions', $data);
    }
}
