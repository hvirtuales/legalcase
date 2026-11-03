<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\EngagementModel;
use Components\LegalCase\Models\SignatureModel;

/**
 * Contrato de prestación de servicios / carta de encargo / poder del asunto.
 *
 * Registra las condiciones pactadas (alcance, exclusiones, modalidad de honorarios, vigencia) y
 * el documento que las contiene. Enviarlo a firma usa `SignatureService`; al firmarse, el contrato
 * pasa a `signed` solo. Un contrato firmado no se edita: se termina y se registra uno nuevo.
 *
 * No es facturación ni contabilidad de fondos de terceros (eso queda fuera del componente).
 */
class EngagementService
{
    public function current(int $matterId): ?array
    {
        return (new EngagementModel())->where('matter_id', $matterId)->where('status !=', 'terminated')->orderBy('id', 'DESC')->first();
    }

    public function save(array $matter, array $in, Access $acc): Result
    {
        $model = new EngagementModel();
        $row   = $this->current((int) $matter['id']);
        if ($row && $row['status'] === 'signed') {
            return Result::fail(lang('Lgc.errEngagementSigned'));
        }
        $docId = (int) ($in['document_id'] ?? 0);
        if ($docId && !(new DocumentModel())->where('matter_id', (int) $matter['id'])->find($docId)) {
            return Result::fail(lang('Lgc.errAuthorityDoc'));
        }
        $date = static fn ($v) => trim((string) $v) !== '' && strtotime((string) $v) ? date('Y-m-d', strtotime((string) $v)) : null;
        $data = [
            'kind'            => (string) ($in['kind'] ?? 'services_contract'),
            'fee_type'        => (string) ($in['fee_type'] ?? 'fixed'),
            'amount'          => is_numeric($in['amount'] ?? null) ? (string) $in['amount'] : '0',
            'currency'        => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($in['currency'] ?? 'COP')), 0, 3)) ?: 'COP',
            'contingency_pct' => is_numeric($in['contingency_pct'] ?? null) ? (string) $in['contingency_pct'] : '0',
            'scope'           => trim((string) ($in['scope'] ?? '')),
            'exclusions'      => trim((string) ($in['exclusions'] ?? '')),
            'starts_on'       => $date($in['starts_on'] ?? ''),
            'ends_on'         => $date($in['ends_on'] ?? ''),
            'document_id'     => $docId ?: null,
            'status'          => $row['status'] ?? 'draft',
        ];
        if ($row) {
            $ok = $model->update((int) $row['id'], $data);
            $id = (int) $row['id'];
        } else {
            $data += ['company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'created_by' => $acc->userId ?: null];
            $ok = $model->insert($data);
            $id = (int) $model->getInsertID();
        }
        if (!$ok) {
            return Result::fail(implode(' ', $model->errors()));
        }
        Audit::log($acc->companyId, (int) $matter['id'], 'engagement.saved', 'engagement', $id, ['fee' => $data['fee_type']]);

        return Result::ok(lang('Lgc.okSaved'), $id);
    }

    /** Envía el documento del contrato a firma del cliente indicado. */
    public function sendForSignature(array $matter, int $accountId, Access $acc): Result
    {
        $row = $this->current((int) $matter['id']);
        if (!$row || !$row['document_id']) {
            return Result::fail(lang('Lgc.errEngagementDoc'));
        }
        $doc = (new DocumentModel())->find((int) $row['document_id']);
        if (!$doc) {
            return Result::fail(lang('Lgc.errEngagementDoc'));
        }
        $r = (new SignatureService())->request($matter, $doc, $accountId, lang('Lgc.engkind_' . $row['kind']), $acc);
        if ($r->ok) {
            (new EngagementModel())->update((int) $row['id'], ['status' => 'sent', 'signature_id' => $r->id]);
        }

        return $r;
    }

    public function terminate(array $matter, Access $acc): Result
    {
        $row = $this->current((int) $matter['id']);
        if (!$row) {
            return Result::fail(lang('Lgc.errNotFound'));
        }
        (new EngagementModel())->update((int) $row['id'], ['status' => 'terminated']);
        if ($row['signature_id']) {
            (new SignatureModel())->where('id', (int) $row['signature_id'])->where('status', 'pending')->set(['status' => 'cancelled'])->update();
        }
        Audit::log($acc->companyId, (int) $matter['id'], 'engagement.terminated', 'engagement', (int) $row['id']);

        return Result::ok(lang('Lgc.okEngagementTerminated'));
    }
}
