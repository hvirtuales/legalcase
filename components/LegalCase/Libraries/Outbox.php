<?php

namespace Components\LegalCase\Libraries;

use App\Libraries\CompanyMailer;
use App\Models\CompanyModel;
use Components\LegalCase\Models\DeliveryModel;
use Components\LegalCase\Models\SettingsModel;

/**
 * Cola de salida de avisos al cliente (outbox, §6.7, §6.13-3/5).
 *
 *  · El aviso es MÍNIMO: «tiene una novedad, ingrese al portal». El contenido sustantivo
 *    solo se lee tras autenticarse; el correo nunca lleva estrategia, plazos ni texto
 *    de la actualización.
 *  · Cada aviso tiene una clave de idempotencia ÚNICA (`kind:ref_type:ref_id:cuenta`):
 *    publicar dos veces, o reintentar tras un fallo, no envía dos correos.
 *  · Se intenta en el momento; si el proveedor falla queda `failed` con su error y el
 *    reloj (`lgc:tick`) reintenta hasta `MAX_ATTEMPTS`. El incidente se ve en la
 *    pestaña Comunicación del asunto.
 *  · «Enviado» es lo que el proveedor aceptó. No se registra «leído» ni «comprendido»:
 *    el proveedor no lo reporta y el documento pide no confundirlos.
 */
class Outbox
{
    /** @var callable|null fn(int $companyId, string $to, string $subject, string $html): array{ok:bool,message:string} */
    private $sender;

    public function __construct(?callable $sender = null)
    {
        $this->sender = $sender;
    }

    /** Destinatarios del portal con acceso vigente a un asunto. */
    public static function recipients(int $companyId, int $matterId): array
    {
        return db_connect()->table('cmp_lgc_participants p')
            ->select('a.id AS account_id, a.email, a.locale, a.notify_email')
            ->join('cmp_lgc_portal_accounts a', 'a.contact_id = p.contact_id AND a.company_id = p.company_id')
            ->where('p.company_id', $companyId)
            ->where('p.matter_id', $matterId)
            ->where('p.portal_access', 1)
            ->where('a.status', 'active')
            ->groupBy('a.id, a.email, a.locale, a.notify_email')
            ->get()->getResultArray();
    }

    /**
     * Encola (y despacha) el aviso para todas las cuentas con acceso al asunto.
     *
     * @return int avisos nuevos encolados
     */
    public function notifyMatter(array $matter, string $kind, string $refType, int $refId, ?array $onlyAccounts = null): int
    {
        $companyId = (int) $matter['company_id'];
        $settings  = (new SettingsModel())->forCompanyOrDefault($companyId);
        $model     = new DeliveryModel();
        $ids       = [];

        foreach (self::recipients($companyId, (int) $matter['id']) as $r) {
            // Aviso dirigido (hilo o solicitud de una persona): solo a ella, no a los coclientes.
            if ($onlyAccounts !== null && !in_array((int) $r['account_id'], array_map('intval', $onlyAccounts), true)) {
                continue;
            }
            $key = $kind . ':' . $refType . ':' . $refId . ':' . $r['account_id'];
            if ($model->where('idem_key', $key)->countAllResults() > 0) {
                continue;
            }
            $optedOut = !(int) $settings['notify_email'] || !(int) $r['notify_email'];
            $model->insert([
                'company_id' => $companyId,
                'matter_id'  => (int) $matter['id'],
                'account_id' => (int) $r['account_id'],
                'channel'    => 'email',
                'kind'       => $kind,
                'ref_type'   => $refType,
                'ref_id'     => $refId,
                'idem_key'   => $key,
                'recipient'  => $r['email'],
                // Sin consentimiento para avisos opcionales: queda constancia, no se envía.
                // La actualización sigue disponible en el portal.
                'status'     => $optedOut ? 'skipped' : 'pending',
            ]);
            if (!$optedOut) {
                $ids[] = (int) $model->getInsertID();
            }
        }

        foreach ($ids as $id) {
            $this->dispatchOne($id);
        }

        return count($ids);
    }

