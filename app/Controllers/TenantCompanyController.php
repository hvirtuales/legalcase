<?php

namespace App\Controllers;

use App\Libraries\TenantGuard;
use App\Libraries\UploadService;
use App\Models\CompanyModel;
use App\Models\CompanyUserModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;
use App\Models\TenantPlanModel;
use App\Models\UserModel;

/**
 * **Empresas del grupo** (`/tenant/empresas`): el administrador de un centro
 * comercial o una cámara de comercio da de alta sus locales/afiliadas, les
 * asigna plan y les pone administrador.
 *
 * Es deliberadamente **otra pantalla que `/companies`**, no el mismo CRUD con
 * un filtro. El catálogo de empresas de la plataforma es del Super Admin: crea
 * empresas sueltas, elige cualquier plan y cuelga de cualquier grupo. Aquí, en
 * cambio, todo está acotado por el grupo y esa acotación no es opcional:
 *
 *  - la empresa nace **dentro del grupo** (`parent_id` sale de la sesión, nunca
 *    del POST) y consume su cupo (`plans.max_companies`);
 *  - el plan solo puede ser uno de la **lista blanca** que le habilitó el Super
 *    Admin (`tenant_plans`);
 *  - el rol del administrador sale de `TenantGuard::assignableRoles()`, así que
 *    nunca puede repartir Super Admin ni roles de plataforma;
 *  - crear exige que el Super Admin le haya dado permiso
 *    (`companies.can_create_children`) y que la suscripción del grupo esté al día.
 *
 * El acceso lo gobierna el **vínculo** (`TenantGuard`), no un permiso: los
 * roles de la plataforma son globales y uno con `companies.*` daría poder sobre
 * las empresas de todos los grupos.
 */
class TenantCompanyController extends BaseController
{
    protected CompanyModel $companyModel;
    protected PlanModel $planModel;
    protected SubscriptionModel $subModel;
    protected UserModel $userModel;
    protected CompanyUserModel $memberModel;
    protected TenantPlanModel $tenantPlanModel;
    protected TenantGuard $guard;

    public function __construct()
    {
        $this->companyModel    = new CompanyModel();
        $this->planModel       = new PlanModel();
        $this->subModel        = new SubscriptionModel();
        $this->userModel       = new UserModel();
        $this->memberModel     = new CompanyUserModel();
        $this->tenantPlanModel = new TenantPlanModel();
        $this->guard           = new TenantGuard();
    }

    // ------------------------------------------------------------------ contexto

