<?php

namespace App\Controllers;

use App\Libraries\TenantGuard;
use App\Models\CompanyModel;
use App\Models\UserModel;

/**
 * Contexto de trabajo. Hay tres y son **excluyentes**:
 *
 *   - **Global** (`active_company_id` y `active_tenant_id` vacíos) — solo Super Admin.
 *   - **Empresa** (`active_company_id`) — lo de siempre: los componentes de
 *     ámbito empresa aíslan sus datos por ahí.
 *   - **Grupo/tenant** (`active_tenant_id`) — el Panel del Tenant y los
 *     componentes de ámbito tenant.
 *
 * Fijar uno limpia el otro: si no, `ComponentManager::currentScope()` no sabría
 * qué menús mostrar y se verían los dos juegos a la vez.
 */
class CompanySwitcherController extends BaseController
{
    /** Cambia a contexto global (ni empresa ni grupo seleccionados). */
    public function none()
    {
        $userId = session()->get('user_id');
        if (!$userId) {
            return redirect()->to('/login');
        }
        // Solo Super Admin puede usar el contexto global
        if (!$this->isSuper((int) $userId)) {
            return redirect()->back()->with('error', lang('Tenant.errNoGlobalContext'));
        }
        session()->remove('active_company_id');
        session()->remove('active_tenant_id');

        return redirect()->back()->with('success', lang('Tenant.okGlobalContext'));
    }

    /**
     * Fija la empresa activa. El plan de contexto se deriva de ella. Super Admin
     * puede entrar a cualquiera; el resto solo a las que administra (dueño,
     * miembro, o empresa de un tenant suyo).
     */
    public function switchCompany($companyId)
    {
        $userId = session()->get('user_id');
        if (!$userId) {
            return redirect()->to('/login');
        }

        $companyModel = new CompanyModel();
        $company      = $companyModel->find($companyId);
        if (!$company) {
            return redirect()->back()->with('error', lang('Tenant.errCompanyNotFound'));
        }

        // A un grupo no se entra como si fuera una empresa: tiene su propio panel.
        if ($companyModel->isTenant($company)) {
            return $this->switchTenant($companyId);
        }

        $isSuper = $this->isSuper((int) $userId);
        if (!$isSuper && !$companyModel->userCanAccess((int) $company['id'], (int) $userId)) {
            return redirect()->back()->with('error', lang('Tenant.errNoCompanyAccess'));
        }

        // Vigencia: la del grupo manda sobre la propia (se le factura al grupo).
        if (!empty($company['parent_id']) && !(new TenantGuard())->tenantSubscriptionValid((int) $company['parent_id'])) {
            $tenant = $companyModel->find((int) $company['parent_id']);

            return redirect()->back()->with('error', lang('Tenant.errTenantExpired', [$tenant['name'] ?? '']));
        }

        $subModel = new \App\Models\SubscriptionModel();
        $sub      = $subModel->markExpiredIfNeeded($subModel->getActiveForCompany((int) $company['id']));
        if (!$subModel->isValid($sub)) {
            return redirect()->back()->with('error', lang('Tenant.errCompanyExpired', [$company['name']]));
        }

        session()->set('active_company_id', (int) $company['id']);
        session()->remove('active_tenant_id');

        return redirect()->back()->with('success', lang('Tenant.okActiveCompany', [$company['name']]));
    }

