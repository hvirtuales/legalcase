<?php

namespace Components\LegalCase\Models;

/** Actuación o evento del asunto, con su fuente. Es INTERNO: lo que llega al cliente es la actualización redactada a partir de él. */
class EventModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_events';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'proceeding_id', 'occurred_at', 'kind', 'title', 'detail', 'source', 'source_ref', 'is_relevant', 'created_by'];

    public const KINDS = ['filing', 'ruling', 'hearing', 'notification', 'meeting', 'internal', 'other'];

    protected $validationRules = [
        'title' => 'required|max_length[190]',
        'kind'  => 'required|in_list[filing,ruling,hearing,notification,meeting,internal,other]',
    ];
    protected $validationMessages = ['title' => ['required' => 'Lgc.vTitle']];
}
