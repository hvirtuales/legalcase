<?php

namespace Components\Formularios\Models;

use CodeIgniter\Model;

/**
 * Plantillas: las de arranque (`company_id` NULL, globales) y las que cada
 * empresa guarda desde sus propios formularios.
 */
class TemplateModel extends Model
{
    use JsonColumns;

    protected $table            = 'cmp_frm_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'code', 'name', 'description', 'category', 'schema_json', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[180]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre de la plantilla es obligatorio.'],
    ];

    protected array $jsonColumns = ['schema_json'];

    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
    protected $afterFind    = ['decodeJson'];

    /** Las que puede usar una empresa: las suyas y las de arranque. */
    public function available(int $companyId): array
    {
        return $this->groupStart()
            ->where('company_id', $companyId)
            ->orWhere('company_id', null)
            ->groupEnd()
            ->where('is_active', 1)
            ->orderBy('company_id', 'asc')
            ->orderBy('name', 'asc')
            ->findAll();
    }

    /** Una que la empresa pueda usar (suya o global), o null. */
    public function usableBy(int $id, int $companyId): ?array
    {
        $row = $this->find($id);
        if (!$row || (int) $row['is_active'] !== 1) {
            return null;
        }
        $owner = $row['company_id'] === null ? null : (int) $row['company_id'];

        return ($owner === null || $owner === $companyId) ? $row : null;
    }
}
