<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\CommPlanModel;
use Components\LegalCase\Models\ContactModel;
use Components\LegalCase\Models\MatterModel;
use Components\LegalCase\Models\ParticipantModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\TeamModel;

/**
 * Alta, edición y ciclo de vida del asunto, con su equipo, sus partes y su plan de
 * comunicación.
 *
 * Al abrir un asunto se crean en la MISMA transacción: el consecutivo, el plan de
 * comunicación con la cadencia del despacho (§12: «plan de comunicación» desde el
 * MVP), el abogado responsable en el equipo y el cliente como parte. Así ningún
 * asunto nace sin plan ni sin responsable.
 */
class MatterService
{
    /** Campos que el formulario puede escribir. El resto los gobierna el servicio. */
    private const EDITABLE = [
        'title', 'public_title', 'specialty', 'country', 'jurisdiction', 'visibility', 'sensitivity',
        'risk_level', 'client_contact_id', 'lead_lawyer_id', 'description', 'objectives', 'strategy',
        'public_stage', 'stage_hidden',
    ];

    public function __construct(private ?PlanGuard $guard = null)
    {
        $this->guard ??= new PlanGuard();
    }

    /** Filtra y normaliza lo que llega del formulario. */
    private function clean(array $in, int $companyId): array
    {
        $out = [];
        foreach (self::EDITABLE as $k) {
            if (array_key_exists($k, $in)) {
                $out[$k] = is_string($in[$k]) ? trim($in[$k]) : $in[$k];
            }
        }
        $out['stage_hidden'] = !empty($in['stage_hidden']) ? 1 : 0;
        $out['country']      = strtoupper((string) ($out['country'] ?? 'CO')) ?: 'CO';
        $out['visibility']   = in_array($out['visibility'] ?? '', MatterModel::VISIBILITIES, true) ? $out['visibility'] : 'firm';
        $out['sensitivity']  = in_array($out['sensitivity'] ?? '', MatterModel::SENSITIVITIES, true) ? $out['sensitivity'] : 'normal';
        $out['risk_level']   = in_array($out['risk_level'] ?? '', MatterModel::RISKS, true) ? $out['risk_level'] : 'medium';

        // Referencias: solo si son de ESTA empresa. Lo demás se descarta, no se confía.
        $contactId = (int) ($out['client_contact_id'] ?? 0);
        $out['client_contact_id'] = $contactId && (new ContactModel())->own($companyId, $contactId) ? $contactId : null;

        $lawyerId = (int) ($out['lead_lawyer_id'] ?? 0);
        $out['lead_lawyer_id'] = $lawyerId && Team::isMember($companyId, $lawyerId) ? $lawyerId : null;

        return $out;
    }

