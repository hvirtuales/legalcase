<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\CommPlanModel;
use Components\LegalCase\Models\DocRequestModel;
use Components\LegalCase\Models\MessageModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\TaskModel;
use Components\LegalCase\Models\ThreadModel;

/**
 * Mensajería segura por asunto (§6.7, §6.8) y solicitudes de documentos (F-05).
 *
 *  · Toda consulta del cliente abre o continúa un hilo vinculado a un asunto, con
 *    responsable y hora objetivo de primera respuesta (SLA del plan del asunto).
 *  · El acuse automático es ADMINISTRATIVO: confirma la recepción y el tiempo objetivo,
 *    nunca contesta el fondo (§6.5, «no fingir asesoría automática»).
 *  · Responder es del despacho; el cliente recibe un aviso mínimo para entrar al portal.
 */
class MessageService
{
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

    /** El cliente escribe desde el portal: hilo nuevo o respuesta en uno suyo. */
    public function clientWrites(array $account, array $matter, ?int $threadId, string $subject, string $body): Result
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 5000) {
            return Result::fail(lang('Lgc.vBody'));
        }
        $companyId = (int) $matter['company_id'];
        $now       = date('Y-m-d H:i:s');
        $threads   = new ThreadModel();

        if ($threadId) {
            $thread = $threads->where('company_id', $companyId)->where('matter_id', (int) $matter['id'])->find($threadId);
            if (!$thread) {
                return Result::fail(lang('Lgc.errNotFound'));
            }
            $threads->update((int) $thread['id'], [
                // Si estaba esperando al cliente o respondido, vuelve a la cola del despacho.
                'status'          => $thread['assigned_to'] ? 'assigned' : 'new',
                'last_message_at' => $now,
                'closed_at'       => null,
            ]);
        } else {
            $subject = trim($subject) !== '' ? mb_substr(trim($subject), 0, 190) : lang('Lgc.threadDefaultSubject');
            $sla     = $this->slaHours($companyId, (int) $matter['id']);
            $threads->insert([
                'company_id'            => $companyId,
                'matter_id'             => (int) $matter['id'],
                'subject'               => $subject,
                'status'                => $matter['lead_lawyer_id'] ? 'assigned' : 'new',
                'priority'              => 'normal',
                'assigned_to'           => $matter['lead_lawyer_id'] ?: null,
                'opened_by_account'     => (int) $account['id'],
                'first_response_due_at' => date('Y-m-d H:i:s', time() + $sla * 3600),
                'last_message_at'       => $now,
            ]);
            $thread = $threads->find((int) $threads->getInsertID());
        }

        $msgs = new MessageModel();
        $msgs->insert([
            'company_id' => $companyId, 'matter_id' => (int) $matter['id'], 'thread_id' => (int) $thread['id'],
            'direction' => 'in', 'body' => $body, 'author_account_id' => (int) $account['id'], 'created_at' => $now,
        ]);

        if (!$threadId) {
            // Acuse administrativo, en el idioma de la cuenta.
            $locale = in_array($account['locale'] ?? 'es', ['es', 'en'], true) ? $account['locale'] : 'es';
            $msgs->insert([
                'company_id' => $companyId, 'matter_id' => (int) $matter['id'], 'thread_id' => (int) $thread['id'],
                'direction' => 'out', 'is_auto' => 1, 'created_at' => $now,
                'body' => lang('Lgc.autoAck', [date('Y-m-d H:i', strtotime((string) $thread['first_response_due_at']))], $locale),
            ]);
        }

        Audit::log($companyId, (int) $matter['id'], 'message.received', 'thread', (int) $thread['id'], [], 'portal', (int) $account['id']);

        return Result::ok(lang('Lgc.okMessageSent'), (int) $thread['id']);
    }

    /** El despacho abre un hilo con el cliente. */
    public function staffStarts(array $matter, string $subject, string $body, Access $acc): Result
    {
        $body = trim($body);
        if ($body === '') {
            return Result::fail(lang('Lgc.vBody'));
        }
        $threads = new ThreadModel();
        $now     = date('Y-m-d H:i:s');
        $threads->insert([
            'company_id'        => $acc->companyId,
            'matter_id'         => (int) $matter['id'],
            'subject'           => mb_substr(trim($subject) ?: lang('Lgc.threadDefaultSubject'), 0, 190),
            'status'            => 'waiting_client',
            'priority'          => 'normal',
            'assigned_to'       => $acc->userId ?: null,
            'opened_by_user'    => $acc->userId ?: null,
            'first_response_at' => $now,
            'last_message_at'   => $now,
        ]);
        $threadId = (int) $threads->getInsertID();

        return $this->post($matter, $threadId, $body, $acc, 'waiting_client', true);
    }

    /** Respuesta del despacho en un hilo existente. */
    public function staffReplies(array $matter, array $thread, string $body, string $nextStatus, Access $acc): Result
    {
        if (trim($body) === '') {
            return Result::fail(lang('Lgc.vBody'));
        }
        $next = in_array($nextStatus, ['answered', 'waiting_client', 'closed'], true) ? $nextStatus : 'answered';

        return $this->post($matter, (int) $thread['id'], trim($body), $acc, $next, empty($thread['first_response_at']));
    }

    private function post(array $matter, int $threadId, string $body, Access $acc, string $status, bool $firstResponse): Result
    {
        $now  = date('Y-m-d H:i:s');
        $msgs = new MessageModel();
        $msgs->insert([
            'company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'thread_id' => $threadId,
            'direction' => 'out', 'body' => $body, 'author_user_id' => $acc->userId ?: null, 'created_at' => $now,
        ]);
        $upd = ['status' => $status, 'last_message_at' => $now, 'closed_at' => $status === 'closed' ? $now : null];
        if ($firstResponse) {
            $upd['first_response_at'] = $now;
        }
        (new ThreadModel())->update($threadId, $upd);
        Audit::log($acc->companyId, (int) $matter['id'], 'message.sent', 'thread', $threadId);

        // Cada mensaje del despacho tiene su propio aviso: la clave lleva el id del mensaje.
        $msgId = (int) $msgs->getInsertID();
        $this->outbox->notifyMatter($matter, 'message_reply', 'message', $msgId);

        return Result::ok(lang('Lgc.okReplied'), $threadId);
    }

    public function setThread(array $thread, array $in, Access $acc): Result
    {
        $upd = [];
        if (isset($in['status']) && in_array($in['status'], ThreadModel::STATUSES, true)) {
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

    public function requestDocument(array $matter, array $in, Access $acc): Result
    {
        $due   = trim((string) ($in['due_at'] ?? ''));
        $model = new DocRequestModel();
        $ok    = $model->insert([
            'company_id'   => $acc->companyId,
            'matter_id'    => (int) $matter['id'],
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
        Audit::log($acc->companyId, (int) $matter['id'], 'docrequest.created', 'docrequest', $id);
        $n = $this->outbox->notifyMatter($matter, 'doc_request', 'docrequest', $id);

        return Result::ok(lang('Lgc.okDocRequest', [$n]), $id);
    }

    /** El cliente entregó: la solicitud pasa a revisión y el responsable recibe una tarea. */
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
            // Hay que corregir: el cliente debe saberlo (aviso mínimo, el motivo se lee en el portal).
            $this->outbox->notifyMatter($matter, 'doc_rejected', 'docrequest', (int) $request['id']);
        }

        return Result::ok(lang('Lgc.okReviewed'));
    }
}
