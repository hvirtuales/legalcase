<?php

namespace Components\Paginas\Models;

use CodeIgniter\Model;

/**
 * Plantillas: un árbol de bloques completo del que nace una página nueva.
 *
 * `company_id` NULL = plantilla de arranque (global, la siembra
 * `Libraries\TemplateLibrary`). Con empresa = guardada desde una página propia.
 */
class TemplateModel extends Model
{
    use JsonColumns;

    protected $table            = 'cmp_pag_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'code', 'name', 'description', 'category', 'blocks', 'settings', 'sort_order',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'        => 'required|min_length[2]|max_length[120]',
        'description' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre de la plantilla es obligatorio.'],
    ];

    protected array $jsonColumns = ['blocks', 'settings'];

    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
    protected $afterFind    = ['decodeJson'];

    /**
     * Las que puede usar una empresa: las suyas primero, luego las de arranque.
     * Una sola consulta (`OR`) en vez de dos + merge en PHP.
     */
    public function forCompany(int $companyId): array
    {
        return $this->groupStart()
            ->where('company_id', $companyId)
            ->orWhere('company_id', null)
            ->groupEnd()
            ->orderBy('company_id', 'desc')   // primero las propias
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->findAll();
    }
}
