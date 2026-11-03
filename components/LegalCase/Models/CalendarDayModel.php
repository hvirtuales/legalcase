<?php

namespace Components\LegalCase\Models;

/** Días no hábiles propios del despacho (cierres, suspensiones de términos). */
class CalendarDayModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_calendar_days';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['company_id', 'day', 'kind', 'name', 'created_by', 'created_at'];

    public const KINDS = ['closure', 'suspension', 'holiday'];

    protected $validationRules = [
        'day'  => 'required|valid_date[Y-m-d]',
        'kind' => 'required|in_list[closure,suspension,holiday]',
        'name' => 'required|max_length[120]',
    ];
}
