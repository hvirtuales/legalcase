<?php

namespace App\Controllers;

use App\Libraries\JwtService;
use App\Models\ApiTokenModel;
use App\Models\RoleModel;
use App\Models\UserModel;

class ApiTokenController extends BaseController
{
    protected $tokenModel;
    protected $userModel;
    protected $roleModel;

    public function __construct()
    {
        $this->tokenModel = new ApiTokenModel();
        $this->userModel  = new UserModel();
        $this->roleModel  = new RoleModel();
    }

    public function index()
    {
        $userId = session()->get('user_id');
        if (!$this->userModel->hasPermission($userId, 'api.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $companies      = (new \App\Models\CompanyModel())->getAccessibleFor((int) $userId, $this->isSuperAdmin((int) $userId));
        $companiesById  = [];
        foreach ($companies as $c) {
            $companiesById[$c['id']] = $c['name'];
        }

        return view('apitokens/index', [
            'tokens'        => $this->tokenModel->getUserTokens($userId),
            'selectable'    => $this->selectableRoles($userId),
            'rolesById'     => $this->rolesById(),
            'companies'     => $companies,
            'companiesById' => $companiesById,
            'title'         => 'Tokens de API',
        ]);
    }

    public function create()
    {
        $userId = session()->get('user_id');
        if (!$this->userModel->hasPermission($userId, 'api.create')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $name      = trim((string) $this->request->getPost('name'));
        $roles     = array_map('intval', (array) $this->request->getPost('roles'));
        $days      = (int) $this->request->getPost('expires_in_days');
        $companyId = (int) $this->request->getPost('company_id');

        if ($name === '' || empty($roles)) {
            return redirect()->back()->withInput()->with('error', 'Indica un nombre y al menos un rol.');
        }

        // La empresa es obligatoria y debe ser una que el usuario administre
        $accessibleIds = array_map(static fn ($c) => (int) $c['id'], (new \App\Models\CompanyModel())->getAccessibleFor((int) $userId, $this->isSuperAdmin((int) $userId)));
        if (!$companyId || !in_array($companyId, $accessibleIds, true)) {
            return redirect()->back()->withInput()->with('error', 'Selecciona una empresa válida para el token.');
        }

        // No se pueden conceder roles que el usuario no tenga (salvo Super Admin)
        $allowed = array_column($this->selectableRoles($userId), 'id');
        $roles   = array_values(array_intersect($roles, array_map('intval', $allowed)));
        if (empty($roles)) {
            return redirect()->back()->withInput()->with('error', 'Los roles seleccionados no son válidos para tu usuario.');
        }

        $expiresAt = $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;

        // 1) Registro (para obtener el jti); 2) emitir JWT; 3) guardar hash de referencia
        $id = $this->tokenModel->insert([
            'user_id'    => $userId,
            'company_id' => $companyId,
            'name'       => $name,
            'token'      => 'pending',
            'abilities'  => json_encode($roles),
            'expires_at' => $expiresAt,
            'created_by' => $userId,
        ]);

        if (!$id) {
            return redirect()->back()->withInput()->with('error', 'No se pudo crear el token.');
        }

        $expTs = $expiresAt ? strtotime($expiresAt) : 0;
        $jwt   = (new JwtService())->issue((int) $userId, (int) $id, $roles, $expTs, $companyId);
        $this->tokenModel->update($id, ['token' => hash('sha256', $jwt)]);

        // El JWT en claro se muestra UNA sola vez
        return redirect()->to('/apitokens')->with('newToken', $jwt)->with('success', 'Token creado. Cópialo ahora: no se volverá a mostrar.');
    }

    public function revoke($id)
    {
        $userId = session()->get('user_id');
        if (!$this->userModel->hasPermission($userId, 'api.delete')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $token = $this->tokenModel->find($id);
        if ($token && ((int) $token['user_id'] === (int) $userId || $this->isSuperAdmin($userId))) {
            $this->tokenModel->delete($id);
            return redirect()->to('/apitokens')->with('success', 'Token revocado.');
        }

        return redirect()->to('/apitokens')->with('error', 'Token no encontrado.');
    }

    /** Roles que el usuario puede conceder a un token. */
    private function selectableRoles(int $userId): array
    {
        if ($this->isSuperAdmin($userId)) {
            return $this->roleModel->findAll();
        }
        $user = $this->userModel->getUserWithRoles($userId);

        return $user['roles'] ?? [];
    }

    private function rolesById(): array
    {
        $map = [];
        foreach ($this->roleModel->findAll() as $r) {
            $map[$r['id']] = $r['name'];
        }

        return $map;
    }

    private function isSuperAdmin(int $userId): bool
    {
        $user = $this->userModel->getUserWithRoles($userId);

        return in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);
    }
}
