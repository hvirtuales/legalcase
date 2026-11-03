<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Grupos familiares. La pertenencia vive en `cmp_cop_people.family_id`; aquí solo
 * está el grupo, su inmueble y quién es la cabeza.
 */
class FamilyModel extends Model
{
    use CompanyScoped;

    protected $table            = 'cmp_cop_families';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = ['company_id', 'unit_id', 'name', 'head_person_id', 'notes'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[120]',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'name' => ['required' => lang('Cop.vFamilyName')],
        ];
    }

    /** Los grupos con su inmueble y cuántos miembros tienen. */
    public function listFor(int $companyId): array
    {
        return $this->select('cmp_cop_families.*, cmp_cop_units.code AS unit_code, cmp_cop_blocks.name AS block_name')
            ->join('cmp_cop_units', 'cmp_cop_units.id = cmp_cop_families.unit_id', 'left')
            ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
            ->where('cmp_cop_families.company_id', $companyId)
            ->orderBy('cmp_cop_families.name', 'asc')
            ->findAll();
    }

    /**
     * Cuántos miembros tiene cada grupo, en **una** consulta.
     *
     * Se agrupa en la base y no contando por grupo en PHP porque el listado los pinta
     * todos: con veinte familias eran veinte consultas.
     */
    public function memberCounts(int $companyId): array
    {
        $rows = $this->db->table('cmp_cop_people')
            ->select('family_id, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('family_id IS NOT NULL')
            ->where('deleted_at', null)
            ->groupBy('family_id')
            ->get()
            ->getResultArray();

        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r['family_id']] = (int) $r['total'];
        }

        return $out;
    }

    /** `[id => nombre]` para los selectores. */
    public function optionsFor(int $companyId): array
    {
        $out = [];

        foreach ($this->forCompany($companyId)->orderBy('name', 'asc')->findAll() as $f) {
            $out[(int) $f['id']] = $f['name'];
        }

        return $out;
    }
}
