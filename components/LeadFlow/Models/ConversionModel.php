<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Una conversión por lead (UNIQUE `lead_id`): es la llave de idempotencia del §22.
 */
class ConversionModel extends Model
{
    use CompanyScoped;

    public const STATUSES = ['pending', 'running', 'done', 'failed'];

    protected $table         = 'cmp_lfl_conversions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'lead_id', 'provider', 'status', 'options', 'contact_external_id',
        'matter_external_id', 'attempts', 'last_error', 'requested_by', 'started_at', 'finished_at'];

    public function forLead(int $leadId): ?array
    {
        return $this->where('lead_id', $leadId)->first();
    }
}
