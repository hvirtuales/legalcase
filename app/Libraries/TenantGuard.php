<?php

namespace App\Libraries;

use App\Models\CompanyModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;
use App\Models\TenantPlanModel;

/**
 * Única fuente de verdad del **alcance del tenant**: qué grupo administra un
 * usuario y qué empresas entran en él.
 *
 * Un tenant (centro comercial, cámara de comercio) es una fila de `companies`
 * con `type = 'tenant'`; sus empresas son las que tienen `parent_id` apuntando
 * a ella. El **administrador del tenant** es un miembro de esa fila con
 * `is_admin = 1` (o su `owner_id`) — deliberadamente NO un rol global: los
 * roles de la plataforma no miran contexto, así que un rol «Admin de tenant»
 * con permisos de empresas daría poder sobre TODAS las empresas.
 *
 * Se engancha en **dos sitios y no más** —`CompanyModel::getAccessibleFor()` y
 * `CompanyModel::userCanAccess()`—, que es a quienes ya preguntan el
 * `AuthFilter`, el selector del encabezado y `CompanySwitcherController`. Por
 * eso los componentes siguen aislados por `active_company_id` sin cambiar una
 * línea: el administrador del tenant **cambia de empresa**, no ve un revuelto
 * de todas.
 *
 * Las consultas van por Query Builder crudo a propósito: llamar de vuelta a
 * `CompanyModel::getAccessibleFor()` desde aquí sería recursión infinita.
 */
class TenantGuard
{
    /**
     * Caché **por petición**. El alcance se pregunta muchas veces en una sola
     * pantalla —`AuthFilter`, el sidebar, el selector del encabezado, el
     * controlador— y cada `new TenantGuard()` volvía a consultarlo: en el panel
     * del grupo eran 4–5 veces las mismas dos consultas. Es estática a
     * propósito, porque quien pregunta son instancias distintas.
     *
     * Se limpia con `flush()` **después de mover empresas o vínculos** dentro de
     * la misma petición (y en CLI, donde un proceso hace de muchas).
     *
     * @var array<string, mixed>
     */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** Memoiza el resultado de $fn bajo $key. */
    private function remember(string $key, callable $fn)
    {
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = $fn();
        }

