<?php

namespace App\Controllers;

use App\Libraries\TenantGuard;
use App\Models\CompanyModel;
use App\Models\CompanyUserModel;
use App\Models\SubscriptionModel;
use App\Models\TenantPlanModel;
use App\Models\UserModel;
use App\Traits\ManagesOwnCompany;

/**
 * **Panel del Tenant** (`/tenant`): lo que ve y hace el administrador de un
 * centro comercial o una cámara de comercio.
 *
 * No lleva permisos `tenants.*` — esos son de plataforma. El acceso lo gobierna
 * el **vínculo**: ser `owner_id` o miembro con `is_admin` de la fila-tenant
 * (`TenantGuard::tenantIdsOf()`), igual que «Mi empresa» se gobierna por
 * `CompanyModel::isManager()`. Un rol global daría poder sobre los grupos de
 * toda la plataforma.
 *
 * Es deliberadamente una vista **de grupo, no un revuelto de datos**: aquí se
 * ven las empresas, su plan y su vigencia, y se **entra** a cada una. Los datos
 * de los componentes se siguen viendo dentro de cada empresa, con el
 * aislamiento por `active_company_id` intacto — por eso ningún componente
 * necesitó cambiar.
 */
class TenantPanelController extends BaseController
{
    // Un grupo es también una fila de `companies`, así que su administrador
    // edita la MISMA ficha y los MISMOS canales que el de una empresa. El trait
    // es lo que evita que sean dos formularios que se van separando.
    use ManagesOwnCompany;

    protected CompanyModel $companyModel;
    protected SubscriptionModel $subModel;
    protected TenantPlanModel $tenantPlanModel;
    protected CompanyUserModel $memberModel;
    protected UserModel $userModel;
    protected TenantGuard $guard;

    public function __construct()
    {
        $this->companyModel    = new CompanyModel();
        $this->subModel        = new SubscriptionModel();
        $this->tenantPlanModel = new TenantPlanModel();
        $this->memberModel     = new CompanyUserModel();
        $this->userModel       = new UserModel();
        $this->guard           = new TenantGuard();
    }

    /**
     * El tenant sobre el que se está trabajando, comprobando el vínculo.
     * Devuelve null si el usuario no administra ninguno.
     */
    private function currentTenant(): ?array
    {
        $userId = (int) session()->get('user_id');

        return $this->guard->currentTenantFor($userId, $this->isSuper($userId));
    }

