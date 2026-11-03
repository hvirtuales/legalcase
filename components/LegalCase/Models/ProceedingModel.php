<?php

namespace Components\LegalCase\Models;

/** Proceso judicial o administrativo de un asunto, con radicado y autoridad. */
class ProceedingModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_proceedings';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'kind', 'filing_number', 'authority', 'jurisdiction', 'procedure_type', 'stage', 'status', 'started_on', 'ended_on', 'notes', 'created_by'];

    public const KINDS    = ['judicial', 'administrative', 'arbitration', 'other'];
    public const STATUSES = ['active', 'suspended', 'finished'];

    protected $validationRules = [
        'kind'          => 'required|in_list[judicial,administrative,arbitration,other]',
        'status'        => 'required|in_list[active,suspended,finished]',
        'filing_number' => 'permit_empty|max_length[60]',
        'authority'     => 'permit_empty|max_length[190]',
    ];
}
