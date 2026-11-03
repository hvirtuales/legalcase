<?php

namespace Components\Mapas\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table          = 'cmp_map_categories';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['company_id', 'name', 'color', 'icon', 'sort_order', 'is_active'];
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[100]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Map.vrCategory'],
    ];

    /** Las activas de una empresa, en su orden. */
    public function forCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->findAll();
    }

    /**
     * ¿Ya hay otra con ese nombre en la empresa? Sin distinguir mayúsculas ni
     * espacios de sobra: «Comida» y «comida » son la misma para quien la lee.
     */
    public function nameTaken(int $companyId, string $name, ?int $exceptId = null): bool
    {
        $q = $this->where('company_id', $companyId)
            ->where('LOWER(TRIM(name))', mb_strtolower(trim($name)));
        if ($exceptId) {
            $q->where('id !=', $exceptId);
        }

        return $q->countAllResults() > 0;
    }
}
