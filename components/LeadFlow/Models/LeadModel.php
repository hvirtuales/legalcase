<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * El prospecto. La información jurídica variable NO está aquí: vive en las
 * respuestas de los formularios (`cmp_lfl_submissions`), como pide el §8.
 */
class LeadModel extends Model
{
    use CompanyScoped;

    public const PRIORITIES   = ['low', 'medium', 'high'];
    public const STATUSES     = ['open', 'won', 'lost', 'converted'];
    public const LOST_REASONS = ['not_eligible', 'no_budget', 'chose_competitor', 'no_response', 'not_interested', 'duplicate', 'other'];
    public const LANGUAGES    = ['es', 'en'];

    protected $table          = 'cmp_lfl_leads';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'company_id', 'first_name', 'middle_name', 'last_name', 'email', 'email_norm', 'phone', 'phone_norm',
        'whatsapp', 'whatsapp_norm', 'country', 'city', 'preferred_language', 'service_id', 'source_id',
        'campaign_id', 'form_id', 'assigned_user_id', 'lawyer_user_id', 'stage_id', 'stage_entered_at',
        'priority', 'priority_manual', 'score', 'status', 'lost_reason', 'lost_note', 'potential_value',
        'won_value', 'requested_consultation', 'consent_at', 'consent_ip', 'first_response_at',
        'last_contact_at', 'last_inbound_at', 'next_activity_at', 'won_at', 'converted_at', 'utm_source',
        'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'landing_page', 'referrer', 'summary', 'created_by',
        // De qué perfil del directorio público vino, si vino de uno.
        'profile_id',
        // Número de recibo de USCIS y el último estado conocido. El estado lo
        // escribe `leadflow:tick`, no una pantalla.
        'uscis_receipt', 'uscis_status', 'uscis_checked_at',
    ];

    protected $validationRules = [
        'company_id'      => 'required|is_natural_no_zero',
        'first_name'      => 'required|max_length[80]',
        'last_name'       => 'required|max_length[80]',
        'email'           => 'permit_empty|valid_email|max_length[150]',
        'country'         => 'permit_empty|alpha|exact_length[2]',
        'priority'        => 'permit_empty|in_list[low,medium,high]',
        'status'          => 'permit_empty|in_list[open,won,lost,converted]',
        'potential_value' => 'permit_empty|decimal',
        'won_value'       => 'permit_empty|decimal',
    ];
    protected $validationMessages = [
        'first_name' => ['required' => 'Lfl.vFirstName'],
        'last_name'  => ['required' => 'Lfl.vLastName'],
        'email'      => ['valid_email' => 'Lfl.vEmail'],
    ];

    public static function fullName(array $lead): string
    {
        return trim(preg_replace('/\s+/', ' ', ($lead['first_name'] ?? '') . ' ' . ($lead['middle_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')));
    }
}