        return self::$cache[$key];
    }

    private function db()
    {
        return \Config\Database::connect();
    }

    /** Empresas vivas (respeta el soft-delete de `companies`). */
    private function companies()
    {
        return $this->db()->table('companies')->where('deleted_at', null);
    }

    // ------------------------------------------------------------ qué administra

    /**
     * IDs de los tenants que administra el usuario: es su `owner_id` o un
     * miembro con `is_admin = 1` de una fila con `type = 'tenant'`.
     *
     * @return list<int>
     */
    public function tenantIdsOf(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->remember("tenants:{$userId}", fn () => $this->fetchTenantIdsOf($userId));
    }

    /** @return list<int> */
    private function fetchTenantIdsOf(int $userId): array
    {
        $owned = array_column(
            $this->companies()->select('id')->where('type', 'tenant')->where('owner_id', $userId)->get()->getResultArray(),
            'id'
        );

        $admin = array_column(
            $this->db()->table('company_users')
                ->select('company_users.company_id')
                ->join('companies', 'companies.id = company_users.company_id')
                ->where('company_users.user_id', $userId)
                ->where('company_users.is_admin', 1)
                ->where('companies.type', 'tenant')
                ->where('companies.deleted_at', null)
                ->get()->getResultArray(),
            'company_id'
        );

        return array_values(array_unique(array_map('intval', array_merge($owned, $admin))));
    }

    /** Los tenants que administra el usuario, como filas de `companies`. */
    public function tenantsOf(int $userId): array
    {
        $ids = $this->tenantIdsOf($userId);

        return $ids ? (new CompanyModel())->whereIn('id', $ids)->orderBy('name', 'ASC')->findAll() : [];
    }

    public function isTenantAdmin(int $userId): bool
    {
        return $this->tenantIdsOf($userId) !== [];
    }

    /**
     * IDs de las empresas que cuelgan de los tenants que administra el usuario.
     * NO incluye las propias filas-tenant: entrar «a la cámara» es el Panel del
     * Tenant, no el contexto de una empresa.
     *
     * @return list<int>
     */
    public function childIdsOf(int $userId): array
    {
        $tenantIds = $this->tenantIdsOf($userId);
        if (!$tenantIds) {
            return [];
        }

        return $this->childIdsOfTenants($tenantIds);
    }

    /**
     * IDs de las empresas de uno o varios tenants.
     *
     * @param list<int> $tenantIds
     * @return list<int>
     */
    public function childIdsOfTenants(array $tenantIds): array
    {
        $tenantIds = array_values(array_filter(array_map('intval', $tenantIds)));
        if (!$tenantIds) {
            return [];
        }

        sort($tenantIds);

        return $this->remember('children:' . implode(',', $tenantIds), fn () => array_map('intval', array_column(
            $this->companies()->select('id')->whereIn('parent_id', $tenantIds)->get()->getResultArray(),
            'id'
        )));
    }

    /** ¿Esa empresa pertenece a un tenant que el usuario administra? */
    public function manages(int $companyId, int $userId): bool
    {
        if ($companyId <= 0 || $userId <= 0) {
            return false;
        }

        $company = $this->companies()->select('parent_id')->where('id', $companyId)->get()->getRowArray();
        if (!$company || empty($company['parent_id'])) {
            return false;
        }

        return in_array((int) $company['parent_id'], $this->tenantIdsOf($userId), true);
    }

    // ------------------------------------------------------------ cupo del plan

    /** Cuántas empresas cuelgan hoy de un tenant. */
    public function companyCount(int $tenantId): int
    {
        return $this->remember(
            "count:{$tenantId}",
            fn () => $this->companies()->where('parent_id', $tenantId)->countAllResults()
        );
    }

    /**
     * Cuántas empresas más puede agrupar el tenant según su plan
     * (`plans.max_companies`, `0` = sin límite). `null` = ilimitado.
     *
     * Sustituye al antiguo `CompanyController::ownerAtLimit()`, que contaba
     * empresas **por dueño**: ahora el cupo es del grupo, que es lo que se
     * contrata. Las empresas sueltas (sin `parent_id`) quedan fuera del cupo.
     */
    public function companiesLeft(int $tenantId): ?int
    {
        return $this->remember("left:{$tenantId}", fn () => $this->fetchCompaniesLeft($tenantId));
    }

    private function fetchCompaniesLeft(int $tenantId): ?int
    {
        $tenant = (new CompanyModel())->find($tenantId);
        if (!$tenant || empty($tenant['plan_id'])) {
            return null; // sin plan asignado → ilimitado, como en el resto de la plataforma
        }

        $plan = (new PlanModel())->find((int) $tenant['plan_id']);
        $max  = (int) ($plan['max_companies'] ?? 0);
        if ($max <= 0) {
            return null;
        }

        return max(0, $max - $this->companyCount($tenantId));
    }

    /** Planes de empresa que el tenant puede repartir (lista blanca del Super Admin). */
    public function assignablePlans(int $tenantId): array
    {
        return (new TenantPlanModel())->plansFor($tenantId);
    }

    // ------------------------------------------------------------ vigencia

    /**
     * ¿La suscripción del tenant está vigente? Si venció, sus empresas quedan
     * bloqueadas aunque tengan la suya al día: se le factura al grupo, no a cada
     * local. Un tenant sin suscripción no bloquea (mismo criterio que una
     * empresa sin plan).
     */
    public function tenantSubscriptionValid(int $tenantId): bool
    {
        return $this->remember("subok:{$tenantId}", fn () => $this->fetchTenantSubscriptionValid($tenantId));
    }

    private function fetchTenantSubscriptionValid(int $tenantId): bool
    {
        $subModel = new SubscriptionModel();
        $sub      = $subModel->getActiveForCompany($tenantId);
        if (!$sub) {
            return true;
        }

        return $subModel->isValid($subModel->markExpiredIfNeeded($sub));
    }

    /**
     * Motivo por el que una empresa no puede usarse, o null si puede.
     * Mira primero al grupo y luego a ella misma.
     */
    public function blockedReason(int $companyId): ?string
    {
        $company = (new CompanyModel())->find($companyId);
        if (!$company) {
            return lang('Tenant.errCompanyNotFound');
        }

        if (!empty($company['parent_id']) && !$this->tenantSubscriptionValid((int) $company['parent_id'])) {
            $tenant = (new CompanyModel())->find((int) $company['parent_id']);

            return lang('Tenant.errTenantExpired', [$tenant['name'] ?? '']);
        }

        return null;
    }

    // ------------------------------------------------------------ roles que puede otorgar

    /**
     * Roles que el administrador de un grupo puede otorgar a los usuarios de
     * sus empresas: **de «Admin» hacia abajo**.
     *
     * Quedan fuera el **Super Admin** y cualquier rol que lleve permisos de
     * plataforma sobre los propios grupos o los componentes (`tenants.*`,
     * `components.manage`, `permissions.*`): repartir uno de esos convertiría a
     * un administrador de local en administrador de la plataforma, que es
     * justo lo que el nivel tenant evita. `Admin` no los tiene, así que sigue
     * siendo asignable — es el rol con el que se administra una empresa.
     */
    public function assignableRoles(): array
    {
        return $this->remember('roles', fn () => $this->fetchAssignableRoles());
    }

    private function fetchAssignableRoles(): array
    {
        $forbidden = $this->db()->table('role_permissions')
            ->select('role_permissions.role_id')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->groupStart()
            ->where('permissions.module', 'tenants')
            ->orWhere('permissions.module', 'permissions')
            ->orWhere('permissions.name', 'components.manage')
            ->groupEnd()
            ->get()->getResultArray();

        $forbidden = array_map('intval', array_column($forbidden, 'role_id'));

        $roles = (new \App\Models\RoleModel())->orderBy('name', 'ASC')->findAll();

        return array_values(array_filter(
            $roles,
            static fn ($r) => $r['name'] !== 'Super Admin' && !in_array((int) $r['id'], $forbidden, true)
        ));
    }

    // ------------------------------------------------------------ contexto activo

    /**
     * El grupo sobre el que se está trabajando, comprobando el **vínculo**.
     *
     * Es la puerta de todas las pantallas de nivel grupo (`/tenant/*`): si
     * devuelve null, el usuario no administra ninguno y no hay nada que
     * mostrarle. Si el grupo fijado en sesión no es suyo, se corrige al
     * primero que sí lo sea en vez de confiar en la sesión.
     *
     * El Super Admin no tiene vínculo con ningún grupo, así que se le deja
     * inspeccionar el que haya elegido en el selector.
     */
    public function currentTenantFor(int $userId, bool $isSuper = false): ?array
    {
        $ids    = $this->tenantIdsOf($userId);
        $active = (int) (session('active_tenant_id') ?? 0);

        if (!$ids) {
            if (!$isSuper || !$active) {
                return null;
            }

            $tenant = (new CompanyModel())->find($active);

            return $tenant && ($tenant['type'] ?? 'company') === 'tenant' ? $tenant : null;
        }

        if (!$active || !in_array($active, $ids, true)) {
            $active = $ids[0];
            session()->set('active_tenant_id', $active);
            session()->remove('active_company_id');
        }

        return (new CompanyModel())->find($active);
    }

    /**
     * Tenant del contexto actual: el del panel del tenant si está fijado, o el
     * de la empresa activa. `null` si se trabaja fuera de un grupo.
     */
    public function activeTenantId(): ?int
    {
        $explicit = (int) (session('active_tenant_id') ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        $companyId = (int) (session('active_company_id') ?? 0);
        if ($companyId <= 0) {
            return null;
        }

        $company = $this->companies()->select('type, parent_id')->where('id', $companyId)->get()->getRowArray();
        if (!$company) {
            return null;
        }

        if (($company['type'] ?? 'company') === 'tenant') {
            return $companyId;
        }

        return !empty($company['parent_id']) ? (int) $company['parent_id'] : null;
    }
}
