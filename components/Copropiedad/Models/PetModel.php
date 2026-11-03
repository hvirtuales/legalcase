<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\BelongsToUnit;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Mascotas.
 */
class PetModel extends Model
{
    use BelongsToUnit;
    use CompanyScoped;

    public const SPECIES = ['perro', 'gato', 'ave', 'pez', 'roedor', 'reptil', 'otro'];
    public const SIZES   = ['pequeno', 'mediano', 'grande'];

    protected $table            = 'cmp_cop_pets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'unit_id', 'person_id', 'name', 'species', 'breed', 'color',
        'size', 'birthdate', 'chip', 'vaccine_date', 'vaccine_expires',
        'is_dangerous', 'photo', 'notes', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'unit_id'    => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[1]|max_length[60]',
        'species'    => 'required|in_list[perro,gato,ave,pez,roedor,reptil,otro]',
        'size'       => 'permit_empty|in_list[pequeno,mediano,grande]',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'unit_id' => ['required' => lang('Cop.vNeedUnit')],
            'name'    => ['required' => lang('Cop.vPetName')],
            'species' => ['in_list' => lang('Cop.vPetSpecies')],
        ];
    }

    /** Las de manejo especial: lo que la portería necesita tener a mano. */
    public function dangerousCount(int $companyId): int
    {
        return $this->where('company_id', $companyId)->where('is_dangerous', 1)->countAllResults();
    }

    /** Vacunas vencidas o a punto de vencer, para el aviso del panel. */
    public function vaccinesDue(int $companyId, int $days = 30): array
    {
        $limit = date('Y-m-d', strtotime('+' . $days . ' days'));

        return $this->withUnit($companyId)
            ->where('cmp_cop_pets.vaccine_expires IS NOT NULL')
            ->where('cmp_cop_pets.vaccine_expires <=', $limit)
            ->orderBy('cmp_cop_pets.vaccine_expires', 'asc')
            ->findAll(20);
    }
}
