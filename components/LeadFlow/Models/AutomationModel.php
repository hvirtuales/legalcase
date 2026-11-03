<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Una automatización: disparador, condiciones y acciones (§19).
 *
 * La columna se llama `trigger_event` y no `trigger` porque TRIGGER es palabra
 * reservada de SQL en varios motores, y el componente tiene que ser portable.
 */
class AutomationModel extends Model
{
    use CompanyScoped;

    protected $table          = 'cmp_lfl_automations';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'name', 'trigger_event', 'match_mode', 'conditions', 'actions',
        'once_per_lead', 'is_active', 'sort', 'rr_index', 'runs', 'last_run_at', 'created_by'];

    protected $validationRules = [
        'company_id'    => 'required|is_natural_no_zero',
        'name'          => 'required|max_length[120]',
        'trigger_event' => 'required|max_length[30]',
        'match_mode'    => 'permit_empty|in_list[all,any]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vName'],
    ];

    /** Las activas de un disparador, en su orden. */
    public function activeFor(int $companyId, string $trigger): array
    {
        return $this->forCompany($companyId)
            ->where('trigger_event', $trigger)
            ->where('is_active', 1)
            ->orderBy('sort', 'asc')->orderBy('id', 'asc')
            ->findAll();
    }

    public static function conditions(?array $row): array
    {
        $d = json_decode((string) ($row['conditions'] ?? ''), true);

        return is_array($d) ? $d : [];
    }

    public static function actions(?array $row): array
    {
        $d = json_decode((string) ($row['actions'] ?? ''), true);

        return is_array($d) ? $d : [];
    }
}
