<?php

namespace App\Commands;

use App\Libraries\TenantGuard;
use App\Models\CompanyModel;
use App\Models\TenantPlanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Autotest TEMPORAL del nivel tenant. Comprueba lo que de verdad duele si se
 * rompe: el alcance, la jerarquía, el cupo y la vigencia heredada.
 *
 * Los datos se insertan por Query Builder, no por los modelos: `Auditable` usa
 * `getUserAgent()`, que no existe en CLI (gotcha documentado). Todo se crea con
 * un prefijo propio y se borra al final, pase o falle.
 *
 *     php spark tenant:autotest
 */
class TenantAutotest extends BaseCommand
{
    protected $group       = 'Tenant';
    protected $name        = 'tenant:autotest';
    protected $description = 'Autotest temporal del nivel tenant (alcance, jerarquía, cupo, vigencia).';

    private const TAG = '__ttest__';

    private int $passed = 0;
    private int $failed = 0;
    private array $ids  = ['companies' => [], 'plans' => [], 'users' => [], 'roles' => []];

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        try {
            // Antes de sembrar, barrer lo que haya quedado de una corrida
            // anterior. El `finally` de abajo limpia si la prueba falla, pero no
            // si el proceso **muere** —una interrupción, el servidor cerrado a
            // mitad—, y entonces el `__ttest__admin` de aquella vez hace que
            // TODAS las corridas siguientes revienten con «Duplicate entry».
            // Una prueba que se envenena sola deja de servir para nada.
            $this->purge($db);

            $this->seed($db);
            $this->checks($db);
        } catch (\Throwable $e) {
            CLI::error('Excepción: ' . $e->getMessage());
            CLI::write($e->getFile() . ':' . $e->getLine());
            $this->failed++;
        } finally {
            $this->cleanup($db);
        }

        CLI::newLine();
        CLI::write('Pasaron: ' . $this->passed . ' · Fallaron: ' . $this->failed, $this->failed ? 'red' : 'green');

