<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    public const DISCOUNT_TYPES = ['%', '$'];

    protected $table            = 'cmp_quo_products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'category_id', 'code', 'name', 'description', 'photo', 'unit_price', 'default_quantity',
        'discount_type', 'discount_value', 'tax_percent', 'details_url', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id'    => 'required|is_natural_no_zero',
        'category_id'   => 'permit_empty|is_natural_no_zero',
        'code'          => 'required|max_length[60]',
        'name'          => 'required|min_length[2]|max_length[200]',
        'unit_price'    => 'permit_empty|decimal',
        'discount_type' => 'permit_empty|in_list[%,$]',
        'details_url'   => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'code' => ['required' => 'El código es obligatorio.'],
        'name' => ['required' => 'El nombre del producto es obligatorio.'],
    ];

    /**
     * ¿Ya existe ese código en la empresa? (la unicidad es por empresa, así que no
     * sirve `is_unique` a secas).
     */
    public function codeTaken(int $companyId, string $code, ?int $exceptId = null): bool
    {
        $b = $this->where('company_id', $companyId)->where('code', $code);
        if ($exceptId) {
            $b->where('id !=', $exceptId);
        }

        return $b->countAllResults() > 0;
    }
}
