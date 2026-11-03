<?php

namespace Components\LegalCase\Models;

/**
 * Plazo o término. Se registra A MANO y nace `to_validate`: el sistema no calcula
 * plazos judiciales y ninguna fecha se presenta como confirmada antes de validarse
 * (§5 LC-05, §6.13-8).
 */
class DeadlineModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_deadlines';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'proceeding_id', 'event_id', 'title', 'basis', 'starts_on', 'due_at', 'is_critical', 'status', 'assigned_to', 'validated_by', 'validated_at', 'second_validated_by', 'second_validated_at', 'done_at', 'created_by'];

    public const STATUSES = ['to_validate', 'validated', 'done', 'cancelled'];
    public const OPEN     = ['to_validate', 'validated'];

    protected $validationRules = [
        'title'  => 'required|max_length[190]',
        'due_at' => 'required|valid_date',
        'status' => 'required|in_list[to_validate,validated,done,cancelled]',
    ];
    protected $validationMessages = [
        'title'  => ['required' => 'Lgc.vTitle'],
        'due_at' => ['required' => 'Lgc.vDueAt', 'valid_date' => 'Lgc.vDueAt'],
    ];
}
