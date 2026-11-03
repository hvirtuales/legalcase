<?php

namespace App\Controllers;

use App\Libraries\DataTable;
use App\Models\CompanyModel;
use App\Models\CompanyUserModel;
use App\Models\RoleModel;
use App\Models\UserModel;

class UserController extends BaseController
{
    protected $userModel;
    protected $roleModel;
    protected $members;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->roleModel = new RoleModel();
        $this->members   = new CompanyUserModel();
    }

    // ------------------------------------------------------------------ scope

    /** Super Admin ve/gestiona usuarios globalmente; el resto solo los de su empresa activa. */
    private function isSuper(): bool
    {
        $user = $this->userModel->getUserWithRoles((int) session()->get('user_id'));

        return in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** Roles asignables: un no-super no puede otorgar el rol Super Admin. */
    private function assignableRoles(): array
    {
        $roles = $this->roleModel->orderBy('name', 'ASC')->findAll();
        if ($this->isSuper()) {
            return $roles;
        }

        return array_values(array_filter($roles, static fn ($r) => $r['name'] !== 'Super Admin'));
    }

    /** IDs de usuarios miembros de la empresa activa (para acotar al no-super). */
    private function scopeMemberIds(): array
    {
        $cid = $this->companyId();
        if (!$cid) {
            return [0];
        }

        return $this->members->getUserIds($cid) ?: [0];
    }

    /** ¿El usuario objetivo es gestionable por quien está logueado? */
    private function canManage(int $userId): bool
    {
        return $this->isSuper() || in_array($userId, $this->scopeMemberIds(), true);
    }

    // ------------------------------------------------------------------ CRUD

    public function index()
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'users.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $isSuper = $this->isSuper();
        $cid     = $this->companyId();
        $dt      = (new DataTable($this->userModel, $this->request))
            ->searchable(['username', 'email', 'first_name', 'last_name'])
            ->sortable(['id', 'username', 'email', 'first_name', 'is_active', 'last_login_at'])
            ->defaultSort('id', 'desc');

        if (!$isSuper) {
            $memberIds = $this->scopeMemberIds();
            $dt->query(static fn ($m) => $m->whereIn('id', $memberIds));
        }

        $result = $dt->paginate(15);

        // Roles globales + estado en la empresa (is_admin) de los usuarios mostrados
        $db       = \Config\Database::connect();
        $ids      = array_column($result['rows'], 'id');
        $rolesMap = [];
        $adminMap = [];
        if ($ids) {
            foreach ($db->table('user_roles ur')->select('ur.user_id, r.name')->join('roles r', 'r.id = ur.role_id')->whereIn('ur.user_id', $ids)->get()->getResultArray() as $rr) {
                $rolesMap[(int) $rr['user_id']][] = $rr['name'];
            }
            if (!$isSuper && $cid) {
                foreach ($db->table('company_users')->select('user_id, is_admin')->where('company_id', $cid)->whereIn('user_id', $ids)->get()->getResultArray() as $cu) {
                    $adminMap[(int) $cu['user_id']] = (int) $cu['is_admin'];
                }
            }
        }

        $company = (!$isSuper && $cid) ? (new CompanyModel())->find($cid) : null;

        return view('users/index', [
            'dt'          => $result,
            'title'       => lang('App.users'),
            'scoped'      => !$isSuper,
            'companyName' => $company['name'] ?? null,
            'rolesMap'    => $rolesMap,
            'adminMap'    => $adminMap,
        ]);
    }

    public function create()
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'users.create')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }
        // Un no-super necesita una empresa activa para registrar miembros
        if (!$this->isSuper() && !$this->companyId()) {
            return redirect()->to('/users')->with('error', 'Selecciona una empresa activa para registrar usuarios.');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store();
        }

        $company = (!$this->isSuper() && $this->companyId()) ? (new CompanyModel())->find($this->companyId()) : null;

        return view('users/create', [
            'roles'       => $this->assignableRoles(),
            'title'       => lang('App.createUser'),
            'scoped'      => !$this->isSuper(),
            'companyName' => $company['name'] ?? null,
        ]);
    }

    private function store()
    {
        $rules = [
            'username'         => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            'first_name'       => 'required|max_length[50]',
            'last_name'        => 'required|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'username'           => $this->request->getPost('username'),
            'email'              => $this->request->getPost('email'),
            'password'           => $this->request->getPost('password'),
            'first_name'         => $this->request->getPost('first_name'),
            'last_name'          => $this->request->getPost('last_name'),
            'phone'              => $this->request->getPost('phone'),
            'is_active'          => $this->request->getPost('is_active') ? true : false,
            'auth_method'        => 'password',
            'preferred_language' => $this->request->getPost('preferred_language') ?? 'es',
        ];

        $userId = $this->userModel->insert($data);
        if (!$userId) {
            return redirect()->back()->withInput()->with('error', lang('App.createFailed') . ' ' . json_encode($this->userModel->errors()));
        }

        // Roles (solo los asignables por quien crea)
        $this->applyRoles((int) $userId, $this->request->getPost('roles'));

        // No-super: el nuevo usuario queda como miembro de la empresa activa
        if (!$this->isSuper() && $this->companyId()) {
            $this->members->addUser($this->companyId(), (int) $userId, false);

            return redirect()->to('/users')->with('success', 'Usuario registrado y agregado como miembro de la empresa.');
        }

        return redirect()->to('/users')->with('success', lang('App.createSuccess'));
    }

    public function edit($id)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'users.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }
        if (!$this->canManage((int) $id)) {
            return redirect()->to('/users')->with('error', 'No tienes acceso a ese usuario.');
        }

        $user = $this->userModel->getUserWithRoles($id);
        if (!$user) {
            return redirect()->to('/users')->with('error', lang('App.notFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->update($id);
        }

        return view('users/edit', [
            'user'  => $user,
            'roles' => $this->assignableRoles(),
            'title' => lang('App.editUser'),
        ]);
    }

    private function update($id)
    {
        $rules = [
            'username'   => "required|min_length[3]|max_length[50]|is_unique[users.username,id,{$id}]",
            'email'      => "required|valid_email|is_unique[users.email,id,{$id}]",
            'password'   => 'permit_empty|min_length[8]',
            'first_name' => 'required|max_length[50]',
            'last_name'  => 'required|max_length[50]',
        ];

        $password = $this->request->getPost('password');
        if (!empty($password)) {
            $rules['password']         = 'min_length[8]';
            $rules['password_confirm'] = 'required|matches[password]';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'username'           => $this->request->getPost('username'),
            'email'              => $this->request->getPost('email'),
            'first_name'         => $this->request->getPost('first_name'),
            'last_name'          => $this->request->getPost('last_name'),
            'phone'              => $this->request->getPost('phone'),
            'is_active'          => $this->request->getPost('is_active') ? true : false,
            'preferred_language' => $this->request->getPost('preferred_language') ?? 'es',
        ];
        if (!empty($password)) {
            $data['password'] = $password;
        }

        // Ver nota en CLAUDE.md (is_unique con {id} en update): valido en el controlador y salto la del modelo
        $this->userModel->skipValidation(true);
        $ok = $this->userModel->update($id, $data);
        $this->userModel->skipValidation(false);

        if (!$ok) {
            return redirect()->back()->withInput()->with('error', lang('App.updateFailed') . ' ' . json_encode($this->userModel->errors()));
        }

        return redirect()->to('/users')->with('success', lang('App.updateSuccess'));
    }

    public function delete($id)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'users.delete')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }
        if ($id == session()->get('user_id')) {
            return redirect()->to('/users')->with('error', lang('App.cannotDeleteSelf'));
        }

        // No-super: "eliminar" = QUITAR de la empresa (no borra el usuario global)
        if (!$this->isSuper()) {
            $cid = $this->companyId();
            if (!$cid || !in_array((int) $id, $this->scopeMemberIds(), true)) {
                return redirect()->to('/users')->with('error', 'No tienes acceso a ese usuario.');
            }
            $company = (new CompanyModel())->find($cid);
            if ($company && (int) $company['owner_id'] === (int) $id) {
                return redirect()->to('/users')->with('error', 'No puedes quitar al dueño de la empresa.');
            }
            $this->members->removeUser($cid, (int) $id);

            return redirect()->to('/users')->with('success', 'Usuario quitado de la empresa.');
        }

        if (!$this->userModel->delete($id)) {
            return redirect()->to('/users')->with('error', lang('App.deleteFailed'));
        }

        return redirect()->to('/users')->with('success', lang('App.deleteSuccess'));
    }

    public function assignRole($userId)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'users.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }
        if (!$this->canManage((int) $userId)) {
            return redirect()->to('/users')->with('error', 'No tienes acceso a ese usuario.');
        }

        if ($this->request->getMethod() === 'POST') {
            \Config\Database::connect()->table('user_roles')->where('user_id', $userId)->delete();
            $this->applyRoles((int) $userId, $this->request->getPost('roles'));

            return redirect()->to('/users')->with('success', lang('App.rolesAssigned'));
        }

        $user = $this->userModel->getUserWithRoles($userId);

        return view('users/assign_role', [
            'user'      => $user,
            'roles'     => $this->assignableRoles(),
            'userRoles' => $user['roles'] ?? [],
            'title'     => lang('App.assignRoles'),
        ]);
    }

    /** Asigna solo los roles que quien opera tiene permitido otorgar (filtra Super Admin para no-super). */
    private function applyRoles(int $userId, $roleIds): void
    {
        if (!$roleIds || !is_array($roleIds)) {
            return;
        }
        $allowed = array_map('intval', array_column($this->assignableRoles(), 'id'));
        foreach ($roleIds as $rid) {
            if (in_array((int) $rid, $allowed, true)) {
                $this->userModel->assignRole($userId, (int) $rid);
            }
        }
    }
}
