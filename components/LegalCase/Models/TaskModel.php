<?php

namespace Components\LegalCase\Models;

/** Tarea del asunto. `kind=update_due` la crea el reloj cuando toca informar al cliente. */
class TaskModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_tasks';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'kind', 'title', 'detail', 'due_at', 'assigned_to', 'status', 'ref', 'done_at', 'created_by'];

    public const KINDS    = ['task', 'update_due', 'doc_review', 'message'];
    public const STATUSES = ['open', 'done', 'cancelled'];

    protected $validationRules = [
        'title'  => 'required|max_length[190]',
        'kind'   => 'required|in_list[task,update_due,doc_review,message]',
        'status' => 'required|in_list[open,done,cancelled]',
        'due_at' => 'permit_empty|valid_date',
    ];
    protected $validationMessages = ['title' => ['required' => 'Lgc.vTitle']];
}
