<?php

namespace Components\LegalCase\Models;

/** Audiencias y diligencias del asunto. */
class HearingModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_hearings';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'proceeding_id', 'title', 'scheduled_at', 'duration_min', 'mode', 'location', 'link', 'client_attendance', 'is_shared', 'client_notes', 'internal_notes', 'status', 'created_by'];

    public const MODES    = ['in_person', 'virtual', 'hybrid'];
    public const STATUSES = ['scheduled', 'held', 'postponed', 'cancelled'];

    protected $validationRules = [
        'title'        => 'required|max_length[190]',
        'scheduled_at' => 'required|valid_date',
        'mode'         => 'required|in_list[in_person,virtual,hybrid]',
        'status'       => 'required|in_list[scheduled,held,postponed,cancelled]',
        'link'         => 'permit_empty|valid_url_strict[https]|max_length[500]',
    ];
    protected $validationMessages = [
        'title'        => ['required' => 'Lgc.vTitle'],
        'scheduled_at' => ['required' => 'Lgc.vDueAt', 'valid_date' => 'Lgc.vDueAt'],
        'link'         => ['valid_url_strict' => 'Lgc.vHttpsLink'],
    ];
}
