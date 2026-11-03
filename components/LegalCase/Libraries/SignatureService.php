<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\EngagementModel;
use Components\LegalCase\Models\SignatureModel;

/**
 * Firma electrónica de documentos por el cliente desde el portal.
 *
 * Proveedor `simple`: firma electrónica con evidencia propia (Ley 527 de 1999 y Decreto 2364 de
 * 2012). Su valor probatorio depende de la confiabilidad del método, y por eso se guarda todo lo
 * que lo sostiene:
 *  · el SHA-256 del documento en el momento de pedir la firma, que se vuelve a comprobar contra
 *    el archivo al firmar: si el documento cambió, la firma no procede;
 *  · quién firma (cuenta del portal autenticada con enlace de un solo uso), el nombre y documento
 *    que declara, la fecha, la IP y el agente;
 *  · un hash de evidencia sobre todos esos datos, para detectar cualquier alteración posterior.
 *
 * Para actos que exijan firma digital certificada (o un proveedor como DocuSign), este servicio es
 * el punto de extensión: `provider` distinto de `simple` y un adaptador.
 */
class SignatureService
{
    public function __construct(private ?Outbox $outbox = null)
    {
        $this->outbox ??= new Outbox();
    }

    public function request(array $matter, array $doc, int $accountId, string $purpose, Access $acc): Result
    {
        if (!$acc->can('lgc.share')) {
            return Result::fail(lang('Lgc.errNeedShare'));
        }
        if ($doc['classification'] !== 'shared' || (int) ($doc['is_current'] ?? 1) !== 1) {
            return Result::fail(lang('Lgc.errSignNotShared'));
        }
        $valid = array_map('intval', array_column(Outbox::recipients($acc->companyId, (int) $matter['id']), 'account_id'));
        if (!in_array($accountId, $valid, true)) {
            return Result::fail(lang('Lgc.errRecipient'));
        }
        $model = new SignatureModel();
        if ($model->where('document_id', (int) $doc['id'])->where('account_id', $accountId)->where('status', 'pending')->first()) {
            return Result::fail(lang('Lgc.errSignDuplicate'));
        }
        $model->insert([
            'company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'document_id' => (int) $doc['id'],
            'account_id' => $accountId, 'provider' => 'simple', 'status' => 'pending',
            'purpose' => mb_substr(trim($purpose) ?: (string) $doc['name'], 0, 190),
            'doc_sha256' => (string) $doc['sha256'], 'created_by' => $acc->userId ?: null,
        ]);
        $id = (int) $model->getInsertID();
        Audit::log($acc->companyId, (int) $matter['id'], 'signature.requested', 'signature', $id, ['document' => (int) $doc['id']]);
        $this->outbox->notifyMatter($matter, 'signature', 'signature', $id, [$accountId]);

        return Result::ok(lang('Lgc.okSignRequested'), $id);
    }

    /** Firma del cliente. `$sig` ya viene resuelta para ESTA cuenta (ClientView::signature()). */
    public function sign(array $account, array $sig, string $name, string $docNumber, bool $accept): Result
    {
        if ($sig['status'] !== 'pending') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        $name = trim($name);
        if (!$accept || mb_strlen($name) < 5) {
            return Result::fail(lang('Lgc.vSignName'));
        }
        $doc  = (new DocumentModel())->find((int) $sig['document_id']);
        $path = $doc ? (new DocumentService())->absolutePath($doc) : '';
        // El documento que se firma tiene que ser EXACTAMENTE el que se pidió firmar.
        if (!$doc || !is_file($path) || hash_file('sha256', $path) !== $sig['doc_sha256']) {
            return Result::fail(lang('Lgc.errSignChanged'));
        }

        $req  = service('request');
        $data = [
            'status'      => 'signed',
            'signer_name' => mb_substr($name, 0, 190),
            'signer_doc'  => mb_substr(trim($docNumber), 0, 40),
            'signed_at'   => date('Y-m-d H:i:s'),
            'ip'          => is_cli() ? '' : mb_substr((string) $req->getIPAddress(), 0, 45),
            'user_agent'  => is_cli() ? 'cli' : mb_substr((string) $req->getUserAgent(), 0, 255),
        ];
        $data['evidence_hash'] = self::evidence(array_merge($sig, $data));
        (new SignatureModel())->update((int) $sig['id'], $data);

        // Si es el contrato de servicios del asunto, queda firmado.
        (new EngagementModel())->where('signature_id', (int) $sig['id'])->set(['status' => 'signed'])->update();

        Audit::log((int) $sig['company_id'], (int) $sig['matter_id'], 'signature.signed', 'signature', (int) $sig['id'], ['evidence' => substr($data['evidence_hash'], 0, 16)], 'portal', (int) $account['id']);
        $matter = db_connect()->table('cmp_lgc_matters')->select('lead_lawyer_id')->where('id', (int) $sig['matter_id'])->get()->getRowArray();
        $this->outbox->notifyStaff((int) $sig['company_id'], (int) $sig['matter_id'], (int) ($matter['lead_lawyer_id'] ?? 0) ?: null, 'signature', 'signature', (int) $sig['id']);

        return Result::ok(lang('Lgc.ptSigned'));
    }

    public function decline(array $account, array $sig, string $note): Result
    {
        if ($sig['status'] !== 'pending') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        (new SignatureModel())->update((int) $sig['id'], ['status' => 'declined', 'decline_note' => mb_substr(trim($note), 0, 255)]);
        Audit::log((int) $sig['company_id'], (int) $sig['matter_id'], 'signature.declined', 'signature', (int) $sig['id'], [], 'portal', (int) $account['id']);

        return Result::ok(lang('Lgc.ptSignDeclined'));
    }

    public function cancel(array $sig, Access $acc): Result
    {
        if ($sig['status'] !== 'pending') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        (new SignatureModel())->update((int) $sig['id'], ['status' => 'cancelled']);
        Audit::log($acc->companyId, (int) $sig['matter_id'], 'signature.cancelled', 'signature', (int) $sig['id']);

        return Result::ok(lang('Lgc.okSaved'));
    }

    /** Hash de evidencia: cualquier cambio posterior en estos campos lo invalida. */
    public static function evidence(array $s): string
    {
        $keys = ['id', 'company_id', 'matter_id', 'document_id', 'account_id', 'doc_sha256', 'signer_name', 'signer_doc', 'signed_at', 'ip', 'user_agent'];
        $base = [];
        foreach ($keys as $k) {
            $base[$k] = (string) ($s[$k] ?? '');
        }

        return hash('sha256', json_encode($base, JSON_UNESCAPED_UNICODE));
    }

    /** ¿La evidencia guardada sigue íntegra? */
    public static function verify(array $s): bool
    {
        return $s['status'] === 'signed' && hash_equals((string) $s['evidence_hash'], self::evidence($s));
    }
}
