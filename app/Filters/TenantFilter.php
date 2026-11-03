<?php

namespace App\Filters;

use App\Libraries\TenantGuard;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * Filtro **`tenant`**: exige estar trabajando dentro de un grupo y que sea uno
 * que el usuario administra de verdad.
 *
 * Es la pieza que necesitan las rutas de los **componentes de ámbito tenant**
 * (`scope => 'tenant'` en su Manifest). Sin él, un componente de grupo tendría
 * que resolver por su cuenta de dónde sale el `tenant_id` y comprobar el
 * vínculo, y bastaría con que uno lo olvidara para filtrar datos entre grupos.
 *
 * Va **después** de `auth` y **antes** de `permission:` en la lista de filtros
 * de la ruta:
 *
 *     $routes->group('directorio', [
 *         'namespace' => 'Components\Directorio\Controllers',
 *         'filter'    => ['auth', 'tenant'],
 *     ], static function ($routes) {
 *         $routes->get('/', 'Entries::index', ['filter' => 'permission:dir.view']);
 *     });
 *
 * Deja el id del grupo en `session('active_tenant_id')`, que es de donde lo
 * tiene que leer el componente — **nunca del POST**.
 */
class TenantFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        if (!$userId) {
            return redirect()->to('/login')->with('error', lang('Auth.mustBeLoggedIn'));
        }

        $tenantId = (int) (session('active_tenant_id') ?? 0);
        if (!$tenantId) {
            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNeedTenantContext'));
        }

        $guard = new TenantGuard();

        // El Super Admin puede inspeccionar cualquier grupo; el resto, solo los
        // suyos. Que el id esté en sesión no basta: se comprueba el vínculo.
        $user    = (new \App\Models\UserModel())->getUserWithRoles($userId);
        $isSuper = in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);

        if (!$isSuper && !in_array($tenantId, $guard->tenantIdsOf($userId), true)) {
            session()->remove('active_tenant_id');

            return redirect()->to('/dashboard')->with('error', lang('Tenant.errNoTenantAccess'));
        }

        // Vigencia del grupo: vencido, sus pantallas no se abren.
        if (!$guard->tenantSubscriptionValid($tenantId)) {
            $tenant = (new \App\Models\CompanyModel())->find($tenantId);

            return redirect()->to('/tenant')->with('error', lang('Tenant.errTenantOwnExpired', [$tenant['name'] ?? '']));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nada
    }
}
