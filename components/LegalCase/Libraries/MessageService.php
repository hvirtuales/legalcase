<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\CommPlanModel;
use Components\LegalCase\Models\DocRequestModel;
use Components\LegalCase\Models\MessageModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\TaskModel;
use Components\LegalCase\Models\ThreadModel;

/**
 * Mensajería segura por asunto (§6.7, §6.8), solicitudes de documentos (F-05) y solicitudes
 * de derechos sobre datos personales (Ley 1581 de 2012).
 *
 * Reglas jurídicas que hace valer el SERVICIO:
 *  · **Secreto entre coclientes.** Cada hilo tiene un destinatario (`account_id`): lo que un
 *    cliente escribe no lo lee el otro cliente del mismo asunto. Solo el despacho puede abrir
 *    un hilo compartido, y lo decide explícitamente (`is_shared`).
 *  · **Supervisión del personal no abogado** (ABA Model Rule 5.3). Quien no tiene `lgc.share`
 *    redacta, pero su mensaje queda `pending_review` y el hilo en `review` hasta que un abogado
 *    lo apruebe. Al cliente nunca le llega un borrador.
 *  · **Acuse automático administrativo**: confirma recepción y hora objetivo; no contesta el fondo.
 *  · **Avisos internos**: el responsable recibe aviso cuando el cliente escribe o entrega.
 */
class MessageService
{
    /** Tipos de solicitud de datos personales y plazo legal en días hábiles (Ley 1581, arts. 14 y 15). */
    public const DATA_RIGHTS = ['consulta' => 10, 'rectificacion' => 15, 'actualizacion' => 15, 'supresion' => 15, 'revocatoria' => 15];

    public function __construct(private ?Outbox $outbox = null)
    {
        $this->outbox ??= new Outbox();
    }

    private function slaHours(int $companyId, int $matterId): int
    {
        $plan = (new CommPlanModel())->where('matter_id', $matterId)->first();
        if ($plan) {
            return max(1, (int) $plan['response_sla_hours']);
        }

        return max(1, (int) ((new SettingsModel())->forCompanyOrDefault($companyId)['response_sla_hours'] ?? 48));
    }

    /** Suma días hábiles (lunes a viernes). No descuenta festivos: aproximación documentada. */
    public static function addBusinessDays(int $days, ?int $from = null): string
    {
        $t = $from ?? time();
        while ($days > 0) {
            $t += 86400;
            if ((int) date('N', $t) < 6) {
                $days--;
            }
        }

        return date('Y-m-d H:i:s', $t);
    }

    /** Destinatarios de un hilo: su cuenta; todos si es compartido; null = no avisar a nadie más. */
    private function threadAudience(array $thread): ?array
    {
        if ((int) ($thread['is_shared'] ?? 0) === 1) {
            return null;
        }
        $aid = (int) ($thread['account_id'] ?: $thread['opened_by_account']);

        return $aid ? [$aid] : [];
    }

    // ------------------------------------------------------------------ lado del cliente

