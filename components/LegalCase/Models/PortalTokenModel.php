<?php

namespace Components\LegalCase\Models;

/** Enlaces de acceso de un solo uso. Solo se guarda el hash del verificador. */
class PortalTokenModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_portal_tokens';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['company_id', 'account_id', 'selector', 'verifier_hash', 'purpose', 'expires_at', 'used_at', 'ip', 'created_at'];

}
