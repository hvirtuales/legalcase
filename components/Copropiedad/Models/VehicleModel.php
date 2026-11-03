<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\BelongsToUnit;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Vehículos.
 *
 * La placa se guarda **normalizada** (mayúsculas, sin espacios ni guiones) porque la
 * portería la teclea a las once de la noche como le sale: «abc 123», «ABC-123».
 * Buscar tendría que acertar con el formato exacto si se guardara tal cual.
 */
class VehicleModel extends Model
{
    use BelongsToUnit;
    use CompanyScoped;

    public const KINDS = ['carro', 'camioneta', 'moto', 'bicicleta', 'otro'];

    protected $table            = 'cmp_cop_vehicles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'unit_id', 'person_id', 'parking_unit_id', 'kind', 'plate',
        'brand', 'model', 'color', 'year', 'sticker', 'notes', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'unit_id'    => 'required|is_natural_no_zero',
        'kind'       => 'required|in_list[carro,camioneta,moto,bicicleta,otro]',
        'plate'      => 'permit_empty|max_length[15]',
        'year'       => 'permit_empty|is_natural',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'unit_id' => ['required' => lang('Cop.vNeedUnit')],
            'kind'    => ['in_list' => lang('Cop.vVehicleKind')],
        ];
    }

    /** Mayúsculas y sin separadores: «abc-123» y «ABC 123» son la misma placa. */
    public static function normalizePlate(?string $plate): ?string
    {
        $plate = strtoupper(trim((string) $plate));
        $plate = preg_replace('/[^A-Z0-9]/', '', $plate);

        return $plate === '' ? null : $plate;
    }

    /** ¿Ya está registrada esta placa en la empresa? */
    public function plateTaken(int $companyId, ?string $plate, ?int $ignoreId = null): bool
    {
        $plate = self::normalizePlate($plate);
        if ($plate === null) {
            return false;
        }
        $q = $this->where('company_id', $companyId)->where('plate', $plate);
        if ($ignoreId) {
            $q->where('id !=', $ignoreId);
        }

        return $q->countAllResults() > 0;
    }

    /** Qué vehículo tiene asignado cada parqueadero (para la ficha del inmueble). */
    public function inParking(int $parkingUnitId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('parking_unit_id', $parkingUnitId)->findAll();
    }
}
