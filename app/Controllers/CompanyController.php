<?php

namespace App\Controllers;

use App\Models\CompanyModel;
use App\Models\CompanyUserModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;
use App\Models\UserModel;

class CompanyController extends BaseController
{
    protected $companyModel;
    protected $planModel;
    protected $subModel;
    protected $userModel;
    protected $memberModel;

    public function __construct()
    {
        $this->companyModel = new CompanyModel();
        $this->planModel    = new PlanModel();
        $this->subModel     = new SubscriptionModel();
        $this->userModel    = new UserModel();
        $this->memberModel  = new CompanyUserModel();
    }

    // ------------------------------------------------------------------ alcance

    /**
     * El catálogo de empresas es de **plataforma**, pero `companies.view/edit`
     * los lleva también el rol «Admin», que es el que se le pone al
     * administrador de una empresa o de un grupo. Sin acotar, cualquiera de
     * ellos vería —y editaría— las empresas de todos los demás.
     *
     * Se acota con la misma regla que ya gobierna el selector del encabezado y
     * el `AuthFilter` (`CompanyModel::getAccessibleFor`/`userCanAccess`): dueño,
     * miembro, o empresa de un grupo que administra. El Super Admin las ve
     * todas, como siempre.
     */
    private ?bool $isSuper = null;

    private function isSuper(): bool
    {
        if ($this->isSuper === null) {
            $user          = $this->userModel->getUserWithRoles((int) session()->get('user_id'));
            $this->isSuper = in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);
        }

