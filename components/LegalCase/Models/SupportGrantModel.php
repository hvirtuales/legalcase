<?php

namespace Components\LegalCase\Models;

/** Accesos de soporte de la plataforma concedidos por el despacho, con caducidad. */
class SupportGrantModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_support_grants';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['company_id', 'user_id', 'reason', 'granted_by', 'expires_at', 'revoked_at', 'created_at'];

    /** El acceso vigente de ese usuario en esa empresa, o null. */
    public function activeFor(int $companyId, int $userId): ?array
    {
        return $this->where('company_id', $companyId)->where('user_id', $userId)->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))->orderBy('expires_at', 'DESC')->first();
    }

    public function listFor(int $companyId): array
    {
        return $this->where('company_id', $companyId)->orderBy('id', 'DESC')->findAll(50);
    }
}
