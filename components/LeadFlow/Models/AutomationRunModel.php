<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Cada vez que una automatización se ejecuta sobre un lead.
 *
 * Sirve para dos cosas: que se pueda auditar qué tocó el sistema solo (y no una
 * persona), y para hacer valer el «una sola vez» por la vía del UNIQUE de `ref`.
 */
class AutomationRunModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_automation_runs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['company_id', 'automation_id', 'lead_id', 'ref', 'status', 'actions', 'message', 'created_at'];

    public function ranAlready(int $automationId, string $ref): bool
    {
        return $this->where('automation_id', $automationId)->where('ref', $ref)->countAllResults() > 0;
    }

    /** Últimas ejecuciones de la empresa, con el nombre de la automatización y del lead. */
    public function recent(int $companyId, int $limit = 40, ?int $automationId = null): array
    {
        $b = $this->select('cmp_lfl_automation_runs.*, a.name AS automation_name, l.first_name, l.last_name')
            ->join('cmp_lfl_automations a', 'a.id = cmp_lfl_automation_runs.automation_id')
            ->join('cmp_lfl_leads l', 'l.id = cmp_lfl_automation_runs.lead_id', 'left')
            ->where('cmp_lfl_automation_runs.company_id', $companyId);
        if ($automationId) {
            $b->where('cmp_lfl_automation_runs.automation_id', $automationId);
        }

        return $b->orderBy('cmp_lfl_automation_runs.id', 'desc')->findAll($limit);
    }
}
