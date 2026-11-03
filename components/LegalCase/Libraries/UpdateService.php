<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\CommPlanModel;
use Components\LegalCase\Models\MatterModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\TaskModel;
use Components\LegalCase\Models\UpdateModel;

/**
 * Ciclo de vida de una actualización al cliente (§6.6, §8.5):
 *
 *   draft ──submit──▶ pending_review ──approve──▶ approved ──publish──▶ published
 *     ▲                    │ return                                       │ revise
 *     └────────────────────┘                                              ▼
 *                                                       (nueva versión) draft … ▶ published
 *                                                       la anterior pasa a superseded
 *   published ──revoke──▶ revoked
 *
 * Reglas que hace valer el SERVICIO (no la vista):
 *  · Política `self`: quien tiene `lgc.approve` puede publicar su propio borrador; la
 *    aprobación queda registrada a su nombre.
 *  · Política `reviewer`: aprobar exige una persona DISTINTA del autor.
 *  · «Sin novedades» exige fuente consultada y fecha de verificación (§6.13-4).
 *  · Un texto publicado no se edita: se revisa creando otra versión.
 *  · Revocar no promete borrar lo ya leído: se informa con transparencia (§8.5).
 */
class UpdateService
{
    /** Expresiones que el documento maestro pide no usar (§1.5, §6.6, §10). Solo avisan. */
    private const RISKY = [
        'es' => ['garantiz', 'seguro que gan', '100%', 'sin duda gan', 'ganaremos', 'probabilidad de éxito', 'porcentaje de éxito'],
        'en' => ['guarantee', 'we will win', '100%', 'certain to win', 'chance of success', 'success rate'],
    ];

    private const TEXT_FIELDS = ['title', 'what_happened', 'what_it_means', 'what_we_do', 'client_action', 'stage_label', 'verified_source'];

    public function __construct(private ?Outbox $outbox = null)
    {
        $this->outbox ??= new Outbox();
    }

    private function settings(int $companyId): array
    {
        return (new SettingsModel())->forCompanyOrDefault($companyId);
    }

    /** Normaliza lo que llega del formulario. */
    private function clean(array $in): array
    {
        $out = [];
        foreach (self::TEXT_FIELDS as $f) {
            if (array_key_exists($f, $in)) {
                $out[$f] = trim((string) $in[$f]);
            }
        }
        foreach (['next_update_at', 'verified_at'] as $f) {
            if (array_key_exists($f, $in)) {
                $v       = trim((string) $in[$f]);
                $out[$f] = $v !== '' && strtotime($v) ? date('Y-m-d H:i:s', strtotime($v)) : null;
            }
        }

        return $out;
    }

    /** Avisos de redacción (no bloquean): promesas de resultado o porcentajes de éxito. */
    public function warnings(array $update): array
    {
        $text = mb_strtolower(implode(' ', array_map(static fn ($f) => (string) ($update[$f] ?? ''), self::TEXT_FIELDS)));
        $hits = [];
        foreach (self::RISKY as $words) {
            foreach ($words as $w) {
                if (str_contains($text, $w)) {
                    $hits[] = $w;
                }
            }
        }

        return array_values(array_unique($hits));
    }

    /** Valida lo que una actualización necesita para salir al cliente. */
    private function readyProblems(array $u): array
    {
        $p = [];
        if (trim((string) $u['title']) === '') {
            $p[] = lang('Lgc.vTitle');
        }
        if (trim((string) $u['what_happened']) === '') {
            $p[] = lang('Lgc.vWhatHappened');
        }
        if ($u['kind'] === 'no_news' && (trim((string) $u['verified_source']) === '' || empty($u['verified_at']))) {
            $p[] = lang('Lgc.vNoNewsSource');
        }

        return $p;
    }

    public function createDraft(array $matter, string $kind, array $in, Access $acc): Result
    {
        if ($matter['status'] === 'closed' && $kind !== 'closing') {
            return Result::fail(lang('Lgc.errAlreadyClosed'));
        }
        $kind = in_array($kind, UpdateModel::KINDS, true) ? $kind : 'regular';
        $data = $this->clean($in) + [
            'company_id' => $acc->companyId,
            'matter_id'  => (int) $matter['id'],
            'kind'       => $kind,
            'status'     => 'draft',
            'version'    => 1,
            'author_id'  => $acc->userId ?: null,
        ];
        if (($data['title'] ?? '') === '') {
            $data['title'] = lang('Lgc.updDefaultTitle_' . $kind);
        }

        $model = new UpdateModel();
        if (!$model->insert($data)) {
            return Result::fail(implode(' ', $model->errors()));
        }
        $id = (int) $model->getInsertID();
        Audit::log($acc->companyId, (int) $matter['id'], 'update.draft', 'update', $id, ['kind' => $kind]);

        return Result::ok(lang('Lgc.okDraft'), $id);
    }

