<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * La relación persona ↔ inmueble. **Y el historial**, que es la misma tabla vista
 * con `is_current = 0` (ver la migración para el porqué).
 *
 * Todas las consultas de aquí unen con `cmp_cop_people` porque una relación sin el
 * nombre de la persona no se puede pintar en ningún sitio, y hacerlo en el
 * controlador significaba una consulta por fila.
 */
class RelationModel extends Model
{
    use CompanyScoped;

    public const ROLES = ['propietario', 'residente', 'arrendatario', 'ocupante'];

    /** Los papeles que implican vivir en el inmueble (para el estado de ocupación). */
    public const LIVING_ROLES = ['residente', 'arrendatario', 'ocupante'];

    protected $table            = 'cmp_cop_unit_people';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'unit_id', 'person_id', 'role', 'share', 'is_main',
        'start_date', 'end_date', 'is_current', 'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'unit_id'    => 'required|is_natural_no_zero',
        'person_id'  => 'required|is_natural_no_zero',
        'role'       => 'required|in_list[propietario,residente,arrendatario,ocupante]',
        'share'      => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'role'  => ['in_list' => lang('Cop.vRelationRole')],
            'share' => ['less_than_equal_to' => lang('Cop.vRelationShare')],
        ];
    }

    /** Consulta base con los datos de la persona ya unidos. */
    private function withPerson(int $companyId)
    {
        return $this->select('cmp_cop_unit_people.*, cmp_cop_people.first_name, cmp_cop_people.last_name,'
                . ' cmp_cop_people.doc_type, cmp_cop_people.doc_number, cmp_cop_people.phone,'
                . ' cmp_cop_people.email, cmp_cop_people.photo')
            ->join('cmp_cop_people', 'cmp_cop_people.id = cmp_cop_unit_people.person_id', 'inner')
            ->where('cmp_cop_unit_people.company_id', $companyId);
    }

    /** Quién ocupa hoy este inmueble y con qué papel. */
    public function currentFor(int $unitId, int $companyId): array
    {
        return $this->withPerson($companyId)
            ->where('cmp_cop_unit_people.unit_id', $unitId)
            ->where('cmp_cop_unit_people.is_current', 1)
            ->orderBy('cmp_cop_unit_people.role', 'asc')
            ->orderBy('cmp_cop_unit_people.is_main', 'desc')
            ->findAll();
    }

    /**
     * Todo lo que ha pasado en este inmueble, lo vigente y lo cerrado.
     *
     * Ordenado por «lo vigente primero, y dentro de eso lo más reciente»: es como se
     * lee un historial, no como está guardado.
     */
    public function historyFor(int $unitId, int $companyId): array
    {
        return $this->withPerson($companyId)
            ->where('cmp_cop_unit_people.unit_id', $unitId)
            ->orderBy('cmp_cop_unit_people.is_current', 'desc')
            ->orderBy('cmp_cop_unit_people.start_date', 'desc')
            ->orderBy('cmp_cop_unit_people.id', 'desc')
            ->findAll();
    }

    /** Dónde ha estado esta persona (su historial personal). */
    public function forPerson(int $personId, int $companyId): array
    {
        return $this->select('cmp_cop_unit_people.*, cmp_cop_units.code AS unit_code,'
                . ' cmp_cop_units.kind AS unit_kind, cmp_cop_blocks.name AS block_name')
            ->join('cmp_cop_units', 'cmp_cop_units.id = cmp_cop_unit_people.unit_id', 'inner')
            ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
            ->where('cmp_cop_unit_people.company_id', $companyId)
            ->where('cmp_cop_unit_people.person_id', $personId)
            ->orderBy('cmp_cop_unit_people.is_current', 'desc')
            ->orderBy('cmp_cop_unit_people.start_date', 'desc')
            ->findAll();
    }

    /** Las relaciones vigentes de un papel concreto en un inmueble. */
    public function currentByRole(int $unitId, int $companyId, string $role): array
    {
        return $this->withPerson($companyId)
            ->where('cmp_cop_unit_people.unit_id', $unitId)
            ->where('cmp_cop_unit_people.role', $role)
            ->where('cmp_cop_unit_people.is_current', 1)
            ->findAll();
    }

    /**
     * ¿Existe ya esta misma relación vigente?
     *
     * Sin esto, pulsar dos veces «añadir» dejaba a la misma persona dos veces como
     * propietaria del mismo inmueble, y los porcentajes dejaban de sumar 100 sin que
     * nada lo explicara.
     */
    public function existsCurrent(int $unitId, int $personId, string $role, int $companyId): bool
    {
        return $this->where('company_id', $companyId)
            ->where('unit_id', $unitId)
            ->where('person_id', $personId)
            ->where('role', $role)
            ->where('is_current', 1)
            ->countAllResults() > 0;
    }

    /** Suma de los porcentajes de propiedad vigentes: debería dar 100. */
    public function shareSum(int $unitId, int $companyId): float
    {
        $row = $this->selectSum('share', 'total')
            ->where('company_id', $companyId)
            ->where('unit_id', $unitId)
            ->where('role', 'propietario')
            ->where('is_current', 1)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return round((float) ($row['total'] ?? 0), 4);
    }

    /** Cuántas relaciones vigentes hay de cada papel (para el panel). */
    public function currentCountsByRole(int $companyId): array
    {
        $rows = $this->select('role, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('is_current', 1)
            ->where('deleted_at', null)
            ->groupBy('role')
            ->get()
            ->getResultArray();

        $out = array_fill_keys(self::ROLES, 0);

        foreach ($rows as $r) {
            $out[$r['role']] = (int) $r['total'];
        }

        return $out;
    }
}
