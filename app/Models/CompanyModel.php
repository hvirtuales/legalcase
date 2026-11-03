<?php

namespace App\Models;

use App\Traits\Auditable;
use CodeIgniter\Model;

class CompanyModel extends Model
{
    use Auditable;

    protected $table          = 'companies';
    protected $primaryKey      = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    // Solo el subconjunto "núcleo" que gestiona este panel; el resto de
    // columnas (tokens, flags de módulos, contadores) las maneja la app mayor.
    protected $allowedFields = [
        'name',
        'nit',
        'slug',
        'logo',
        'banner',
        'website',
        'address',
        'city',
        'description',
        'brand_color',
        'contact_email',
        'contact_phone',
        'whatsapp',
        'facebook',
        'instagram',
        'tiktok',
        'youtube',
        'linkedin',
        'sector_id',
        'latitude',
        'longitude',
        'plan_id',
        'owner_id',
        'is_active',
        'type',
        'parent_id',
        'can_create_children',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'          => 'required|min_length[2]|max_length[150]',
        'nit'           => 'permit_empty|max_length[40]',
        'slug'          => 'permit_empty|max_length[160]|is_unique[companies.slug,id,{id}]',
        'website'       => 'permit_empty|max_length[255]',
        'contact_email' => 'permit_empty|valid_email|max_length[100]',
        'contact_phone' => 'permit_empty|max_length[30]',
        'whatsapp'      => 'permit_empty|max_length[40]',
        'brand_color'   => 'permit_empty|max_length[9]',
        'facebook'      => 'permit_empty|max_length[255]',
        'instagram'     => 'permit_empty|max_length[255]',
        'tiktok'        => 'permit_empty|max_length[255]',
        'youtube'       => 'permit_empty|max_length[255]',
        'linkedin'      => 'permit_empty|max_length[255]',
    ];

