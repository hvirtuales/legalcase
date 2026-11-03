<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Personas del censo. El **papel** (propietario, residente, arrendatario, ocupante)
 * no está aquí: vive en la relación con el inmueble. Ver `RelationModel`.
 */
class PersonModel extends Model
{
    use CompanyScoped;

    public const DOC_TYPES    = ['CC', 'CE', 'TI', 'RC', 'PA', 'NIT'];
    public const FAMILY_ROLES = ['jefe', 'conyuge', 'hijo', 'padre', 'hermano', 'otro'];

    protected $table            = 'cmp_cop_people';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'family_id', 'family_role', 'user_id', 'doc_type', 'doc_number',
        'first_name', 'last_name', 'email', 'phone', 'whatsapp', 'birthdate',
        'occupation', 'photo', 'notes', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'doc_type'   => 'required|in_list[CC,CE,TI,RC,PA,NIT]',
        'doc_number' => 'required|min_length[3]|max_length[30]',
        'first_name' => 'required|min_length[2]|max_length[60]',
        'last_name'  => 'required|min_length[2]|max_length[60]',
        'email'      => 'permit_empty|valid_email|max_length[120]',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'doc_number' => ['required' => lang('Cop.vPersonDoc')],
            'first_name' => ['required' => lang('Cop.vPersonFirstName')],
            'last_name'  => ['required' => lang('Cop.vPersonLastName')],
            'email'      => ['valid_email' => lang('Cop.vPersonEmail')],
        ];
    }

    /**
     * ¿Ya hay otra persona con ese documento en la empresa?
     *
     * Igual que el código del inmueble: comprobación en el modelo y no índice
     * UNIQUE, por el soft-delete.
     */
    public function docTaken(int $companyId, string $doc, ?int $ignoreId = null): bool
    {
        $q = $this->where('company_id', $companyId)->where('doc_number', $doc);
        if ($ignoreId) {
            $q->where('id !=', $ignoreId);
        }

        return $q->countAllResults() > 0;
    }

    public static function fullName(array $person): string
    {
        return trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
    }

    /** Miembros de un grupo familiar. */
    public function membersOf(int $familyId, int $companyId): array
    {
        return $this->forCompany($companyId)
            ->where('family_id', $familyId)
            ->orderBy('last_name', 'asc')->orderBy('first_name', 'asc')
            ->findAll();
    }

    /** `[id => "Nombre Apellido (CC 123)"]` para los selectores. */
    public function optionsFor(int $companyId): array
    {
        $rows = $this->forCompany($companyId)
            ->orderBy('last_name', 'asc')->orderBy('first_name', 'asc')
            ->findAll();
        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r['id']] = self::fullName($r) . ' (' . $r['doc_type'] . ' ' . $r['doc_number'] . ')';
        }

        return $out;
    }

    /** Búsqueda para el autocompletado del formulario de relaciones. */
    public function search(int $companyId, string $q, int $limit = 15): array
    {
        $q = trim($q);
        $b = $this->forCompany($companyId);

        if ($q !== '') {
            $b->groupStart()
                ->like('first_name', $q)
                ->orLike('last_name', $q)
                ->orLike('doc_number', $q)
                ->groupEnd();
        }

        return $b->orderBy('last_name', 'asc')->findAll($limit);
    }
}
