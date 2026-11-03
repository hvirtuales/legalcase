<?php

namespace Components\LegalCase\Models;

/** Solicitud de documentos al cliente (§7 F-05). */
class DocRequestModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_doc_requests';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'title', 'instructions', 'due_at', 'status', 'review_note', 'submitted_at', 'reviewed_by', 'reviewed_at', 'created_by', 'account_id'];

    public const STATUSES = ['pending', 'submitted', 'accepted', 'rejected', 'cancelled'];
    public const OPEN     = ['pending', 'submitted', 'rejected'];

    protected $validationRules = [
        'title'  => 'required|max_length[190]',
        'status' => 'required|in_list[pending,submitted,accepted,rejected,cancelled]',
        'due_at' => 'permit_empty|valid_date',
    ];
    protected $validationMessages = ['title' => ['required' => 'Lgc.vTitle']];
}
