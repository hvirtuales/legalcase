<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Puntos por criterio (§15). Los CRITERIOS son código —cada uno sabe cómo se
 * evalúa— y los PUNTOS son de la firma. Un criterio inventado desde el navegador
 * no llega a guardarse: `ScoringService::CRITERIA` es la lista blanca.
 */
class ScoringRuleModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_scoring_rules';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'criterion', 'points', 'is_active'];

    /** @return array<string,array> criterio => fila */
    public function mapFor(int $companyId): array
    {
        $out = [];
        foreach ($this->forCompany($companyId)->findAll() as $r) {
            $out[$r['criterion']] = $r;
        }

        return $out;
    }
}