        return $this->failed ? 1 : 0;
    }

    // ------------------------------------------------------------------ datos

    private function seed($db): void
    {
        $now = date('Y-m-d H:i:s');

        // Planes: uno de tenant (cupo 2 empresas) y dos de empresa.
        $this->ids['plans']['tenant'] = $this->insert($db, 'plans', [
            'name' => self::TAG . 'PlanGrupo', 'scope' => 'tenant', 'max_companies' => 2,
            'service_days' => 30, 'price_amount' => 0, 'price_currency' => 'USD', 'is_active' => 1, 'created_at' => $now,
        ]);
        $this->ids['plans']['co1'] = $this->insert($db, 'plans', [
            'name' => self::TAG . 'PlanEmpresaA', 'scope' => 'company', 'max_companies' => 0,
            'service_days' => 30, 'price_amount' => 0, 'price_currency' => 'USD', 'is_active' => 1, 'created_at' => $now,
        ]);
        $this->ids['plans']['co2'] = $this->insert($db, 'plans', [
            'name' => self::TAG . 'PlanEmpresaB', 'scope' => 'company', 'max_companies' => 0,
            'service_days' => 30, 'price_amount' => 0, 'price_currency' => 'USD', 'is_active' => 1, 'created_at' => $now,
        ]);

        // El administrador del grupo.
        $this->ids['users']['admin'] = $this->insert($db, 'users', [
            'username' => self::TAG . 'admin', 'email' => self::TAG . 'admin@test.local',
            'password' => password_hash('x', PASSWORD_DEFAULT), 'first_name' => 'Test', 'last_name' => 'Tenant',
            'is_active' => 1, 'auth_method' => 'password', 'preferred_language' => 'es', 'created_at' => $now,
        ]);

        // Un tenant, dos empresas suyas, una suelta y un segundo tenant ajeno.
        $this->ids['companies']['tenant'] = $this->insert($db, 'companies', [
            'name' => self::TAG . 'Centro Comercial', 'slug' => self::TAG . 'cc', 'type' => 'tenant',
            'plan_id' => $this->ids['plans']['tenant'], 'owner_id' => $this->ids['users']['admin'],
            'is_active' => 1, 'created_at' => $now,
        ]);
        $this->ids['companies']['otherTenant'] = $this->insert($db, 'companies', [
            'name' => self::TAG . 'Camara Ajena', 'slug' => self::TAG . 'ca', 'type' => 'tenant',
            'is_active' => 1, 'created_at' => $now,
        ]);
        foreach (['a', 'b'] as $k) {
            $this->ids['companies'][$k] = $this->insert($db, 'companies', [
                'name' => self::TAG . 'Local ' . strtoupper($k), 'slug' => self::TAG . $k, 'type' => 'company',
                'parent_id' => $this->ids['companies']['tenant'], 'plan_id' => $this->ids['plans']['co1'],
                'is_active' => 1, 'created_at' => $now,
            ]);
        }
        $this->ids['companies']['loose'] = $this->insert($db, 'companies', [
            'name' => self::TAG . 'Empresa Suelta', 'slug' => self::TAG . 'suelta', 'type' => 'company',
            'plan_id' => $this->ids['plans']['co2'], 'is_active' => 1, 'created_at' => $now,
        ]);
        $this->ids['companies']['alien'] = $this->insert($db, 'companies', [
            'name' => self::TAG . 'Local Ajeno', 'slug' => self::TAG . 'ajeno', 'type' => 'company',
            'parent_id' => $this->ids['companies']['otherTenant'], 'is_active' => 1, 'created_at' => $now,
        ]);

        // El admin es dueño del tenant y, además, miembro admin (lo normal).
        $db->table('company_users')->insert([
            'company_id' => $this->ids['companies']['tenant'], 'user_id' => $this->ids['users']['admin'],
            'is_admin' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Lista blanca: solo el plan A, y por defecto.
        $db->table('tenant_plans')->insert([
            'tenant_id' => $this->ids['companies']['tenant'], 'plan_id' => $this->ids['plans']['co1'],
            'is_default' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Un rol de plataforma (lleva `tenants.view`): el administrador de un
        // grupo no puede repartirlo, o convertiría a otro en administrador de
        // los grupos de toda la plataforma.
        $this->ids['roles']['platform'] = $this->insert($db, 'roles', [
            'name' => self::TAG . 'RolPlataforma', 'description' => 'temporal', 'created_at' => $now,
        ]);
        $perm = $db->table('permissions')->select('id')->where('module', 'tenants')->get()->getRowArray();
        if ($perm) {
            $db->table('role_permissions')->insert([
                'role_id' => $this->ids['roles']['platform'], 'permission_id' => (int) $perm['id'],
            ]);
        }

        // Suscripciones vigentes para el grupo y sus empresas.
        foreach (['tenant' => 'tenant', 'a' => 'co1', 'b' => 'co1'] as $co => $plan) {
            $db->table('subscriptions')->insert([
                'company_id' => $this->ids['companies'][$co], 'plan_id' => $this->ids['plans'][$plan],
                'start_date' => $now, 'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    // ------------------------------------------------------------------ pruebas

    private function checks($db): void
    {
        $guard   = new TenantGuard();
        $company = new CompanyModel();
        $plans   = new TenantPlanModel();

        $adminId  = $this->ids['users']['admin'];
        $tenantId = $this->ids['companies']['tenant'];
        $a        = $this->ids['companies']['a'];
        $loose    = $this->ids['companies']['loose'];
        $alien    = $this->ids['companies']['alien'];

        CLI::write('Alcance', 'yellow');
        $this->ok('administra su grupo', $guard->tenantIdsOf($adminId) === [$tenantId]);
        $this->ok('ve las 2 empresas del grupo', count($guard->childIdsOf($adminId)) === 2);
        $this->ok('manages() acepta una hija suya', $guard->manages($a, $adminId));
        $this->ok('manages() rechaza una empresa suelta', !$guard->manages($loose, $adminId));
        $this->ok('manages() rechaza la hija de otro grupo', !$guard->manages($alien, $adminId));

        $accessible = array_map(static fn ($c) => (int) $c['id'], $company->getAccessibleFor($adminId, false));
        $this->ok('el selector le ofrece sus 2 empresas', count(array_intersect($accessible, [$a, $this->ids['companies']['b']])) === 2);
        $this->ok('el selector NO ofrece la fila-tenant', !in_array($tenantId, $accessible, true));
        $this->ok('el selector NO ofrece una empresa ajena', !in_array($alien, $accessible, true));
        $this->ok('userCanAccess() acepta su hija', $company->userCanAccess($a, $adminId));
        $this->ok('userCanAccess() rechaza la ajena (por URL también)', !$company->userCanAccess($alien, $adminId));

        CLI::write('Jerarquía (un solo nivel)', 'yellow');
        $this->ok('un tenant no cuelga de otro', !$company->canBeParent($this->ids['companies']['otherTenant'], $tenantId));
        $this->ok('una empresa no cuelga de otra empresa', !$company->canBeParent($loose, $a));
        $this->ok('una empresa no es su propio padre', !$company->canBeParent($a, $a));
        $this->ok('una empresa sí cuelga de un tenant', $company->canBeParent($tenantId, $loose));
        $this->ok('sin padre siempre vale', $company->canBeParent(null, $loose));

        // TenantGuard memoiza el alcance por petición; en CLI un proceso hace de
        // muchas, así que hay que olvidarlo después de tocar los datos.
        CLI::write('Cupo del grupo', 'yellow');
        TenantGuard::flush();
        $this->ok('con 2 de 2 no queda cupo', $guard->companiesLeft($tenantId) === 0);
        $db->table('companies')->where('id', $this->ids['companies']['b'])->update(['parent_id' => null]);
        TenantGuard::flush();
        $this->ok('descolgar libera una plaza', $guard->companiesLeft($tenantId) === 1);
        $db->table('companies')->where('id', $this->ids['companies']['b'])->update(['parent_id' => $tenantId]);
        $db->table('plans')->where('id', $this->ids['plans']['tenant'])->update(['max_companies' => 0]);
        TenantGuard::flush();
        $this->ok('max_companies = 0 es ilimitado', $guard->companiesLeft($tenantId) === null);
        $db->table('plans')->where('id', $this->ids['plans']['tenant'])->update(['max_companies' => 2]);
        TenantGuard::flush();

        CLI::write('Planes', 'yellow');
        $this->ok('la lista blanca acepta el plan habilitado', $plans->allows($tenantId, $this->ids['plans']['co1']));
        $this->ok('la lista blanca rechaza uno no habilitado', !$plans->allows($tenantId, $this->ids['plans']['co2']));
        $this->ok('hay plan por defecto', $plans->defaultPlanId($tenantId) === $this->ids['plans']['co1']);
        $this->ok('un plan de tenant no se puede repartir', !in_array(
            $this->ids['plans']['tenant'],
            array_map(static fn ($p) => (int) $p['id'], $plans->plansFor($tenantId)),
            true
        ));
        $this->ok('effectivePlan() usa el plan propio de la empresa',
            (int) ($company->effectivePlan($a)['id'] ?? 0) === $this->ids['plans']['co1']);
        $this->ok('effectivePlan() del grupo es su plan de tenant',
            (int) ($company->effectivePlan($tenantId)['id'] ?? 0) === $this->ids['plans']['tenant']);

        CLI::write('Vigencia heredada', 'yellow');
        $this->ok('con el grupo al día, la empresa se puede usar', $company->isUsable($a));
        $db->table('subscriptions')->where('company_id', $tenantId)->update(['status' => 'expired']);
        TenantGuard::flush();
        $this->ok('vencido el grupo, su empresa NO se puede usar', !$company->isUsable($a));
        $this->ok('una empresa suelta no se ve afectada', $company->isUsable($loose));
        $db->table('subscriptions')->where('company_id', $tenantId)->update(['status' => 'active']);
        TenantGuard::flush();

        CLI::write('Roles que puede repartir', 'yellow');
        $names = array_column($guard->assignableRoles(), 'name');
        $this->ok('«Admin» sí se puede otorgar', in_array('Admin', $names, true));
        $this->ok('«Super Admin» NO se puede otorgar', !in_array('Super Admin', $names, true));
        $this->ok('un rol con permisos de plataforma NO se puede otorgar',
            !in_array(self::TAG . 'RolPlataforma', $names, true));
        $this->ok('los roles de siempre siguen disponibles',
            count(array_intersect(['Auditor', 'Usuario'], $names)) === 2);

        CLI::write('Contexto del grupo', 'yellow');
        $this->ok('sin vínculo no hay grupo que administrar', $guard->currentTenantFor(0) === null);
        $resolved = $guard->currentTenantFor($adminId);
        $this->ok('con vínculo resuelve su grupo', (int) ($resolved['id'] ?? 0) === $tenantId);
        // La sesión pudo quedar apuntando a un grupo ajeno: se corrige al suyo.
        session()->set('active_tenant_id', $this->ids['companies']['otherTenant']);
        $resolved = $guard->currentTenantFor($adminId);
        $this->ok('un grupo ajeno en sesión se corrige al suyo', (int) ($resolved['id'] ?? 0) === $tenantId);
        session()->remove('active_tenant_id');

        CLI::write('Componentes', 'yellow');
        $manager = new \App\Libraries\ComponentManager();
        $scopes  = [];
        foreach ($manager->discover() as $folder => $m) {
            $scopes[$folder] = $manager->scopeOf($m);
        }
        // Los cinco que ya existían no declaran `scope`, así que tienen que
        // seguir siendo de ámbito empresa: si uno cambiara, su menú desaparecería
        // del contexto de empresa sin que nadie lo hubiera pedido.
        $legacy = array_intersect_key($scopes, array_flip(['Contabilidad', 'Marketplace', 'Networking', 'Pqrsf', 'Servicios']));
        $this->ok('los 5 componentes previos siguen siendo de ámbito empresa',
            $legacy !== [] && array_values(array_unique($legacy)) === ['company']);
        $this->ok('ninguno declara ámbito inválido',
            count(array_diff(array_values($scopes), ['company', 'tenant'])) === 0);
        $this->ok('planColumns() incluye las banderas de los componentes',
            in_array('service_enabled', $manager->planColumns(), true));
    }

    // ------------------------------------------------------------------ utilidades

    private function ok(string $label, bool $condition): void
    {
        if ($condition) {
            $this->passed++;
            CLI::write('  ✓ ' . $label, 'green');

            return;
        }
        $this->failed++;
        CLI::write('  ✗ ' . $label, 'red');
    }

    private function insert($db, string $table, array $row): int
    {
        $db->table($table)->insert($row);

        return (int) $db->insertID();
    }

    /**
     * Barre por ETIQUETA lo que haya dejado una corrida muerta a medias.
     *
     * `cleanup()` borra por los ids que esta corrida ha ido apuntando; esto
     * borra por el prefijo `__ttest__`, que es lo único que queda cuando el
     * proceso no llegó a terminar. Va primero, y en orden de dependencias: las
     * tablas que apuntan a otras, antes que ellas.
     */
    private function purge($db): void
    {
        $ids = static fn (string $tabla, string $col) => array_column(
            $db->table($tabla)->select('id')->like($col, self::TAG, 'after')->get()->getResultArray(),
            'id'
        );

        $companias = $ids('companies', 'name');
        if ($companias) {
            $db->table('subscriptions')->whereIn('company_id', $companias)->delete();
            $db->table('company_users')->whereIn('company_id', $companias)->delete();
            $db->table('tenant_plans')->whereIn('tenant_id', $companias)->delete();
            $db->table('companies')->whereIn('id', $companias)->delete();
        }

        $usuarios = $ids('users', 'username');
        if ($usuarios) {
            $db->table('user_roles')->whereIn('user_id', $usuarios)->delete();
            $db->table('company_users')->whereIn('user_id', $usuarios)->delete();
            $db->table('users')->whereIn('id', $usuarios)->delete();
        }

        $roles = $ids('roles', 'name');
        if ($roles) {
            $db->table('role_permissions')->whereIn('role_id', $roles)->delete();
            $db->table('user_roles')->whereIn('role_id', $roles)->delete();
            $db->table('roles')->whereIn('id', $roles)->delete();
        }

        $planes = $ids('plans', 'name');
        if ($planes) {
            $db->table('tenant_plans')->whereIn('plan_id', $planes)->delete();
            $db->table('plans')->whereIn('id', $planes)->delete();
        }
    }

    private function cleanup($db): void
    {
        $companyIds = array_values($this->ids['companies']);
        if ($companyIds) {
            $db->table('subscriptions')->whereIn('company_id', $companyIds)->delete();
            $db->table('company_users')->whereIn('company_id', $companyIds)->delete();
            $db->table('tenant_plans')->whereIn('tenant_id', $companyIds)->delete();
            $db->table('companies')->whereIn('id', $companyIds)->delete();
        }
        if ($this->ids['plans']) {
            $db->table('tenant_plans')->whereIn('plan_id', array_values($this->ids['plans']))->delete();
            $db->table('plans')->whereIn('id', array_values($this->ids['plans']))->delete();
        }
        if ($this->ids['users']) {
            $db->table('user_roles')->whereIn('user_id', array_values($this->ids['users']))->delete();
            $db->table('users')->whereIn('id', array_values($this->ids['users']))->delete();
        }
        if ($this->ids['roles']) {
            $roleIds = array_values($this->ids['roles']);
            $db->table('role_permissions')->whereIn('role_id', $roleIds)->delete();
            $db->table('user_roles')->whereIn('role_id', $roleIds)->delete();
            $db->table('roles')->whereIn('id', $roleIds)->delete();
        }
    }
}