    private function tenant(): ?array
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->getUserWithRoles($userId);
        $isSuper = in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);

        return $this->guard->currentTenantFor($userId, $isSuper);
    }

    /** Una empresa concreta, comprobando que sea de SU grupo (el id viene de la URL). */
    private function ownCompany($id, int $tenantId): ?array
    {
        $company = $this->companyModel->find($id);

        return ($company && (int) ($company['parent_id'] ?? 0) === $tenantId) ? $company : null;
    }

    // ------------------------------------------------------------------ listado

    public function index()
    {
        $tenant = $this->tenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        $tenantId = (int) $tenant['id'];
        $blocked  = $this->canCreate($tenant);

        // Mismo listado que `/companies` (DataTable + el partial), acotado a las
        // empresas del grupo.
        $dt = (new \App\Libraries\DataTable($this->companyModel, $this->request))
            ->query(static fn ($m) => $m
                ->select('companies.*, plans.name AS plan_name, CONCAT(u.first_name, " ", u.last_name) AS owner_name')
                ->join('plans', 'plans.id = companies.plan_id', 'left')
                ->join('users u', 'u.id = companies.owner_id', 'left')
                ->where('companies.type', 'company')
                ->where('companies.parent_id', $tenantId))
            ->searchable(['companies.name', 'companies.nit', 'companies.contact_email'])
            ->sortable(['companies.id', 'companies.name', 'companies.nit', 'companies.is_active'])
            ->defaultSort('companies.name', 'asc')
            ->paginate(15);

        $ids = array_map(static fn ($c) => (int) $c['id'], $dt['rows']);

        return view('tenants/companies_panel', [
            'title'     => lang('Tenant.groupCompanies'),
            'tenant'    => $tenant,
            'dt'        => $dt,
            'plans'     => $this->tenantPlanModel->plansFor($tenantId),
            'subMap'    => $this->subModel->getMapForCompanies($ids),
            'subModel'  => $this->subModel,
            'used'      => $this->guard->companyCount($tenantId),
            'left'      => $this->guard->companiesLeft($tenantId),
            'canCreate' => $blocked === null,
            // Si el Super Admin no le dio permiso para crear, no es un aviso:
            // es que esta pantalla es de consulta y asignación, sin alta.
            'blocked'   => empty($tenant['can_create_children']) ? null : $blocked,
        ]);
    }

    // ------------------------------------------------------------------ alta

    /**
     * Motivo por el que el grupo no puede dar de alta una empresa, o null si
     * puede. Se consulta dos veces a propósito: para no pintar el botón y para
     * rechazar el POST.
     */
    private function canCreate(array $tenant): ?string
    {
        $tenantId = (int) $tenant['id'];

        if (empty($tenant['can_create_children'])) {
            return lang('Tenant.errCantCreate');
        }
        if (!$this->guard->tenantSubscriptionValid($tenantId)) {
            return lang('Tenant.errTenantOwnExpired', [$tenant['name']]);
        }
        $left = $this->guard->companiesLeft($tenantId);
        if ($left !== null && $left <= 0) {
            return lang('Tenant.errQuotaFull', [$tenant['name']]);
        }

        return null;
    }

    public function create()
    {
        $tenant = $this->tenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        if ($blocked = $this->canCreate($tenant)) {
            return redirect()->to('/tenant/empresas')->with('error', $blocked);
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->store($tenant);
        }

        return view('tenants/company_form', [
            'title'   => lang('Tenant.newCompany'),
            'tenant'  => $tenant,
            'company' => null,
            'plans'   => $this->tenantPlanModel->plansFor((int) $tenant['id']),
            'roles'   => $this->guard->assignableRoles(),
            'users'   => $this->userModel->where('is_active', 1)->orderBy('first_name', 'ASC')->findAll(),
            'members' => [],
            'left'    => $this->guard->companiesLeft((int) $tenant['id']),
        ]);
    }

    private function store(array $tenant)
    {
        $tenantId  = (int) $tenant['id'];
        $adminMode = $this->request->getPost('admin_mode') ?: 'existing';

        $rules = [
            'name'          => 'required|max_length[150]',
            'plan_id'       => 'required|is_natural_no_zero',
            'contact_email' => 'permit_empty|valid_email',
        ];
        $rules += $this->adminRules($adminMode, true);

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $planId = (int) $this->request->getPost('plan_id');
        if (!$this->tenantPlanModel->allows($tenantId, $planId)) {
            return redirect()->back()->withInput()->with('error', lang('Tenant.errPlanNotAllowed'));
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $data = $this->collect() + [
            'type'      => 'company',
            'parent_id' => $tenantId, // del contexto, nunca del POST
            'plan_id'   => $planId,
            'owner_id'  => null,
        ];

        $companyId = $this->companyModel->insert($data);
        if (!$companyId) {
            $db->transRollback();

            return redirect()->back()->withInput()
                ->with('errors', $this->companyModel->errors() ?: [lang('Tenant.errCompanyCreate')]);
        }

        $adminId = $this->resolveAdmin($adminMode);
        if (!$adminId) {
            $db->transRollback();

            return redirect()->back()->withInput()
                ->with('errors', $this->userModel->errors() ?: [lang('Tenant.errAdmin')]);
        }

        $this->companyModel->skipValidation(true);
        $this->companyModel->update($companyId, ['owner_id' => $adminId]);
        $this->companyModel->skipValidation(false);

        $this->memberModel->addUser((int) $companyId, $adminId, true);
        $this->applyRole($adminId, (int) $this->request->getPost('role_id'));

        $plan = $this->planModel->find($planId);
        $this->subModel->createForCompany(
            (int) $companyId,
            $planId,
            'active',
            null,
            null,
            (int) ($plan['service_days'] ?? 0)
        );

        $db->transComplete();
        TenantGuard::flush(); // el cupo y el alcance acaban de cambiar

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', lang('Tenant.errCompanyCreate'));
        }

        return redirect()->to('/tenant/empresas')->with('success', lang('Tenant.okCompanyCreated'));
    }

    // ------------------------------------------------------------------ edición

    public function edit($id)
    {
        $tenant = $this->tenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        $company = $this->ownCompany($id, (int) $tenant['id']);
        if (!$company) {
            return redirect()->to('/tenant/empresas')->with('error', lang('Tenant.errNotInGroup'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->update($company, $tenant);
        }

        return view('tenants/company_form', [
            'title'   => lang('Tenant.editCompany'),
            'tenant'  => $tenant,
            'company' => $company,
            'plans'   => $this->tenantPlanModel->plansFor((int) $tenant['id']),
            'roles'   => $this->guard->assignableRoles(),
            'users'   => $this->userModel->where('is_active', 1)->orderBy('first_name', 'ASC')->findAll(),
            'members' => $this->memberModel->getUsers((int) $company['id']),
            'left'    => $this->guard->companiesLeft((int) $tenant['id']),
        ]);
    }

    private function update(array $company, array $tenant)
    {
        $id        = (int) $company['id'];
        $tenantId  = (int) $tenant['id'];
        $adminMode = $this->request->getPost('admin_mode') ?: 'keep';

        // El slug se valida aquí con el $id real: la regla del modelo marcaría
        // el propio registro como duplicado en update() (gotcha is_unique CI4).
        $rules = [
            'name'          => 'required|max_length[150]',
            'slug'          => "permit_empty|max_length[160]|is_unique[companies.slug,id,$id]",
            'plan_id'       => 'required|is_natural_no_zero',
            'contact_email' => 'permit_empty|valid_email',
        ];
        $rules += $this->adminRules($adminMode, false);

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $planId = (int) $this->request->getPost('plan_id');
        if (!$this->tenantPlanModel->allows($tenantId, $planId)) {
            return redirect()->back()->withInput()->with('error', lang('Tenant.errPlanNotAllowed'));
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $data = $this->collect($id) + [
            'type'      => 'company',
            'parent_id' => $tenantId, // no se puede sacar del grupo desde aquí
            'plan_id'   => $planId,
        ];

        $this->companyModel->skipValidation(true);
        $this->companyModel->update($id, $data);
        $this->companyModel->skipValidation(false);

        if ($adminMode !== 'keep') {
            $adminId = $this->resolveAdmin($adminMode);
            if (!$adminId) {
                $db->transRollback();

                return redirect()->back()->withInput()
                    ->with('errors', $this->userModel->errors() ?: [lang('Tenant.errAdmin')]);
            }

            $this->companyModel->skipValidation(true);
            $this->companyModel->update($id, ['owner_id' => $adminId]);
            $this->companyModel->skipValidation(false);

            $this->memberModel->addUser($id, $adminId, true);
            $this->applyRole($adminId, (int) $this->request->getPost('role_id'));
        }

        $this->syncSubscription($id, $planId);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', lang('Tenant.errUpdate'));
        }

        return redirect()->to('/tenant/empresas')->with('success', lang('Tenant.okCompanyUpdated'));
    }

    /**
     * Asigna a una de sus empresas uno de los planes que el Super Admin le
     * habilitó (el atajo del panel). La comprobación de pertenencia va **en el
     * servidor**: estar en el panel no autoriza a tocar una empresa de otro
     * grupo ni a poner un plan fuera de la lista blanca.
     */
    public function assignPlan($companyId)
    {
        $tenant = $this->tenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        $back    = $this->request->getPost('back') === 'empresas' ? '/tenant/empresas' : '/tenant';
        $company = $this->ownCompany($companyId, (int) $tenant['id']);
        if (!$company) {
            return redirect()->to($back)->with('error', lang('Tenant.errNotInGroup'));
        }

        $planId = (int) $this->request->getPost('plan_id');
        if (!$planId || !$this->tenantPlanModel->allows((int) $tenant['id'], $planId)) {
            return redirect()->to($back)->with('error', lang('Tenant.errPlanNotAllowed'));
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $this->companyModel->skipValidation(true);
        $this->companyModel->update($companyId, ['plan_id' => $planId]);
        $this->companyModel->skipValidation(false);
        CompanyModel::flushPlanCache();

        $this->syncSubscription((int) $companyId, $planId);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to($back)->with('error', lang('Tenant.errUpdate'));
        }

        return redirect()->to($back)->with('success', lang('Tenant.okPlanAssigned', [$company['name']]));
    }

    // ------------------------------------------------------------------ helpers

    /** La suscripción de la empresa sigue al plan asignado; si no tenía, se crea. */
    private function syncSubscription(int $companyId, int $planId): void
    {
        $current = $this->subModel->getActiveForCompany($companyId);
        if ($current) {
            $this->subModel->update($current['id'], ['plan_id' => $planId]);

            return;
        }

        $plan = $this->planModel->find($planId);
        $this->subModel->createForCompany($companyId, $planId, 'active', null, null, (int) ($plan['service_days'] ?? 0));
    }

    /** Reglas del bloque «administrador» según el modo elegido. */
    private function adminRules(string $mode, bool $required): array
    {
        if ($mode === 'new') {
            return [
                'admin_username'   => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
                'admin_email'      => 'required|valid_email|is_unique[users.email]',
                'admin_password'   => 'required|min_length[8]',
                'admin_first_name' => 'required|max_length[50]',
                'admin_last_name'  => 'required|max_length[50]',
            ];
        }

        if ($mode === 'existing') {
            return ['admin_user_id' => 'required|is_natural_no_zero'];
        }

        return $required ? ['admin_user_id' => 'required|is_natural_no_zero'] : [];
    }

    /** Crea o toma el usuario que administrará la empresa. */
    private function resolveAdmin(string $mode): ?int
    {
        if ($mode !== 'new') {
            return (int) $this->request->getPost('admin_user_id') ?: null;
        }

        $id = $this->userModel->insert([
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

        return $id ? (int) $id : null;
    }

    /**
     * Deja al usuario con **un** rol de los asignables (de Admin hacia abajo).
     * Los que el administrador del grupo no puede otorgar ni se ponen ni se
     * quitan. Sin rol elegido, se le da «Admin»: es el administrador de la
     * empresa y sin rol no podría gestionarla.
     */
    private function applyRole(int $userId, int $roleId): void
    {
        $allowed = $this->guard->assignableRoles();
        $ids     = array_map('intval', array_column($allowed, 'id'));

        if (!$roleId || !in_array($roleId, $ids, true)) {
            foreach ($allowed as $r) {
                if ($r['name'] === 'Admin') {
                    $roleId = (int) $r['id'];
                    break;
                }
            }
        }

        if (!$roleId || !in_array($roleId, $ids, true)) {
            return;
        }

        foreach ($ids as $rid) {
            if ($rid !== $roleId) {
                $this->userModel->removeRole($userId, $rid);
            }
        }

        $this->userModel->assignRole($userId, $roleId);
    }

    /** Campos de la empresa desde el POST (sin plan, grupo ni dueño: los pone el controlador). */
    private function collect(?int $id = null): array
    {
        $name = (string) $this->request->getPost('name');
        $slug = $this->request->getPost('slug') ?: $this->companyModel->makeSlug($name, $id);

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
            'is_active'     => $this->request->getPost('is_active') ? 1 : 0,
        ];

        $data += CompanyModel::locationFields($this->request);

        foreach (CompanyModel::SOCIAL_NETWORKS as $net) {
            $data[$net] = CompanyModel::socialUrl($net, $this->request->getPost($net));
        }

        $uploader = new UploadService();
        if ($logo = $uploader->storeImage($this->request->getFile('logo'), 'companies', 'company')) {
            $data['logo'] = $logo;
        }
        if ($banner = $uploader->storeImage($this->request->getFile('banner'), 'companies', 'banner')) {
            $data['banner'] = $banner;
        }

        return $data;
    }
}
