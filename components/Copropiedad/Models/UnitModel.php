<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Inmuebles: casas, apartamentos, locales, oficinas, parqueaderos y depósitos.
 *
 * **`MANUAL_OCCUPANCIES` es la clave del estado de ocupación.** El resto de estados
 * se deducen de las relaciones vigentes y `RegistryService::syncOccupancy()` los
 * recalcula solo; estos dos no se deducen de nada —son una decisión del
 * administrador— así que el recálculo los respeta. Sin esa lista, marcar un
 * apartamento «en venta» habría durado hasta el siguiente cambio de residente.
 */
class UnitModel extends Model
{
    use CompanyScoped;

    public const KINDS = ['apartamento', 'casa', 'local', 'oficina', 'parqueadero', 'deposito', 'otro'];

    /** Los tipos que se adscriben a otro inmueble en vez de vivir por su cuenta. */
    public const ATTACHABLE_KINDS = ['parqueadero', 'deposito'];

    public const OCCUPANCIES = ['desocupado', 'propietario', 'arrendado', 'ocupado', 'en_venta', 'remodelacion'];

    /** Estados que ninguna relación puede deducir: los pone una persona. */
    public const MANUAL_OCCUPANCIES = ['en_venta', 'remodelacion'];

    protected $table            = 'cmp_cop_units';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'block_id', 'parent_unit_id', 'code', 'kind', 'floor',
        'area', 'coefficient', 'bedrooms', 'bathrooms', 'matricula',
        'occupancy', 'notes', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id'  => 'required|is_natural_no_zero',
        'code'        => 'required|min_length[1]|max_length[30]',
        'kind'        => 'required|in_list[apartamento,casa,local,oficina,parqueadero,deposito,otro]',
        'area'        => 'permit_empty|decimal|greater_than_equal_to[0]',
        'coefficient' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'bedrooms'    => 'permit_empty|is_natural',
        'bathrooms'   => 'permit_empty|is_natural',
        'occupancy'   => 'required|in_list[desocupado,propietario,arrendado,ocupado,en_venta,remodelacion]',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'code'        => ['required' => lang('Cop.vUnitCode')],
            'kind'        => ['in_list' => lang('Cop.vUnitKind')],
            'coefficient' => ['less_than_equal_to' => lang('Cop.vUnitCoefficient')],
            'occupancy'   => ['in_list' => lang('Cop.vUnitOccupancy')],
        ];
    }

    /**
     * ¿Ya hay otro inmueble con ese código en la empresa?
     *
     * Se comprueba aquí y no con un índice UNIQUE porque la tabla usa soft-delete:
     * un UNIQUE impediría volver a crear el «501» después de haberlo borrado por
     * error, ya que la fila borrada seguiría ocupando el código.
     */
    public function codeTaken(int $companyId, string $code, ?int $ignoreId = null): bool
    {
        $q = $this->where('company_id', $companyId)->where('code', $code);
        if ($ignoreId) {
            $q->where('id !=', $ignoreId);
        }

        return $q->countAllResults() > 0;
    }

    /** Suma de coeficientes vigentes: debería dar 100 en una copropiedad completa. */
    public function coefficientSum(int $companyId): float
    {
        $row = $this->selectSum('coefficient', 'total')
            ->where('company_id', $companyId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return round((float) ($row['total'] ?? 0), 6);
    }

    /** Cuántos inmuebles hay de cada estado de ocupación. */
    public function occupancyCounts(int $companyId): array
    {
        $rows = $this->select('occupancy, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('deleted_at', null)
            ->groupBy('occupancy')
            ->get()
            ->getResultArray();

        $out = array_fill_keys(self::OCCUPANCIES, 0);

        foreach ($rows as $r) {
            $out[$r['occupancy']] = (int) $r['total'];
        }

        return $out;
    }

    /** Cuántos inmuebles hay de cada tipo. */
    public function kindCounts(int $companyId): array
    {
        $rows = $this->select('kind, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('deleted_at', null)
            ->groupBy('kind')
            ->get()
            ->getResultArray();

        $out = array_fill_keys(self::KINDS, 0);

        foreach ($rows as $r) {
            $out[$r['kind']] = (int) $r['total'];
        }

        return $out;
    }

    /**
     * `[id => etiqueta]` para los selectores, con la torre delante para que dos
     * «501» de torres distintas se distingan.
     */
    public function optionsFor(int $companyId, ?array $onlyKinds = null): array
    {
        $q = $this->select('cmp_cop_units.id, cmp_cop_units.code, cmp_cop_units.kind, cmp_cop_blocks.name AS block_name')
            ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
            ->where('cmp_cop_units.company_id', $companyId)
            ->where('cmp_cop_units.deleted_at', null);

        if ($onlyKinds) {
            $q->whereIn('cmp_cop_units.kind', $onlyKinds);
        }

        $rows = $q->orderBy('cmp_cop_blocks.name', 'asc')->orderBy('cmp_cop_units.code', 'asc')->findAll();
        $out  = [];

        foreach ($rows as $r) {
            $out[(int) $r['id']] = ($r['block_name'] ? $r['block_name'] . ' · ' : '') . $r['code'];
        }

        return $out;
    }

    /** Los inmuebles adscritos a otro (sus parqueaderos y depósitos). */
    public function attachedTo(int $unitId, int $companyId): array
    {
        return $this->forCompany($companyId)
            ->where('parent_unit_id', $unitId)
            ->orderBy('kind', 'asc')->orderBy('code', 'asc')
            ->findAll();
    }
}