    /**
     * Aviso INTERNO a una persona del despacho (plazo próximo, mensaje del cliente, entrega).
     * Mismo outbox e idempotencia; sin cuenta del portal. `$window` distingue avisos repetidos
     * sobre el mismo registro (p. ej. plazo a 7, 3 y 1 días).
     */
    public function notifyStaff(int $companyId, ?int $matterId, ?int $userId, string $kind, string $refType, int $refId, string $window = ''): bool
    {
        if (!$userId || !Team::isMember($companyId, $userId)) {
            return false;
        }
        $user = (new \App\Models\UserModel())->find($userId);
        if (!$user || !filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $key   = 'staff_' . $kind . ':' . $refType . ':' . $refId . ':' . $userId . ($window !== '' ? ':' . $window : '');
        $model = new DeliveryModel();
        if ($model->where('idem_key', $key)->countAllResults() > 0) {
            return false;
        }
        $model->insert([
            'company_id' => $companyId, 'matter_id' => $matterId, 'account_id' => null, 'channel' => 'email',
            'kind' => 'staff_' . $kind, 'ref_type' => $refType, 'ref_id' => $refId, 'idem_key' => $key,
            'recipient' => $user['email'], 'status' => 'pending',
        ]);
        $this->dispatchOne((int) $model->getInsertID());

        return true;
    }

    /** Reintenta lo pendiente o fallido. @return array{sent:int,failed:int,skipped:int} */
    public function dispatch(int $limit = 50, ?int $companyId = null): array
    {
        $q = (new DeliveryModel())->whereIn('status', ['pending', 'failed'])
            ->where('attempts <', DeliveryModel::MAX_ATTEMPTS);
        if ($companyId) {
            $q->where('company_id', $companyId);
        }
        $rows = $q->orderBy('id', 'ASC')->findAll($limit);

        $out = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($rows as $r) {
            $this->dispatchOne((int) $r['id']);
            $status = (string) ((new DeliveryModel())->find((int) $r['id'])['status'] ?? 'failed');
            $out[$status === 'sent' ? 'sent' : ($status === 'skipped' ? 'skipped' : 'failed')]++;
        }

        return $out;
    }

    public function dispatchOne(int $id): bool
    {
        $model = new DeliveryModel();
        $d     = $model->find($id);
        if (!$d || $d['status'] === 'sent' || $d['status'] === 'skipped') {
            return true;
        }

        if (str_starts_with((string) $d['kind'], 'staff_')) {
            return $this->dispatchStaff($d);
        }

        $account = db_connect()->table('cmp_lgc_portal_accounts')->where('id', (int) $d['account_id'])->get()->getRowArray();
        if (!$account || $account['status'] !== 'active') {
            // La cuenta se borró o se bloqueó después de encolar: no se avisa a quien ya no tiene acceso.
            $model->update($id, ['status' => 'skipped', 'last_error' => 'account_inactive']);

            return true;
        }
        $locale = in_array($account['locale'] ?? 'es', ['es', 'en'], true) ? $account['locale'] : 'es';
        $company = (new CompanyModel())->find((int) $d['company_id']) ?? [];

        $subject = lang('Lgc.mailNotice_' . $d['kind'], [(string) ($company['name'] ?? '')], $locale);
        $html    = view('Components\LegalCase\Views\email\notice', [
            'company' => $company,
            'locale'  => $locale,
            'kind'    => $d['kind'],
            'url'     => Portal::url(),
        ]);

        try {
            $res = $this->sender
                ? ($this->sender)((int) $d['company_id'], (string) $d['recipient'], $subject, $html)
                : (new CompanyMailer())->send((int) $d['company_id'], (string) $d['recipient'], $subject, $html);
        } catch (\Throwable $e) {
            $res = ['ok' => false, 'message' => $e->getMessage()];
        }

        $ok = !empty($res['ok']);
        $model->update($id, [
            'status'     => $ok ? 'sent' : 'failed',
            'attempts'   => (int) $d['attempts'] + 1,
            'last_error' => $ok ? '' : mb_substr((string) ($res['message'] ?? ''), 0, 255),
            'sent_at'    => $ok ? date('Y-m-d H:i:s') : null,
        ]);

        return $ok;
    }

    /** Envío de un aviso interno. Lleva el número del asunto, nunca su contenido. */
    private function dispatchStaff(array $d): bool
    {
        $matter  = $d['matter_id'] ? db_connect()->table('cmp_lgc_matters')->select('number')->where('id', (int) $d['matter_id'])->get()->getRowArray() : null;
        $company = (new CompanyModel())->find((int) $d['company_id']) ?? [];
        $number  = (string) ($matter['number'] ?? '');
        $subject = lang('Lgc.mailStaff_' . $d['kind'], [$number], 'es');
        $html    = view('Components\LegalCase\Views\email\staff', [
            'company' => $company, 'locale' => 'es', 'kind' => $d['kind'], 'number' => $number,
            'url'     => base_url($d['matter_id'] ? 'legalcase/asuntos/ficha/' . (int) $d['matter_id'] : 'legalcase'),
        ]);

        return $this->send($d, $subject, $html);
    }

    private function send(array $d, string $subject, string $html): bool
    {
        try {
            $res = $this->sender
                ? ($this->sender)((int) $d['company_id'], (string) $d['recipient'], $subject, $html)
                : (new CompanyMailer())->send((int) $d['company_id'], (string) $d['recipient'], $subject, $html);
        } catch (\Throwable $e) {
            $res = ['ok' => false, 'message' => $e->getMessage()];
        }
        $ok = !empty($res['ok']);
        (new DeliveryModel())->update((int) $d['id'], [
            'status'     => $ok ? 'sent' : 'failed',
            'attempts'   => (int) $d['attempts'] + 1,
            'last_error' => $ok ? '' : mb_substr((string) ($res['message'] ?? ''), 0, 255),
            'sent_at'    => $ok ? date('Y-m-d H:i:s') : null,
        ]);

        return $ok;
    }
}