    /**
     * Entra al contexto del **grupo**: el Panel del Tenant y los componentes de
     * ámbito tenant. Solo para quien lo administra (o el Super Admin).
     */
    public function switchTenant($tenantId)
    {
        $userId = session()->get('user_id');
        if (!$userId) {
            return redirect()->to('/login');
        }

        $companyModel = new CompanyModel();
        $tenant       = $companyModel->find($tenantId);
        if (!$tenant || !$companyModel->isTenant($tenant)) {
            return redirect()->back()->with('error', lang('Tenant.errTenantNotFound'));
        }

        $guard = new TenantGuard();
        if (!$this->isSuper((int) $userId) && !in_array((int) $tenant['id'], $guard->tenantIdsOf((int) $userId), true)) {
            return redirect()->back()->with('error', lang('Tenant.errNoTenantAccess'));
        }

        if (!$guard->tenantSubscriptionValid((int) $tenant['id'])) {
            return redirect()->back()->with('error', lang('Tenant.errTenantOwnExpired', [$tenant['name']]));
        }

        session()->set('active_tenant_id', (int) $tenant['id']);
        session()->remove('active_company_id');

        return redirect()->to('/tenant')->with('success', lang('Tenant.okActiveTenant', [$tenant['name']]));
    }

    private function isSuper(int $userId): bool
    {
        $user = (new UserModel())->getUserWithRoles($userId);

        return in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);
    }

    /**
     * Buscador del selector de contexto (JSON).
     *
     * El selector pintaba **todas** las empresas accesibles en la cabecera de
     * cada página. Con 30 grupos y 1200 empresas eso son 1200 enlaces y una
     * consulta pesada en cada carga, aunque nadie vaya a cambiar de contexto.
     * Aquí la base filtra y devuelve un puñado; la cabecera deja de crecer con
     * el catálogo.
     *
     * El alcance es **el mismo de siempre** (`accessibleIdsFor`): el buscador no
     * puede enseñar nada que el selector no enseñara. Y `switchCompany` sigue
     * validando el acceso por su cuenta — esto es una lista, no un permiso.
     */
    public function search()
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        if (!$userId) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false]);
        }

        $isSuper = $this->isSuper($userId);
        $q       = trim((string) $this->request->getGet('q'));
        $model   = new CompanyModel();
        $guard   = new \App\Libraries\TenantGuard();

        // Grupos: el Super Admin los ve todos; el resto, los que administra.
        $tenantIds = $isSuper ? null : $guard->tenantIdsOf($userId);
        $tenants   = $model->searchTenants($tenantIds, $q, self::LIMIT);

        $companies = $model->searchAccessibleFor($userId, $isSuper, $q, self::LIMIT);

        // La vigencia decide si una empresa se puede elegir; se resuelve en una
        // sola consulta para las que se devuelven, no una por fila.
        $subModel = new \App\Models\SubscriptionModel();
        $subMap   = $subModel->getMapForCompanies(
            array_map(static fn ($c) => (int) $c['id'], $companies['rows'])
        );

        $activeCompany = (int) (session()->get('active_company_id') ?? 0);
        $activeTenant  = (int) (session()->get('active_tenant_id') ?? 0);

        return $this->response->setJSON([
            'ok'      => true,
            'global'  => $isSuper,          // solo el Super Admin puede usar Global
            'active'  => ['company' => $activeCompany, 'tenant' => $activeTenant],
            'tenants' => array_map(static fn ($t) => [
                'id'     => (int) $t['id'],
                'name'   => (string) $t['name'],
                'city'   => (string) ($t['city'] ?? ''),
                'active' => (int) $t['id'] === $activeTenant,
            ], $tenants['rows']),
            'companies' => array_map(static function ($c) use ($subModel, $subMap, $activeCompany) {
                $sub = $subMap[(int) $c['id']] ?? null;

                return [
                    'id'     => (int) $c['id'],
                    'name'   => (string) $c['name'],
                    'city'   => (string) ($c['city'] ?? ''),
                    'valid'  => $subModel->isValid($sub),
                    'active' => (int) $c['id'] === $activeCompany,
                ];
            }, $companies['rows']),
            // Cuántas quedan fuera del recorte: sin esto, buscar «a» devolvería
            // veinte resultados y parecería que no hay más.
            'more' => [
                'tenants'   => max(0, $tenants['total'] - count($tenants['rows'])),
                'companies' => max(0, $companies['total'] - count($companies['rows'])),
            ],
        ]);
    }

    /** Cuántos resultados devuelve cada bloque del buscador. */
    private const LIMIT = 20;
}