    public function edit(array $u, array $in, Access $acc): Result
    {
        if (!in_array($u['status'], UpdateModel::EDITABLE, true)) {
            return Result::fail(lang('Lgc.errNotEditable'));
        }
        $data = $this->clean($in);
        // Editar algo que estaba en revisión lo devuelve a borrador: lo aprobado es
        // exactamente lo que se revisó, no una versión posterior.
        $data['status'] = 'draft';
        (new UpdateModel())->update((int) $u['id'], $data);
        Audit::log($acc->companyId, (int) $u['matter_id'], 'update.edited', 'update', (int) $u['id']);

        return Result::ok(lang('Lgc.okSaved'), (int) $u['id']);
    }

    public function submit(array $u, Access $acc): Result
    {
        if ($u['status'] !== 'draft') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        if ($p = $this->readyProblems($u)) {
            return Result::fail(implode(' ', $p));
        }
        (new UpdateModel())->update((int) $u['id'], ['status' => 'pending_review', 'submitted_at' => date('Y-m-d H:i:s')]);
        Audit::log($acc->companyId, (int) $u['matter_id'], 'update.submitted', 'update', (int) $u['id']);

        return Result::ok(lang('Lgc.okSubmitted'));
    }

    public function approve(array $u, Access $acc): Result
    {
        if ($u['status'] !== 'pending_review') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        if ($p = $this->readyProblems($u)) {
            return Result::fail(implode(' ', $p));
        }
        $policy = $this->settings($acc->companyId)['approval_policy'] ?? 'self';
        if ($policy === 'reviewer' && (int) $u['author_id'] === $acc->userId) {
            return Result::fail(lang('Lgc.errSelfApprove'));
        }
        (new UpdateModel())->update((int) $u['id'], [
            'status' => 'approved', 'reviewed_by' => $acc->userId ?: null, 'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => '',
        ]);
        Audit::log($acc->companyId, (int) $u['matter_id'], 'update.approved', 'update', (int) $u['id']);

        return Result::ok(lang('Lgc.okApproved'));
    }

