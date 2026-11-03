<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * La plantilla de requisitos documentales de un servicio.
 *
 * Es lo que convierte el catálogo plano de tipos de documento en algo utilizable: el
 * catálogo dice qué papeles existen, esto dice **cuáles hacen falta para qué trámite y
 * de quién**. Al abrir un expediente se copia a `cmp_lfl_matter_requirements`, que es
 * la que lleva el estado.
 *
 * `applies_to` no es decorativo: en inmigración la mitad de los papeles no son del
 * solicitante sino del cónyuge, del dependiente o del empleador, y sin distinguirlo el
 * checklist se vuelve una lista de nombres repetidos.
 */
class ServiceRequirementModel extends Model
{
    use CompanyScoped;

    /** De quién es el documento. */
    public const APPLIES = ['client', 'spouse', 'dependent', 'employer', 'other'];

    protected $table          = 'cmp_lfl_service_requirements';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'company_id', 'service_id', 'document_type_id', 'label', 'is_required',
        'applies_to', 'notes', 'sort', 'is_active',
    ];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'service_id' => 'required|is_natural_no_zero',
        'label'      => 'required|max_length[120]',
        'applies_to' => 'required|in_list[client,spouse,dependent,employer,other]',
    ];

    protected $validationMessages = [
        'label' => ['required' => 'Lfl.vRequirementLabel'],
    ];

    /** Los requisitos de un servicio, en su orden. */
    public function forService(int $companyId, int $serviceId, bool $activeOnly = false): array
    {
        $b = $this->forCompany($companyId)->where('service_id', $serviceId)
            ->orderBy('sort', 'asc')->orderBy('id', 'asc');
        if ($activeOnly) {
            $b->where('is_active', 1);
        }

        return $b->findAll();
    }

    /** Cuántos requisitos activos tiene cada servicio, para pintarlo en el listado. */
    public function countsByService(int $companyId): array
    {
        $out = [];
        foreach ($this->select('service_id, COUNT(*) AS n')
            ->where('company_id', $companyId)->where('is_active', 1)
            ->groupBy('service_id')->findAll() as $r) {
            $out[(int) $r['service_id']] = (int) $r['n'];
        }

        return $out;
    }
}
