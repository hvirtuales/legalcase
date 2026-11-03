<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\HearingModel;
use Components\LegalCase\Models\ProceedingModel;
use Components\LegalCase\Models\TaskModel;

/**
 * Audiencias y diligencias (§6.5 «Audiencia programada»).
 *
 *  · Modalidad presencial, virtual o mixta, con lugar o enlace (solo https).
 *  · Al programarla se crea la tarea de preparación para el responsable.
 *  · Compartirla con el cliente es decisión del abogado (`lgc.share`): el portal muestra fecha,
 *    modalidad, lugar o enlace y las indicaciones para el cliente; nunca las notas internas.
 *  · Reprogramar (fecha, lugar o enlace) una audiencia compartida avisa al cliente, y también
 *    aplazarla o cancelarla (aviso mínimo, una vez por cambio). La tarea de preparación se mueve
 *    con la fecha.
 *  · Una audiencia compartida y vigente no se elimina: se cancela, y el portal lo muestra.
 *  · El reloj avisa al responsable el día anterior.
 */
class HearingService
{
    public function __construct(private ?Outbox $outbox = null)
    {
        $this->outbox ??= new Outbox();
    }

    private function clean(array $in): array
    {
        $at = trim((string) ($in['scheduled_at'] ?? ''));

        return [
            'title'             => trim((string) ($in['title'] ?? '')),
            'scheduled_at'      => $at !== '' && strtotime($at) ? date('Y-m-d H:i:s', strtotime($at)) : null,
            'duration_min'      => max(5, min(1440, (int) ($in['duration_min'] ?? 60))),
            'mode'              => in_array($in['mode'] ?? '', HearingModel::MODES, true) ? $in['mode'] : 'in_person',
            'location'          => mb_substr(trim((string) ($in['location'] ?? '')), 0, 255),
            'link'              => mb_substr(trim((string) ($in['link'] ?? '')), 0, 500),
            'client_attendance' => !empty($in['client_attendance']) ? 1 : 0,
            'client_notes'      => trim((string) ($in['client_notes'] ?? '')),
            'internal_notes'    => trim((string) ($in['internal_notes'] ?? '')),
        ];
    }

