<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

/** Vitrina pública: qué productos se muestran, con qué valor y quién la comparte. */
class CatalogModel extends Model
{
    protected $table            = 'cmp_quo_catalogs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'name', 'headline', 'intro', 'price_tier', 'show_prices', 'is_published',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[150]',
        'headline'   => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre del catálogo es obligatorio.'],
    ];
}