    private function isSuper(int $userId): bool
    {
        $user = $this->userModel->getUserWithRoles($userId);

        return in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);
    }

    public function index()
    {
        $tenant = $this->currentTenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        $tenantId = (int) $tenant['id'];
        $children = $this->companyModel->childrenOf($tenantId);
        $childIds = array_map(static fn ($c) => (int) $c['id'], $children);

        $sub      = $this->subModel->markExpiredIfNeeded($this->subModel->getActiveForCompany($tenantId));
        $daysLeft = null;
        if ($sub && !empty($sub['end_date'])) {
            $daysLeft = (int) floor((strtotime($sub['end_date']) - strtotime('today')) / 86400);
        }

        // Plan y vigencia de cada empresa del grupo, para la tabla. La lista
        // blanca se pide UNA vez: se usa para el selector y para los nombres.
        $assignable = $this->tenantPlanModel->plansFor($tenantId);
        $planNames  = [];
        foreach ($assignable as $p) {
            $planNames[(int) $p['id']] = $p['name'];
        }

        return view('tenants/panel', [
            'title'      => lang('Tenant.tenantPanel'),
            'tenant'     => $tenant,
            'plan'       => $this->companyModel->effectivePlan($tenantId),
            'sub'        => $sub,
            'valid'      => $this->subModel->isValid($sub),
            'daysLeft'   => $daysLeft,
            'children'   => $children,
            'subMap'     => $this->subModel->getMapForCompanies($childIds),
            'subModel'   => $this->subModel,
            'used'       => count($children),
            'left'       => $this->guard->companiesLeft($tenantId),
            'plans'      => $assignable,
            'planNames'  => $planNames,
            'services'   => (new \App\Libraries\ComponentManager())->planStatus(),
            'canCreate'  => !empty($tenant['can_create_children']),
        ]);
    }

    /**
     * Usuarios de las empresas del grupo. Mismo listado que `/users`
     * (`DataTable` + el partial) pero acotado a los miembros de SUS empresas;
     * un usuario que esté en dos de ellas sale una vez por empresa, que es lo
     * que hay que ver aquí.
     */
    public function users()
    {
        $tenant = $this->currentTenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        $tenantId = (int) $tenant['id'];
        $children = $this->companyModel->childrenOf($tenantId);
        $childIds = array_map(static fn ($c) => (int) $c['id'], $children) ?: [0];

        $dt = (new \App\Libraries\DataTable($this->userModel, $this->request))
            ->query(static fn ($m) => $m
                ->select('users.*, companies.id AS company_id, companies.name AS company_name, companies.owner_id, company_users.is_admin')
                ->join('company_users', 'company_users.user_id = users.id')
                ->join('companies', 'companies.id = company_users.company_id')
                ->whereIn('company_users.company_id', $childIds)
                ->where('companies.deleted_at', null))
            ->searchable(['users.username', 'users.email', 'users.first_name', 'users.last_name'])
            ->sortable(['users.id', 'users.username', 'users.email', 'users.first_name', 'users.is_active', 'users.last_login_at'])
            ->defaultSort('companies.name', 'asc')
            ->paginate(15);

        return view('tenants/users', [
            'title'    => lang('Tenant.groupUsers'),
            'tenant'   => $tenant,
            'dt'       => $dt,
            'children' => $children,
            'rolesMap' => $this->rolesMap(array_map(static fn ($u) => (int) $u['id'], $dt['rows'])),
            'roles'    => $this->guard->assignableRoles(),
        ]);
    }

    /** Roles actuales de un puñado de usuarios: [userId => [['id','name'], …]]. */
    private function rolesMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (!$userIds) {
            return [];
        }

        $rows = \Config\Database::connect()->table('user_roles')
            ->select('user_roles.user_id, roles.id, roles.name')
            ->join('roles', 'roles.id = user_roles.role_id')
            ->whereIn('user_roles.user_id', $userIds)
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['user_id']][] = ['id' => (int) $r['id'], 'name' => $r['name']];
        }

        return $map;
    }

    /**
     * Alta de un usuario **dentro de una empresa del grupo**: uno nuevo, o uno
     * que ya tiene cuenta en la plataforma. Pantalla propia, como
     * `/users/create`.
     */
    public function newUser()
    {
        $tenant = $this->currentTenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        $children = $this->companyModel->childrenOf((int) $tenant['id']);

        if ($this->request->getMethod() === 'POST') {
            return $this->storeUser($tenant, array_map(static fn ($c) => (int) $c['id'], $children));
        }

        return view('tenants/user_form', [
            'title'    => lang('Tenant.newUser'),
            'tenant'   => $tenant,
            'children' => $children,
            'roles'    => $this->guard->assignableRoles(),
            'users'    => $this->userModel->where('is_active', 1)->orderBy('first_name', 'ASC')->findAll(),
        ]);
    }

    private function storeUser(array $tenant, array $childIds)
    {
        // Pertenencia: la empresa llega por POST, así que se comprueba aquí.
        $companyId = (int) $this->request->getPost('company_id');
        if (!$companyId || !in_array($companyId, $childIds, true)) {
            return redirect()->to('/tenant/usuarios')->with('error', lang('Tenant.errNotInGroup'));
        }

        $mode    = $this->request->getPost('mode') === 'new' ? 'new' : 'existing';
        $isAdmin = (bool) $this->request->getPost('is_admin');
        $roleId  = (int) $this->request->getPost('role_id');

        if ($mode === 'existing') {
            $userId = (int) $this->request->getPost('user_id');
            if (!$userId) {
                return redirect()->back()->withInput()->with('error', lang('Tenant.errPickUser'));
            }

            $this->memberModel->addUser($companyId, $userId, $isAdmin);

            // Un rol vacío al vincular significa «déjale el que ya tenía», no
            // «quítaselo»: quitarlo sería un efecto lateral que nadie pidió.
            if ($roleId) {
                $this->applyRole($userId, $roleId);
            }

            return redirect()->to('/tenant/usuarios')->with('success', lang('Tenant.okUserLinked'));
        }

        $rules = [
            'username'   => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'      => 'required|valid_email|is_unique[users.email]',
            'password'   => 'required|min_length[8]',
            'first_name' => 'required|max_length[50]',
            'last_name'  => 'required|max_length[50]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = $this->userModel->insert([
            'username'           => $this->request->getPost('username'),
            'email'              => $this->request->getPost('email'),
            'password'           => $this->request->getPost('password'),
            'first_name'         => $this->request->getPost('first_name'),
            'last_name'          => $this->request->getPost('last_name'),
            'phone'              => $this->request->getPost('phone'),
            'is_active'          => 1,
            'auth_method'        => 'password',
            'preferred_language' => $this->request->getPost('preferred_language') ?: 'es',
        ]);

        if (!$userId) {
            return redirect()->back()->withInput()
                ->with('errors', $this->userModel->errors() ?: [lang('Tenant.errUserCreate')]);
        }

        $this->memberModel->addUser($companyId, (int) $userId, $isAdmin);

        if ($roleId) {
            $this->applyRole((int) $userId, $roleId);
        }

        return redirect()->to('/tenant/usuarios')->with('success', lang('Tenant.okUserCreated'));
    }

    /**
     * Rol de un usuario de sus empresas — pantalla propia, como
     * `/users/assign-role/{id}`. Solo entre los asignables (de Admin hacia
     * abajo) y solo si el usuario es miembro de una empresa **del grupo**.
     */
    public function role($userId)
    {
        $tenant = $this->currentTenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        if (!$this->isGroupMember((int) $userId, (int) $tenant['id'])) {
            return redirect()->to('/tenant/usuarios')->with('error', lang('Tenant.errNotGroupUser'));
        }

        $user = $this->userModel->getUserWithRoles((int) $userId);
        if (!$user) {
            return redirect()->to('/tenant/usuarios')->with('error', lang('Tenant.errNotGroupUser'));
        }

        if ($this->request->getMethod() === 'POST') {
            if (!$this->applyRole((int) $userId, (int) $this->request->getPost('role_id'))) {
                return redirect()->to('/tenant/usuarios')->with('error', lang('Tenant.errRoleNotAllowed'));
            }

            return redirect()->to('/tenant/usuarios')->with('success', lang('Tenant.okRoleAssigned'));
        }

        $roles   = $this->guard->assignableRoles();
        $ids     = array_map(static fn ($r) => (int) $r['id'], $roles);
        $current = array_values(array_filter(
            array_map(static fn ($r) => (int) $r['id'], $user['roles'] ?? []),
            static fn ($id) => in_array($id, $ids, true)
        ));

        return view('tenants/user_role', [
            'title'   => lang('Tenant.assignRole'),
            'tenant'  => $tenant,
            'user'    => $user,
            'roles'   => $roles,
            'current' => $current[0] ?? 0,
            // Los que no puede otorgar se muestran, pero no se tocan.
            'locked'  => array_values(array_filter(
                $user['roles'] ?? [],
                static fn ($r) => !in_array((int) $r['id'], $ids, true)
            )),
        ]);
    }

    /** ¿El usuario es miembro de alguna empresa del grupo? */
    private function isGroupMember(int $userId, int $tenantId): bool
    {
        $childIds = $this->guard->childIdsOfTenants([$tenantId]);
        if (!$childIds) {
            return false;
        }

        return $this->memberModel->whereIn('company_id', $childIds)->where('user_id', $userId)->countAllResults() > 0;
    }

    /**
     * Deja al usuario con **un** rol de los asignables. Los que el
     * administrador del grupo no puede otorgar (Super Admin, roles de
     * plataforma) ni se ponen ni se quitan: no son suyos.
     *
     * `$roleId = 0` es una elección válida — «sin rol»— y quita los asignables.
     */
    private function applyRole(int $userId, int $roleId): bool
    {
        $allowed = array_map('intval', array_column($this->guard->assignableRoles(), 'id'));

        if ($roleId && !in_array($roleId, $allowed, true)) {
            return false;
        }

        foreach ($allowed as $rid) {
            if ($rid !== $roleId) {
                $this->userModel->removeRole($userId, $rid);
            }
        }

        if ($roleId) {
            $this->userModel->assignRole($userId, $roleId);
        }

        return true;
    }

    /** Quita a un usuario de una empresa del grupo (no borra el usuario). */
    public function removeUser($companyId, $userId)
    {
        $tenant = $this->currentTenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        $company = $this->companyModel->find($companyId);
        if (!$company || (int) ($company['parent_id'] ?? 0) !== (int) $tenant['id']) {
            return redirect()->to('/tenant/usuarios')->with('error', lang('Tenant.errNotInGroup'));
        }
        if ((int) $company['owner_id'] === (int) $userId) {
            return redirect()->to('/tenant/usuarios')->with('error', lang('Tenant.errCantRemoveOwner'));
        }

        $this->memberModel->removeUser((int) $companyId, (int) $userId);

        return redirect()->to('/tenant/usuarios')->with('success', lang('Tenant.okUserRemoved'));
    }

    // =====================================================================
    // MI GRUPO — la ficha y los canales del propio tenant
    //
    // Es el equivalente exacto de «Mi empresa» para el administrador del
    // grupo. Faltaba: el contexto de grupo limpia `active_company_id`, así que
    // «Mi empresa» lo rechazaba, y el panel global de Empresas es de
    // plataforma (Super Admin). El resultado era que quien administra un
    // centro comercial no podía ni cambiarle el logo a su propio grupo.
    //
    // Mismo alcance que el resto del panel: lo gobierna el **vínculo**
    // (`currentTenant()`), no un permiso global.
    // =====================================================================

    /** Prefijo de URL de estas pantallas (lo usan las vistas compartidas). */
    private function profileBase(): string
    {
        return 'tenant';
    }

    public function data()
    {
        $tenant = $this->currentTenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        if ($this->request->getMethod() === 'POST') {
            $errors = $this->saveProfile((int) $tenant['id']);
            if ($errors) {
                return redirect()->back()->withInput()->with('errors', $errors);
            }

            // El nombre y la marca del grupo los pinta el sidebar y los lee
            // `TenantGuard`, que memoiza por petición: sin limpiar, lo viejo
            // sigue a la vista el resto de la petición.
            TenantGuard::flush();

            return redirect()->to('/tenant/datos')->with('success', lang('Tenant.okGroupSaved'));
        }

        return view('my_company/datos', [
            'title'   => lang('Tenant.myTenant'),
            'heading' => lang('Tenant.myTenant'),
            'base'    => $this->profileBase(),
            'company' => $tenant,
            'tab'     => 'datos',
        ]);
    }

    public function channels()
    {
        $tenant = $this->currentTenant();
        if (!$tenant) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }
        if ($this->request->getMethod() === 'POST') {
            $this->saveChannelsFor((int) $tenant['id']);

            return redirect()->to('/tenant/canales')->with('success', lang('Tenant.okGroupChannels'));
        }

        [$cfg, $secret] = $this->channelConfig((int) $tenant['id']);

        return view('my_company/channels', [
            'title'     => lang('Tenant.groupChannels') . ' — ' . $tenant['name'],
            'heading'   => lang('Tenant.myTenant'),
            'base'      => $this->profileBase(),
            'company'   => $tenant,
            'config'    => $cfg,
            'secretSet' => $secret,
            'tab'       => 'canales',
        ]);
    }

    public function testChannel($service = null)
    {
        $tenant = $this->currentTenant();
        if (!$tenant) {
            return $this->response->setStatusCode(403)
                ->setJSON(['ok' => false, 'message' => lang('Tenant.errNoTenantAccess'), 'csrf' => csrf_hash()]);
        }

        $res         = $this->probeChannel((int) $tenant['id'], $service);
        $res['csrf'] = csrf_hash();

        return $this->response->setJSON($res);
    }
}