    /**
     * El cliente escribe desde el portal: hilo nuevo (suyo) o respuesta en uno que puede ver.
     * `$thread` ya viene resuelto por ClientView::thread() (visible para la cuenta) o es null.
     */
    public function clientWrites(array $account, array $matter, ?array $thread, string $subject, string $body, string $kind = 'general'): Result
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 5000) {
            return Result::fail(lang('Lgc.vBody'));
        }
        $companyId = (int) $matter['company_id'];
        $now       = date('Y-m-d H:i:s');
        $threads   = new ThreadModel();
        $isNew     = $thread === null;

        if (!$isNew) {
            $threads->update((int) $thread['id'], [
                'status'          => $thread['assigned_to'] ? 'assigned' : 'new',
                'last_message_at' => $now,
                'closed_at'       => null,
            ]);
        } else {
            $kind    = in_array($kind, ThreadModel::KINDS, true) ? $kind : 'general';
            $subject = trim($subject) !== '' ? mb_substr(trim($subject), 0, 190) : lang('Lgc.threadDefaultSubject');
            $due     = $kind === 'data_rights'
                ? self::addBusinessDays(self::DATA_RIGHTS[$this->rightOf($subject)] ?? 10)
                : date('Y-m-d H:i:s', time() + $this->slaHours($companyId, (int) $matter['id']) * 3600);
            $threads->insert([
                'company_id'            => $companyId,
                'matter_id'             => (int) $matter['id'],
                'subject'               => $subject,
                'status'                => $matter['lead_lawyer_id'] ? 'assigned' : 'new',
                'priority'              => $kind === 'data_rights' ? 'high' : 'normal',
                'kind'                  => $kind,
                'assigned_to'           => $matter['lead_lawyer_id'] ?: null,
                'opened_by_account'     => (int) $account['id'],
                'account_id'            => (int) $account['id'],
                'is_shared'             => 0,
                'first_response_due_at' => $due,
                'last_message_at'       => $now,
            ]);
            $thread = $threads->find((int) $threads->getInsertID());
        }

        $msgs = new MessageModel();
        $msgs->insert([
            'company_id' => $companyId, 'matter_id' => (int) $matter['id'], 'thread_id' => (int) $thread['id'],
            'direction' => 'in', 'body' => $body, 'author_account_id' => (int) $account['id'], 'status' => 'sent', 'created_at' => $now,
        ]);
        $msgId = (int) $msgs->getInsertID();

        if ($isNew) {
            $locale = in_array($account['locale'] ?? 'es', ['es', 'en'], true) ? $account['locale'] : 'es';
            $msgs->insert([
                'company_id' => $companyId, 'matter_id' => (int) $matter['id'], 'thread_id' => (int) $thread['id'],
                'direction' => 'out', 'is_auto' => 1, 'status' => 'sent', 'created_at' => $now,
                'body' => lang($thread['kind'] === 'data_rights' ? 'Lgc.autoAckData' : 'Lgc.autoAck', [date('Y-m-d H:i', strtotime((string) $thread['first_response_due_at']))], $locale),
            ]);
        }

        Audit::log($companyId, (int) $matter['id'], $thread['kind'] === 'data_rights' ? 'data_rights.requested' : 'message.received', 'thread', (int) $thread['id'], [], 'portal', (int) $account['id']);
        $this->outbox->notifyStaff($companyId, (int) $matter['id'], (int) ($thread['assigned_to'] ?: $matter['lead_lawyer_id']) ?: null,
            $thread['kind'] === 'data_rights' ? 'data_rights' : 'message', 'message', $msgId);

        return Result::ok(lang('Lgc.okMessageSent'), (int) $thread['id']);
    }

    private function rightOf(string $subject): string
    {
        foreach (array_keys(self::DATA_RIGHTS) as $k) {
            if (str_contains($subject, lang('Lgc.right_' . $k)) || str_contains($subject, lang('Lgc.right_' . $k, [], 'en'))) {
                return $k;
            }
        }

        return 'consulta';
    }

    /** Solicitud de derechos sobre datos personales desde el portal. */
    public function dataRightsRequest(array $account, array $matter, string $right, string $detail): Result
    {
        if (!isset(self::DATA_RIGHTS[$right])) {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        $locale  = in_array($account['locale'] ?? 'es', ['es', 'en'], true) ? $account['locale'] : 'es';
        $subject = lang('Lgc.dataRightsSubject', [lang('Lgc.right_' . $right, [], $locale)], $locale);

        return $this->clientWrites($account, $matter, null, $subject, $detail, 'data_rights');
    }

    // ------------------------------------------------------------------ lado del despacho

    /**
     * El despacho abre un hilo con UNA cuenta del portal del asunto o, si se decide así,
     * compartido con todos los clientes del asunto.
     */
    public function staffStarts(array $matter, string $subject, string $body, Access $acc, string $recipient = ''): Result
    {
        $body = trim($body);
        if ($body === '') {
            return Result::fail(lang('Lgc.vBody'));
        }
        $shared    = $recipient === 'shared';
        $accountId = $shared ? null : (int) $recipient;
        $valid     = array_map('intval', array_column(Outbox::recipients($acc->companyId, (int) $matter['id']), 'account_id'));
        if (!$shared && !in_array($accountId, $valid, true)) {
            return Result::fail(lang('Lgc.errRecipient'));
        }

        $threads = new ThreadModel();
        $now     = date('Y-m-d H:i:s');
        $threads->insert([
            'company_id'      => $acc->companyId,
            'matter_id'       => (int) $matter['id'],
            'subject'         => mb_substr(trim($subject) ?: lang('Lgc.threadDefaultSubject'), 0, 190),
            'status'          => 'waiting_client',
            'priority'        => 'normal',
            'kind'            => 'general',
            'assigned_to'     => $acc->userId ?: null,
            'opened_by_user'  => $acc->userId ?: null,
            'account_id'      => $accountId,
            'is_shared'       => $shared ? 1 : 0,
            'last_message_at' => $now,
        ]);
        $thread = $threads->find((int) $threads->getInsertID());

        return $this->post($matter, $thread, $body, $acc, 'waiting_client');
    }

    public function staffReplies(array $matter, array $thread, string $body, string $nextStatus, Access $acc): Result
    {
        if (trim($body) === '') {
            return Result::fail(lang('Lgc.vBody'));
        }
        $next = in_array($nextStatus, ['answered', 'waiting_client', 'closed'], true) ? $nextStatus : 'answered';

        return $this->post($matter, $thread, trim($body), $acc, $next);
    }

    /** Publica (o deja en revisión) un mensaje del despacho. */
    private function post(array $matter, array $thread, string $body, Access $acc, string $status): Result
    {
        $now    = date('Y-m-d H:i:s');
        $direct = $acc->can('lgc.share');
        $msgs   = new MessageModel();
        $msgs->insert([
            'company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'thread_id' => (int) $thread['id'],
            'direction' => 'out', 'body' => $body, 'author_user_id' => $acc->userId ?: null,
            'status' => $direct ? 'sent' : 'pending_review', 'created_at' => $now,
        ]);
        $msgId = (int) $msgs->getInsertID();

        if (!$direct) {
            (new ThreadModel())->update((int) $thread['id'], ['status' => 'review']);
            Audit::log($acc->companyId, (int) $matter['id'], 'message.drafted', 'thread', (int) $thread['id']);

            return Result::ok(lang('Lgc.okReplyInReview'), (int) $thread['id']);
        }

        $this->deliver($matter, $thread, $msgId, $status, $acc);

        return Result::ok(lang('Lgc.okReplied'), (int) $thread['id']);
    }

    private function deliver(array $matter, array $thread, int $msgId, string $status, Access $acc): void
    {
        $now = date('Y-m-d H:i:s');
        $upd = ['status' => $status, 'last_message_at' => $now, 'closed_at' => $status === 'closed' ? $now : null];
        if (empty($thread['first_response_at'])) {
            $upd['first_response_at'] = $now;
        }
        (new ThreadModel())->update((int) $thread['id'], $upd);
        Audit::log($acc->companyId, (int) $matter['id'], 'message.sent', 'thread', (int) $thread['id']);
        $this->outbox->notifyMatter($matter, 'message_reply', 'message', $msgId, $this->threadAudience($thread));
    }

    /** Un abogado aprueba (envía) o descarta un mensaje que esperaba revisión. */
    public function review(array $matter, array $message, bool $approve, Access $acc): Result
    {
        if ($message['status'] !== 'pending_review' || $message['direction'] !== 'out') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        if ((int) $message['author_user_id'] === $acc->userId) {
            return Result::fail(lang('Lgc.errSelfApprove'));
        }
        $thread = (new ThreadModel())->find((int) $message['thread_id']);
        (new MessageModel())->update((int) $message['id'], [
            'status' => $approve ? 'sent' : 'discarded', 'reviewed_by' => $acc->userId ?: null, 'reviewed_at' => date('Y-m-d H:i:s'),
            // La hora que cuenta para el cliente es la del envío, no la del borrador.
            'created_at' => $approve ? date('Y-m-d H:i:s') : $message['created_at'],
        ]);
        if ($approve) {
            $this->deliver($matter, $thread, (int) $message['id'], 'answered', $acc);
        } else {
            (new ThreadModel())->update((int) $thread['id'], ['status' => $thread['assigned_to'] ? 'assigned' : 'new']);
            Audit::log($acc->companyId, (int) $matter['id'], 'message.discarded', 'thread', (int) $thread['id']);
        }

        return Result::ok(lang($approve ? 'Lgc.okReplied' : 'Lgc.okDiscarded'));
    }

    public function setThread(array $thread, array $in, Access $acc): Result
    {
        $upd = [];
        if (isset($in['status']) && in_array($in['status'], ThreadModel::STATUSES, true) && $in['status'] !== 'review') {
            $upd['status']    = $in['status'];
            $upd['closed_at'] = $in['status'] === 'closed' ? date('Y-m-d H:i:s') : null;
        }
        if (array_key_exists('assigned_to', $in)) {
            $uid = (int) $in['assigned_to'];
            $upd['assigned_to'] = $uid && Team::isMember($acc->companyId, $uid) ? $uid : null;
            if ($upd['assigned_to'] && ($upd['status'] ?? $thread['status']) === 'new') {
                $upd['status'] = 'assigned';
            }
        }
        if (isset($in['priority']) && in_array($in['priority'], ThreadModel::PRIORITIES, true)) {
            $upd['priority'] = $in['priority'];
        }
        if (!$upd) {
            return Result::fail(lang('Lgc.errNothing'));
        }
        (new ThreadModel())->update((int) $thread['id'], $upd);
        Audit::log($acc->companyId, (int) $thread['matter_id'], 'thread.updated', 'thread', (int) $thread['id'], $upd);

        return Result::ok(lang('Lgc.okSaved'));
    }

    // ------------------------------------------------------------------ solicitudes de documentos

    /** Solicitud a UN cliente del asunto (`account_id`) o a cualquiera de ellos (vacío). */
    public function requestDocument(array $matter, array $in, Access $acc): Result
    {
        $due       = trim((string) ($in['due_at'] ?? ''));
        $accountId = (int) ($in['account_id'] ?? 0) ?: null;
        if ($accountId) {
            $valid = array_map('intval', array_column(Outbox::recipients($acc->companyId, (int) $matter['id']), 'account_id'));
            if (!in_array($accountId, $valid, true)) {
                return Result::fail(lang('Lgc.errRecipient'));
            }
        }
        $model = new DocRequestModel();
        $ok    = $model->insert([
            'company_id'   => $acc->companyId,
            'matter_id'    => (int) $matter['id'],
            'account_id'   => $accountId,
            'title'        => trim((string) ($in['title'] ?? '')),
            'instructions' => trim((string) ($in['instructions'] ?? '')),
            'due_at'       => $due !== '' && strtotime($due) ? date('Y-m-d H:i:s', strtotime($due)) : null,
            'status'       => 'pending',
            'created_by'   => $acc->userId ?: null,
        ]);
        if (!$ok) {
            return Result::fail(implode(' ', $model->errors()));
        }
        $id = (int) $model->getInsertID();
        Audit::log($acc->companyId, (int) $matter['id'], 'docrequest.created', 'docrequest', $id, ['to' => $accountId]);
        $n = $this->outbox->notifyMatter($matter, 'doc_request', 'docrequest', $id, $accountId ? [$accountId] : null);

        return Result::ok(lang('Lgc.okDocRequest', [$n]), $id);
    }

    /** El cliente entregó: la solicitud pasa a revisión y el responsable recibe tarea y aviso. */
    public function markSubmitted(array $request, array $matter, int $accountId): void
    {
        $now = date('Y-m-d H:i:s');
        (new DocRequestModel())->update((int) $request['id'], ['status' => 'submitted', 'submitted_at' => $now]);

        $ref   = 'doc_review:' . (int) $request['id'];
        $tasks = new TaskModel();
        if (!$tasks->where('company_id', (int) $matter['company_id'])->where('ref', $ref)->where('status', 'open')->first()) {
            $tasks->insert([
                'company_id' => (int) $matter['company_id'], 'matter_id' => (int) $matter['id'], 'kind' => 'doc_review',
                'title' => lang('Lgc.taskDocReview', [$request['title']]), 'due_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
                'assigned_to' => $matter['lead_lawyer_id'] ?: null, 'status' => 'open', 'ref' => $ref,
            ]);
        }
        Audit::log((int) $matter['company_id'], (int) $matter['id'], 'docrequest.submitted', 'docrequest', (int) $request['id'], [], 'portal', $accountId);
        $this->outbox->notifyStaff((int) $matter['company_id'], (int) $matter['id'], (int) $matter['lead_lawyer_id'] ?: null, 'delivery', 'docrequest', (int) $request['id'], $now);
    }

    public function reviewRequest(array $request, array $matter, string $decision, string $note, Access $acc): Result
    {
        $map = ['accept' => 'accepted', 'reject' => 'rejected', 'cancel' => 'cancelled'];
        if (!isset($map[$decision])) {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        if ($decision === 'reject' && trim($note) === '') {
            return Result::fail(lang('Lgc.vRejectNote'));
        }
        (new DocRequestModel())->update((int) $request['id'], [
            'status' => $map[$decision], 'review_note' => mb_substr(trim($note), 0, 255),
            'reviewed_by' => $acc->userId ?: null, 'reviewed_at' => date('Y-m-d H:i:s'),
        ]);
        (new TaskModel())->where('company_id', $acc->companyId)->where('ref', 'doc_review:' . (int) $request['id'])->where('status', 'open')
            ->set(['status' => 'done', 'done_at' => date('Y-m-d H:i:s')])->update();
        Audit::log($acc->companyId, (int) $matter['id'], 'docrequest.' . $map[$decision], 'docrequest', (int) $request['id']);

        if ($decision === 'reject') {
            $this->outbox->notifyMatter($matter, 'doc_rejected', 'docrequest', (int) $request['id'], $request['account_id'] ? [(int) $request['account_id']] : null);
        }

        return Result::ok(lang('Lgc.okReviewed'));
    }

    /** Cuentas del portal con acceso al asunto: [id => correo]. */
    public static function accountsFor(int $companyId, int $matterId): array
    {
        $out = [];
        foreach (Outbox::recipients($companyId, $matterId) as $r) {
            $out[(int) $r['account_id']] = (string) $r['email'];
        }

        return $out;
    }
}