    /** Redes que la empresa puede publicar, en el orden en que se muestran. */
    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];

    /**
     * Qué es esta fila: una empresa normal o un **tenant** (centro comercial,
     * cámara de comercio) que agrupa empresas. VARCHAR + validación aquí, no
     * ENUM (portabilidad a los 3 motores).
     */
    public const TYPES = ['company', 'tenant'];

    /**
     * Clasificación y ubicación tal como llegan del formulario.
     *
     * Vive aquí y no repetido en los cuatro sitios que guardan una empresa
     * (`/companies`, `/tenants`, `/tenant/empresas` y «Mi empresa»): son las
     * mismas reglas, y cuatro copias es garantizar que un día se separen.
     *
     * - `sector_id` vacío o 0 → NULL (sin clasificar), nunca 0.
     * - Las coordenadas se **validan y se normalizan**: coma decimal a punto,
     *   y fuera de rango → NULL. Una latitud de 200 no es un error del
     *   usuario que haya que conservar: es un punto que no existe, y guardarlo
     *   pondría un marcador en cualquier parte.
     *
     * @return array{sector_id:?int,latitude:?string,longitude:?string}
     */
    public static function locationFields($request): array
    {
        $sector = (int) $request->getPost('sector_id');

        return [
            'sector_id' => $sector > 0 ? $sector : null,
            'latitude'  => self::coordinate($request->getPost('latitude'), 90),
            'longitude' => self::coordinate($request->getPost('longitude'), 180),
        ];
    }

    /** Una coordenada válida como cadena, o null. */
    public static function coordinate($value, float $max): ?string
    {
        $v = str_replace(',', '.', trim((string) $value));
        if ($v === '' || !is_numeric($v)) {
            return null;
        }
        $n = (float) $v;

        return ($n >= -$max && $n <= $max) ? (string) $n : null;
    }

    /**
     * Normaliza el enlace de una red: acepta la URL completa o solo el usuario
     * (`@mimarca`, `mimarca`) y devuelve siempre una URL absoluta, que es lo
     * único que sirve para un `href`. Cadena vacía si no hay nada.
     */
    public static function socialUrl(string $network, ?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if (str_starts_with($value, 'www.') || str_contains($value, '.com') || str_contains($value, '.co/')) {
            return 'https://' . ltrim($value, '/');
        }

        $handle = ltrim($value, '@/');
        $base   = [
            'facebook'  => 'https://facebook.com/',
            'instagram' => 'https://instagram.com/',
            'tiktok'    => 'https://tiktok.com/@',
            'youtube'   => 'https://youtube.com/@',
            'linkedin'  => 'https://linkedin.com/company/',
        ];

        return isset($base[$network]) ? $base[$network] . $handle : 'https://' . $handle;
    }

    protected $validationMessages = [
        'name' => ['required' => 'El nombre de la empresa es requerido'],
        'slug' => ['is_unique' => 'Ese slug ya está en uso'],
    ];

    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = ['setUpdatedBy'];
    protected $beforeDelete = ['setDeletedBy'];
    protected $afterInsert  = ['auditInsert'];
    protected $afterUpdate  = ['auditUpdate'];
    protected $afterDelete  = ['auditDelete'];

    /** Empresas con nombre de plan y del dueño (para el listado). */
    public function getWithRelations(): array
    {
        return $this->select('companies.*, plans.name AS plan_name, CONCAT(u.first_name, " ", u.last_name) AS owner_name')
            ->join('plans', 'plans.id = companies.plan_id', 'left')
            ->join('users u', 'u.id = companies.owner_id', 'left')
            ->orderBy('companies.name', 'ASC')
            ->findAll();
    }

    /**
     * Empresas a las que un usuario puede "entrar": todas si es super admin, o
     * aquellas donde es **dueño** (`owner_id`), **miembro** (pivote
     * `company_users`) **o que cuelgan de un tenant que administra**
     * (`TenantGuard`). El orden por nombre y el filtro de soft-delete se
     * aplican en el Model (`findAll`).
     *
     * Las **filas-tenant** se excluyen del selector de empresa: al grupo se
     * entra por el Panel del Tenant (`/tenant`), no eligiéndolo como si fuera
     * una empresa. El Super Admin sí las ve, porque para él el selector es
     * también la forma de inspeccionar cualquier fila.
     */
    public function getAccessibleFor(int $userId, bool $isSuperAdmin): array
    {
        $builder = $this->orderBy('name', 'ASC');
        $ids     = $this->accessibleIdsFor($userId, $isSuperAdmin);
        if ($ids !== null) {
            if ($ids === []) {
                return []; // sin empresas (dueño, miembro ni grupo)
            }
            $builder->whereIn('id', $ids);
            // Al grupo se entra por /tenant, no por el selector de empresa.
            $builder->groupStart()->where('type', 'company')->orWhere('type', null)->orWhere('type', '')->groupEnd();
        }

        return $builder->findAll();
    }

    /**
     * Los **ids** a los que el usuario tiene alcance, o `null` si los ve todos.
     *
     * Se saca del cuerpo de `getAccessibleFor()` para poder reutilizar la MISMA
     * regla de alcance en el buscador del selector de contexto sin traerse las
     * filas: con 1200 empresas, cargarlas todas para quedarse con veinte es lo
     * que hay que evitar.
     *
     * @return int[]|null null = sin límite (Super Admin)
     */
    public function accessibleIdsFor(int $userId, bool $isSuperAdmin): ?array
    {
        if ($isSuperAdmin) {
            return null;
        }

        $db     = \Config\Database::connect();
        $owned  = array_column($db->table('companies')->select('id')->where('owner_id', $userId)->get()->getResultArray(), 'id');
        $member = array_column($db->table('company_users')->select('company_id')->where('user_id', $userId)->get()->getResultArray(), 'company_id');
        $group  = (new \App\Libraries\TenantGuard())->childIdsOf($userId);

        return array_values(array_unique(array_map('intval', array_merge($owned, $member, $group))));
    }

    /**
     * Empresas accesibles que casan con un texto, **acotadas en la consulta**.
     *
     * El selector de contexto pintaba las 1200 empresas en la cabecera de cada
     * página. Aquí la base hace el filtro y devuelve un puñado: la cabecera deja
     * de crecer con el catálogo.
     *
     * @return array{rows:array,total:int}
     */
    public function searchAccessibleFor(int $userId, bool $isSuperAdmin, string $q = '', int $limit = 20): array
    {
        $ids = $this->accessibleIdsFor($userId, $isSuperAdmin);
        if ($ids !== null && $ids === []) {
            return ['rows' => [], 'total' => 0];
        }

        $aplica = function ($b) use ($ids, $q) {
            if ($ids !== null) {
                $b->whereIn('id', $ids);
            }
            // Al grupo se entra por su panel, no por el selector de empresa.
            $b->groupStart()->where('type', 'company')->orWhere('type', null)->orWhere('type', '')->groupEnd();
            $q = trim($q);
            if ($q !== '') {
                // Agrupado para no romper el filtro de soft-delete ni el de tipo.
                $b->groupStart()->like('name', $q)->orLike('nit', $q)->orLike('city', $q)->groupEnd();
            }

            return $b;
        };

        $total = $aplica($this->builder())->where('deleted_at', null)->countAllResults();
        $rows  = $aplica($this)->orderBy('name', 'ASC')->findAll($limit);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * ¿El usuario puede entrar a esta empresa? Es dueño, miembro, o la empresa
     * cuelga de un tenant que administra.
     */
    public function userCanAccess(int $companyId, int $userId): bool
    {
        $company = $this->find($companyId);
        if ($company && (int) $company['owner_id'] === $userId) {
            return true;
        }

        if ((new CompanyUserModel())->isMember($companyId, $userId)) {
            return true;
        }

        return (new \App\Libraries\TenantGuard())->manages($companyId, $userId);
    }

    /**
     * ¿El usuario administra esta empresa (dueño, miembro con is_admin, o
     * administrador del tenant del que cuelga)?
     */
    public function isManager(int $companyId, int $userId): bool
    {
        $company = $this->find($companyId);
        if ($company && (int) $company['owner_id'] === $userId) {
            return true;
        }

        $isAdminMember = (bool) (new CompanyUserModel())
            ->where('company_id', $companyId)->where('user_id', $userId)->where('is_admin', 1)->first();

        return $isAdminMember || (new \App\Libraries\TenantGuard())->manages($companyId, $userId);
    }

    // ------------------------------------------------------------------ jerarquía

    public function isTenant(?array $company): bool
    {
        return ($company['type'] ?? 'company') === 'tenant';
    }

    /** Los tenants del catálogo (para el panel global y los selects). */
    public function tenants(): array
    {
        return $this->where('type', 'tenant')->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Tenants que casan con un texto, acotados y contados en la consulta.
     *
     * @return array{rows:array,total:int}
     */
    public function searchTenants(?array $onlyIds, string $q = '', int $limit = 20): array
    {
        if ($onlyIds !== null && $onlyIds === []) {
            return ['rows' => [], 'total' => 0];
        }

        $aplica = function ($b) use ($onlyIds, $q) {
            $b->where('type', 'tenant');
            if ($onlyIds !== null) {
                $b->whereIn('id', $onlyIds);
            }
            $q = trim($q);
            if ($q !== '') {
                $b->groupStart()->like('name', $q)->orLike('nit', $q)->orLike('city', $q)->groupEnd();
            }

            return $b;
        };

        $total = $aplica($this->builder())->where('deleted_at', null)->countAllResults();
        $rows  = $aplica($this)->orderBy('name', 'ASC')->findAll($limit);

        return ['rows' => $rows, 'total' => $total];
    }

    /** Empresas que cuelgan de un tenant. */
    public function childrenOf(int $tenantId): array
    {
        return $this->where('parent_id', $tenantId)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * ¿`$parentId` puede ser el tenant de `$companyId`?
     *
     * **Un solo nivel de anidación**, a propósito: el padre tiene que ser un
     * tenant y la hija no puede serlo. Sin esta regla, el día que alguien
     * encadene tres niveles habría que reescribir todas las consultas de
     * alcance (`TenantGuard` asume profundidad 1).
     */
    public function canBeParent(?int $parentId, ?int $companyId = null): bool
    {
        if (!$parentId) {
            return true; // empresa suelta
        }
        if ($companyId !== null && $parentId === $companyId) {
            return false; // ni su propio padre
        }

        $parent = $this->find($parentId);
        if (!$parent || !$this->isTenant($parent)) {
            return false; // solo se cuelga de un tenant
        }

        if ($companyId !== null) {
            $self = $this->find($companyId);
            if ($self && $this->isTenant($self)) {
                return false; // un tenant no cuelga de otro
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ plan y vigencia

    /**
     * El plan que rige para esta fila.
     *
     * Es siempre **su propio** `plan_id`: los planes tienen ámbito
     * (`plans.scope`) y el de un tenant no sirve para una empresa —sus banderas
     * son las de los componentes de nivel tenant—, así que no hay herencia. Lo
     * que sí hay es que una empresa recién colgada del grupo recibe
     * automáticamente el plan marcado por defecto en la lista blanca del tenant
     * (`TenantPlanModel::defaultPlanId()`), para que no se quede sin servicios
     * por un olvido.
     *
     * `null` = sin plan asignado, que en toda la plataforma significa permitido
     * e ilimitado.
     */
    /**
     * Memo **por petición**: el plan efectivo se pregunta desde el panel, el
     * sidebar y cada componente activo (uno por menú), y son dos consultas cada
     * vez. Se limpia con `flushPlanCache()` al cambiar de plan.
     *
     * @var array<int, array|null>
     */
    private static array $planCache = [];

    public static function flushPlanCache(): void
    {
        self::$planCache = [];
    }

    public function effectivePlan(int $companyId): ?array
    {
        if (array_key_exists($companyId, self::$planCache)) {
            return self::$planCache[$companyId];
        }

        $company = $this->find($companyId);
        if (!$company || empty($company['plan_id'])) {
            return self::$planCache[$companyId] = null;
        }

        return self::$planCache[$companyId] = (new PlanModel())->find((int) $company['plan_id']);
    }

    /**
     * ¿Esta empresa está vigente? Lo está si su propia suscripción lo está **y**
     * la de su tenant también: se le factura al centro comercial, no a cada
     * local, así que si vence la del grupo caen todas sus empresas.
     */
    public function isUsable(int $companyId): bool
    {
        $company = $this->find($companyId);
        if (!$company) {
            return false;
        }

        if (!empty($company['parent_id'])
            && !(new \App\Libraries\TenantGuard())->tenantSubscriptionValid((int) $company['parent_id'])) {
            return false;
        }

        $subModel = new SubscriptionModel();
        $sub      = $subModel->getActiveForCompany($companyId);

        return $sub ? $subModel->isValid($subModel->markExpiredIfNeeded($sub)) : true;
    }

    /** Genera un slug único a partir del nombre. */
    public function makeSlug(string $name, ?int $ignoreId = null): string
    {
        helper('text');
        $base = url_title(strtolower($name), '-', true);
        if ($base === '') {
            $base = 'empresa';
        }

        $slug = $base;
        $i    = 1;
        while (true) {
            $builder = $this->where('slug', $slug);
            if ($ignoreId !== null) {
                $builder->where('id !=', $ignoreId);
            }
            if ($builder->first() === null) {
                return $slug;
            }
            $slug = $base . '-' . (++$i);
        }
    }
}
