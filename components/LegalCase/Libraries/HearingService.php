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
 *  · Cambiar fecha o estado de una audiencia compartida avisa al cliente (aviso mínimo).
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
        if ($share && !$acc->can('lgc.share')) {
            return Result::fail(lang('Lgc.errNeedShare'));
        }
        $data['is_shared'] = $share ? 1 : 0;

        $model = new HearingModel();
        if ($existing) {
            if (!$model->update((int) $existing['id'], $data)) {
                return Result::fail(implode(' ', $model->errors()));
            }
            $id      = (int) $existing['id'];
            $changed = $existing['scheduled_at'] !== $data['scheduled_at'] || $existing['link'] !== $data['link'] || $existing['location'] !== $data['location'];
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

        return Result::ok(lang('Lgc.okSaved'));
    }
}