    public function sendBack(array $u, string $note, Access $acc): Result
    {
        if (!in_array($u['status'], ['pending_review', 'approved'], true)) {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        $note = trim($note);
        if ($note === '') {
            return Result::fail(lang('Lgc.vReviewNote'));
        }
        (new UpdateModel())->update((int) $u['id'], [
            'status' => 'draft', 'reviewed_by' => $acc->userId ?: null, 'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => mb_substr($note, 0, 255),
        ]);
        Audit::log($acc->companyId, (int) $u['matter_id'], 'update.returned', 'update', (int) $u['id']);

        return Result::ok(lang('Lgc.okReturned'));
    }

    /**
     * Publica. Con política `self` acepta un borrador del propio autor y deja registrada
     * la auto-aprobación; con `reviewer` exige que esté aprobado por otra persona.
     * Idempotente: publicar algo ya publicado no vuelve a avisar.
     */
    public function publish(array $u, array $matter, Access $acc): Result
    {
        if ($u['status'] === 'published') {
            return Result::ok(lang('Lgc.okPublished'));
        }
        if ($p = $this->readyProblems($u)) {
            return Result::fail(implode(' ', $p));
        }

        $policy = $this->settings($acc->companyId)['approval_policy'] ?? 'self';
        $now    = date('Y-m-d H:i:s');
        $data   = ['status' => 'published', 'published_by' => $acc->userId ?: null, 'published_at' => $now];

        if ($u['status'] !== 'approved') {
            if ($policy === 'reviewer' || !in_array($u['status'], UpdateModel::EDITABLE, true)) {
                return Result::fail(lang('Lgc.errNeedsApproval'));
            }
            // Política self: queda constancia de quién aprobó (él mismo) y cuándo.
            $data += ['reviewed_by' => $acc->userId ?: null, 'reviewed_at' => $now];
        }

        $plans = new CommPlanModel();
        $plan  = $plans->where('matter_id', (int) $matter['id'])->first();
        $next  = $u['next_update_at'] ?: ($plan && CommPlanModel::isPeriodic((string) $plan['mode']) && (int) $plan['is_active']
            ? date('Y-m-d H:i:s', strtotime('+' . max(1, (int) $plan['cadence_days']) . ' days'))
            : null);

        $db = db_connect();
        $db->transStart();

        $model = new UpdateModel();
        $model->update((int) $u['id'], $data + ['next_update_at' => $next]);
        if (!empty($u['supersedes_id'])) {
            (new UpdateModel())->where('matter_id', (int) $matter['id'])->update((int) $u['supersedes_id'], ['status' => 'superseded']);
        }

        $mUpd = ['last_published_at' => $now, 'next_update_due_at' => $next];
        if (trim((string) $u['stage_label']) !== '') {
            $mUpd['public_stage'] = $u['stage_label'];
        }
        (new MatterModel())->update((int) $matter['id'], $mUpd);
        if ($plan) {
            $plans->update((int) $plan['id'], ['next_update_due_at' => $next]);
        }

        // Cumplida la comunicación: se cierran los recordatorios «toca actualizar».
        (new TaskModel())->where('matter_id', (int) $matter['id'])->where('kind', 'update_due')->where('status', 'open')
            ->set(['status' => 'done', 'done_at' => $now])->update();

        $db->transComplete();
        if (!$db->transStatus()) {
            return Result::fail(lang('Lgc.errSave'));
        }

        Audit::log($acc->companyId, (int) $matter['id'], 'update.published', 'update', (int) $u['id'], [
            'kind' => $u['kind'], 'version' => (int) $u['version'], 'self_approved' => $u['status'] !== 'approved',
        ]);

        $queued = $this->outbox->notifyMatter($matter, 'update_published', 'update', (int) $u['id']);

        return Result::ok(lang('Lgc.okPublishedNotified', [$queued]));
    }

    public function revoke(array $u, string $reason, Access $acc): Result
    {
        if ($u['status'] !== 'published') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        $reason = trim($reason);
        if ($reason === '') {
            return Result::fail(lang('Lgc.vRevokeReason'));
        }
        (new UpdateModel())->update((int) $u['id'], [
            'status' => 'revoked', 'revoked_by' => $acc->userId ?: null, 'revoked_at' => date('Y-m-d H:i:s'),
            'revoke_reason' => mb_substr($reason, 0, 255),
        ]);
        Audit::log($acc->companyId, (int) $u['matter_id'], 'update.revoked', 'update', (int) $u['id'], ['reason' => mb_substr($reason, 0, 120)]);

        return Result::ok(lang('Lgc.okRevoked'));
    }

    /** Crea una nueva versión (borrador) a partir de una publicada. */
    public function revise(array $u, Access $acc): Result
    {
        if ($u['status'] !== 'published') {
            return Result::fail(lang('Lgc.errBadTransition'));
        }
        $model = new UpdateModel();
        $open  = $model->where('supersedes_id', (int) $u['id'])->whereIn('status', ['draft', 'pending_review', 'approved'])->first();
        if ($open) {
            return Result::ok(lang('Lgc.okRevisionExists'), (int) $open['id']);
        }

        $copy = array_intersect_key($u, array_flip(array_merge(self::TEXT_FIELDS, ['kind', 'next_update_at', 'verified_at'])));
        $copy += [
            'company_id' => $acc->companyId, 'matter_id' => (int) $u['matter_id'], 'status' => 'draft',
            'version' => (int) $u['version'] + 1, 'supersedes_id' => (int) $u['id'], 'author_id' => $acc->userId ?: null,
        ];
        $ins = new UpdateModel();
        $ins->insert($copy);
        $id = (int) $ins->getInsertID();
        Audit::log($acc->companyId, (int) $u['matter_id'], 'update.revision', 'update', $id, ['of' => (int) $u['id']]);

        return Result::ok(lang('Lgc.okRevision'), $id);
    }

    /**
     * Borrador sugerido de «sin novedades» (§6.10) a partir de datos verificables: la
     * última actuación registrada y el plan. No inventa progreso: si no hay gestiones
     * registradas, lo dice así.
     */
    public function noNewsTemplate(array $matter, ?array $lastEvent, ?string $nextDate): array
    {
        $since = $matter['last_published_at'] ?: $matter['opened_at'];

        return [
            'title'         => lang('Lgc.updDefaultTitle_no_news'),
            'what_happened' => lang('Lgc.tplNoNewsHappened', [date('Y-m-d', strtotime((string) $since))]),
            'what_it_means' => lang('Lgc.tplNoNewsMeans'),
            'what_we_do'    => lang('Lgc.tplNoNewsDoing'),
            'client_action' => lang('Lgc.tplNoNewsClient'),
            'next_update_at' => $nextDate,
            'verified_at'   => date('Y-m-d H:i'),
            'last_event'    => $lastEvent,
        ];
    }
}
