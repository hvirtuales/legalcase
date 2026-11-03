<?php

namespace Components\LegalCase\Controllers;

use App\Libraries\DataTable;
use Components\LegalCase\Libraries\MatterService;
use Components\LegalCase\Libraries\Portal;
use Components\LegalCase\Libraries\Team;
use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Libraries\MessageService;
use Components\LegalCase\Models\AuditModel;
use Components\LegalCase\Models\ConflictCheckModel;
use Components\LegalCase\Models\CommPlanModel;
use Components\LegalCase\Models\ContactModel;
use Components\LegalCase\Models\DeadlineModel;
use Components\LegalCase\Models\DeliveryModel;
use Components\LegalCase\Models\DocRequestModel;
use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\EventModel;
use Components\LegalCase\Models\MatterModel;
use Components\LegalCase\Models\ParticipantModel;
use Components\LegalCase\Models\PortalAccountModel;
use Components\LegalCase\Models\ProceedingModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\TaskModel;
use Components\LegalCase\Models\TeamModel;
use Components\LegalCase\Models\ThreadModel;
use Components\LegalCase\Models\UpdateModel;

/** Asuntos: listado, alta, ficha con pestañas, ciclo de vida, equipo, partes y portal. */
class Matters extends BaseLgc
{
    private const TABS = ['resumen', 'procesos', 'plazos', 'documentos', 'comunicacion', 'mensajes', 'partes', 'bitacora'];

    public function index()
    {
        $acc    = $this->acc();
        $status = (string) $this->request->getGet('estado');
        $mine   = (string) $this->request->getGet('mios') === '1';

        $dt = (new DataTable(new MatterModel(), $this->request))
            ->searchable(['cmp_lgc_matters.number', 'cmp_lgc_matters.title', 'cmp_lgc_matters.public_title', 'cmp_lgc_matters.jurisdiction'])
            ->sortable(['number', 'title', 'status', 'next_update_due_at', 'opened_at'])
            ->defaultSort('opened_at', 'desc')
            ->query(static function ($m) use ($acc, $status, $mine) {
                $m->select('cmp_lgc_matters.*, c.name AS client_name')
                    ->join('cmp_lgc_contacts c', 'c.id = cmp_lgc_matters.client_contact_id', 'left');
                $acc->scopeMatters($m);
                if (in_array($status, MatterModel::STATUSES, true)) {
                    $m->where('cmp_lgc_matters.status', $status);
                }
                if ($mine) {
                    $m->where('cmp_lgc_matters.lead_lawyer_id', $acc->userId);
                }

                return $m;
            })
            ->paginate(20);

        return view('Components\LegalCase\Views\matters\index', [
            'title'   => lang('Lgc.matters'),
            'dt'      => $dt,
            'filters' => ['estado' => $status, 'mios' => $mine ? '1' : ''],
            'users'   => Team::users($this->companyId()),
        ]);
    }

    public function create()
    {
        if ($this->isPost()) {
            $r = (new MatterService())->create($this->request->getPost(), $this->acc());

            return $r->ok
                ? redirect()->to('legalcase/asuntos/ficha/' . $r->id)->with('success', esc($r->message))
                : $this->failWithData($r, 'legalcase/asuntos/nuevo');
        }

        return view('Components\LegalCase\Views\matters\form', [
            'title'    => lang('Lgc.matterNew'),
            'row'      => ['visibility' => 'firm', 'sensitivity' => 'normal', 'risk_level' => 'medium', 'country' => 'CO', 'lead_lawyer_id' => $this->acc()->userId],
            'action'   => base_url('legalcase/asuntos/nuevo'),
            'insider'  => true,
            'contacts' => (new ContactModel())->mapFor($this->companyId()),
            'users'    => Team::users($this->companyId()),
        ]);
    }

    public function edit($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }
        if ($this->isPost()) {
            $r = (new MatterService())->update($matter, $this->request->getPost(), $this->acc());

            return $r->ok ? $this->done($r, $this->matterUrl($matter)) : $this->failWithData($r, 'legalcase/asuntos/editar/' . (int) $matter['id']);
        }

