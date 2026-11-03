<?php

namespace Components\LegalCase\Models;

/** Configuración por despacho. Una fila por empresa; se crea con valores por defecto al primer uso. */
class SettingsModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_settings';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_prefix', 'matter_sequence', 'approval_policy', 'deadline_policy', 'default_cadence_days', 'response_sla_hours', 'portal_enabled', 'notify_email', 'portal_help'];

    public const APPROVAL_POLICIES = ['self', 'reviewer'];
    public const DEADLINE_POLICIES = ['single', 'dual'];

    protected $validationRules = [
        'approval_policy'      => 'permit_empty|in_list[self,reviewer]',
        'deadline_policy'      => 'permit_empty|in_list[single,dual]',
        'default_cadence_days' => 'permit_empty|integer|greater_than[0]|less_than[366]',
        'response_sla_hours'   => 'permit_empty|integer|greater_than[0]|less_than[721]',
        'matter_prefix'        => 'permit_empty|alpha_numeric|max_length[10]',
    ];

    /** La configuración de la empresa, creándola con los valores por defecto si no existe. */
    public function forCompanyOrDefault(int $companyId): array
    {
        $row = $this->where('company_id', $companyId)->first();
        if ($row) {
            return $row;
        }
        $this->insert(['company_id' => $companyId]);

        return $this->where('company_id', $companyId)->first() ?? ['company_id' => $companyId];
    }
}
