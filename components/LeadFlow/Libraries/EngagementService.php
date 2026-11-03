<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\CommunicationModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\NoteModel;

/**
 * El trabajo diario sobre un lead: actividades (§17), notas y comunicaciones
 * (§25, MVP: llamadas, email y registro de WhatsApp/SMS).
 *
 * Cada acción deja su evento en la línea de tiempo y alimenta las métricas: un
 * contacto saliente fija la primera respuesta; uno entrante dice que el prospecto
 * respondió (y suma en el scoring).
 */
class EngagementService
{
    private static function user(): ?int
    {
        return (int) (session('user_id') ?? 0) ?: null;
    }

    private static function when(?string $date, ?string $time): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }
        $time = trim((string) $time) ?: '09:00';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }
        $ts = strtotime($date . ' ' . $time . ':00');

        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    // ── Actividades ───────────────────────────────────────────────────────────

    public function addActivity(array $lead, array $in): array
    {
        $cid      = (int) $lead['company_id'];
        $assigned = (int) ($in['assigned_user_id'] ?? 0) ?: (self::user() ?? 0);
        if ($assigned && ! Team::isMember($cid, $assigned)) {
            $assigned = 0;
        }

        $data = [
            'company_id'       => $cid,
            'lead_id'          => (int) $lead['id'],
            'assigned_user_id' => $assigned ?: null,
            'activity_type'    => in_array($in['activity_type'] ?? '', ActivityModel::TYPES, true) ? $in['activity_type'] : 'follow_up',
            'title'            => mb_substr(trim(strip_tags((string) ($in['title'] ?? ''))), 0, 150),
            'description'      => mb_substr(trim((string) ($in['description'] ?? '')), 0, 2000) ?: null,
            'due_at'           => self::when($in['due_date'] ?? null, $in['due_time'] ?? null),
            'status'           => 'pending',
            'priority'         => in_array($in['priority'] ?? '', LeadModel::PRIORITIES, true) ? $in['priority'] : 'medium',
            'created_by'       => self::user(),
        ];
        if ($data['title'] === '') {
            $data['title'] = Meta::label(Meta::ACTIVITY_TYPES, $data['activity_type']);
        }

        $model = new ActivityModel();
        if (! $model->insert($data)) {
            return ['ok' => false, 'message' => implode(' ', $model->errors())];
        }

        Timeline::log($cid, (int) $lead['id'], 'activity', lang('Lfl.tlActivityNew', [$data['title']]));
        (new LeadService())->refreshNextActivity($cid, (int) $lead['id']);
        MatterService::touchByLead((int) $lead['id']);

        return ['ok' => true, 'message' => lang('Lfl.okActivityCreated')];
    }

    public function completeActivity(array $act): array
    {
        if ($act['status'] === 'done') {
            return ['ok' => true, 'message' => lang('Lfl.okNoChanges')];
        }
        $cid = (int) $act['company_id'];
        $now = date('Y-m-d H:i:s');

        (new ActivityModel())->update((int) $act['id'], ['status' => 'done', 'completed_at' => $now]);
        Timeline::log($cid, (int) $act['lead_id'], 'activity', lang('Lfl.tlActivityDone', [$act['title']]));

        $leads = new LeadService();
        // Completar una llamada, un email o un WhatsApp ES un contacto con el prospecto.
        if (in_array($act['activity_type'], ['call', 'email', 'whatsapp'], true)) {
            $leads->touchOutbound($cid, (int) $act['lead_id'], $now);
        }
        $leads->refreshNextActivity($cid, (int) $act['lead_id']);
        MatterService::touchByLead((int) $act['lead_id']);

        return ['ok' => true, 'message' => lang('Lfl.okActivityDone')];
    }

    public function reopenActivity(array $act): array
    {
        (new ActivityModel())->update((int) $act['id'], ['status' => 'pending', 'completed_at' => null]);
        Timeline::log((int) $act['company_id'], (int) $act['lead_id'], 'activity', lang('Lfl.tlActivityReopened', [$act['title']]));
        (new LeadService())->refreshNextActivity((int) $act['company_id'], (int) $act['lead_id']);
        MatterService::touchByLead((int) $act['lead_id']);

        return ['ok' => true, 'message' => lang('Lfl.okUpdated')];
    }

    public function deleteActivity(array $act): array
    {
        (new ActivityModel())->delete((int) $act['id']);
        (new LeadService())->refreshNextActivity((int) $act['company_id'], (int) $act['lead_id']);
        MatterService::touchByLead((int) $act['lead_id']);

        return ['ok' => true, 'message' => lang('Lfl.okDeleted')];
    }

    // ── Notas ─────────────────────────────────────────────────────────────────

    public function addNote(array $lead, string $body, bool $transferable): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'message' => lang('Lfl.vNoteBody')];
        }
        $model = new NoteModel();
        $model->insert([
            'company_id'      => (int) $lead['company_id'],
            'lead_id'         => (int) $lead['id'],
            'body'            => mb_substr($body, 0, 5000),
            'is_transferable' => $transferable ? 1 : 0,
            'created_by'      => self::user(),
        ]);
        Timeline::log((int) $lead['company_id'], (int) $lead['id'], 'note', mb_substr($body, 0, 120));

        return ['ok' => true, 'message' => lang('Lfl.okNoteAdded')];
    }

    public function deleteNote(array $note): array
    {
        (new NoteModel())->delete((int) $note['id']);

        return ['ok' => true, 'message' => lang('Lfl.okDeleted')];
    }

    // ── Comunicaciones ────────────────────────────────────────────────────────

    public function logCommunication(array $lead, array $in): array
    {
        $cid       = (int) $lead['company_id'];
        $channel   = in_array($in['channel'] ?? '', CommunicationModel::CHANNELS, true) ? $in['channel'] : 'call';
        $direction = ($in['direction'] ?? 'out') === 'in' ? 'in' : 'out';
        $outcome   = in_array($in['outcome'] ?? '', CommunicationModel::OUTCOMES, true) ? $in['outcome'] : null;
        $minutes   = (int) ($in['duration_min'] ?? 0);

        $model = new CommunicationModel();
        $ok    = $model->insert([
            'company_id'   => $cid,
            'lead_id'      => (int) $lead['id'],
            'channel'      => $channel,
            'direction'    => $direction,
            'subject'      => mb_substr(trim(strip_tags((string) ($in['subject'] ?? ''))), 0, 200) ?: null,
            'body'         => mb_substr(trim((string) ($in['body'] ?? '')), 0, 5000) ?: null,
            'outcome'      => $outcome,
            'duration_sec' => $minutes > 0 ? min($minutes, 600) * 60 : null,
            'status'       => 'logged',
            'created_by'   => self::user(),
        ]);
        if (! $ok) {
            return ['ok' => false, 'message' => implode(' ', $model->errors())];
        }

        Timeline::log($cid, (int) $lead['id'], $channel, lang('Lfl.tlComm', [
            Meta::label(Meta::CHANNELS, $channel), Meta::label(Meta::DIRECTIONS, $direction),
            $outcome ? Meta::label(Meta::OUTCOMES, $outcome) : '—',
        ]));

        $leads = new LeadService();
        $direction === 'in' ? $leads->touchInbound($cid, (int) $lead['id']) : $leads->touchOutbound($cid, (int) $lead['id']);

        return ['ok' => true, 'message' => lang('Lfl.okCommLogged')];
    }

    /**
     * Envía un email al prospecto por el SMTP de la firma (con respaldo al correo
     * de la plataforma, lo decide `CompanyMailer`) y lo deja registrado.
     */
    public function sendEmail(array $lead, string $subject, string $body): array
    {
        $cid     = (int) $lead['company_id'];
        $subject = mb_substr(trim(strip_tags($subject)), 0, 200);
        $body    = trim($body);

        if (empty($lead['email'])) {
            return ['ok' => false, 'message' => lang('Lfl.errNoEmail')];
        }
        if ($subject === '' || $body === '') {
            return ['ok' => false, 'message' => lang('Lfl.vEmailFields')];
        }

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">'
            . nl2br(esc($body)) . '</div>';

        try {
            $res = (new \App\Libraries\CompanyMailer())->send($cid, (string) $lead['email'], $subject, $html);
        } catch (\Throwable $e) {
            $res = ['ok' => false, 'message' => $e->getMessage()];
        }
        $ok = ! empty($res['ok']);

        (new CommunicationModel())->insert([
            'company_id' => $cid,
            'lead_id'    => (int) $lead['id'],
            'channel'    => 'email',
            'direction'  => 'out',
            'subject'    => $subject,
            'body'       => mb_substr($body, 0, 5000),
            'outcome'    => $ok ? 'sent' : 'failed',
            'status'     => $ok ? 'sent' : 'failed',
            'error'      => $ok ? null : mb_substr((string) ($res['message'] ?? ''), 0, 255),
            'created_by' => self::user(),
        ]);
        Timeline::log($cid, (int) $lead['id'], 'email', lang($ok ? 'Lfl.tlEmailSent' : 'Lfl.tlEmailFailed', [$subject]));

        if ($ok) {
            (new LeadService())->touchOutbound($cid, (int) $lead['id']);

            return ['ok' => true, 'message' => lang('Lfl.okEmailSent')];
        }

        return ['ok' => false, 'message' => lang('Lfl.errEmailSend', [(string) ($res['message'] ?? '')])];
    }
}