        return view('Components\LegalCase\Views\matters\form', [
            'title'    => lang('Lgc.matterEdit'),
            'insider'  => $this->acc()->isInsider($matter),
            'row'      => $matter,
            'action'   => base_url('legalcase/asuntos/editar/' . (int) $matter['id']),
            'contacts' => (new ContactModel())->mapFor($this->companyId()),
            'users'    => Team::users($this->companyId()),
        ]);
    }

    public function show($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }
        $tab = (string) $this->request->getGet('tab');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'resumen';
        $mid = (int) $matter['id'];
        $cid = $this->companyId();

        $data = [
            'title'    => $matter['number'],
            'matter'   => $matter,
            'tab'      => $tab,
            'tabs'     => self::TABS,
            'users'    => Team::users($cid),
            'client'   => $matter['client_contact_id'] ? (new ContactModel())->own($cid, (int) $matter['client_contact_id']) : null,
            'plan'     => (new CommPlanModel())->where('matter_id', $mid)->first(),
            'settings' => (new SettingsModel())->forCompanyOrDefault($cid),
            'acc'      => $this->acc(),
            'insider'  => $this->acc()->isInsider($matter),
            'canWork'  => $this->acc()->canWorkOn($matter),
            'pending'  => (new MatterService())->pendingItems($matter),
        ];

        // Registro de lecturas de lo sensible (§3 «registro de accesos»): una entrada por usuario,
        // asunto y media hora, para que la bitácora sirva sin llenarse de ruido.
        if ($matter['sensitivity'] === 'high' || $matter['visibility'] === 'team') {
            $key = 'lgc_viewed_' . $mid;
            if ((int) session($key) < time() - 1800) {
                session()->set($key, time());
                Audit::log($cid, $mid, 'matter.viewed', 'matter', $mid, ['tab' => $tab]);
            }
        }

        $data['counts'] = [
            'deadlines' => (new DeadlineModel())->where('matter_id', $mid)->whereIn('status', DeadlineModel::OPEN)->countAllResults(),
            'threads'   => (new ThreadModel())->where('matter_id', $mid)->whereIn('status', ThreadModel::OPEN)->countAllResults(),
            'requests'  => (new DocRequestModel())->where('matter_id', $mid)->where('status', 'submitted')->countAllResults(),
            'drafts'    => (new UpdateModel())->where('matter_id', $mid)->whereIn('status', ['draft', 'pending_review', 'approved'])->countAllResults(),
        ];

        switch ($tab) {
            case 'resumen':
                $data['lastUpdate'] = (new UpdateModel())->where('matter_id', $mid)->where('status', 'published')->orderBy('published_at', 'DESC')->first();
                $data['nextDeadlines'] = (new DeadlineModel())->where('matter_id', $mid)->whereIn('status', DeadlineModel::OPEN)->orderBy('due_at', 'ASC')->findAll(5);
                $data['proceedings'] = (new ProceedingModel())->where('matter_id', $mid)->orderBy('id', 'ASC')->findAll();
                break;

            case 'procesos':
                $data['proceedings'] = (new ProceedingModel())->where('matter_id', $mid)->orderBy('id', 'ASC')->findAll();
                $data['events']      = (new EventModel())->where('matter_id', $mid)->orderBy('occurred_at', 'DESC')->findAll(200);
                break;

            case 'plazos':
                $data['deadlines']   = (new DeadlineModel())->where('matter_id', $mid)->orderBy('status', 'DESC')->orderBy('due_at', 'ASC')->findAll(200);
                $data['tasks']       = (new TaskModel())->where('matter_id', $mid)->orderBy('status', 'DESC')->orderBy('due_at', 'ASC')->findAll(200);
                $data['proceedings'] = (new ProceedingModel())->where('matter_id', $mid)->findAll();
                break;

            case 'documentos':
                $data['documents'] = (new DocumentModel())->where('matter_id', $mid)->orderBy('created_at', 'DESC')->findAll(300);
                $data['requests']  = (new DocRequestModel())->where('matter_id', $mid)->orderBy('created_at', 'DESC')->findAll(100);
                $data['accounts']  = MessageService::accountsFor($cid, $mid);
                break;

            case 'comunicacion':
                $data['updates']    = (new UpdateModel())->where('matter_id', $mid)->orderBy('id', 'DESC')->findAll(100);
                $data['deliveries'] = (new DeliveryModel())->where('company_id', $cid)->where('matter_id', $mid)->orderBy('id', 'DESC')->findAll(50);
                $data['recipients'] = \Components\LegalCase\Libraries\Outbox::recipients($cid, $mid);
                break;

            case 'mensajes':
                $data['threads']  = (new ThreadModel())->where('matter_id', $mid)->orderBy('last_message_at', 'DESC')->findAll(100);
                $data['accounts'] = MessageService::accountsFor($cid, $mid);
                break;

            case 'partes':
                $data['team']         = (new TeamModel())->where('matter_id', $mid)->findAll();
                $data['participants'] = (new ParticipantModel())->withContacts($mid);
                $data['contacts']     = (new ContactModel())->mapFor($cid);
                $accounts = [];
                foreach ((new PortalAccountModel())->where('company_id', $cid)->findAll(1000) as $a) {
                    $accounts[(int) $a['contact_id']] = $a;
                }
                $data['accounts']  = $accounts;
                $data['conflicts'] = (new ConflictCheckModel())->forMatter($cid, $mid);
                $data['matterDocs'] = (new DocumentModel())->select('id, name')->where('matter_id', $mid)->orderBy('created_at', 'DESC')->findAll(200);
                break;

            case 'bitacora':
                $data['audit'] = (new AuditModel())->forMatter($cid, $mid);
                break;
        }

        return view('Components\LegalCase\Views\matters\show', $data);
    }

    public function close($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        $r = (new MatterService())->close($matter, (string) $this->post('close_reason'), $this->acc(), $this->post('confirm_pending') === '1');

        return $r->ok ? $this->done($r, $this->matterUrl($matter)) : $this->failWithData($r, $this->matterUrl($matter));
    }

    public function reopen($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->reopen($matter, $this->acc()), $this->matterUrl($matter));
    }

    public function hold($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->setOnHold($matter, $matter['status'] === 'open', $this->acc()), $this->matterUrl($matter));
    }

    public function delete($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->delete($matter, $this->acc()), 'legalcase/asuntos');
    }

    // ------------------------------------------------------------------ equipo y partes

    public function addTeam($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->addTeam($matter, (int) $this->post('user_id'), (string) $this->post('role', 'collaborator'), $this->acc()), $this->matterUrl($matter, 'partes'));
    }

    public function removeTeam($id = null, $rowId = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->removeTeam($matter, (int) $rowId, $this->acc()), $this->matterUrl($matter, 'partes'));
    }

    public function addParticipant($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        $r = (new MatterService())->addParticipant($matter, (int) $this->post('contact_id'), (string) $this->post('role', 'client'), $this->acc(), (string) $this->post('notes'), (array) $this->request->getPost());

        return $r->ok ? $this->done($r, $this->matterUrl($matter, 'partes')) : $this->failWithData($r, $this->matterUrl($matter, 'partes'));
    }

    /** POST — poder del representante (documento, alcance y vigencia). */
    public function authority($id = null, $rowId = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->setAuthority($matter, (int) $rowId, (array) $this->request->getPost(), $this->acc()), $this->matterUrl($matter, 'partes'));
    }

    /** POST — bloqueo legal (impide eliminar el asunto). */
    public function legalHold($id = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->setLegalHold($matter, (int) $matter['legal_hold'] !== 1, $this->acc()), $this->matterUrl($matter));
    }

    public function removeParticipant($id = null, $rowId = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new MatterService())->removeParticipant($matter, (int) $rowId, $this->acc()), $this->matterUrl($matter, 'partes'));
    }

    /** POST — concede (e invita) o retira el acceso al portal de una parte en ESTE asunto. */
    public function portalAccess($id = null, $rowId = null)
    {
        $matter = $this->findMatter($id);
        if (!$matter) {
            return $this->notFound();
        }
        $p = (new ParticipantModel())->where('company_id', $this->companyId())->where('matter_id', (int) $matter['id'])->find((int) $rowId);
        if (!$p) {
            return $this->notFound($this->matterUrl($matter, 'partes'));
        }
        $portal = new Portal();
        $r      = $this->post('grant') === '1' ? $portal->grant($matter, $p, $this->acc()) : $portal->revoke($matter, $p, $this->acc());

        return $this->done($r, $this->matterUrl($matter, 'partes'));
    }

    /** POST — bloquea la cuenta del portal en todos los asuntos. */
    public function blockAccount($accountId = null)
    {
        $back = (string) $this->post('return_to');
        $back = preg_match('#^legalcase/[a-z0-9/_?=&-]*$#i', $back) ? $back : 'legalcase/asuntos';

        return $this->done((new Portal())->blockAccount($this->companyId(), (int) $accountId, $this->acc()), $back);
    }
}
