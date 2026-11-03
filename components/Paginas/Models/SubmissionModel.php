<?php

namespace Components\Paginas\Models;

use CodeIgniter\Model;

/**
 * Mensajes recibidos por el bloque «formulario de contacto».
 *
 * `data` es un mapa etiqueta → valor con **solo** los campos que el bloque
 * declaró (lo filtra `PageService::collectSubmission()`): lo que llegue de más
 * en el POST no se guarda.
 */
class SubmissionModel extends Model
{
    use JsonColumns;

    protected $table            = 'cmp_pag_submissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'page_id', 'block_uid', 'form_title', 'data', 'ip', 'is_read',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'page_id'    => 'required|is_natural_no_zero',
    ];

    protected array $jsonColumns = ['data'];

    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
    protected $afterFind    = ['decodeJson'];

    /** Mensajes sin leer de la empresa (badge del menú y del listado). */
    public function unreadCount(int $companyId): int
    {
        return $this->where('company_id', $companyId)->where('is_read', 0)->countAllResults();
    }
}