        return $this->isSuper;
    }

    /** @return list<int> ids visibles para quien opera (solo si NO es super). */
    private function accessibleIds(): array
    {
        // Instancia aparte: el DataTable usa $this->companyModel y no conviene
        // dejarle el builder con un orderBy/where de esta consulta.
        $rows = (new CompanyModel())->getAccessibleFor((int) session()->get('user_id'), false);

        return array_map(static fn ($c) => (int) $c['id'], $rows) ?: [0];
    }

    private function canAccess($companyId): bool
    {
        return $this->isSuper()
            || $this->companyModel->userCanAccess((int) $companyId, (int) session()->get('user_id'));
    }

    public function index()
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'companies.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        // Las filas-tenant no salen aquí: tienen su propia pantalla (/tenants).
        $scope = $this->isSuper() ? null : $this->accessibleIds();

        $dt = (new \App\Libraries\DataTable($this->companyModel, $this->request))
            ->query(static function ($m) use ($scope) {
                $m->select('companies.*, plans.name AS plan_name, CONCAT(u.first_name, " ", u.last_name) AS owner_name, t.name AS tenant_name')
                    ->join('plans', 'plans.id = companies.plan_id', 'left')
                    ->join('users u', 'u.id = companies.owner_id', 'left')
                    ->join('companies t', 't.id = companies.parent_id', 'left')
                    ->where('companies.type', 'company');

                if ($scope !== null) {
                    $m->whereIn('companies.id', $scope);
                }

                return $m;
            })
            ->searchable(['companies.name', 'companies.nit', 'companies.contact_email'])
            ->sortable(['companies.id', 'companies.name', 'companies.nit', 'companies.is_active'])
            ->defaultSort('companies.name', 'asc')
            ->paginate(15);

        $ids    = array_map(static fn ($c) => (int) $c['id'], $dt['rows']);
        $subMap = $this->subModel->getMapForCompanies($ids);

        return view('companies/index', [
            'dt'       => $dt,
            'subModel' => $this->subModel,
            'subMap'   => $subMap,
            'title'    => 'Empresas',
        ]);
    }

    public function create()
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'companies.create')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store();
        }

        return view('companies/create', [
            // Solo planes de ámbito EMPRESA: los de tenant no le sirven a una
            // empresa (sus banderas son las de los componentes del grupo).
            'plans'   => $this->planModel->getActive('company'),
            'users'   => $this->userModel->orderBy('first_name', 'ASC')->findAll(),
            'tenants' => $this->companyModel->tenants(),
            'title'   => 'Crear Empresa',
        ]);
    }

    private function store()
    {
        $adminMode = $this->request->getPost('admin_mode') ?: 'existing';

        $rules = [
            'name'     => 'required|max_length[150]',
            'plan_id'  => 'required|is_natural_no_zero',
            'status'   => 'required|in_list[trial,active,expired,cancelled]',
            'contact_email' => 'permit_empty|valid_email',
        ];

        if ($adminMode === 'new') {
            $rules += [
                'admin_username'   => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
                'admin_email'      => 'required|valid_email|is_unique[users.email]',
                'admin_password'   => 'required|min_length[8]',
                'admin_first_name' => 'required|max_length[50]',
                'admin_last_name'  => 'required|max_length[50]',
            ];
        } else {
            $rules['admin_user_id'] = 'required|is_natural_no_zero';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Jerarquía y cupo del grupo (sustituyen al antiguo límite «por dueño»:
        // el cupo lo contrata el tenant, no la persona).
        $parentId = (int) $this->request->getPost('parent_id') ?: null;
        if ($error = $this->parentError($parentId, null)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // 1) Empresa (sin owner aún)
        $companyData             = $this->collectCompany();
        $companyData['owner_id'] = null;
        $companyId               = $this->companyModel->insert($companyData);

        if (!$companyId) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('errors', $this->companyModel->errors() ?: ['No se pudo crear la empresa.']);
        }

        // 2) Administrador de la empresa
        $adminRoleId = $this->adminRoleId();

        if ($adminMode === 'new') {
            $adminId = $this->userModel->insert([
                'username'           => $this->request->getPost('admin_username'),
                'email'              => $this->request->getPost('admin_email'),
                'password'           => $this->request->getPost('admin_password'),
                'first_name'         => $this->request->getPost('admin_first_name'),
                'last_name'          => $this->request->getPost('admin_last_name'),
                'phone'              => $this->request->getPost('admin_phone'),
                'is_active'          => 1,
                'auth_method'        => 'password',
                'preferred_language' => 'es',
            ]);
            if (!$adminId) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('errors', $this->userModel->errors() ?: ['No se pudo crear el administrador.']);
            }
        } else {
            $adminId = (int) $this->request->getPost('admin_user_id');
        }

        // Un admin puede gestionar varias empresas: el vínculo es companies.owner_id
        // (no se ata el usuario a una sola empresa).
        if ($adminRoleId) {
            $this->userModel->assignRole($adminId, $adminRoleId);
        }

        // 3) owner_id de la empresa + membresía (admin dentro de la empresa)
        $this->companyModel->update($companyId, ['owner_id' => $adminId]);
        $this->memberModel->addUser((int) $companyId, (int) $adminId, true);

        // 4) Suscripción
        $plan = $this->planModel->find($this->request->getPost('plan_id'));
        $this->subModel->createForCompany(
            (int) $companyId,
            (int) $this->request->getPost('plan_id'),
            $this->request->getPost('status'),
            $this->request->getPost('start_date') ?: null,
            $this->request->getPost('end_date') ?: null,
            (int) ($plan['service_days'] ?? 0)
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Error al crear la empresa. Revisa los datos.');
        }

        return redirect()->to('/companies')->with('success', 'Empresa creada con su administrador y suscripción.');
    }

    public function edit($id)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'companies.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $company = $this->companyModel->find($id);
        if (!$company) {
            return redirect()->to('/companies')->with('error', 'Empresa no encontrada.');
        }
        if (!$this->canAccess($id)) {
            return redirect()->to('/companies')->with('error', lang('Tenant.errNoCompanyAccess'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->update($id, $company);
        }

        return view('companies/edit', [
            'company'      => $company,
            'plans'        => $this->planModel->getActive('company'),
            'users'        => $this->userModel->orderBy('first_name', 'ASC')->findAll(),
            'tenants'      => $this->companyModel->tenants(),
            'subscription' => $this->subModel->getActiveForCompany((int) $id),
            'title'        => 'Editar Empresa',
        ]);
    }

    private function update($id, array $company)
    {
        $rules = [
            'name'    => 'required|max_length[150]',
            'slug'    => "permit_empty|max_length[160]|is_unique[companies.slug,id,$id]",
            'plan_id' => 'required|is_natural_no_zero',
            'status'  => 'required|in_list[trial,active,expired,cancelled]',
            'contact_email' => 'permit_empty|valid_email',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $ownerId  = (int) $this->request->getPost('owner_id') ?: null;
        $parentId = (int) $this->request->getPost('parent_id') ?: null;

        // Solo se comprueba el cupo si CAMBIA de grupo: reeditar una empresa que
        // ya está dentro no debe rebotar por un cupo que ella misma ocupa.
        $previousParent = (int) ($company['parent_id'] ?? 0) ?: null;
        if ($error = $this->parentError($parentId, (int) $id, $previousParent)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $data = $this->collectCompany($id);
        $data['owner_id'] = $ownerId;
        // Se validó en el controlador; se salta la del modelo (is_unique con {id}
        // marcaría el propio slug como duplicado en update()).
        $this->companyModel->skipValidation(true);
        $this->companyModel->update($id, $data);
        $this->companyModel->skipValidation(false);

        // El dueño recibe el rol Admin y queda como miembro admin de la empresa
        if ($ownerId) {
            if ($roleId = $this->adminRoleId()) {
                $this->userModel->assignRole($ownerId, $roleId);
            }
            $this->memberModel->addUser((int) $id, (int) $ownerId, true);
        }

        // Upsert de la suscripción vigente
        $planId  = (int) $this->request->getPost('plan_id');
        $status  = $this->request->getPost('status');
        $start   = $this->request->getPost('start_date') ?: null;
        $end     = $this->request->getPost('end_date') ?: null;
        $current = $this->subModel->getActiveForCompany((int) $id);

        if ($current) {
            $this->subModel->update($current['id'], array_filter([
                'plan_id'    => $planId,
                'status'     => $status,
                'start_date' => $start,
                'end_date'   => $end,
            ], static fn ($v) => $v !== null));
        } else {
            $plan = $this->planModel->find($planId);
            $this->subModel->createForCompany((int) $id, $planId, $status, $start, $end, (int) ($plan['service_days'] ?? 0));
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'No se pudo actualizar la empresa.');
        }

        return redirect()->to('/companies')->with('success', 'Empresa actualizada.');
    }

    public function delete($id)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'companies.delete')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        if (!$this->canAccess($id)) {
            return redirect()->to('/companies')->with('error', lang('Tenant.errNoCompanyAccess'));
        }

        if (!$this->companyModel->delete($id)) {
            return redirect()->to('/companies')->with('error', 'No se pudo eliminar la empresa.');
        }

        return redirect()->to('/companies')->with('success', 'Empresa eliminada.');
    }

    /** Gestión de miembros (usuarios) de una empresa. */
    public function members($id)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'companies.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $company = $this->companyModel->find($id);
        if (!$company) {
            return redirect()->to('/companies')->with('error', 'Empresa no encontrada.');
        }
        if (!$this->canAccess($id)) {
            return redirect()->to('/companies')->with('error', lang('Tenant.errNoCompanyAccess'));
        }

        if ($this->request->getMethod() === 'POST') {
            $userId = (int) $this->request->getPost('user_id');
            if ($userId) {
                $this->memberModel->addUser((int) $id, $userId, false);
                return redirect()->to('companies/members/' . $id)->with('success', 'Usuario agregado a la empresa.');
            }

            return redirect()->to('companies/members/' . $id)->with('error', 'Selecciona un usuario.');
        }

        $members   = $this->memberModel->getUsers((int) $id);
        $memberIds = array_map(static fn ($m) => (int) $m['id'], $members) ?: [0];
        $available = $this->userModel->whereNotIn('id', $memberIds)->orderBy('first_name', 'ASC')->findAll();

        return view('companies/members', [
            'company'   => $company,
            'members'   => $members,
            'available' => $available,
            'title'     => 'Miembros — ' . $company['name'],
        ]);
    }

    public function removeMember($companyId, $userId)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'companies.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        if (!$this->canAccess($companyId)) {
            return redirect()->to('/companies')->with('error', lang('Tenant.errNoCompanyAccess'));
        }

        $company = $this->companyModel->find($companyId);
        if ($company && (int) $company['owner_id'] === (int) $userId) {
            return redirect()->to('companies/members/' . $companyId)->with('error', 'No puedes quitar al dueño de la empresa.');
        }

        $this->memberModel->removeUser((int) $companyId, (int) $userId);

        return redirect()->to('companies/members/' . $companyId)->with('success', 'Usuario quitado de la empresa.');
    }

    /**
     * Valida colgar la empresa de un tenant: que el padre sea de verdad un
     * tenant, que no se encadenen niveles y que quede cupo en su plan.
     *
     * Sustituye al antiguo `ownerAtLimit()`, que contaba empresas **por dueño**:
     * ahora `plans.max_companies` es el cupo del **grupo**, que es lo que se
     * contrata. Una empresa suelta (sin `parent_id`) no consume cupo de nadie.
     *
     * @return string|null mensaje de error, o null si se puede
     */
    private function parentError(?int $parentId, ?int $companyId, ?int $previousParent = null): ?string
    {
        if (!$parentId) {
            return null; // empresa suelta
        }

        if (!$this->companyModel->canBeParent($parentId, $companyId)) {
            return lang('Tenant.errInvalidParent');
        }

        // Ya estaba en ese grupo: no vuelve a consumir cupo.
        if ($previousParent === $parentId) {
            return null;
        }

        $left = (new \App\Libraries\TenantGuard())->companiesLeft($parentId);
        if ($left !== null && $left <= 0) {
            $tenant = $this->companyModel->find($parentId);

            return lang('Tenant.errQuotaFull', [$tenant['name'] ?? '']);
        }

        return null;
    }

    /** id del rol "Admin" (para el administrador de empresa). */
    private function adminRoleId(): ?int
    {
        $row = \Config\Database::connect()->table('roles')->where('name', 'Admin')->get()->getRow();

        return $row ? (int) $row->id : null;
    }

    /** Campos núcleo de la empresa desde el POST. */
    private function collectCompany(?int $id = null): array
    {
        $name = (string) $this->request->getPost('name');
        $slug = $this->request->getPost('slug');
        $slug = $slug ? $slug : $this->companyModel->makeSlug($name, $id);

        $data = [
            'name'          => $name,
            'nit'           => $this->request->getPost('nit'),
            'slug'          => $slug,
            'website'       => $this->request->getPost('website'),
            'address'       => $this->request->getPost('address'),
            'city'          => trim((string) $this->request->getPost('city')),
            'description'   => $this->request->getPost('description'),
            'brand_color'   => $this->request->getPost('brand_color'),
            'contact_email' => $this->request->getPost('contact_email'),
            'contact_phone' => $this->request->getPost('contact_phone'),
            'whatsapp'      => $this->request->getPost('whatsapp'),
            'plan_id'       => (int) $this->request->getPost('plan_id') ?: null,
            'is_active'     => $this->request->getPost('is_active') ? 1 : 0,
            // Esta pantalla gestiona empresas; los grupos se dan de alta en
            // /tenants (TenantController), que fija `type = 'tenant'`.
            'type'          => 'company',
            'parent_id'     => (int) $this->request->getPost('parent_id') ?: null,
        ];

        // Sector y coordenadas: las mismas reglas en los cuatro sitios que
        // guardan una empresa, definidas una sola vez en el modelo.
        $data += CompanyModel::locationFields($this->request);

        // Redes sociales: se guardan ya normalizadas a URL absoluta (el
        // formulario acepta también solo el usuario).
        foreach (CompanyModel::SOCIAL_NETWORKS as $net) {
            $data[$net] = CompanyModel::socialUrl($net, $this->request->getPost($net));
        }

        // Marca (opcional): logo y banner, local o S3/R2 según configuración
        $uploader = new \App\Libraries\UploadService();
        if ($logo = $uploader->storeImage($this->request->getFile('logo'), 'companies', 'company')) {
            $data['logo'] = $logo;
        }
        if ($banner = $uploader->storeImage($this->request->getFile('banner'), 'companies', 'banner')) {
            $data['banner'] = $banner;
        }

        return $data;
    }
}
