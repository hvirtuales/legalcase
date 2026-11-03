<!DOCTYPE html>
<html lang="<?= session()->get('language') ?? 'es' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Dashboard') ?></title>

    <!-- Favicon -->
    <?php
    helper('media');
    $settingModel = new \App\Models\AppSettingModel();
    $favicon = $settingModel->getSetting('favicon');
    if ($favicon): ?>
        <link rel="icon" href="<?= media_url($favicon) ?>" type="image/x-icon">
    <?php endif; ?>

    <!-- Inter (auto-hospedada) -->
    <link rel="stylesheet" href="<?= base_url('css/fonts.css') ?>?v=<?= filemtime(FCPATH . 'css/fonts.css') ?>">

    <!-- Iconos SVG Lucide (auto-hospedados, vía CSS mask) -->
    <link rel="stylesheet" href="<?= base_url('css/icons.css') ?>?v=<?= filemtime(FCPATH . 'css/icons.css') ?>">

    <!-- intl-tel-input (banderas + código de país en teléfonos) — auto-hospedado -->
    <link rel="stylesheet" href="<?= base_url('vendor/iti/css/intlTelInput.css') ?>">
    <style>.iti { width: 100%; } .iti__country-list { color: #1e293b; }</style>

    <!-- Tailwind (compilado) -->
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>?v=<?= filemtime(FCPATH . 'css/tailwind.css') ?>">

    <!-- Dynamic Theme Colors (sobre-escribe --color-primary*) -->
    <link rel="stylesheet" href="<?= base_url('theme.css') ?>">

    <!-- Prevent FOUC - Apply theme immediately -->
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('theme'); } catch (e) {}
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    <style>
        .sidebar-group > summary { list-style: none; }
        .sidebar-group > summary::-webkit-details-marker { display: none; }
        .sidebar-group[open] > summary .sidebar-caret { transform: rotate(180deg); }
    </style>

    <?= $this->renderSection('styles') ?>
</head>

