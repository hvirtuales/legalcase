<?php

namespace Components\LegalCase\Models;

/** Documento del asunto. Solo `classification = shared` se ve en el portal. */
class DocumentModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_documents';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'request_id', 'name', 'path', 'mime', 'size', 'sha256', 'classification', 'source', 'uploaded_by', 'uploaded_by_account', 'shared_by', 'shared_at', 'version', 'previous_id', 'is_current'];

    public const CLASSIFICATIONS = ['internal', 'privileged', 'review', 'shared'];

    /** Documentos vigentes (última versión) de un asunto. */
    public function currentFor(int $matterId): array
    {
        return $this->where('matter_id', $matterId)->where('is_current', 1)->orderBy('created_at', 'DESC')->findAll(300);
    }

    protected $validationRules = ['classification' => 'required|in_list[internal,privileged,review,shared]'];
}
