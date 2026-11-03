<?php

namespace Components\LegalCase\Models;

/**
 * Asunto jurídico: la unidad de contratación, trabajo y comunicación.
 *
 * Los campos `description`, `objectives` y `strategy` son INTERNOS y ninguna vista
 * del portal los lee. Lo que el cliente ve del asunto es `public_title`,
 * `public_stage` (si no está oculto) y las actualizaciones publicadas.
 */
class MatterModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_matters';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'number', 'title', 'public_title', 'specialty', 'country', 'jurisdiction', 'status', 'visibility', 'sensitivity', 'risk_level', 'client_contact_id', 'lead_lawyer_id', 'description', 'objectives', 'strategy', 'public_stage', 'stage_hidden', 'opened_at', 'closed_at', 'close_reason', 'next_update_due_at', 'last_published_at', 'created_by', 'legal_hold'];

    public const STATUSES      = ['open', 'on_hold', 'closed'];
    public const VISIBILITIES  = ['firm', 'team'];
    public const SENSITIVITIES = ['normal', 'high'];
    public const RISKS         = ['low', 'medium', 'high'];
    public const SPECIALTIES   = ['civil', 'labor', 'family', 'corporate', 'administrative', 'criminal', 'immigration', 'tax', 'extrajudicial', 'other'];
    public const CLOSE_REASONS = ['won', 'settled', 'lost', 'withdrawn', 'completed', 'other'];

    protected $validationRules = [
        'title'       => 'required|max_length[190]',
        'status'      => 'required|in_list[open,on_hold,closed]',
        'visibility'  => 'required|in_list[firm,team]',
        'sensitivity' => 'required|in_list[normal,high]',
        'risk_level'  => 'required|in_list[low,medium,high]',
        'specialty'   => 'permit_empty|in_list[civil,labor,family,corporate,administrative,criminal,immigration,tax,extrajudicial,other]',
        'country'     => 'permit_empty|alpha|exact_length[2]',
    ];
    protected $validationMessages = [
        'title' => ['required' => 'Lgc.vMatterTitle'],
    ];

    /** Título que ve el cliente: el público si existe; si no, un genérico con el número. */
    public static function clientTitle(array $m): string
    {
        return trim((string) ($m['public_title'] ?? '')) !== ''
            ? (string) $m['public_title']
            : lang('Lgc.matterGeneric', [(string) ($m['number'] ?? '')]);
    }
}
