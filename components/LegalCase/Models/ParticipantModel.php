<?php

namespace Components\LegalCase\Models;

/**
 * Partes del asunto. El papel (cliente, representante, contraparte…) es del contacto
 * EN ESE ASUNTO. Solo `client` y `representative` pueden tener acceso al portal:
 * registrar a la contraparte como contacto nunca le da acceso (§6.9).
 */
class ParticipantModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_participants';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'contact_id', 'role', 'portal_access', 'notes'];

    public const ROLES        = ['client', 'representative', 'payer', 'counterparty', 'counsel', 'witness', 'authority', 'other'];
    public const PORTAL_ROLES = ['client', 'representative'];

    protected $validationRules = ['role' => 'required|in_list[client,representative,payer,counterparty,counsel,witness,authority,other]'];

    /** Partes con el contacto unido. */
    public function withContacts(int $matterId): array
    {
        return $this->select('cmp_lgc_participants.*, c.name AS contact_name, c.email AS contact_email, c.phone AS contact_phone')
            ->join('cmp_lgc_contacts c', 'c.id = cmp_lgc_participants.contact_id')
            ->where('cmp_lgc_participants.matter_id', $matterId)
            ->where('c.deleted_at', null)
            ->orderBy('cmp_lgc_participants.role', 'ASC')
            ->findAll();
    }
}
