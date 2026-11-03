<?php

namespace App\Models;

use App\Traits\Auditable;
use CodeIgniter\Model;

class PlanModel extends Model
{
    use Auditable;

    protected $table          = 'plans';
    protected $primaryKey      = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    /** Ámbitos válidos de un plan (VARCHAR + validación aquí: portabilidad). */
    public const SCOPES = ['company', 'tenant'];

    protected $allowedFields = [
        'name',
        'description',
        'scope',
        'max_companies',
        'service_days',
        'price_amount',
        'price_currency',
        'features',
        'is_active',
        'service_enabled',
        'max_vouchers',
        'max_payment_requests',
        'networking_enabled',
        'max_connections',
        'marketplace_enabled',
        'max_listings',
        'quotes_enabled',
        'max_quotes',
        'pqrsf_enabled',
        'max_requests',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Las columnas de los cinco componentes que ya existían quedan arriba por
     * claridad, pero un componente nuevo **no** tiene que tocar esta lista: sus
     * banderas y cupos se añaden solos desde su Manifest (`plan_flag` /
     * `plan_fields` / `plan_limits`). Sin esto, la columna de un componente
     * recién instalado se descartaría en silencio al guardar el plan.
     *
     * Se cachea por proceso: `discover()` recorre el disco y este modelo se
     * instancia muchas veces por request.
     */
    private static ?array $componentColumns = null;

    public function __construct()
    {
        parent::__construct();

        self::$componentColumns ??= (new \App\Libraries\ComponentManager())->planColumns();
        $this->allowedFields = array_values(array_unique(array_merge($this->allowedFields, self::$componentColumns)));
    }

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'           => 'required|min_length[2]|max_length[80]',
        'service_days'   => 'required|is_natural',
        'price_amount'   => 'permit_empty|decimal',
        'price_currency' => 'permit_empty|exact_length[3]',
        'max_companies'  => 'permit_empty|is_natural',
        'scope'          => 'permit_empty|in_list[company,tenant]',
    ];

    protected $validationMessages = [
        'name'         => ['required' => 'El nombre del plan es requerido'],
        'service_days' => [
            'required'   => 'Los días de servicio son requeridos',
            'is_natural' => 'Los días de servicio deben ser un número entero',
        ],
    ];

    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = ['setUpdatedBy'];
    protected $beforeDelete = ['setDeletedBy'];
    protected $afterInsert  = ['auditInsert'];
    protected $afterUpdate  = ['auditUpdate'];
    protected $afterDelete  = ['auditDelete'];

    /**
     * Planes activos para selects.
     *
     * `$scope` acota al ámbito: `company` (lo que contrata una empresa) o
     * `tenant` (lo que contrata el grupo). Sin ámbito devuelve todos, para las
     * pantallas de catálogo. Los planes anteriores a la columna `scope` cuentan
     * como `company`, que es lo que eran.
     */
    public function getActive(?string $scope = null): array
    {
        $builder = $this->where('is_active', 1);
        if ($scope !== null) {
            $builder->groupStart()->where('scope', $scope);
            if ($scope === 'company') {
                $builder->orWhere('scope', null)->orWhere('scope', '');
            }
            $builder->groupEnd();
        }

        return $builder->orderBy('price_amount', 'ASC')->findAll();
    }

    /** Ámbito de un plan, normalizado (los heredados sin columna son `company`). */
    public static function scopeOf(?array $plan): string
    {
        $scope = trim((string) ($plan['scope'] ?? ''));

        return in_array($scope, self::SCOPES, true) ? $scope : 'company';
    }
}
