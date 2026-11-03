<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

class SellerModel extends Model
{
    public const STATUSES = ['activo', 'inactivo'];

    protected $table            = 'cmp_quo_sellers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'name', 'position', 'email', 'phone', 'status', 'card_url',
        'photo', 'signature', 'public_token',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[150]',
        'position'   => 'permit_empty|max_length[120]',
        'email'      => 'permit_empty|valid_email',
        'status'     => 'permit_empty|in_list[activo,inactivo]',
        'card_url'   => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre del vendedor es obligatorio.'],
    ];

    /** Vendedores activos de la empresa, para el selector del cotizador. */
    public function activeFor(int $companyId): array
    {
        return $this->where('company_id', $companyId)
            ->where('status', 'activo')
            ->orderBy('name', 'asc')
            ->findAll();
    }
}