    public function save(array $matter, array $in, Access $acc, ?array $existing = null): Result
    {
        $data = $this->clean($in);
        if ($data['mode'] !== 'in_person' && $data['link'] === '' && $data['location'] === '') {
            return Result::fail(lang('Lgc.vHearingPlace'));
        }
        $proc = (int) ($in['proceeding_id'] ?? 0);
        $data['proceeding_id'] = $proc && (new ProceedingModel())->where('matter_id', (int) $matter['id'])->find($proc) ? $proc : null;

        $share = !empty($in['is_shared']);
        if (!$acc->can('lgc.share')) {
            // Compartir o dejar de compartir es decisión del abogado: quien no tiene lgc.share
            // no lo puede activar, y al editar conserva lo que ya estaba decidido.
            if ($share && !($existing && (int) $existing['is_shared'])) {
                return Result::fail(lang('Lgc.errNeedShare'));
            }
            $share = $existing ? (bool) (int) $existing['is_shared'] : false;
        }
        $data['is_shared'] = $share ? 1 : 0;

        $model = new HearingModel();
        if ($existing) {
            if (!$model->update((int) $existing['id'], $data)) {
                return Result::fail(implode(' ', $model->errors()));
            }
            $id      = (int) $existing['id'];
            $changed = $existing['scheduled_at'] !== $data['scheduled_at'] || $existing['link'] !== $data['link'] || $existing['location'] !== $data['location'];
            if ($existing['scheduled_at'] !== $data['scheduled_at']) {
                $this->reschedule($matter, $existing, (string) $data['scheduled_at'], $acc);
            }
            Audit::log($acc->companyId, (int) $matter['id'], 'hearing.updated', 'hearing', $id, ['rescheduled' => $changed]);
            if ($share && ($changed || !(int) $existing['is_shared'])) {
                // Cada combinación de fecha/lugar/enlace es un aviso distinto: reprogramar vuelve a avisar,
                // guardar sin cambios no.
                $version = 'hearing' . substr(md5((string) $data['scheduled_at'] . '|' . $data['link'] . '|' . $data['location']), 0, 8);
                $this->outbox->notifyMatter($matter, 'hearing', $version, $id);
            }

            return Result::ok(lang('Lgc.okSaved'), $id);
        }

        $data += ['company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'status' => 'scheduled', 'created_by' => $acc->userId ?: null];
        if (!$model->insert($data)) {
            return Result::fail(implode(' ', $model->errors()));
        }
        $id = (int) $model->getInsertID();

        // Preparación: una tarea para el responsable dos días hábiles antes (o ya, si no da tiempo).
        $prep = strtotime((string) $data['scheduled_at']) - 2 * 86400;
        (new TaskModel())->insert([
            'company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'kind' => 'task',
            'title' => lang('Lgc.taskHearingPrep', [$data['title']]), 'due_at' => date('Y-m-d H:i:s', max(time(), $prep)),
            'assigned_to' => $matter['lead_lawyer_id'] ?: ($acc->userId ?: null), 'status' => 'open', 'ref' => 'hearing_prep:' . $id,
        ]);
        Audit::log($acc->companyId, (int) $matter['id'], 'hearing.created', 'hearing', $id, ['at' => $data['scheduled_at'], 'shared' => $data['is_shared']]);
        if ($share) {
            $this->outbox->notifyMatter($matter, 'hearing', 'hearing', $id);
        }

        return Result::ok(lang('Lgc.okHearing'), $id);
    }

    /**
     * Nueva fecha: una audiencia aplazada vuelve a estar programada y la tarea de preparación
     * se mueve con ella (o se reabre si el aplazamiento la había cancelado).
     */
    private function reschedule(array $matter, array $h, string $at, Access $acc): void
    {
        $hid = (int) $h['id'];
        if ($h['status'] === 'postponed' && strtotime($at) > time()) {
            (new HearingModel())->update($hid, ['status' => 'scheduled']);
        }
        if (in_array($h['status'], ['held', 'cancelled'], true)) {
            return;
        }
        $due   = date('Y-m-d H:i:s', max(time(), strtotime($at) - 2 * 86400));
        $tasks = new TaskModel();
        $prep  = $tasks->where('company_id', $acc->companyId)->where('ref', 'hearing_prep:' . $hid)->orderBy('id', 'DESC')->first();
        if ($prep) {
            $tasks->update((int) $prep['id'], ['due_at' => $due, 'status' => 'open', 'done_at' => null]);

            return;
        }
        $tasks->insert([
            'company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'kind' => 'task',
            'title' => lang('Lgc.taskHearingPrep', [$h['title']]), 'due_at' => $due,
            'assigned_to' => $matter['lead_lawyer_id'] ?: ($acc->userId ?: null), 'status' => 'open', 'ref' => 'hearing_prep:' . $hid,
        ]);
    }

    /**
     * Eliminar es para un registro equivocado. Una audiencia que el cliente ya ve y sigue viva
     * no se borra: se cancela, para que el portal lo diga y el cliente reciba el aviso.
     */
    public function delete(array $matter, array $h, Access $acc): Result
    {
        if ((int) $h['is_shared'] && in_array($h['status'], ['scheduled', 'postponed'], true)) {
            return Result::fail(lang('Lgc.errHearingShared'));
        }
        (new HearingModel())->delete((int) $h['id']);
        (new TaskModel())->where('company_id', $acc->companyId)->where('ref', 'hearing_prep:' . (int) $h['id'])->where('status', 'open')
            ->set(['status' => 'cancelled', 'done_at' => date('Y-m-d H:i:s')])->update();
        Audit::log($acc->companyId, (int) $matter['id'], 'hearing.deleted', 'hearing', (int) $h['id'], ['title' => $h['title'], 'at' => $h['scheduled_at']]);

        return Result::ok(lang('Lgc.okDeleted'));
    }

    public function setStatus(array $matter, array $h, string $status, Access $acc): Result
    {
        if (!in_array($status, HearingModel::STATUSES, true)) {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        (new HearingModel())->update((int) $h['id'], ['status' => $status]);
        if ($status !== 'scheduled') {
            (new TaskModel())->where('company_id', $acc->companyId)->where('ref', 'hearing_prep:' . (int) $h['id'])->where('status', 'open')
                ->set(['status' => $status === 'held' ? 'done' : 'cancelled', 'done_at' => date('Y-m-d H:i:s')])->update();
        }
        Audit::log($acc->companyId, (int) $matter['id'], 'hearing.' . $status, 'hearing', (int) $h['id']);

        // Si el cliente la ve, aplazarla o cancelarla es una novedad para él (aviso mínimo, una vez por estado).
        if ((int) $h['is_shared'] && $status !== $h['status'] && in_array($status, ['postponed', 'cancelled'], true)) {
            $this->outbox->notifyMatter($matter, 'hearing', 'hearing_' . $status, (int) $h['id']);
        }

        return Result::ok(lang('Lgc.okSaved'));
    }
}
