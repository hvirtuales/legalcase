<?php

namespace Components\Pqrsf\Models;

use CodeIgniter\Model;

/**
 * Punto de publicación: el QR pegado en un área concreta. El token de la URL es
 * aleatorio (no el id) para que los enlaces no se puedan recorrer.
 */
class PointModel extends Model
{
    protected $table            = 'cmp_pqr_points';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = ['company_id', 'name', 'area', 'notes', 'token', 'visits', 'is_active'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[150]',
        'area'       => 'permit_empty|max_length[150]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre del punto es obligatorio.'],
    ];

    /** Puntos activos de la empresa, como `[id => nombre]` para selectores. */
    public function optionsFor(int $companyId): array
    {
        $rows = $this->where('company_id', $companyId)->where('is_active', 1)->orderBy('name', 'asc')->findAll();

        return array_column($rows, 'name', 'id');
    }

    /** Todos (activos o no), para resolver nombres en los listados. */
    public function mapFor(int $companyId): array
    {
        return array_column($this->where('company_id', $companyId)->findAll(), 'name', 'id');
    }
}
