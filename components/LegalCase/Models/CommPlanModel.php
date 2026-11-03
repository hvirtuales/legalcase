<?php

namespace Components\LegalCase\Models;

/** Plan de comunicación del asunto (§6.4). Sus fechas son compromisos de informar, no plazos judiciales. */
class CommPlanModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_comm_plans';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'mode', 'cadence_days', 'response_sla_hours', 'next_update_due_at', 'owner_id', 'is_active', 'notes'];

    public const MODES = ['periodic', 'on_event', 'hybrid', 'on_request'];

    protected $validationRules = [
        'mode'               => 'required|in_list[periodic,on_event,hybrid,on_request]',
        'cadence_days'       => 'required|integer|greater_than[0]|less_than[366]',
        'response_sla_hours' => 'required|integer|greater_than[0]|less_than[721]',
    ];

    /** ¿El modo obliga a informar con una cadencia (aunque no haya novedades)? */
    public static function isPeriodic(string $mode): bool
    {
        return in_array($mode, ['periodic', 'hybrid'], true);
    }
}
