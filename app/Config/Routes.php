<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Theme dynamic CSS
$routes->get('/theme.css', 'ThemeController::customCss');

// Asistente de Instalación (acceso gobernado por InstallFilter)
$routes->get('install', 'InstallController::index');
$routes->match(['GET', 'POST'], 'install/requirements', 'InstallController::requirements');
$routes->match(['GET', 'POST'], 'install/database', 'InstallController::database');
$routes->match(['GET', 'POST'], 'install/migrate', 'InstallController::migrate');
$routes->match(['GET', 'POST'], 'install/admin', 'InstallController::admin');
$routes->match(['GET', 'POST'], 'install/settings', 'InstallController::settings');
$routes->match(['GET', 'POST'], 'install/finish', 'InstallController::finish');

// Gestor de componentes (panel admin, solo Super Admin)
$routes->group('components', ['filter' => 'permission:components.manage'], static function ($routes) {
    $routes->get('/', 'ComponentsController::index');
    $routes->get('ver/(:segment)', 'ComponentsController::show/$1');
    $routes->post('instalar', 'ComponentsController::install');
    $routes->post('activar', 'ComponentsController::activate');
    $routes->post('desactivar', 'ComponentsController::deactivate');
    $routes->post('actualizar', 'ComponentsController::update');
    $routes->post('desinstalar', 'ComponentsController::uninstall');
});

// Public routes
/*
 * La raíz la sirve el directorio público de LeadFlow cuando el componente está
 * activo, y el login cuando no. Es la única ruta del núcleo que un componente
 * reclama, y hace falta que esté aquí: CI4 no deja que una ruta auto-descubierta
 * pise una del app («The app routes should always be the source of truth»), así
 * que el componente no puede quedarse la raíz por su cuenta.
 *
 * La comprobación de clase no es una precaución teórica: el Autoload solo registra
 * el namespace de los componentes ACTIVOS, así que desactivar LeadFlow devuelve la
 * raíz al login sin tocar nada. El acceso sigue estando en `/login`, que es a donde
 * manda el filtro `auth`.
 */
$routes->get('/', class_exists('Components\LeadFlow\Controllers\Directory')
    ? '\Components\LeadFlow\Controllers\Directory::home'
    : 'AuthController::login');
$routes->get('/login', 'AuthController::login');
$routes->post('/login', 'AuthController::login');
$routes->get('/register', 'AuthController::register');
$routes->post('/register', 'AuthController::register');
$routes->get('/logout', 'AuthController::logout');

// Password Recovery
$routes->get('/forgot-password', 'AuthController::forgotPassword');
$routes->post('/forgot-password', 'AuthController::processForgotPassword');
$routes->get('/reset-password/(:segment)', 'AuthController::resetPassword/$1');
$routes->post('/reset-password', 'AuthController::processResetPassword');

// 2FA routes
$routes->get('/verify-2fa', 'AuthController::verify2FA');
$routes->post('/verify-2fa', 'AuthController::verify2FA');

// Language switcher
$routes->get('/language/(:segment)', 'LanguageController::switch/$1');

// Role switcher (requires authentication)
$routes->get('/switch-role/(:num)', 'RoleSwitcherController::switchRole/$1', ['filter' => 'auth']);

// Company switcher (empresa activa) y Tenant switcher (contexto de grupo).
// Los dos contextos son excluyentes: fijar uno limpia el otro.
$routes->get('/switch-company/none', 'CompanySwitcherController::none', ['filter' => 'auth']);
$routes->get('/switch-company/(:num)', 'CompanySwitcherController::switchCompany/$1', ['filter' => 'auth']);
$routes->get('/switch-tenant/(:num)', 'CompanySwitcherController::switchTenant/$1', ['filter' => 'auth']);
// Buscador del selector de contexto (JSON). La cabecera ya no pinta las 1200
// empresas: las pide aquí, filtradas y acotadas por la base.
$routes->get('/switch-context/buscar', 'CompanySwitcherController::search', ['filter' => 'auth']);

// Panel del Tenant — el administrador del grupo. Sin permisos `tenants.*`: el
// acceso lo gobierna el vínculo (TenantGuard), no un rol global.
$routes->group('tenant', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'TenantPanelController::index');

    // Empresas del grupo: alta, edición y plan. Acotadas al grupo activo, no
    // por permisos — ver TenantCompanyController.
    $routes->get('empresas', 'TenantCompanyController::index');
    $routes->get('empresas/nueva', 'TenantCompanyController::create');
    $routes->post('empresas/nueva', 'TenantCompanyController::create');
    $routes->get('empresas/(:num)/editar', 'TenantCompanyController::edit/$1');
    $routes->post('empresas/(:num)/editar', 'TenantCompanyController::edit/$1');
    $routes->post('empresas/(:num)/plan', 'TenantCompanyController::assignPlan/$1');

    // «Mi grupo»: la ficha y los canales del PROPIO tenant. Es el equivalente
    // de «Mi empresa» —misma vista, mismo trait—, y faltaba: el contexto de
    // grupo limpia `active_company_id`, así que `/mi-empresa` lo rechazaba.
    $routes->match(['GET', 'POST'], 'datos', 'TenantPanelController::data');
    $routes->match(['GET', 'POST'], 'canales', 'TenantPanelController::channels');
    $routes->post('canales/test/(:segment)', 'TenantPanelController::testChannel/$1');

    $routes->get('usuarios', 'TenantPanelController::users');
    $routes->get('usuarios/nuevo', 'TenantPanelController::newUser');
    $routes->post('usuarios/nuevo', 'TenantPanelController::newUser');
    $routes->get('usuarios/(:num)/rol', 'TenantPanelController::role/$1');
    $routes->post('usuarios/(:num)/rol', 'TenantPanelController::role/$1');
    $routes->post('usuarios/(:num)/quitar/(:num)', 'TenantPanelController::removeUser/$1/$2');
});

