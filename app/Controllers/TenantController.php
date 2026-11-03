<?php

namespace App\Controllers;

use App\Libraries\TenantGuard;
use App\Models\CompanyModel;
use App\Models\CompanyUserModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;
use App\Models\TenantPlanModel;
use App\Models\UserModel;

/**
 * Catálogo de **tenants** (grupos de empresas: centros comerciales, cámaras de
 * comercio) — administración de plataforma, permisos `tenants.*`, solo Super
 * Admin.
 *
 * Un tenant es una fila de `companies` con `type = 'tenant'`, así que reutiliza
 * sus datos, su marca, sus canales y su suscripción. Lo propio del nivel es:
 *
 *  - su **plan de tenant** (`plans.scope = 'tenant'`): qué componentes de nivel
 *    grupo usa y **cuántas empresas puede agrupar** (`max_companies`);
 *  - la **lista blanca** de planes de empresa que puede repartir entre sus
 *    empresas (`tenant_plans`), con uno marcado por defecto;
 *  - su **administrador**, que es un miembro con `is_admin = 1` de la fila-tenant.
 *
 * El administrador del grupo NO se gestiona aquí con un rol global: ver
 * `TenantGuard` para por qué el alcance sale del vínculo y no del rol.
 */
class TenantController extends BaseController
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

    public function index()
    {
        $dt = (new \App\Libraries\DataTable($this->companyModel, $this->request))
            ->query(static fn ($m) => $m
                ->select('companies.*, plans.name AS plan_name, CONCAT(u.first_name, " ", u.last_name) AS owner_name')
                ->join('plans', 'plans.id = companies.plan_id', 'left')
                ->join('users u', 'u.id = companies.owner_id', 'left')
                ->where('companies.type', 'tenant'))
            ->searchable(['companies.name', 'companies.nit', 'companies.contact_email'])
            ->sortable(['companies.id', 'companies.name', 'companies.nit', 'companies.is_active'])
            ->defaultSort('companies.name', 'asc')
            ->paginate(15);

        $ids     = array_map(static fn ($c) => (int) $c['id'], $dt['rows']);
        $counts  = [];
        foreach ($ids as $id) {
            $counts[$id] = ['used' => $this->guard->companyCount($id), 'left' => $this->guard->companiesLeft($id)];
        }

        return view('tenants/index', [
            'dt'       => $dt,
            'subModel' => $this->subModel,
            'subMap'   => $this->subModel->getMapForCompanies($ids),
            'counts'   => $counts,
            'title'    => lang('Tenant.tenants'),
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'POST') {
            return $this->store();
        }

        return view('tenants/create', [
            'plans'        => $this->planModel->getActive('tenant'),
            'companyPlans' => $this->planModel->getActive('company'),
            'users'        => $this->userModel->orderBy('first_name', 'ASC')->findAll(),
            'title'        => lang('Tenant.createTenant'),
        ]);
    }

    private function store()
    {
        $adminMode = $this->request->getPost('admin_mode') ?: 'existing';

        $rules = [
            'name'          => 'required|max_length[150]',
            'plan_id'       => 'required|is_natural_no_zero',
            'status'        => 'required|in_list[trial,active,expired,cancelled]',
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

        if ($error = $this->planScopeError((int) $this->request->getPost('plan_id'))) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $data             = $this->collect();
        $data['owner_id'] = null;
        $tenantId         = $this->companyModel->insert($data);

        if (!$tenantId) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('errors', $this->companyModel->errors() ?: [lang('Tenant.errCreate')]);
        }

        $adminId = $this->resolveAdmin($adminMode);
        if (!$adminId) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('errors', $this->userModel->errors() ?: [lang('Tenant.errAdmin')]);
        }

        // El administrador del grupo es un miembro con is_admin de la fila-tenant.
        // De ahí sale su alcance (TenantGuard), no de un rol global.
        $this->companyModel->update($tenantId, ['owner_id' => $adminId]);
        $this->memberModel->addUser((int) $tenantId, (int) $adminId, true);
        if ($roleId = $this->adminRoleId()) {
            $this->userModel->assignRole($adminId, $roleId);
        }

        $this->saveWhitelist((int) $tenantId);

        $plan = $this->planModel->find($this->request->getPost('plan_id'));
        $this->subModel->createForCompany(
            (int) $tenantId,
            (int) $this->request->getPost('plan_id'),
            $this->request->getPost('status'),
            $this->request->getPost('start_date') ?: null,
            $this->request->getPost('end_date') ?: null,
            (int) ($plan['service_days'] ?? 0)
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', lang('Tenant.errCreate'));
        }

        return redirect()->to('/tenants')->with('success', lang('Tenant.okCreated'));
    }

    public function edit($id)
    {
        $tenant = $this->companyModel->find($id);
        if (!$tenant || !$this->companyModel->isTenant($tenant)) {
            return redirect()->to('/tenants')->with('error', lang('Tenant.errTenantNotFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->update($id, $tenant);
        }

        return view('tenants/edit', [
            'tenant'        => $tenant,
            'plans'         => $this->planModel->getActive('tenant'),
            'companyPlans'  => $this->planModel->getActive('company'),
            'whitelist'     => $this->tenantPlanModel->planIdsFor((int) $id),
            'defaultPlanId' => $this->tenantPlanModel->defaultPlanId((int) $id),
            'users'         => $this->userModel->orderBy('first_name', 'ASC')->findAll(),
            'subscription'  => $this->subModel->getActiveForCompany((int) $id),
            'childCount'    => $this->guard->companyCount((int) $id),
            'title'         => lang('Tenant.editTenant'),
        ]);
    }

    private function update($id, array $tenant)
    {
        $rules = [
            'name'          => 'required|max_length[150]',
            'slug'          => "permit_empty|max_length[160]|is_unique[companies.slug,id,$id]",
            'plan_id'       => 'required|is_natural_no_zero',
            'status'        => 'required|in_list[trial,active,expired,cancelled]',
            'contact_email' => 'permit_empty|valid_email',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($error = $this->planScopeError((int) $this->request->getPost('plan_id'))) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $data             = $this->collect((int) $id);
        $ownerId          = (int) $this->request->getPost('owner_id') ?: null;
        $data['owner_id'] = $ownerId;

        // Validado arriba con el $id real; la regla del modelo marcaría el
        // propio slug como duplicado en update() (gotcha is_unique de CI4).
        $this->companyModel->skipValidation(true);
        $this->companyModel->update($id, $data);
        $this->companyModel->skipValidation(false);

        if ($ownerId) {
            $this->memberModel->addUser((int) $id, $ownerId, true);
            if ($roleId = $this->adminRoleId()) {
                $this->userModel->assignRole($ownerId, $roleId);
            }
        }

        $this->saveWhitelist((int) $id);

        $planId  = (int) $this->request->getPost('plan_id');
        $current = $this->subModel->getActiveForCompany((int) $id);
        if ($current) {
            $this->subModel->update($current['id'], array_filter([
                'plan_id'    => $planId,
                'status'     => $this->request->getPost('status'),
                'start_date' => $this->request->getPost('start_date') ?: null,
                'end_date'   => $this->request->getPost('end_date') ?: null,
            ], static fn ($v) => $v !== null));
        } else {
            $plan = $this->planModel->find($planId);
            $this->subModel->createForCompany(
                (int) $id,
                $planId,
                $this->request->getPost('status'),
                $this->request->getPost('start_date') ?: null,
                $this->request->getPost('end_date') ?: null,
                (int) ($plan['service_days'] ?? 0)
            );
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', lang('Tenant.errUpdate'));
        }

        return redirect()->to('/tenants')->with('success', lang('Tenant.okUpdated'));
    }

    /**
     * Elimina el grupo. **No borra sus empresas**: las descuelga y quedan
     * sueltas (la confirmación avisa de cuántas). El soft-delete de `companies`
     * no dispararía ninguna FK, así que se descuelgan explícitamente.
     */
    public function delete($id)
    {
        $tenant = $this->companyModel->find($id);
        if (!$tenant || !$this->companyModel->isTenant($tenant)) {
            return redirect()->to('/tenants')->with('error', lang('Tenant.errTenantNotFound'));
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $freed = $this->guard->companyCount((int) $id);
        $db->table('companies')->where('parent_id', $id)->update(['parent_id' => null]);
        $this->tenantPlanModel->where('tenant_id', $id)->delete();
        $this->companyModel->delete($id);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/tenants')->with('error', lang('Tenant.errDelete'));
        }

        return redirect()->to('/tenants')->with('success', lang('Tenant.okDeleted', [$freed]));
    }

    // ------------------------------------------------------------------ empresas del grupo

    /** Colgar y descolgar empresas del tenant. */
    public function companies($id)
    {
        $tenant = $this->companyModel->find($id);
        if (!$tenant || !$this->companyModel->isTenant($tenant)) {
            return redirect()->to('/tenants')->with('error', lang('Tenant.errTenantNotFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->attach((int) $id);
        }

        // Candidatas: empresas sueltas (una que ya está en otro grupo se mueve
        // desde su propia ficha, para que quede constancia de la salida).
        $available = $this->companyModel
            ->where('type', 'company')
            ->groupStart()->where('parent_id', null)->groupEnd()
            ->orderBy('name', 'ASC')->findAll();

        return view('tenants/companies', [
            'tenant'    => $tenant,
            'children'  => $this->companyModel->childrenOf((int) $id),
            'available' => $available,
            'left'      => $this->guard->companiesLeft((int) $id),
            'plans'     => $this->tenantPlanModel->plansFor((int) $id),
            'title'     => lang('Tenant.groupCompanies') . ' — ' . $tenant['name'],
        ]);
    }

    private function attach(int $tenantId)
    {
        $companyId = (int) $this->request->getPost('company_id');
        if (!$companyId) {
            return redirect()->back()->with('error', lang('Tenant.errPickCompany'));
        }

        if (!$this->companyModel->canBeParent($tenantId, $companyId)) {
            return redirect()->back()->with('error', lang('Tenant.errInvalidParent'));
        }

        $left = $this->guard->companiesLeft($tenantId);
        if ($left !== null && $left <= 0) {
            $tenant = $this->companyModel->find($tenantId);

            return redirect()->back()->with('error', lang('Tenant.errQuotaFull', [$tenant['name'] ?? '']));
        }

        $data = ['parent_id' => $tenantId];

        // Sin plan propio recibe el marcado por defecto en la lista blanca, para
        // que no quede sin servicios por un olvido.
        $company = $this->companyModel->find($companyId);
        if (empty($company['plan_id']) && ($default = $this->tenantPlanModel->defaultPlanId($tenantId))) {
            $data['plan_id'] = $default;
        }

        $this->companyModel->skipValidation(true);
        $this->companyModel->update($companyId, $data);
        $this->companyModel->skipValidation(false);
        TenantGuard::flush(); // cambió quién cuelga de quién

        return redirect()->to('tenants/empresas/' . $tenantId)->with('success', lang('Tenant.okAttached'));
    }

    public function detach($tenantId, $companyId)
    {
        $company = $this->companyModel->find($companyId);
        if (!$company || (int) ($company['parent_id'] ?? 0) !== (int) $tenantId) {
            return redirect()->to('tenants/empresas/' . $tenantId)->with('error', lang('Tenant.errNotInGroup'));
        }

        $this->companyModel->skipValidation(true);
        $this->companyModel->update($companyId, ['parent_id' => null]);
        $this->companyModel->skipValidation(false);
        TenantGuard::flush();

        return redirect()->to('tenants/empresas/' . $tenantId)->with('success', lang('Tenant.okDetached'));
    }

    // ------------------------------------------------------------------ helpers

    /** El plan elegido tiene que ser de ámbito tenant. */
    private function planScopeError(int $planId): ?string
    {
        $plan = $this->planModel->find($planId);
        if (!$plan || PlanModel::scopeOf($plan) !== 'tenant') {
            return lang('Tenant.errPlanScope');
        }

        return null;
    }

    /** Guarda la lista blanca de planes de empresa que el tenant puede repartir. */
    private function saveWhitelist(int $tenantId): void
    {
        $ids = array_map('intval', (array) $this->request->getPost('assignable_plans'));

        // Solo planes de ámbito EMPRESA: repartir un plan de tenant no tendría
        // sentido (sus banderas son las de los componentes del grupo).
        $valid = [];
        foreach ($ids as $planId) {
            $plan = $planId ? $this->planModel->find($planId) : null;
            if ($plan && PlanModel::scopeOf($plan) === 'company') {
                $valid[] = $planId;
            }
        }

        $this->tenantPlanModel->sync($tenantId, $valid, (int) $this->request->getPost('default_plan') ?: null);
    }

    /** Crea o toma el usuario que administrará el grupo. */
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

    private function adminRoleId(): ?int
    {
        $row = \Config\Database::connect()->table('roles')->where('name', 'Admin')->get()->getRow();

        return $row ? (int) $row->id : null;
    }

    /** Campos del tenant desde el POST (reusa los de empresa + `type`). */
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
            'plan_id'       => (int) $this->request->getPost('plan_id') ?: null,
            'is_active'     => $this->request->getPost('is_active') ? 1 : 0,
            'type'          => 'tenant',
            'parent_id'     => null, // un tenant nunca cuelga de otro (un solo nivel)
            'can_create_children' => $this->request->getPost('can_create_children') ? 1 : 0,
        ];

        $data += CompanyModel::locationFields($this->request);

        foreach (CompanyModel::SOCIAL_NETWORKS as $net) {
            $data[$net] = CompanyModel::socialUrl($net, $this->request->getPost($net));
        }

        $uploader = new \App\Libraries\UploadService();
        if ($logo = $uploader->storeImage($this->request->getFile('logo'), 'companies', 'tenant')) {
            $data['logo'] = $logo;
        }
        if ($banner = $uploader->storeImage($this->request->getFile('banner'), 'companies', 'banner')) {
            $data['banner'] = $banner;
        }

        return $data;
    }
}
