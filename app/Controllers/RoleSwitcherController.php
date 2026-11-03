<?php

namespace App\Controllers;

use App\Models\UserModel;

class RoleSwitcherController extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Switch active role
     */
    public function switchRole($roleId)
    {
        $userId = session()->get('user_id');

        if (!$userId) {
            return redirect()->to('/login');
        }

        // Get user's roles
        $user = $this->userModel->getUserWithRoles($userId);
        $userRoleIds = array_column($user['roles'] ?? [], 'id');

        // Verify user has this role
        if (!in_array($roleId, $userRoleIds)) {
            return redirect()->back()->with('error', lang('App.roleNotAssigned'));
        }

        // Set active role in session
        session()->set('active_role_id', $roleId);

        // Get role name for confirmation
        $roleModel = new \App\Models\RoleModel();
        $role = $roleModel->find($roleId);

        return redirect()->back()->with('success', sprintf(lang('App.roleSwitched'), $role['name']));
    }
}
