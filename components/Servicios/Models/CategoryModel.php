<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

/** Categorías del catálogo, propias de cada empresa. */
class CategoryModel extends Model
{
    protected $table            = 'cmp_quo_categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'name', 'description', 'sort_order', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id'  => 'required|is_natural_no_zero',
        'name'        => 'required|min_length[2]|max_length[120]',
        'description' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre de la categoría es obligatorio.'],
    ];

    /**
     * Categorías activas de la empresa, listas para un `<select>`
     * (`[id => nombre]`). El orden lo fija `sort_order` y, a igualdad, el nombre.
     */
    public function optionsFor(int $companyId): array
    {
        $rows = $this->where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->findAll();

        return array_column($rows, 'name', 'id');
    }

    /** Todas las de la empresa (activas o no), para resolver nombres en listados. */
    public function mapFor(int $companyId): array
    {
        return array_column($this->where('company_id', $companyId)->findAll(), 'name', 'id');
    }

    /**
     * ¿Ya existe una categoría con ese nombre en la empresa? Evita el catálogo
     * lleno de «Servicios», «servicios» y «SERVICIOS».
     */
    public function nameTaken(int $companyId, string $name, ?int $exceptId = null): bool
    {
        // La comparación sin distinguir mayúsculas se hace en PHP: `LOWER()` en el
        // WHERE obligaría a desactivar el escapado del Query Builder (y son pocas
        // categorías por empresa).
        $needle = mb_strtolower(trim($name));

        foreach ($this->where('company_id', $companyId)->findAll() as $row) {
            if ((int) $row['id'] !== (int) $exceptId && mb_strtolower((string) $row['name']) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** Cuántos productos hay clasificados en cada categoría de la empresa. */
    public function productCounts(int $companyId): array
    {
        $rows = $this->db->table('cmp_quo_products')
            ->select('category_id, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('category_id IS NOT NULL')
            ->where('deleted_at IS NULL')
            ->groupBy('category_id')
            ->get()->getResultArray();

        return array_column($rows, 'total', 'category_id');
    }
}
