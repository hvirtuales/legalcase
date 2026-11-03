<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Documentos: tabla polimórfica (`owner_type` + `owner_id`).
 *
 * `OWNER_TYPES` es **lista blanca cerrada**, y no por pulcritud: `owner_type` viaja
 * en el formulario, y `DocumentService` lo usa para decidir en qué tabla comprobar
 * que el dueño es de la empresa activa. Un valor inventado tiene que caer fuera
 * antes de llegar a esa decisión.
 */
class DocumentModel extends Model
{
    use CompanyScoped;

    public const OWNER_TYPES = ['unit', 'person', 'vehicle', 'pet', 'authorization'];

    /** Tipos de documento que se manejan en una copropiedad. */
    public const KINDS = [
        'escritura', 'certificado_tradicion', 'contrato_arriendo', 'documento_identidad',
        'rut', 'paz_y_salvo', 'poder', 'reglamento', 'acta', 'vacunas', 'soat',
        'tecnicomecanica', 'poliza', 'otro',
    ];

    /** Qué tabla respalda cada `owner_type` (para comprobar la pertenencia). */
    public const OWNER_TABLES = [
        'unit'          => 'cmp_cop_units',
        'person'        => 'cmp_cop_people',
        'vehicle'       => 'cmp_cop_vehicles',
        'pet'           => 'cmp_cop_pets',
        'authorization' => 'cmp_cop_authorizations',
    ];

    protected $table            = 'cmp_cop_documents';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'owner_type', 'owner_id', 'kind', 'title', 'file_path',
        'original_name', 'mime', 'size', 'issued_at', 'expires_at', 'uploaded_by', 'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'owner_type' => 'required|in_list[unit,person,vehicle,pet,authorization]',
        'owner_id'   => 'required|is_natural_no_zero',
        'title'      => 'required|min_length[2]|max_length[140]',
        'file_path'  => 'required|max_length[255]',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'title'      => ['required' => lang('Cop.vDocTitle')],
            'owner_type' => ['in_list' => lang('Cop.vDocOwner')],
        ];
    }

    /** Los documentos de un dueño concreto. */
    public function forOwner(string $type, int $id, int $companyId): array
    {
        if (! in_array($type, self::OWNER_TYPES, true)) {
            return [];
        }

        return $this->forCompany($companyId)
            ->where('owner_type', $type)
            ->where('owner_id', $id)
            ->orderBy('created_at', 'desc')
            ->findAll();
    }

    /**
     * Lo que vence pronto o ya venció: SOAT, pólizas, contratos, carnés.
     *
     * Es la mitad del valor de guardar la fecha de vencimiento — la otra mitad es que
     * alguien la vea a tiempo.
     */
    public function expiring(int $companyId, int $days = 30): array
    {
        $limit = date('Y-m-d', strtotime('+' . $days . ' days'));

        return $this->forCompany($companyId)
            ->where('expires_at IS NOT NULL')
            ->where('expires_at <=', $limit)
            ->orderBy('expires_at', 'asc')
            ->findAll(20);
    }
}