// Mi empresa (datos + canales) — el dueño/admin de la empresa activa
$routes->match(['GET', 'POST'], 'mi-empresa', 'MyCompanyController::datos', ['filter' => 'auth']);
$routes->match(['GET', 'POST'], 'mi-empresa/canales', 'MyCompanyController::channels', ['filter' => 'auth']);
$routes->post('mi-empresa/canales/test/(:segment)', 'MyCompanyController::testChannel/$1', ['filter' => 'auth']);

// ============================================================
// API REST  —  autenticación por JWT (Authorization: Bearer)
// ============================================================
$routes->group('api/v1', ['filter' => 'apiauth', 'namespace' => 'App\Controllers\Api'], function ($routes) {
    $routes->get('ping', 'MeController::ping');
    $routes->get('me', 'MeController::index');
    $routes->get('company', 'CompaniesController::current');

    foreach (['users' => 'UsersController', 'companies' => 'CompaniesController', 'plans' => 'PlansController'] as $seg => $ctrl) {
        $routes->get($seg, $ctrl . '::index');
        $routes->get($seg . '/(:num)', $ctrl . '::show/$1');
        $routes->post($seg, $ctrl . '::create');
        $routes->put($seg . '/(:num)', $ctrl . '::update/$1');
        $routes->patch($seg . '/(:num)', $ctrl . '::update/$1');
        $routes->delete($seg . '/(:num)', $ctrl . '::delete/$1');
    }
});

