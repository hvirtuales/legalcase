<?php

namespace Components\LegalCase\Models;

/** Contrato de prestación de servicios / carta de encargo / poder del asunto. */
class EngagementModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_engagements';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'kind', 'fee_type', 'amount', 'currency', 'contingency_pct', 'scope', 'exclusions', 'starts_on', 'ends_on', 'status', 'document_id', 'signature_id', 'created_by'];

    public const KINDS     = ['services_contract', 'engagement_letter', 'power_of_attorney'];
    public const FEE_TYPES = ['fixed', 'hourly', 'contingency', 'mixed', 'pro_bono'];
    public const STATUSES  = ['draft', 'sent', 'signed', 'terminated'];

    protected $validationRules = [
        'kind'            => 'required|in_list[services_contract,engagement_letter,power_of_attorney]',
        'fee_type'        => 'required|in_list[fixed,hourly,contingency,mixed,pro_bono]',
        'status'          => 'required|in_list[draft,sent,signed,terminated]',
        'amount'          => 'permit_empty|decimal',
        'contingency_pct' => 'permit_empty|decimal|less_than_equal_to[100]',
        'currency'        => 'permit_empty|alpha|exact_length[3]',
        'scope'           => 'required',
    ];
    protected $validationMessages = ['scope' => ['required' => 'Lgc.vScope']];
}
