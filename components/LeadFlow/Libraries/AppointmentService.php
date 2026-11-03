<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\AppointmentModel;
use Components\LeadFlow\Models\LeadModel;

/**
 * Agenda de consultas (§18): crear, reprogramar, confirmar, cancelar, registrar
 * asistencia o no-show y el resultado.
 *
 * Programar una consulta y realizarla son hechos del pipeline: el lead avanza
 * solo a «Consulta programada» y «Consulta realizada» (sin retroceder nunca), y
 * el score se recalcula porque «consulta programada» es un criterio.
 */
class AppointmentService
{
    private AppointmentModel $appts;

    public function __construct()
    {
        $this->appts = new AppointmentModel();
    }

    private static function when(?string $date, ?string $time): ?string
    {
        $date = trim((string) $date);
        $time = trim((string) $time) ?: '09:00';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }
        $ts = strtotime($date . ' ' . $time . ':00');

        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    public function schedule(array $lead, array $in): array
    {
        $cid = (int) $lead['company_id'];
        $at  = self::when($in['date'] ?? null, $in['time'] ?? null);
        if (! $at) {
            return ['ok' => false, 'message' => lang('Lfl.vApptDate')];
        }

        $lawyer = (int) ($in['lawyer_user_id'] ?? 0) ?: null;
        if ($lawyer && ! Team::isMember($cid, $lawyer)) {
            $lawyer = null;
        }
        $mode = in_array($in['mode'] ?? '', AppointmentModel::MODES, true) ? $in['mode'] : 'video';

        $ok = $this->appts->insert([
            'company_id'     => $cid,
            'lead_id'        => (int) $lead['id'],
            'lawyer_user_id' => $lawyer,
            'scheduled_at'   => $at,
            'duration_min'   => max(5, min(480, (int) ($in['duration_min'] ?? 30) ?: 30)),
            'mode'           => $mode,
            'location'       => mb_substr(trim((string) ($in['location'] ?? '')), 0, 255) ?: null,
            'status'         => 'scheduled',
            'notes'          => mb_substr(trim((string) ($in['notes'] ?? '')), 0, 2000) ?: null,
            'created_by'     => (int) (session('user_id') ?? 0) ?: null,
        ]);
        if (! $ok) {
            return ['ok' => false, 'message' => implode(' ', $this->appts->errors())];
        }
        $apptId = (int) $this->appts->getInsertID();

        // El abogado de la consulta pasa a ser el abogado del lead si no tenía.
        if ($lawyer && empty($lead['lawyer_user_id'])) {
            (new LeadModel())->skipValidation(true)->update((int) $lead['id'], ['lawyer_user_id' => $lawyer]);
        }

        Timeline::log($cid, (int) $lead['id'], 'appointment', lang('Lfl.tlApptScheduled', [date('d/m/Y H:i', strtotime($at)), Meta::label(Meta::APPT_MODES, $mode)]));
        (new LeadService())->advanceTo($lead, 'consultation_scheduled');
        (new ScoringService())->recalculate((int) $lead['id'], $cid, 'appointment');
        $this->fire('appointment_scheduled', $lead, $apptId);

        return ['ok' => true, 'message' => lang('Lfl.okApptScheduled')];
    }

    public function reschedule(array $appt, array $lead, array $in): array
    {
        if (in_array($appt['status'], ['completed', 'cancelled'], true)) {
            return ['ok' => false, 'message' => lang('Lfl.errApptClosed')];
        }
        $at = self::when($in['date'] ?? null, $in['time'] ?? null);
        if (! $at) {
            return ['ok' => false, 'message' => lang('Lfl.vApptDate')];
        }

        $this->appts->update((int) $appt['id'], [
            'scheduled_at'     => $at,
            'status'           => 'scheduled',
            'reschedule_count' => (int) $appt['reschedule_count'] + 1,
        ]);
        Timeline::log((int) $lead['company_id'], (int) $lead['id'], 'appointment',
            lang('Lfl.tlApptRescheduled', [date('d/m/Y H:i', strtotime($appt['scheduled_at'])), date('d/m/Y H:i', strtotime($at))]));

        return ['ok' => true, 'message' => lang('Lfl.okApptRescheduled')];
    }

