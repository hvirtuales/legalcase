<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Comunicaciones con el prospecto (§25, MVP: llamadas, email y registro de
 * WhatsApp/SMS). La dirección importa: una entrante (`in`) es lo que dice que el
 * prospecto respondió, y eso suma en el scoring.
 */
class CommunicationModel extends Model
{
    use CompanyScoped;

    public const CHANNELS   = ['call', 'email', 'whatsapp', 'sms'];
    public const DIRECTIONS = ['out', 'in'];
    public const OUTCOMES   = ['answered', 'no_answer', 'voicemail', 'wrong_number', 'sent', 'received', 'failed'];

    protected $table         = 'cmp_lfl_communications';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'lead_id', 'channel', 'direction', 'subject', 'body', 'outcome',
        'duration_sec', 'status', 'error', 'created_by'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'lead_id'    => 'required|is_natural_no_zero',
        'channel'    => 'required|in_list[call,email,whatsapp,sms]',
        'direction'  => 'required|in_list[out,in]',
        'subject'    => 'permit_empty|max_length[200]',
    ];

    public function forLead(int $leadId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('lead_id', $leadId)->orderBy('id', 'desc')->findAll();
    }
}
