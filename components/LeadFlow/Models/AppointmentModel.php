<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/** Agenda de consultas (§18). */
class AppointmentModel extends Model
{
    use CompanyScoped;

    public const MODES    = ['video', 'phone', 'in_person'];
    public const STATUSES = ['scheduled', 'confirmed', 'completed', 'no_show', 'cancelled'];
    public const RESULTS  = ['likely_hire', 'follow_up', 'pending_docs', 'not_eligible', 'not_interested', 'escalate'];

    /** Estados que cuentan como «consulta programada» para el scoring. */
    public const ACTIVE = ['scheduled', 'confirmed', 'completed'];

    protected $table          = 'cmp_lfl_appointments';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'lead_id', 'lawyer_user_id', 'scheduled_at', 'duration_min', 'mode',
        'location', 'status', 'result', 'notes', 'reschedule_count', 'created_by'];

    protected $validationRules = [
        'company_id'   => 'required|is_natural_no_zero',
        'lead_id'      => 'required|is_natural_no_zero',
        'scheduled_at' => 'required|valid_date[Y-m-d H:i:s]',
        'duration_min' => 'permit_empty|integer|greater_than[0]|less_than_equal_to[480]',
        'mode'         => 'required|in_list[video,phone,in_person]',
        'status'       => 'permit_empty|in_list[scheduled,confirmed,completed,no_show,cancelled]',
    ];
    protected $validationMessages = [
        'scheduled_at' => ['required' => 'Lfl.vApptDate', 'valid_date' => 'Lfl.vApptDate'],
    ];

    public function forLead(int $leadId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('lead_id', $leadId)->orderBy('scheduled_at', 'desc')->findAll();
    }
}
