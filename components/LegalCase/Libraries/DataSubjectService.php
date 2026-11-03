<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\ContactModel;
use Components\LegalCase\Models\PortalAccountModel;

/**
 * Derechos del titular sobre sus datos personales (Ley 1581 de 2012, arts. 8, 14 y 15).
 *
 *  · `export()`  — copia de los datos que el despacho tiene de la persona: el contacto, su
 *    autorización, su cuenta del portal y los asuntos en que participa (número y papel, sin el
 *    contenido privilegiado de esos asuntos).
 *  · `anonymize()` — supresión. NO procede mientras exista un deber legal o contractual de
 *    conservar (la persona participa en algún asunto vigente o dentro del plazo de retención):
 *    en ese caso se responde al titular con esa razón. Cuando procede, se sustituyen los datos
 *    identificativos por un marcador y se bloquea su cuenta del portal. Todo queda en la bitácora.
 */
class DataSubjectService
{
    public function export(int $companyId, int $contactId): ?array
    {
        $c = (new ContactModel())->own($companyId, $contactId);
        if (!$c) {
            return null;
        }
        $db    = db_connect();
        $parts = $db->table('cmp_lgc_participants p')
            ->select('m.number, p.role, p.portal_access, p.created_at, m.status')
            ->join('cmp_lgc_matters m', 'm.id = p.matter_id')
            ->where('p.company_id', $companyId)->where('p.contact_id', $contactId)->where('m.deleted_at', null)
            ->get()->getResultArray();
        $acc = (new PortalAccountModel())->where('company_id', $companyId)->where('contact_id', $contactId)->first();

        return [
            'generated_at' => date('c'),
            'controller'   => ['company_id' => $companyId],
            'contact'      => array_intersect_key($c, array_flip(['kind', 'name', 'doc_type', 'doc_number', 'email', 'phone', 'address', 'city', 'country', 'locale', 'legal_capacity', 'created_at', 'updated_at'])),
            'authorization' => array_intersect_key($c, array_flip(['consent_status', 'consent_at', 'consent_method', 'consent_evidence', 'consent_version'])),
            'portal_account' => $acc ? array_intersect_key($acc, array_flip(['email', 'locale', 'status', 'notify_email', 'invited_at', 'last_login_at', 'privacy_version', 'privacy_accepted_at'])) : null,
            'matters'      => $parts,
        ];
    }

    /** Motivo por el que NO procede la supresión, o null si procede. */
    public function retentionReason(int $companyId, int $contactId): ?string
    {
        $years = (int) ((new \Components\LegalCase\Models\SettingsModel())->forCompanyOrDefault($companyId)['retention_years'] ?? 10);
        $limit = date('Y-m-d H:i:s', strtotime('-' . max(1, $years) . ' years'));
        $n = db_connect()->table('cmp_lgc_participants p')
            ->join('cmp_lgc_matters m', 'm.id = p.matter_id')
            ->where('p.company_id', $companyId)->where('p.contact_id', $contactId)->where('m.deleted_at', null)
            ->groupStart()->where('m.status !=', 'closed')->orWhere('m.closed_at >', $limit)->orWhere('m.legal_hold', 1)->groupEnd()
            ->countAllResults();

        return $n > 0 ? lang('Lgc.errAnonymizeRetention', [$n, $years]) : null;
    }

    public function anonymize(int $companyId, int $contactId, Access $acc): Result
    {
        $c = (new ContactModel())->own($companyId, $contactId);
        if (!$c) {
            return Result::fail(lang('Lgc.errNotFound'));
        }
        if ($reason = $this->retentionReason($companyId, $contactId)) {
            return Result::fail($reason);
        }
        $marker = lang('Lgc.anonymized') . ' #' . $contactId;
        (new ContactModel())->skipValidation(true)->update($contactId, [
            'name' => $marker, 'doc_type' => '', 'doc_number' => '', 'email' => '', 'phone' => '', 'address' => '', 'city' => '',
            'notes' => null, 'consent_status' => 'revoked', 'consent_at' => date('Y-m-d H:i:s'), 'consent_evidence' => 'supresion',
        ]);
        $acc2 = new PortalAccountModel();
        foreach ($acc2->where('company_id', $companyId)->where('contact_id', $contactId)->findAll() as $a) {
            (new PortalAccountModel())->skipValidation(true)->update((int) $a['id'], ['status' => 'revoked', 'email' => 'anon-' . (int) $a['id'] . '@invalid.local']);
        }
        Audit::log($companyId, null, 'contact.anonymized', 'contact', $contactId, [], 'user', $acc->userId ?: null);

        return Result::ok(lang('Lgc.okAnonymized'));
    }
}
