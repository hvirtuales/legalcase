<?php

namespace Components\LegalCase\Models;

/** Conversación por asunto con estado de ticket (§6.8). */
class ThreadModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_threads';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'subject', 'status', 'priority', 'assigned_to', 'opened_by_account', 'opened_by_user', 'first_response_due_at', 'first_response_at', 'last_message_at', 'closed_at'];

    public const STATUSES = ['new', 'assigned', 'waiting_client', 'answered', 'closed'];
    public const OPEN     = ['new', 'assigned', 'waiting_client'];
    public const PRIORITIES = ['normal', 'high'];

    protected $validationRules = [
        'subject'  => 'required|max_length[190]',
        'status'   => 'required|in_list[new,assigned,waiting_client,answered,closed]',
        'priority' => 'required|in_list[normal,high]',
    ];
    protected $validationMessages = ['subject' => ['required' => 'Lgc.vSubject']];
}