<body class="bg-canvas text-ink">
    <!-- Sidebar -->
    <aside id="sidebar"
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col overflow-y-auto border-r border-line bg-surface p-6 transition-transform lg:translate-x-0">
        <div class="mb-8 border-b border-line pb-6 text-center">
            <?php
            $appLogo = $settingModel->getSetting('app_logo');
            if ($appLogo): ?>
                <img src="<?= base_url($appLogo) ?>" alt="App Logo" class="mx-auto mb-2 max-h-20">
                <div class="text-sm font-bold text-primary"><?= esc($settingModel->getAppName()) ?></div>
            <?php else: ?>
                <div class="text-xl font-bold text-primary">
                    <i class="icon icon-shield-check"></i>
                    <?= esc($settingModel->getAppName()) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php
        $uid = session()->get('user_id');
        $um = new \App\Models\UserModel();
        // Resalta el enlace de la vista actual (url_is soporta comodín *)
        $act = static fn (string $pattern): string => url_is($pattern) ? 'active' : '';
        // ¿Puede gestionar la empresa activa? (Super Admin, dueño o admin-miembro)
        $sbActiveCompany  = $uid ? session()->get('active_company_id') : null;
        $sbIsSuper        = $uid && in_array('Super Admin', array_column($um->getUserWithRoles((int) $uid)['roles'] ?? [], 'name'), true);
        $canManageCompany = $sbActiveCompany && ($sbIsSuper || (new \App\Models\CompanyModel())->isManager((int) $sbActiveCompany, (int) $uid));

        // Grupos (tenants) que administra. El «Panel del Tenant» se muestra por
        // el VÍNCULO, no por un permiso: los roles de la plataforma son globales
        // y uno de tenant daría poder sobre los grupos de todos.
        $sbTenantGuard  = new \App\Libraries\TenantGuard();
        $sbMyTenants    = $uid ? $sbTenantGuard->tenantsOf((int) $uid) : [];
        $sbActiveTenant = $uid ? session()->get('active_tenant_id') : null;

        /**
         * ¿Se muestra la administración de la PLATAFORMA (empresas, planes, roles,
         * permisos, auditoría, API, configuración, componentes)?
         *
         * El Super Admin que entra a una empresa concreta pasa a ver la aplicación
         * como la ve el administrador de esa empresa: lo global se oculta hasta
         * que vuelva a «Global (ninguna)» desde el selector del encabezado.
         *
         * Es **presentación, no seguridad**: sus permisos no cambian y las rutas
         * siguen respondiendo si las escribe a mano — igual que `active_role_id`,
         * que es una pista de contexto y no una frontera de autorización.
         *
         * El administrador de un grupo tampoco la ve: su rol global es «Admin»
         * (trae `companies.*`, `plans.*`…), pero su sitio son las pantallas del
         * grupo —empresas y usuarios de SUS empresas—, no el catálogo de toda la
         * plataforma. Al Super Admin no le aplica aunque administre un grupo.
         */
        $sbPlatform = !($sbIsSuper && ($sbActiveCompany || $sbActiveTenant)) && ($sbIsSuper || !$sbMyTenants);
        ?>
        <nav class="flex flex-col gap-1">
            <?php
            /*
             * Con un TENANT activo no se ofrece el panel de control: el panel
             * de ese contexto es «Panel del Tenant», que va justo debajo. El
             * de siempre hablaría del usuario —sus roles y permisos—, que no
             * es de lo que trata la pantalla en la que se está.
             *
             * Con empresa activa sí se ofrece, porque ahí el panel cambia de
             * contenido y habla de la empresa y su plan.
             *
             * Es presentación: `/dashboard` sigue respondiendo si se escribe.
             */
            ?>
            <?php if (!$sbActiveTenant): ?>
                <a href="<?= base_url('dashboard') ?>" class="sidebar-link <?= $act('dashboard') ?>">
                    <i class="icon icon-speedometer2"></i>
                    <span><?= $sbActiveCompany ? lang('App.companyPanel') : lang('App.dashboard') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbMyTenants || ($sbIsSuper && $sbActiveTenant)): ?>
                <a href="<?= base_url('tenant') ?>" class="sidebar-link <?= $act('tenant') ?>">
                    <i class="icon icon-diagram-3"></i>
                    <span><?= lang('Tenant.tenantPanel') ?></span>
                </a>
                <?php // La ficha del PROPIO tenant, como «Mi empresa» para una empresa. ?>
                <a href="<?= base_url('tenant/datos') ?>" class="sidebar-link <?= $act('tenant/datos*') ?> <?= $act('tenant/canales*') ?>">
                    <i class="icon icon-gear"></i>
                    <span><?= lang('Tenant.myTenant') ?></span>
                </a>
                <?php
                /*
                 * Las empresas y los usuarios del tenant: acotados por el
                 * vínculo, no por permisos.
                 *
                 * Aquí se llaman «Empresas» y «Usuarios» a secas, no «… del
                 * grupo»: ya se está DENTRO del grupo, así que el apellido no
                 * distingue nada. Los rótulos largos siguen en las pantallas
                 * de plataforma (`/tenants`), donde el Super Admin sí mira un
                 * grupo concreto desde fuera y el apellido hace falta.
                 */
                ?>
                <a href="<?= base_url('tenant/empresas') ?>" class="sidebar-link <?= $act('tenant/empresas*') ?>">
                    <i class="icon icon-buildings"></i>
                    <span><?= lang('Tenant.navCompanies') ?></span>
                </a>
                <a href="<?= base_url('tenant/usuarios') ?>" class="sidebar-link <?= $act('tenant/usuarios*') ?>">
                    <i class="icon icon-people"></i>
                    <span><?= lang('Tenant.navUsers') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($canManageCompany): ?>
                <a href="<?= base_url('mi-empresa') ?>" class="sidebar-link <?= $act('mi-empresa*') ?>">
                    <i class="icon icon-buildings"></i>
                    <span><?= lang('App.myCompany') ?></span>
                </a>
            <?php endif; ?>

            <?php // En contexto de grupo los usuarios se gestionan por empresa desde /tenant/usuarios. ?>
            <?php if (!$sbActiveTenant && $uid && $um->hasPermission($uid, 'users.view')): ?>
                <a href="<?= base_url('users') ?>" class="sidebar-link <?= $act('users*') ?>">
                    <i class="icon icon-people"></i>
                    <span><?= lang('App.users') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'companies.view')): ?>
                <a href="<?= base_url('companies') ?>" class="sidebar-link <?= $act('companies*') ?>">
                    <i class="icon icon-buildings"></i>
                    <span><?= lang('App.companies') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'tenants.view')): ?>
                <a href="<?= base_url('tenants') ?>" class="sidebar-link <?= $act('tenants*') ?>">
                    <i class="icon icon-diagram-3"></i>
                    <span><?= lang('Tenant.tenants') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'plans.view')): ?>
                <a href="<?= base_url('plans') ?>" class="sidebar-link <?= $act('plans*') ?>">
                    <i class="icon icon-box-seam"></i>
                    <span><?= lang('App.plans') ?></span>
                </a>
            <?php endif; ?>

            <?php // Sectores: vocabulario de plataforma con el que se clasifican las empresas. ?>
            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'sectors.view')): ?>
                <a href="<?= base_url('sectors') ?>" class="sidebar-link <?= $act('sectors*') ?>">
                    <i class="icon icon-diagram-3"></i>
                    <span><?= lang('Sector.sectors') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'roles.view')): ?>
                <a href="<?= base_url('roles') ?>" class="sidebar-link <?= $act('roles*') ?>">
                    <i class="icon icon-person-badge"></i>
                    <span><?= lang('App.roles') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'permissions.view')): ?>
                <a href="<?= base_url('permissions') ?>" class="sidebar-link <?= $act('permissions*') ?>">
                    <i class="icon icon-key"></i>
                    <span><?= lang('App.permissions') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'audit.view')): ?>
                <a href="<?= base_url('audit') ?>" class="sidebar-link <?= $act('audit*') ?>">
                    <i class="icon icon-journal-text"></i>
                    <span><?= lang('App.audit') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'api.view')): ?>
                <a href="<?= base_url('apitokens') ?>" class="sidebar-link <?= $act('apitokens*') ?>">
                    <i class="icon icon-key"></i>
                    <span><?= lang('App.apiTokens') ?></span>
                </a>
                <a href="<?= base_url('apidocs') ?>" class="sidebar-link <?= $act('apidocs*') ?>">
                    <i class="icon icon-book"></i>
                    <span><?= lang('App.apiDocs') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'settings.edit')): ?>
                <a href="<?= base_url('settings') ?>" class="sidebar-link <?= $act('settings*') ?>">
                    <i class="icon icon-gear"></i>
                    <span><?= lang('App.settings') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($sbPlatform && $uid && $um->hasPermission($uid, 'components.manage')): ?>
                <a href="<?= base_url('components') ?>" class="sidebar-link <?= $act('components*') ?>">
                    <i class="icon icon-puzzle"></i>
                    <span><?= lang('App.components') ?></span>
                </a>
            <?php endif; ?>

            <?php // Menús de componentes: grupos DESPLEGABLES (collapsible)
            foreach ((new \App\Libraries\ComponentManager())->menuGroups() as $group):
                $visible = array_filter($group['items'], static fn ($mi) => empty($mi['permission']) || ($uid && $um->hasPermission($uid, $mi['permission'])));
                if (!$visible) {
                    continue;
                }
                /*
                 * El grupo se abre si coincide CUALQUIERA de sus rutas, pero solo se
                 * marca como activo el ítem MÁS ESPECÍFICO: la ruta de «Inicio» de un
                 * componente es el prefijo de todas las suyas, así que con una simple
                 * coincidencia de prefijo se encendían dos a la vez —tres cuando una
                 * pantalla cuelga de otra, como Ayuda dentro de Configuración—.
                 */
                $open    = false;
                $bestKey = null;
                $bestLen = -1;
                foreach ($visible as $k => $mi) {
                    $r = rtrim((string) $mi['route'], '/');
                    if (url_is($r . '*')) {
                        $open = true;
                        if (strlen($r) > $bestLen) {
                            $bestLen = strlen($r);
                            $bestKey = $k;
                        }
                    }
                } ?>
                <details class="sidebar-group mt-2"<?= $open ? ' open' : '' ?>>
                    <summary class="sidebar-link cursor-pointer select-none">
                        <i class="icon <?= esc($group['icon']) ?>"></i>
                        <span><?= esc($group['label']) ?></span>
                        <i class="icon icon-chevron-down sidebar-caret ml-auto text-xs transition-transform"></i>
                    </summary>
                    <div class="mt-1 flex flex-col gap-1 pl-3">
                        <?php foreach ($visible as $k => $mi): ?>
                            <a href="<?= base_url($mi['route']) ?>" class="sidebar-link <?= $k === $bestKey ? 'active' : '' ?>">
                                <i class="icon <?= esc($mi['icon'] ?? 'icon-puzzle') ?>"></i>
                                <span><?= esc($mi['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </nav>

        <div class="mt-auto pt-6">
            <a href="<?= base_url('logout') ?>" class="sidebar-link">
                <i class="icon icon-box-arrow-right"></i>
                <span><?= lang('App.logout') ?></span>
            </a>
        </div>
    </aside>

    <!-- Overlay (móvil) -->
    <div id="sidebarOverlay" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>

    <!-- Main Content -->
    <main class="min-h-screen lg:ml-64">
        <!-- Top Bar (ancho completo, sticky al hacer scroll) -->
        <div class="sticky top-0 z-20 flex w-full items-center justify-between border-b border-line bg-surface px-6 py-4 shadow-sm">
            <div class="flex items-center gap-3">
                <!-- Hamburguesa a la izquierda (solo móvil/tablet) -->
                <button id="sidebarToggle" class="text-2xl text-ink lg:hidden" aria-label="Toggle menu">
                    <i class="icon icon-list"></i>
                </button>
                <!-- Branding móvil: isotipo seguido del nombre; el nombre se oculta en <768px -->
                <a href="<?= base_url('dashboard') ?>" class="flex items-center gap-2 lg:hidden">
                    <?php $topLogo = $settingModel->getSetting('app_logo'); ?>
                    <?php if ($topLogo): ?>
                        <img src="<?= base_url($topLogo) ?>" alt="Logo" class="h-8 w-auto">
                    <?php else: ?>
                        <i class="icon icon-shield-check text-2xl text-primary"></i>
                    <?php endif; ?>
                    <span class="hidden text-lg font-bold text-primary md:inline">
                        <?= esc($settingModel->getAppName()) ?>
                    </span>
                </a>
                <h1 class="text-xl font-semibold sm:text-2xl"><?= esc($title ?? 'Dashboard') ?></h1>
            </div>
            <div class="flex items-center gap-3">
                <?php
                $userModel = new \App\Models\UserModel();
                $currentUser = $userModel->getUserWithRoles(session()->get('user_id'));
                $userRoles = $currentUser['roles'] ?? [];
                $activeRoleId = session()->get('active_role_id');

                if (!$activeRoleId && !empty($userRoles)) {
                    $activeRoleId = $userRoles[0]['id'];
                    session()->set('active_role_id', $activeRoleId);
                }

                $activeRoleName = 'N/A';
                foreach ($userRoles as $role) {
                    if ($role['id'] == $activeRoleId) {
                        $activeRoleName = $role['name'];
                        break;
                    }
                }
                ?>

                <!-- Selector de contexto: Global · grupo · empresa -->
                <?php
                $isSuperAdmin = in_array('Super Admin', array_column($userRoles, 'name'), true);
                $companyModel = new \App\Models\CompanyModel();

                // Aquí solo se resuelve el contexto ACTIVO. La lista la pide el
                // diálogo al servidor (`switch-context/buscar`), filtrada y
                // acotada: antes se pintaban en la cabecera de CADA página todas
                // las empresas accesibles, y con 30 grupos y 1200 empresas eso
                // eran 1200 enlaces que casi nadie iba a usar.
                $activeCompanyId = (int) (session()->get('active_company_id') ?? 0);
                $activeCompany   = $activeCompanyId ? $companyModel->find($activeCompanyId) : null;
                $activeTenantRow = $sbActiveTenant ? $companyModel->find((int) $sbActiveTenant) : null;

                $activePlanName = null;
                $activeValid    = false;
                if ($activeCompany) {
                    $subModel    = new \App\Models\SubscriptionModel();
                    $activeSub   = $subModel->getActiveForCompany((int) $activeCompany['id']);
                    $activeValid = $subModel->isValid($activeSub);
                    if (!empty($activeSub['plan_id'])) {
                        $pl             = (new \App\Models\PlanModel())->find($activeSub['plan_id']);
                        $activePlanName = $pl['name'] ?? null;
                    }
                }
                ?>
                <button type="button" class="btn btn-secondary btn-sm" data-ctx-open
                        title="<?= esc(lang('App.ctxTitle'), 'attr') ?>">
                    <?php if ($activeTenantRow): ?>
                        <i class="icon icon-diagram-3"></i>
                        <span class="hidden sm:inline"><?= esc($activeTenantRow['name']) ?></span>
                        <span class="badge badge-primary"><?= lang('Tenant.tenant') ?></span>
                    <?php elseif ($activeCompany): ?>
                        <i class="icon icon-buildings"></i>
                        <span class="hidden sm:inline"><?= esc($activeCompany['name']) ?></span>
                        <?php if ($activePlanName): ?>
                            <span class="badge <?= $activeValid ? 'badge-primary' : 'badge-danger' ?>">
                                <?= esc($activePlanName) ?><?= $activeValid ? '' : ' · ' . lang('Tenant.expired') ?>
                            </span>
                        <?php elseif (!$activeValid): ?>
                            <span class="badge badge-danger"><?= lang('Tenant.expired') ?></span>
                        <?php endif; ?>
                    <?php elseif ($isSuperAdmin): ?>
                        <i class="icon icon-globe"></i>
                        <span class="hidden sm:inline"><?= lang('App.ctxGlobal') ?></span>
                    <?php else: ?>
                        <i class="icon icon-buildings"></i>
                        <span class="hidden sm:inline"><?= lang('App.ctxNone') ?></span>
                    <?php endif; ?>
                    <i class="icon icon-chevron-down text-faint"></i>
                </button>


                <!-- Role Switcher (solo si tiene múltiples roles) -->
                <?php if (count($userRoles) > 1): ?>
                    <details class="dd">
                        <summary class="btn btn-primary">
                            <i class="icon icon-person-badge-fill"></i>
                            <span><?= esc($activeRoleName) ?></span>
                        </summary>
                        <div class="dd-menu">
                            <p class="px-3 py-2 text-xs font-medium text-faint"><?= lang('App.switchRole') ?></p>
                            <?php foreach ($userRoles as $role): ?>
                                <a class="dd-item <?= $role['id'] == $activeRoleId ? 'active' : '' ?>"
                                    href="<?= base_url('switch-role/' . $role['id']) ?>">
                                    <?php if ($role['id'] == $activeRoleId): ?>
                                        <i class="icon icon-check-circle-fill text-primary"></i>
                                    <?php else: ?>
                                        <i class="icon icon-circle text-faint"></i>
                                    <?php endif; ?>
                                    <span><?= esc($role['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <!-- Theme Toggle (claro/oscuro) -->
                <button type="button" data-theme-toggle class="btn btn-secondary btn-sm"
                    title="<?= lang('App.toggleTheme') ?>" aria-label="<?= lang('App.toggleTheme') ?>">
                    <i class="icon icon-moon-stars" data-theme-icon></i>
                </button>

                <!-- Language Switcher -->
                <details class="dd">
                    <summary class="btn btn-secondary btn-sm">
                        <i class="icon icon-globe"></i>
                        <?= session()->get('language') === 'en' ? 'EN' : 'ES' ?>
                    </summary>
                    <div class="dd-menu">
                        <a class="dd-item" href="<?= base_url('language/es') ?>">Español</a>
                        <a class="dd-item" href="<?= base_url('language/en') ?>">English</a>
                    </div>
                </details>

                <!-- User Menu -->
                <details class="dd">
                    <summary class="btn btn-secondary">
                        <i class="icon icon-person-circle"></i>
                        <?= esc(session()->get('username')) ?>
                    </summary>
                    <div class="dd-menu">
                        <a class="dd-item" href="<?= base_url('profile') ?>">
                            <i class="icon icon-person"></i><?= lang('App.myProfile') ?>
                        </a>
                        <hr class="my-1 border-line">
                        <a class="dd-item text-danger hover:bg-danger-soft hover:text-danger" href="<?= base_url('logout') ?>">
                            <i class="icon icon-box-arrow-right"></i><?= lang('App.logout') ?>
                        </a>
                    </div>
                </details>
            </div>
        </div>

        <!-- Cuerpo de la página (con espaciado) -->
        <div class="p-6">
            <!-- Flash Messages -->
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success">
                    <i class="icon icon-check-circle"></i>
                    <span><?= session()->getFlashdata('success') ?></span>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-error">
                    <i class="icon icon-exclamation-circle"></i>
                    <span><?= session()->getFlashdata('error') ?></span>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <?= $this->renderSection('content') ?>
        </div>
    </main>

    <!-- Mobile Menu Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            const overlay = document.getElementById('sidebarOverlay');

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            }
            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }

            if (toggle) toggle.addEventListener('click', openSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);

            // Cerrar al navegar (solo móvil)
            if (window.innerWidth < 1024) {
                sidebar.querySelectorAll('.sidebar-link').forEach(link => {
                    link.addEventListener('click', closeSidebar);
                });
            }
        });

        // Cerrar dropdowns <details> al hacer clic fuera
        document.addEventListener('click', function (e) {
            document.querySelectorAll('details.dd[open]').forEach(d => {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
    </script>

    <!-- Theme Manager - Auto Dark Mode -->
    <script src="<?= base_url('js/theme-manager.js') ?>?v=<?= filemtime(FCPATH . 'js/theme-manager.js') ?>"></script>

    <!-- Teléfonos con banderas + miniatura de imágenes -->
    <script>window.ITI_UTILS = '<?= base_url('vendor/iti/js/utils.js') ?>';</script>
    <script src="<?= base_url('vendor/iti/js/intlTelInput.min.js') ?>"></script>
    <script src="<?= base_url('js/phone-input.js') ?>?v=<?= filemtime(FCPATH . 'js/phone-input.js') ?>"></script>
    <script src="<?= base_url('js/image-preview.js') ?>?v=<?= filemtime(FCPATH . 'js/image-preview.js') ?>"></script>

    <!-- Support Widget -->
    <?= view('partials/support_widget') ?>

    <?= $this->renderSection('scripts') ?>
    <!--
      El diálogo vive al FINAL del body, no dentro de la cabecera: esa es
      `sticky z-20`, y un `z-index` propio crea contexto de apilado, así que
      el `z-50` del diálogo solo valía DENTRO de la cabecera — la barra
      lateral (`fixed z-40`) se le pintaba encima en pantallas anchas.
    -->
    <!-- Diálogo del selector. Se pinta vacío: el contenido llega del
         buscador, así la cabecera pesa lo mismo con 3 empresas que
         con 1200. -->
    <div id="ctxModal" class="fixed inset-0 z-50 hidden items-start justify-center bg-black/50 p-4 pt-16"
         role="dialog" aria-modal="true" aria-labelledby="ctxTitle">
        <div class="w-full max-w-lg overflow-hidden rounded-lg bg-surface shadow-lg">
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 id="ctxTitle" class="text-sm font-semibold"><?= lang('App.ctxTitle') ?></h2>
                <button type="button" class="btn btn-ghost btn-sm" data-ctx-close
                        aria-label="<?= esc(lang('App.close'), 'attr') ?>">
                    <i class="icon icon-x-lg"></i>
                </button>
            </div>
            <form class="flex gap-2 border-b border-line px-4 py-3" data-ctx-form>
                <input type="search" id="ctxQ" class="form-input flex-1"
                       placeholder="<?= esc(lang('App.ctxSearchPlaceholder'), 'attr') ?>"
                       autocomplete="off">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="icon icon-search"></i>
                    <span class="hidden sm:inline"><?= lang('App.search') ?></span>
                </button>
            </form>
            <div id="ctxList" class="max-h-96 overflow-y-auto p-2"></div>
        </div>
    </div>

    <script>
    (function () {
        var btn = document.querySelector('[data-ctx-open]');
        var box = document.getElementById('ctxModal');
        if (!btn || !box) { return; }

        var list  = document.getElementById('ctxList');
        var input = document.getElementById('ctxQ');
        var base  = <?= json_encode(base_url('/'), JSON_UNESCAPED_SLASHES) ?>;
        var t     = {
            global:   <?= json_encode(lang('App.ctxGlobal')) ?>,
            globalNo: <?= json_encode(lang('App.ctxGlobalNone')) ?>,
            groups:   <?= json_encode(lang('App.ctxGroups')) ?>,
            companies:<?= json_encode(lang('App.ctxCompanies')) ?>,
            empty:    <?= json_encode(lang('App.ctxEmpty')) ?>,
            more:     <?= json_encode(lang('App.ctxMore')) ?>,
            expired:  <?= json_encode(lang('Tenant.expired')) ?>,
            loading:  <?= json_encode(lang('App.loading')) ?>
        };
        var ultima = 0;   // descarta respuestas que lleguen tarde

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }

        function fila(href, icono, nombre, ciudad, extra, activo) {
            return '<a class="dd-item' + (activo ? ' active' : '') + '" href="' + esc(href) + '">'
                + '<i class="icon ' + icono + '"></i>'
                + '<span class="truncate">' + esc(nombre)
                + (ciudad ? ' <span class="text-xs text-faint">· ' + esc(ciudad) + '</span>' : '')
                + '</span>' + (extra || '') + '</a>';
        }

        function rotulo(texto) {
            return '<p class="px-3 pb-1 pt-2 text-xs font-medium text-faint">' + esc(texto) + '</p>';
        }

        function pinta(d) {
            var h = '';

            if (d.global) {
                var enGlobal = !d.active.company && !d.active.tenant;
                h += fila(base + 'switch-company/none',
                    enGlobal ? 'icon-check-circle-fill text-primary' : 'icon-globe text-faint',
                    t.globalNo, '', '', enGlobal);
            }

            if (d.tenants.length) {
                h += rotulo(t.groups);
                d.tenants.forEach(function (g) {
                    h += fila(base + 'switch-tenant/' + g.id,
                        g.active ? 'icon-check-circle-fill text-primary' : 'icon-diagram-3 text-faint',
                        g.name, g.city, '', g.active);
                });
                if (d.more.tenants) {
                    h += '<p class="px-3 py-1 text-xs text-muted">' + esc(t.more.replace('{0}', d.more.tenants)) + '</p>';
                }
            }

            if (d.companies.length) {
                h += rotulo(t.companies);
                d.companies.forEach(function (c) {
                    if (!c.valid) {
                        // Vencida: se muestra, pero no se puede entrar
                        // (el servidor la rechaza igual).
                        h += '<span class="dd-item cursor-not-allowed opacity-60" title="' + esc(t.expired) + '">'
                            + '<i class="icon icon-lock text-faint"></i>'
                            + '<span class="truncate">' + esc(c.name) + '</span>'
                            + '<span class="badge badge-danger ml-auto">' + esc(t.expired) + '</span></span>';

                        return;
                    }
                    h += fila(base + 'switch-company/' + c.id,
                        c.active ? 'icon-check-circle-fill text-primary' : 'icon-circle text-faint',
                        c.name, c.city, '', c.active);
                });
                if (d.more.companies) {
                    h += '<p class="px-3 py-1 text-xs text-muted">' + esc(t.more.replace('{0}', d.more.companies)) + '</p>';
                }
            }

            if (!d.tenants.length && !d.companies.length) {
                h += '<p class="px-3 py-4 text-center text-sm text-muted">' + esc(t.empty) + '</p>';
            }

            list.innerHTML = h;
        }

        function busca() {
            var mio = ++ultima;
            list.innerHTML = '<p class="px-3 py-4 text-center text-sm text-muted">' + esc(t.loading) + '</p>';
            fetch(base + 'switch-context/buscar?q=' + encodeURIComponent(input.value), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (mio !== ultima) { return; }   // llegó una respuesta vieja
                    if (d && d.ok) { pinta(d); }
                })
                .catch(function () {
                    if (mio !== ultima) { return; }
                    list.innerHTML = '<p class="px-3 py-4 text-center text-sm text-danger">'
                        + esc(t.empty) + '</p>';
                });
        }

        function abre() {
            box.classList.remove('hidden');
            box.classList.add('flex');
            input.value = '';
            busca();
            input.focus();
        }

        function cierra() {
            box.classList.add('hidden');
            box.classList.remove('flex');
        }

        btn.addEventListener('click', abre);
        box.addEventListener('click', function (e) {
            // Clic fuera de la tarjeta o en la X.
            if (e.target === box || e.target.closest('[data-ctx-close]')) { cierra(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !box.classList.contains('hidden')) { cierra(); }
        });
        box.querySelector('[data-ctx-form]').addEventListener('submit', function (e) {
            e.preventDefault();
            busca();
        });
        // Además del botón, se busca solo al dejar de teclear.
        var reloj = null;
        input.addEventListener('input', function () {
            clearTimeout(reloj);
            reloj = setTimeout(busca, 300);
        });
    })();
    </script>

</body>

</html>