    public function create(array $in, Access $acc): Result
    {
        $companyId = $acc->companyId;
        if (!$this->guard->serviceEnabled($companyId)) {
            return Result::fail(lang('Lgc.errPlanDisabled'));
        }
        $left = $this->guard->mattersLeft($companyId);
        if ($left !== null && $left < 1) {
            return Result::fail(lang('Lgc.errQuotaMatters'));
        }

        $data = $this->clean($in, $companyId);
        $data['company_id'] = $companyId;
        $data['status']     = 'open';
        $data['opened_at']  = date('Y-m-d H:i:s');
        $data['created_by'] = $acc->userId ?: null;
        $data['lead_lawyer_id'] ??= $acc->userId ?: null;

        $settings = (new SettingsModel())->forCompanyOrDefault($companyId);
        $cadence  = max(1, (int) ($settings['default_cadence_days'] ?? 15));
        $sla      = max(1, (int) ($settings['response_sla_hours'] ?? 48));

        $db = db_connect();
        $db->transStart();

        $data['number'] = $this->nextNumber($companyId, (string) ($settings['matter_prefix'] ?? 'LC'));
        $data['next_update_due_at'] = date('Y-m-d H:i:s', strtotime('+' . $cadence . ' days'));

        $model = new MatterModel();
        if (!$model->insert($data)) {
            $db->transRollback();

            return Result::fail(implode(' ', $model->errors()));
        }
        $matterId = (int) $model->getInsertID();

        (new CommPlanModel())->insert([
            'company_id'         => $companyId,
            'matter_id'          => $matterId,
            'mode'               => 'hybrid',
            'cadence_days'       => $cadence,
            'response_sla_hours' => $sla,
            'next_update_due_at' => $data['next_update_due_at'],
            'owner_id'           => $data['lead_lawyer_id'],
            'is_active'          => 1,
        ]);

        if ($data['lead_lawyer_id']) {
            (new TeamModel())->insert([
                'company_id' => $companyId, 'matter_id' => $matterId, 'user_id' => $data['lead_lawyer_id'],
                'role' => 'responsible', 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($data['client_contact_id']) {
            (new ParticipantModel())->insert([
                'company_id' => $companyId, 'matter_id' => $matterId,
                'contact_id' => $data['client_contact_id'], 'role' => 'client', 'portal_access' => 0,
            ]);
        }

        $db->transComplete();
        if (!$db->transStatus()) {
            return Result::fail(lang('Lgc.errSave'));
        }

        Audit::log($companyId, $matterId, 'matter.created', 'matter', $matterId, ['number' => $data['number']], $acc->isSystem() ? 'system' : 'user', $acc->userId ?: null);

        return Result::ok(lang('Lgc.okMatterCreated', [$data['number']]), $matterId);
    }

    public function update(array $matter, array $in, Access $acc): Result
    {
        if (!$acc->canSeeMatter($matter)) {
            return Result::fail(lang('Lgc.errNotFound'));
        }

        $data  = $this->clean($in, $acc->companyId);
        $model = new MatterModel();
        if (!$model->update((int) $matter['id'], $data)) {
            return Result::fail(implode(' ', $model->errors()));
        }

        if ($data['lead_lawyer_id'] && (int) $data['lead_lawyer_id'] !== (int) $matter['lead_lawyer_id']) {
            $this->addTeam($matter, (int) $data['lead_lawyer_id'], 'responsible', $acc);
        }
        if ($data['client_contact_id'] && (int) $data['client_contact_id'] !== (int) $matter['client_contact_id']) {
            $this->addParticipant($matter, (int) $data['client_contact_id'], 'client', $acc);
        }

        Audit::log($acc->companyId, (int) $matter['id'], 'matter.updated', 'matter', (int) $matter['id']);

        return Result::ok(lang('Lgc.okSaved'), (int) $matter['id']);
    }

    public function close(array $matter, string $reason, Access $acc): Result
    {
        if (!in_array($reason, MatterModel::CLOSE_REASONS, true)) {
            return Result::fail(lang('Lgc.errCloseReason'));
        }
        if ($matter['status'] === 'closed') {
            return Result::fail(lang('Lgc.errAlreadyClosed'));
        }

        (new MatterModel())->update((int) $matter['id'], [
            'status' => 'closed', 'closed_at' => date('Y-m-d H:i:s'), 'close_reason' => $reason,
        ]);
        // Un asunto cerrado no genera recordatorios de comunicación.
        (new CommPlanModel())->where('matter_id', (int) $matter['id'])->set(['is_active' => 0])->update();
        Audit::log($acc->companyId, (int) $matter['id'], 'matter.closed', 'matter', (int) $matter['id'], ['reason' => $reason]);

        return Result::ok(lang('Lgc.okMatterClosed'));
    }

    public function reopen(array $matter, Access $acc): Result
    {
        if ($matter['status'] !== 'closed') {
            return Result::fail(lang('Lgc.errNotClosed'));
        }
        (new MatterModel())->update((int) $matter['id'], ['status' => 'open', 'closed_at' => null, 'close_reason' => '']);
        (new CommPlanModel())->where('matter_id', (int) $matter['id'])->set(['is_active' => 1])->update();
        Audit::log($acc->companyId, (int) $matter['id'], 'matter.reopened', 'matter', (int) $matter['id']);

        return Result::ok(lang('Lgc.okMatterReopened'));
    }

    public function setOnHold(array $matter, bool $onHold, Access $acc): Result
    {
        if ($matter['status'] === 'closed') {
            return Result::fail(lang('Lgc.errAlreadyClosed'));
        }
        (new MatterModel())->update((int) $matter['id'], ['status' => $onHold ? 'on_hold' : 'open']);
        Audit::log($acc->companyId, (int) $matter['id'], $onHold ? 'matter.on_hold' : 'matter.resumed', 'matter', (int) $matter['id']);

        return Result::ok(lang('Lgc.okSaved'));
    }

    public function delete(array $matter, Access $acc): Result
    {
        // Soft-delete: los hijos quedan colgando de una fila invisible y ninguna consulta
        // (ni del portal) los alcanza, porque todas resuelven el asunto primero.
        (new MatterModel())->delete((int) $matter['id']);
        Audit::log($acc->companyId, (int) $matter['id'], 'matter.deleted', 'matter', (int) $matter['id'], ['number' => $matter['number']]);

        return Result::ok(lang('Lgc.okMatterDeleted', [$matter['number']]));
    }

    // ------------------------------------------------------------------ equipo y partes

    public function addTeam(array $matter, int $userId, string $role, Access $acc): Result
    {
        if (!in_array($role, TeamModel::ROLES, true) || !Team::isMember($acc->companyId, $userId)) {
            return Result::fail(lang('Lgc.errTeamUser'));
        }
        $team = new TeamModel();
        $row  = $team->where('matter_id', (int) $matter['id'])->where('user_id', $userId)->first();
        if ($row) {
            $team->update((int) $row['id'], ['role' => $role]);
        } else {
            $team->insert([
                'company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'user_id' => $userId,
                'role' => $role, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        Audit::log($acc->companyId, (int) $matter['id'], 'team.set', 'user', $userId, ['role' => $role]);

        return Result::ok(lang('Lgc.okTeam'));
    }

    public function removeTeam(array $matter, int $rowId, Access $acc): Result
    {
        $team = new TeamModel();
        $row  = $team->where('matter_id', (int) $matter['id'])->find($rowId);
        if (!$row) {
            return Result::fail(lang('Lgc.errNotFound'));
        }
        if ((int) $row['user_id'] === (int) $matter['lead_lawyer_id']) {
            return Result::fail(lang('Lgc.errTeamLead'));
        }
        $team->delete($rowId);
        Audit::log($acc->companyId, (int) $matter['id'], 'team.removed', 'user', (int) $row['user_id']);

        return Result::ok(lang('Lgc.okTeamRemoved'));
    }

    public function addParticipant(array $matter, int $contactId, string $role, Access $acc, string $notes = ''): Result
    {
        if (!in_array($role, ParticipantModel::ROLES, true) || !(new ContactModel())->own($acc->companyId, $contactId)) {
            return Result::fail(lang('Lgc.errParticipant'));
        }
        $model = new ParticipantModel();
        $exists = $model->where('matter_id', (int) $matter['id'])->where('contact_id', $contactId)->where('role', $role)->first();
        if ($exists) {
            return Result::fail(lang('Lgc.errParticipantDup'));
        }
        $model->insert([
            'company_id' => $acc->companyId, 'matter_id' => (int) $matter['id'], 'contact_id' => $contactId,
            'role' => $role, 'portal_access' => 0, 'notes' => mb_substr($notes, 0, 255),
        ]);
        Audit::log($acc->companyId, (int) $matter['id'], 'participant.added', 'contact', $contactId, ['role' => $role]);

        return Result::ok(lang('Lgc.okParticipant'));
    }

    public function removeParticipant(array $matter, int $rowId, Access $acc): Result
    {
        $model = new ParticipantModel();
        $row   = $model->where('matter_id', (int) $matter['id'])->find($rowId);
        if (!$row) {
            return Result::fail(lang('Lgc.errNotFound'));
        }
        $model->delete($rowId);
        // Quitar la parte retira su acceso al portal para este asunto.
        Audit::log($acc->companyId, (int) $matter['id'], 'participant.removed', 'contact', (int) $row['contact_id'], ['role' => $row['role'], 'had_portal' => (int) $row['portal_access']]);

        return Result::ok(lang('Lgc.okParticipantRemoved'));
    }

    // ------------------------------------------------------------------ plan de comunicación

    public function savePlan(array $matter, array $in, Access $acc): Result
    {
        $plans = new CommPlanModel();
        $plan  = $plans->where('matter_id', (int) $matter['id'])->first();

        $mode    = in_array($in['mode'] ?? '', CommPlanModel::MODES, true) ? $in['mode'] : 'hybrid';
        $cadence = (int) ($in['cadence_days'] ?? 15);
        $sla     = (int) ($in['response_sla_hours'] ?? 48);
        $next    = trim((string) ($in['next_update_due_at'] ?? ''));
        $ownerId = (int) ($in['owner_id'] ?? 0);

        $data = [
            'company_id'         => $acc->companyId,
            'matter_id'          => (int) $matter['id'],
            'mode'               => $mode,
            'cadence_days'       => $cadence,
            'response_sla_hours' => $sla,
            'is_active'          => !empty($in['is_active']) ? 1 : 0,
            'owner_id'           => $ownerId && Team::isMember($acc->companyId, $ownerId) ? $ownerId : ($plan['owner_id'] ?? null),
            'notes'              => mb_substr(trim((string) ($in['notes'] ?? '')), 0, 255),
            'next_update_due_at' => $next !== '' && strtotime($next) ? date('Y-m-d H:i:s', strtotime($next)) : null,
        ];
        if (!CommPlanModel::isPeriodic($mode)) {
            // Sin cadencia, no hay fecha comprometida que vigilar.
            $data['next_update_due_at'] = null;
        } elseif ($data['next_update_due_at'] === null) {
            $data['next_update_due_at'] = date('Y-m-d H:i:s', strtotime('+' . max(1, $cadence) . ' days'));
        }

        $ok = $plan ? $plans->update((int) $plan['id'], $data) : $plans->insert($data);
        if (!$ok) {
            return Result::fail(implode(' ', $plans->errors()));
        }
        (new MatterModel())->update((int) $matter['id'], ['next_update_due_at' => $data['next_update_due_at']]);
        Audit::log($acc->companyId, (int) $matter['id'], 'plan.saved', 'plan', (int) ($plan['id'] ?? $plans->getInsertID()), ['mode' => $mode, 'cadence' => $cadence]);

        return Result::ok(lang('Lgc.okPlan'));
    }

    // ------------------------------------------------------------------ consecutivo

    /**
     * Siguiente número `PREFIJO-000001`. El incremento se evalúa en el servidor de BD
     * dentro de la transacción del alta: dos altas simultáneas no obtienen el mismo
     * número y un alta fallida no gasta ninguno.
     */
    private function nextNumber(int $companyId, string $prefix): string
    {
        $db = db_connect();
        $db->table('cmp_lgc_settings')->where('company_id', $companyId)
            ->set('matter_sequence', 'matter_sequence + 1', false)->update();
        $seq = (int) ($db->table('cmp_lgc_settings')->select('matter_sequence')->where('company_id', $companyId)
            ->get()->getRowArray()['matter_sequence'] ?? 1);

        $prefix = preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?: 'LC';

        return strtoupper($prefix) . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