    /** Confirmar, cancelar o marcar no-show. */
    public function setStatus(array $appt, array $lead, string $status): array
    {
        if (! in_array($status, ['confirmed', 'cancelled', 'no_show'], true)) {
            return ['ok' => false, 'message' => lang('Lfl.errInvalid')];
        }
        if ($appt['status'] === 'completed') {
            return ['ok' => false, 'message' => lang('Lfl.errApptClosed')];
        }

        $this->appts->update((int) $appt['id'], ['status' => $status]);
        Timeline::log((int) $lead['company_id'], (int) $lead['id'], 'appointment',
            lang('Lfl.tlApptStatus', [Meta::label(Meta::APPT_STATUSES, $status)]));
        (new ScoringService())->recalculate((int) $lead['id'], (int) $lead['company_id'], 'appointment');

        // Un no-show es de los hechos que el §19 quiere automatizar (reintento,
        // aviso, reprogramación); confirmar o cancelar no disparan nada por ahora.
        if ($status === 'no_show') {
            $this->fire('no_show', $lead, (int) $appt['id']);
        }

        return ['ok' => true, 'message' => lang('Lfl.okUpdated')];
    }

    /** Registra la asistencia con su resultado. */
    public function complete(array $appt, array $lead, string $result, ?string $notes): array
    {
        if (! in_array($result, AppointmentModel::RESULTS, true)) {
            return ['ok' => false, 'message' => lang('Lfl.errApptResult')];
        }
        if ($appt['status'] === 'cancelled') {
            return ['ok' => false, 'message' => lang('Lfl.errApptClosed')];
        }

        $data = ['status' => 'completed', 'result' => $result];
        $notes = trim((string) $notes);
        if ($notes !== '') {
            $data['notes'] = mb_substr(trim(($appt['notes'] ?? '') . "\n" . $notes), 0, 2000);
        }
        $this->appts->update((int) $appt['id'], $data);

        Timeline::log((int) $lead['company_id'], (int) $lead['id'], 'appointment',
            lang('Lfl.tlApptDone', [Meta::label(Meta::APPT_RESULTS, $result)]));
        (new LeadService())->advanceTo($lead, 'consultation_done');
        (new ScoringService())->recalculate((int) $lead['id'], (int) $lead['company_id'], 'appointment');
        $this->fire('appointment_done', $lead, (int) $appt['id']);

        return ['ok' => true, 'message' => lang('Lfl.okApptDone')];
    }

    /**
     * Borra una consulta de la agenda.
     *
     * Es un borrado suave, como el resto del componente: lo que ya pasó —la etapa a
     * la que llevó, su rastro en la línea de tiempo— no se reescribe, porque el
     * historial del lead cuenta lo que ocurrió y no lo que quedó guardado. Sí se
     * recalcula el score, porque «consulta programada» es un criterio y dejaría de
     * cumplirse.
     */
    public function delete(array $appt, array $lead): array
    {
        if (! $this->appts->delete((int) $appt['id'])) {
            return ['ok' => false, 'message' => lang('Lfl.errInvalid')];
        }

        Timeline::log((int) $lead['company_id'], (int) $lead['id'], 'appointment',
            lang('Lfl.tlApptDeleted', [date('d/m/Y H:i', strtotime((string) $appt['scheduled_at']))]));
        (new ScoringService())->recalculate((int) $lead['id'], (int) $lead['company_id'], 'appointment');

        return ['ok' => true, 'message' => lang('Lfl.okApptDeleted')];
    }

    /**
     * Dispara una automatización de agenda (§19).
     *
     * El lead se relee porque `advanceTo()` acaba de poder cambiarle la etapa, y el
     * «una sola vez» va por CONSULTA: dos consultas del mismo lead son dos hechos
     * distintos y cada una merece su recordatorio.
     */
    private function fire(string $trigger, array $lead, int $apptId): void
    {
        $fresh = (new LeadModel())->find((int) $lead['id']);
        if ($fresh) {
            AutomationEngine::fire($trigger, $fresh, ['ref' => 'appt:' . $apptId, 'appointment_id' => $apptId]);
        }
    }
}
