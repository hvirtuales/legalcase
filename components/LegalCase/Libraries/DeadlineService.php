<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\DeadlineModel;
use Components\LegalCase\Models\EventModel;
use Components\LegalCase\Models\ProceedingModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\TaskModel;

/**
 * Plazos (registro MANUAL), tareas, procesos y actuaciones.
 *
 * El componente no calcula términos judiciales ni deja que nadie —ni una IA— fije la
 * fecha legal final (§5 LC-05). El plazo nace `to_validate` y pasa a `validated`:
 *  · política `single`: con una validación de alguien con `lgc.approve`;
 *  · política `dual`: un plazo CRÍTICO necesita dos personas distintas.
 */
class DeadlineService
{
    private function date(?string $v, bool $withTime = true): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || !strtotime($v)) {
            return null;
        }

        return date($withTime ? 'Y-m-d H:i:s' : 'Y-m-d', strtotime($v));
    }

    private function ownChild(string $modelClass, int $matterId, int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }

        return (new $modelClass())->where('matter_id', $matterId)->find($id) ? $id : null;
    }

    // ------------------------------------------------------------------ plazos

    public function createDeadline(array $matter, array $in, Access $acc): Result
    {
        $mid  = (int) $matter['id'];
        $data = [
            'company_id'    => $acc->companyId,
            'matter_id'     => $mid,
            'proceeding_id' => $this->ownChild(ProceedingModel::class, $mid, (int) ($in['proceeding_id'] ?? 0)),
            'event_id'      => $this->ownChild(EventModel::class, $mid, (int) ($in['event_id'] ?? 0)),
            'title'         => trim((string) ($in['title'] ?? '')),
            'basis'         => trim((string) ($in['basis'] ?? '')),
            'starts_on'     => $this->date($in['starts_on'] ?? null, false),
            'due_at'        => $this->date($in['due_at'] ?? null),
            'is_critical'   => !empty($in['is_critical']) ? 1 : 0,
            'status'        => 'to_validate',
            'assigned_to'   => Team::isMember($acc->companyId, (int) ($in['assigned_to'] ?? 0)) ? (int) $in['assigned_to'] : ($matter['lead_lawyer_id'] ?: null),
            'created_by'    => $acc->userId ?: null,
        ];
        $model = new DeadlineModel();
        if (!$model->insert($data)) {
            return Result::fail(implode(' ', $model->errors()));
        }
        $id = (int) $model->getInsertID();
        Audit::log($acc->companyId, $mid, 'deadline.created', 'deadline', $id, ['due' => $data['due_at'], 'critical' => $data['is_critical']]);

        return Result::ok(lang('Lgc.okDeadline'), $id);
    }

    public function validate(array $d, Access $acc): Result
    {
        if ($d['status'] !== 'to_validate') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        $policy = (new SettingsModel())->forCompanyOrDefault($acc->companyId)['deadline_policy'] ?? 'single';
        $now    = date('Y-m-d H:i:s');
        $model  = new DeadlineModel();

        if ($policy === 'dual' && (int) $d['is_critical'] === 1) {
            if (empty($d['validated_by'])) {
                $model->update((int) $d['id'], ['validated_by' => $acc->userId ?: null, 'validated_at' => $now]);
                Audit::log($acc->companyId, (int) $d['matter_id'], 'deadline.validated_first', 'deadline', (int) $d['id']);

                return Result::ok(lang('Lgc.okDeadlineFirst'));
            }
            if ((int) $d['validated_by'] === $acc->userId) {
                return Result::fail(lang('Lgc.errDualSame'));
            }
            $model->update((int) $d['id'], ['second_validated_by' => $acc->userId ?: null, 'second_validated_at' => $now, 'status' => 'validated']);
        } else {
            $model->update((int) $d['id'], ['validated_by' => $acc->userId ?: null, 'validated_at' => $now, 'status' => 'validated']);
        }
        Audit::log($acc->companyId, (int) $d['matter_id'], 'deadline.validated', 'deadline', (int) $d['id']);

        return Result::ok(lang('Lgc.okDeadlineValidated'));
    }

    public function setDeadlineStatus(array $d, string $status, Access $acc): Result
    {
        if (!in_array($status, ['done', 'cancelled'], true) || !in_array($d['status'], DeadlineModel::OPEN, true)) {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        (new DeadlineModel())->update((int) $d['id'], ['status' => $status, 'done_at' => $status === 'done' ? date('Y-m-d H:i:s') : null]);
        Audit::log($acc->companyId, (int) $d['matter_id'], 'deadline.' . $status, 'deadline', (int) $d['id']);

        return Result::ok(lang('Lgc.okSaved'));
    }

    // ------------------------------------------------------------------ tareas

    public function createTask(array $matter, array $in, Access $acc): Result
    {
        $data = [
            'company_id'  => $acc->companyId,
            'matter_id'   => (int) $matter['id'],
            'kind'        => 'task',
            'title'       => trim((string) ($in['title'] ?? '')),
            'detail'      => trim((string) ($in['detail'] ?? '')),
            'due_at'      => $this->date($in['due_at'] ?? null),
            'assigned_to' => Team::isMember($acc->companyId, (int) ($in['assigned_to'] ?? 0)) ? (int) $in['assigned_to'] : ($acc->userId ?: null),
            'status'      => 'open',
            'created_by'  => $acc->userId ?: null,
        ];
        $model = new TaskModel();
        if (!$model->insert($data)) {
            return Result::fail(implode(' ', $model->errors()));
        }

        return Result::ok(lang('Lgc.okTask'), (int) $model->getInsertID());
    }

    public function setTaskStatus(array $t, string $status, Access $acc): Result
    {
        if (!in_array($status, TaskModel::STATUSES, true)) {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        (new TaskModel())->update((int) $t['id'], ['status' => $status, 'done_at' => $status === 'done' ? date('Y-m-d H:i:s') : null]);

        return Result::ok(lang('Lgc.okSaved'));
    }

    // ------------------------------------------------------------------ procesos y actuaciones

    public function saveProceeding(array $matter, array $in, Access $acc, ?array $existing = null): Result
    {
        $data = [
            'company_id'     => $acc->companyId,
            'matter_id'      => (int) $matter['id'],
            'kind'           => (string) ($in['kind'] ?? 'judicial'),
            'filing_number'  => trim((string) ($in['filing_number'] ?? '')),
            'authority'      => trim((string) ($in['authority'] ?? '')),
            'jurisdiction'   => trim((string) ($in['jurisdiction'] ?? '')),
            'procedure_type' => trim((string) ($in['procedure_type'] ?? '')),
            'stage'          => trim((string) ($in['stage'] ?? '')),
            'status'         => (string) ($in['status'] ?? 'active'),
            'started_on'     => $this->date($in['started_on'] ?? null, false),
            'ended_on'       => $this->date($in['ended_on'] ?? null, false),
            'notes'          => trim((string) ($in['notes'] ?? '')),
        ];
        $model = new ProceedingModel();
        if ($existing) {
            unset($data['company_id'], $data['matter_id']);
            $ok = $model->update((int) $existing['id'], $data);
            $id = (int) $existing['id'];
        } else {
            $data['created_by'] = $acc->userId ?: null;
            $ok = $model->insert($data);
            $id = (int) $model->getInsertID();
        }
        if (!$ok) {
            return Result::fail(implode(' ', $model->errors()));
        }
        Audit::log($acc->companyId, (int) $matter['id'], $existing ? 'proceeding.updated' : 'proceeding.created', 'proceeding', $id, ['filing' => $data['filing_number']]);

        return Result::ok(lang('Lgc.okSaved'), $id);
    }

    public function createEvent(array $matter, array $in, Access $acc): Result
    {
        $mid  = (int) $matter['id'];
        $data = [
            'company_id'    => $acc->companyId,
            'matter_id'     => $mid,
            'proceeding_id' => $this->ownChild(ProceedingModel::class, $mid, (int) ($in['proceeding_id'] ?? 0)),
            'occurred_at'   => $this->date($in['occurred_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'kind'          => (string) ($in['kind'] ?? 'other'),
            'title'         => trim((string) ($in['title'] ?? '')),
            'detail'        => trim((string) ($in['detail'] ?? '')),
            'source'        => trim((string) ($in['source'] ?? '')),
            'source_ref'    => trim((string) ($in['source_ref'] ?? '')),
            'is_relevant'   => !empty($in['is_relevant']) ? 1 : 0,
            'created_by'    => $acc->userId ?: null,
        ];
        $model = new EventModel();
        if (!$model->insert($data)) {
            return Result::fail(implode(' ', $model->errors()));
        }
        $id = (int) $model->getInsertID();
        Audit::log($acc->companyId, $mid, 'event.created', 'event', $id, ['relevant' => $data['is_relevant']]);

        return Result::ok($data['is_relevant'] ? lang('Lgc.okEventRelevant') : lang('Lgc.okEvent'), $id);
    }
}
