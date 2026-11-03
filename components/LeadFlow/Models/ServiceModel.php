<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Catálogo de servicios jurídicos (§14).
 *
 * Cada servicio puede definir su formulario, su responsable por defecto, si es
 * prioritario (suma en el scoring) y el **tipo de Matter** de Docketwise. La API
 * v1 no documenta cómo asignar el tipo al crear el Matter, así que se guarda aquí
 * y la conversión lo incluye en el título y la descripción.
 */
class ServiceModel extends Model
{
    use CompanyScoped;

    protected $table          = 'cmp_lfl_services';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'name', 'description', 'is_priority', 'form_id', 'owner_user_id',
        'matter_type_id', 'matter_type_name', 'sort', 'is_active', 'area_id', 'is_public'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|max_length[120]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vName'],
    ];

    /** @return array<int,string> */
    public function options(int $companyId, bool $activeOnly = true): array
    {
        $b = $this->forCompany($companyId)->orderBy('sort', 'asc')->orderBy('name', 'asc');
        if ($activeOnly) {
            $b->where('is_active', 1);
        }

        return array_column($b->findAll(), 'name', 'id');
    }
}