// Protected routes (require authentication)
$routes->group('', ['filter' => 'auth'], function ($routes) {
    // Dashboard
    $routes->get('/dashboard', 'DashboardController::index');

    // Users management
    $routes->group('users', ['filter' => 'permission:users.view'], function ($routes) {
        $routes->get('/', 'UserController::index');
        $routes->get('create', 'UserController::create', ['filter' => 'permission:users.create']);
        $routes->post('create', 'UserController::create', ['filter' => 'permission:users.create']);
        $routes->get('edit/(:num)', 'UserController::edit/$1', ['filter' => 'permission:users.edit']);
        $routes->post('edit/(:num)', 'UserController::edit/$1', ['filter' => 'permission:users.edit']);
        $routes->post('delete/(:num)', 'UserController::delete/$1', ['filter' => 'permission:users.delete']);
        $routes->get('assign-role/(:num)', 'UserController::assignRole/$1', ['filter' => 'permission:users.edit']);
        $routes->post('assign-role/(:num)', 'UserController::assignRole/$1', ['filter' => 'permission:users.edit']);
    });

    // Roles management
    $routes->group('roles', ['filter' => 'permission:roles.view'], function ($routes) {
        $routes->get('/', 'RoleController::index');
        $routes->get('create', 'RoleController::create', ['filter' => 'permission:roles.create']);
        $routes->post('create', 'RoleController::create', ['filter' => 'permission:roles.create']);
        $routes->get('edit/(:num)', 'RoleController::edit/$1', ['filter' => 'permission:roles.edit']);
        $routes->post('edit/(:num)', 'RoleController::edit/$1', ['filter' => 'permission:roles.edit']);
        $routes->post('delete/(:num)', 'RoleController::delete/$1', ['filter' => 'permission:roles.delete']);
        $routes->get('permissions/(:num)', 'RoleController::permissions/$1', ['filter' => 'permission:roles.edit']);
        $routes->post('permissions/(:num)', 'RoleController::permissions/$1', ['filter' => 'permission:roles.edit']);
    });

    // Permissions management
    $routes->group('permissions', ['filter' => 'permission:permissions.view'], function ($routes) {
        $routes->get('/', 'PermissionController::index');
        $routes->get('create', 'PermissionController::create', ['filter' => 'permission:permissions.create']);
        $routes->post('create', 'PermissionController::create', ['filter' => 'permission:permissions.create']);
        $routes->get('edit/(:num)', 'PermissionController::edit/$1', ['filter' => 'permission:permissions.edit']);
        $routes->post('edit/(:num)', 'PermissionController::edit/$1', ['filter' => 'permission:permissions.edit']);
        $routes->post('delete/(:num)', 'PermissionController::delete/$1', ['filter' => 'permission:permissions.delete']);
    });

    // Empresas
    $routes->group('companies', ['filter' => 'permission:companies.view'], function ($routes) {
        $routes->get('/', 'CompanyController::index');
        $routes->get('create', 'CompanyController::create', ['filter' => 'permission:companies.create']);
        $routes->post('create', 'CompanyController::create', ['filter' => 'permission:companies.create']);
        $routes->get('edit/(:num)', 'CompanyController::edit/$1', ['filter' => 'permission:companies.edit']);
        $routes->post('edit/(:num)', 'CompanyController::edit/$1', ['filter' => 'permission:companies.edit']);
        $routes->post('delete/(:num)', 'CompanyController::delete/$1', ['filter' => 'permission:companies.delete']);
        $routes->get('members/(:num)', 'CompanyController::members/$1', ['filter' => 'permission:companies.edit']);
        $routes->post('members/(:num)', 'CompanyController::members/$1', ['filter' => 'permission:companies.edit']);
        $routes->post('members/(:num)/remove/(:num)', 'CompanyController::removeMember/$1/$2', ['filter' => 'permission:companies.edit']);
    });

    // Tenants (grupos de empresas) — administración de plataforma
    $routes->group('tenants', ['filter' => 'permission:tenants.view'], function ($routes) {
        $routes->get('/', 'TenantController::index');
        $routes->get('crear', 'TenantController::create', ['filter' => 'permission:tenants.create']);
        $routes->post('crear', 'TenantController::create', ['filter' => 'permission:tenants.create']);
        $routes->get('editar/(:num)', 'TenantController::edit/$1', ['filter' => 'permission:tenants.edit']);
        $routes->post('editar/(:num)', 'TenantController::edit/$1', ['filter' => 'permission:tenants.edit']);
        $routes->post('eliminar/(:num)', 'TenantController::delete/$1', ['filter' => 'permission:tenants.delete']);
        $routes->get('empresas/(:num)', 'TenantController::companies/$1', ['filter' => 'permission:tenants.edit']);
        $routes->post('empresas/(:num)', 'TenantController::companies/$1', ['filter' => 'permission:tenants.edit']);
        $routes->post('empresas/(:num)/quitar/(:num)', 'TenantController::detach/$1/$2', ['filter' => 'permission:tenants.edit']);
    });

    // Planes
    $routes->group('plans', ['filter' => 'permission:plans.view'], function ($routes) {
        $routes->get('/', 'PlanController::index');
        $routes->get('create', 'PlanController::create', ['filter' => 'permission:plans.create']);
        $routes->post('create', 'PlanController::create', ['filter' => 'permission:plans.create']);
        $routes->get('edit/(:num)', 'PlanController::edit/$1', ['filter' => 'permission:plans.edit']);
        $routes->post('edit/(:num)', 'PlanController::edit/$1', ['filter' => 'permission:plans.edit']);
        $routes->post('delete/(:num)', 'PlanController::delete/$1', ['filter' => 'permission:plans.delete']);
    });

    // Sectores: el vocabulario con el que se clasifican las empresas. Catálogo
    // de plataforma, solo Super Admin (ver la migración de sus permisos).
    $routes->group('sectors', ['filter' => 'permission:sectors.view'], function ($routes) {
        $routes->get('/', 'SectorController::index');
        $routes->get('create', 'SectorController::create', ['filter' => 'permission:sectors.create']);
        $routes->post('create', 'SectorController::create', ['filter' => 'permission:sectors.create']);
        $routes->get('edit/(:num)', 'SectorController::edit/$1', ['filter' => 'permission:sectors.edit']);
        $routes->post('edit/(:num)', 'SectorController::edit/$1', ['filter' => 'permission:sectors.edit']);
        $routes->post('delete/(:num)', 'SectorController::delete/$1', ['filter' => 'permission:sectors.delete']);
    });

    // Gestor de API (tokens, documentación, playground) — UI web
    $routes->group('apitokens', ['filter' => 'permission:api.view'], function ($routes) {
        $routes->get('/', 'ApiTokenController::index');
        $routes->post('create', 'ApiTokenController::create', ['filter' => 'permission:api.create']);
        $routes->post('revoke/(:num)', 'ApiTokenController::revoke/$1', ['filter' => 'permission:api.delete']);
    });
    $routes->get('apidocs', 'ApiDocsController::index', ['filter' => 'permission:api.view']);

    // Profile
    $routes->get('profile', 'ProfileController::index');
    $routes->post('profile', 'ProfileController::update');

    // Settings
    $routes->group('settings', ['filter' => 'permission:settings.edit'], function ($routes) {
        $routes->get('/', 'SettingsController::index');
        $routes->post('/', 'SettingsController::index');
        $routes->post('upload-login-image', 'SettingsController::uploadLoginImage');
        $routes->post('upload-favicon', 'SettingsController::uploadFavicon');
        $routes->post('test/(:segment)', 'SettingsController::testConnection/$1');
    });

    // Audit logs
    $routes->group('audit', ['filter' => 'permission:audit.view'], function ($routes) {
        $routes->get('/', 'AuditController::index');
        $routes->get('view/(:num)', 'AuditController::view/$1');
        $routes->get('statistics', 'AuditController::statistics');
        $routes->get('export', 'AuditController::export', ['filter' => 'permission:audit.export']);
    });
});
