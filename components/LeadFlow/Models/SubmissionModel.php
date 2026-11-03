<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Respuestas de formulario de un lead (la información jurídica variable, §8).
 *
 * Guarda una **instantánea de las etiquetas** (`labels`): renombrar una pregunta
 * del formulario no reescribe lo que el prospecto ya contestó.
 */
class SubmissionModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_submissions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['company_id', 'lead_id', 'form_id', 'answers', 'labels', 'is_complete', 'channel', 'user_id', 'ip', 'created_at'];

    public function forLead(int $leadId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('lead_id', $leadId)->orderBy('id', 'desc')->findAll();
    }
}
