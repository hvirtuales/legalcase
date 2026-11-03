<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * La línea de tiempo del lead (§12). Solo se escribe desde `Timeline::log()`,
 * que es quien la llama cada servicio: así ningún cambio se queda sin rastro
 * porque alguien olvidó registrarlo en un controlador.
 */
class TimelineModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_timeline';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['company_id', 'lead_id', 'type', 'summary', 'meta', 'user_id', 'created_at'];

    public function forLead(int $leadId, int $companyId, int $limit = 200): array
    {
        return $this->select('cmp_lfl_timeline.*, users.first_name AS u_first, users.last_name AS u_last, users.username AS u_name')
            ->join('users', 'users.id = cmp_lfl_timeline.user_id', 'left')
            ->where('cmp_lfl_timeline.company_id', $companyId)
            ->where('cmp_lfl_timeline.lead_id', $leadId)
            ->orderBy('cmp_lfl_timeline.id', 'desc')
            ->findAll($limit);
    }
}
