<?php

namespace Components\LegalCase\Controllers;

use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Libraries\MatterService;
use Components\LegalCase\Libraries\Outbox;
use Components\LegalCase\Libraries\UpdateService;
use Components\LegalCase\Models\CommPlanModel;
use Components\LegalCase\Models\EventModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\UpdateModel;

/** LegalCase Connect: plan de comunicación y actualizaciones al cliente con aprobación. */
class Updates extends BaseLgc
{
    private UpdateService $svc;

    public function __construct()
    {
        $this->svc = new UpdateService();
    }

    /** [actualización, asunto] o [null, null]. */
    private function find($id): array
    {
        return $this->findChild(UpdateModel::class, $id);
    }

    public function plan($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->savePlan($matter, $this->request->getPost(), $this->acc()), $this->matterUrl($matter, 'comunicacion'));
    }

    public function create($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        $kind = (string) ($this->isPost() ? $this->post('kind') : $this->request->getGet('tipo'));
        $kind = in_array($kind, UpdateModel::KINDS, true) ? $kind : 'regular';

        if ($this->isPost()) {
            $r = $this->svc->createDraft($matter, $kind, $this->request->getPost(), $this->acc());

            return $r->ok
                ? redirect()->to('legalcase/actualizaciones/ver/' . $r->id)->with('success', esc($r->message))
                : redirect()->back()->withInput()->with('error', esc($r->message));
        }

        $plan = (new CommPlanModel())->where('matter_id', (int) $matter['id'])->first();
        $next = $plan && CommPlanModel::isPeriodic((string) $plan['mode'])
            ? date('Y-m-d H:i', strtotime('+' . max(1, (int) $plan['cadence_days']) . ' days'))
            : null;

        $row = ['kind' => $kind, 'next_update_at' => $next, 'title' => lang('Lgc.updDefaultTitle_' . $kind), 'stage_label' => $matter['public_stage']];
        if ($kind === 'no_news') {
            $last = (new EventModel())->where('matter_id', (int) $matter['id'])->orderBy('occurred_at', 'DESC')->first();
            $row  = array_merge($row, $this->svc->noNewsTemplate($matter, $last, $next));
        }

        return view('Components\LegalCase\Views\updates\form', [
            'title'  => lang('Lgc.updNew'),
            'matter' => $matter,
            'row'    => $row,
            'action' => base_url('legalcase/asuntos/actualizacion/' . (int) $matter['id']),
            'events' => (new EventModel())->where('matter_id', (int) $matter['id'])->orderBy('occurred_at', 'DESC')->findAll(10),
        ]);
    }

    public function show($id = null)
    {
        [$u, $matter] = $this->find($id);
        if (!$u) {
            return $this->notFound();
        }
        $settings = (new SettingsModel())->forCompanyOrDefault($this->companyId());

        return view('Components\LegalCase\Views\updates\show', [
            'title'      => $u['title'],
            'u'          => $u,
            'matter'     => $matter,
            'warnings'   => $this->svc->warnings($u),
            'policy'     => $settings['approval_policy'] ?? 'self',
            'recipients' => Outbox::recipients($this->companyId(), (int) $matter['id']),
            'versions'   => (new UpdateModel())->where('matter_id', (int) $matter['id'])
                ->groupStart()->where('id', (int) ($u['supersedes_id'] ?: 0))->orWhere('supersedes_id', (int) $u['id'])->groupEnd()
                ->orderBy('version', 'ASC')->findAll(),
            'acc'        => $this->acc(),
            'canWork'    => $this->acc()->canWorkOn($matter),
        ]);
    }

    public function edit($id = null)
    {
        [$u, $matter] = $this->find($id);
        if (!$u) {
            return $this->notFound();
        }
        if (!in_array($u['status'], UpdateModel::EDITABLE, true)) {
            return redirect()->to('legalcase/actualizaciones/ver/' . (int) $u['id'])->with('error', esc(lang('Lgc.errNotEditable')));
        }
        if ($this->isPost()) {
            return $this->done($this->svc->edit($u, $this->request->getPost(), $this->acc()), 'legalcase/actualizaciones/ver/' . (int) $u['id']);
        }

        return view('Components\LegalCase\Views\updates\form', [
            'title'  => lang('Lgc.updEdit'),
            'matter' => $matter,
            'row'    => $u,
            'action' => base_url('legalcase/actualizaciones/editar/' . (int) $u['id']),
            'events' => (new EventModel())->where('matter_id', (int) $matter['id'])->orderBy('occurred_at', 'DESC')->findAll(10),
        ]);
    }

    public function submit($id = null)
    {
        [$u] = $this->find($id);

        return $u ? $this->done($this->svc->submit($u, $this->acc()), 'legalcase/actualizaciones/ver/' . (int) $u['id']) : $this->notFound();
    }

    public function revise($id = null)
    {
        [$u] = $this->find($id);
        if (!$u) {
            return $this->notFound();
        }
        $r = $this->svc->revise($u, $this->acc());

        return $this->done($r, 'legalcase/actualizaciones/' . ($r->ok ? 'editar/' . $r->id : 'ver/' . (int) $u['id']));
    }

    public function approve($id = null)
    {
        [$u] = $this->find($id);

        return $u ? $this->done($this->svc->approve($u, $this->acc()), 'legalcase/actualizaciones/ver/' . (int) $u['id']) : $this->notFound();
    }

    public function sendBack($id = null)
    {
        [$u] = $this->find($id);

        return $u ? $this->done($this->svc->sendBack($u, (string) $this->post('note'), $this->acc()), 'legalcase/actualizaciones/ver/' . (int) $u['id']) : $this->notFound();
    }

    public function publish($id = null)
    {
        [$u, $matter] = $this->find($id);
        if (!$u) {
            return $this->notFound();
        }
        // Verificación de destinatarios (HC-C03): el formulario confirma que se revisaron.
        if ($this->post('confirm_recipients') !== '1') {
            return redirect()->to('legalcase/actualizaciones/ver/' . (int) $u['id'])->with('error', esc(lang('Lgc.errConfirmRecipients')));
        }

        return $this->done($this->svc->publish($u, $matter, $this->acc()), $this->matterUrl($matter, 'comunicacion'), 'legalcase/actualizaciones/ver/' . (int) $u['id']);
    }

    public function revoke($id = null)
    {
        [$u] = $this->find($id);

        return $u ? $this->done($this->svc->revoke($u, (string) $this->post('reason'), $this->acc()), 'legalcase/actualizaciones/ver/' . (int) $u['id']) : $this->notFound();
    }

    /** Solo borradores: lo que el cliente pudo haber visto no se borra, se revoca. */
    public function delete($id = null)
    {
        [$u, $matter] = $this->find($id);
        if (!$u) {
            return $this->notFound();
        }
        if (!in_array($u['status'], UpdateModel::EDITABLE, true)) {
            return redirect()->to('legalcase/actualizaciones/ver/' . (int) $u['id'])->with('error', esc(lang('Lgc.errDeletePublished')));
        }
        (new UpdateModel())->delete((int) $u['id']);
        Audit::log($this->companyId(), (int) $matter['id'], 'update.deleted', 'update', (int) $u['id']);

        return redirect()->to($this->matterUrl($matter, 'comunicacion'))->with('success', esc(lang('Lgc.okDeleted')));
    }
}
