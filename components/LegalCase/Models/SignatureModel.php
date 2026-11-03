<?php

namespace Components\LegalCase\Models;

/** Solicitudes de firma electrónica con su evidencia. La evidencia de una firma hecha no se modifica. */
class SignatureModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_signatures';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'document_id', 'account_id', 'provider', 'status', 'purpose', 'doc_sha256', 'signer_name', 'signer_doc', 'signed_at', 'ip', 'user_agent', 'evidence_hash', 'decline_note', 'created_by'];

    public const STATUSES = ['pending', 'signed', 'declined', 'cancelled'];
}
