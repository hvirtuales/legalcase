<?php

namespace Components\Paginas\Models;

use CodeIgniter\Model;

/**
 * Bloques personalizados: un fragmento del árbol guardado con nombre para
 * reutilizarlo. Siempre pertenece a una empresa (lleva su marca y sus enlaces).
 */
class BlockModel extends Model
{
    use JsonColumns;

    protected $table            = 'cmp_pag_blocks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = ['company_id', 'user_id', 'name', 'root_type', 'blocks', 'uses'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[120]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre del bloque es obligatorio.'],
    ];

    protected array $jsonColumns = ['blocks'];

    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
    protected $afterFind    = ['decodeJson'];

    /** Los de la empresa, para la paleta del editor y el listado. */
    public function forCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId)->orderBy('name', 'asc')->findAll();
    }
}
