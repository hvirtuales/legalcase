<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if user is logged in
        if (!session()->get('logged_in')) {
            // Save intended URL for redirect after login
            session()->set('redirect_url', current_url());

            return redirect()->to('/login')->with('error', lang('Auth.mustBeLoggedIn'));
        }

        // Check if user is active
        $userId = session()->get('user_id');
        if ($userId) {
            $userModel = new \App\Models\UserModel();
            $user      = $userModel->getUserWithRoles((int) $userId);

            if (!$user || !$user['is_active']) {
                session()->destroy();
                return redirect()->to('/login')->with('error', lang('Auth.accountInactive'));
            }

            // Contexto de trabajo: solo Super Admin puede usar "Global". El resto
            // queda acotado a una empresa suya o al grupo (tenant) que administra;
            // si no tiene ninguno de los dos, solo puede acceder a su perfil.
            $isSuper = in_array('Super Admin', array_column($user['roles'] ?? [], 'name'), true);
            if (!$isSuper) {
                helper('url');
                $path = trim(uri_string(), '/');
                $free = $path === 'profile'
                    || strpos($path, 'profile/') === 0
                    || strpos($path, 'switch-company') === 0
                    || strpos($path, 'switch-tenant') === 0
                    // El buscador del selector devuelve JSON: si se le empuja a
                    // /profile, el diálogo recibe una página HTML y revienta al
                    // parsearla. Con las manos vacías responderá listas vacías,
                    // que es la respuesta correcta para quien no tiene empresa.
                    || strpos($path, 'switch-context') === 0;

                $guard     = new \App\Libraries\TenantGuard();
                $tenantIds = $guard->tenantIdsOf((int) $userId);

                // Si ya está trabajando dentro de un grupo suyo, se respeta: no
                // se le empuja a una empresa. (Un `active_tenant_id` que ya no
                // administre se descarta y se recalcula el contexto.)
                $activeTenant = (int) session()->get('active_tenant_id');
                if ($activeTenant && in_array($activeTenant, $tenantIds, true)) {
                    return;
                }
                session()->remove('active_tenant_id');

                $companies = (new \App\Models\CompanyModel())->getAccessibleFor((int) $userId, false);
                if (empty($companies)) {
                    session()->remove('active_company_id');

                    // Administra un grupo pero todavía no tiene empresas colgadas:
                    // su sitio es el Panel del Tenant, no el perfil.
                    if (!empty($tenantIds)) {
                        session()->set('active_tenant_id', $tenantIds[0]);

                        return;
                    }

                    if (!$free) {
                        return redirect()->to('/profile')->with('error', lang('Tenant.errNoContext'));
                    }
                } else {
                    $ids    = array_map(static fn ($c) => (int) $c['id'], $companies);
                    $active = (int) session()->get('active_company_id');
                    if (!$active || !in_array($active, $ids, true)) {
                        session()->set('active_company_id', $ids[0]); // nunca Global: auto-selecciona la primera empresa
                    }
                }
            }
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
