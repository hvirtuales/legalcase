<?php

namespace Components\LegalCase\Models;

/** Cuenta del portal del cliente. Propia del componente; no es un usuario del núcleo. */
class PortalAccountModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_portal_accounts';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'contact_id', 'email', 'locale', 'status', 'notify_email', 'invited_by', 'invited_at', 'last_login_at', 'privacy_version', 'privacy_accepted_at', 'privacy_ip'];

    public const STATUSES = ['active', 'revoked'];

    protected $validationRules = [
        'email'  => 'required|valid_email|max_length[190]',
        'status' => 'required|in_list[active,revoked]',
        'locale' => 'permit_empty|in_list[es,en]',
    ];
    protected $validationMessages = ['email' => ['required' => 'Lgc.vEmail', 'valid_email' => 'Lgc.vEmail']];
}
