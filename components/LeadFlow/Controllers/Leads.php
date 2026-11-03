<?php

namespace Components\LeadFlow\Controllers;

use App\Libraries\DataTable;
use Components\LeadFlow\Libraries\DemoData;
use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Libraries\LeadService;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Libraries\ScoringService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\AppointmentModel;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\CommunicationModel;
use Components\LeadFlow\Models\ConversionModel;
use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\DocumentTypeModel;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\IntegrationLogModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\MappingModel;
use Components\LeadFlow\Models\NoteModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SettingsModel;
use Components\LeadFlow\Models\SourceModel;
use Components\LeadFlow\Models\StageModel;
use Components\LeadFlow\Models\SubmissionModel;
use Components\LeadFlow\Models\TimelineModel;

/**
 * Leads: listado con sus vistas (todos, míos, nuevos, sin asignar), alta, la
 * ficha 360 con sus pestañas (§11) y las acciones de etapa, asignación y
 * prioridad.
 */
class Leads extends BaseLfl
{
    public const TABS = ['resumen', 'timeline', 'info', 'precalificacion', 'actividades', 'consultas', 'comunicaciones', 'documentos', 'expediente', 'docketwise'];

    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('leads/index', ['dt' => null]);
        }
        $a = $this->access();

        $view    = (string) $this->request->getGet('vista');
        $view    = in_array($view, ['todos', 'mis', 'nuevos', 'sin_asignar'], true) ? $view : 'todos';
        $stage   = (int) $this->request->getGet('etapa');
        $service = (int) $this->request->getGet('servicio');
        $source  = (int) $this->request->getGet('fuente');
        $prio    = (string) $this->request->getGet('prioridad');
        $status  = (string) $this->request->getGet('estado');
        $newId   = (int) ((new StageModel())->byCode($cid, 'new')['id'] ?? 0);

        $dt = (new DataTable(new LeadModel(), $this->request))
            ->searchable(['cmp_lfl_leads.first_name', 'cmp_lfl_leads.last_name', 'cmp_lfl_leads.email', 'cmp_lfl_leads.phone'])
            ->sortable(['cmp_lfl_leads.created_at', 'cmp_lfl_leads.score', 'cmp_lfl_leads.last_name', 'cmp_lfl_leads.next_activity_at'])
            ->defaultSort('cmp_lfl_leads.created_at', 'desc')
            ->query(function ($m) use ($a, $view, $stage, $service, $source, $prio, $status, $newId) {
                $m->select('cmp_lfl_leads.*, s.name AS service_name, st.name AS stage_name, st.color AS stage_color')
                    ->join('cmp_lfl_services s', 's.id = cmp_lfl_leads.service_id', 'left')
                    ->join('cmp_lfl_stages st', 'st.id = cmp_lfl_leads.stage_id', 'left');
                $a->scope($m);

                if ($view === 'mis') {
                    $m->where('cmp_lfl_leads.assigned_user_id', $a->userId);
                } elseif ($view === 'nuevos') {
                    $m->where('cmp_lfl_leads.stage_id', $newId);
                } elseif ($view === 'sin_asignar') {
                    $m->where('cmp_lfl_leads.assigned_user_id', null)->where('cmp_lfl_leads.status', 'open');
                }
                if ($stage) {
                    $m->where('cmp_lfl_leads.stage_id', $stage);
                }
                if ($service) {
                    $m->where('cmp_lfl_leads.service_id', $service);
                }
                if ($source) {
                    $m->where('cmp_lfl_leads.source_id', $source);
                }
                if (in_array($prio, LeadModel::PRIORITIES, true)) {
                    $m->where('cmp_lfl_leads.priority', $prio);
                }
                if (in_array($status, LeadModel::STATUSES, true)) {
                    $m->where('cmp_lfl_leads.status', $status);
                }
            })
            ->paginate(20);

        return $this->render('leads/index', [
            'dt'        => $dt,
            'view'      => $view,
            'filters'   => ['etapa' => $stage ?: '', 'servicio' => $service ?: '', 'fuente' => $source ?: '', 'prioridad' => $prio, 'estado' => $status],
            'stages'    => (new StageModel())->ordered($cid),
            'services'  => (new ServiceModel())->options($cid, false),
            'sources'   => (new SourceModel())->options($cid, false),
            'team'      => Team::users($cid),
            'planBlock' => (new PlanGuard())->blockReason($cid),
            'leadsLeft' => (new PlanGuard())->leadsLeft($cid),
            'isEmpty'   => DemoData::isEmpty($cid),
            'canCreate' => $this->can('lfl.create') && $a->canWrite,
            'canImport' => $this->can('lfl.import') && $a->canWrite,
            // Las filas ya vienen acotadas por `Access::scope()`, así que lo que se
            // ve aquí es editable si la función comercial escribe y el permiso está.
            'canEdit'   => $this->can('lfl.edit') && $a->canWrite,
        ]);
    }

    private function formOptions(int $cid): array
    {
        return [
            'services'  => (new ServiceModel())->options($cid),
            'sources'   => (new SourceModel())->options($cid),
            'campaigns' => (new CampaignModel())->options($cid),
            'team'      => Team::users($cid),
            'currency'  => (new SettingsModel())->forCompany($cid)['currency'] ?? 'USD',
        ];
    }

    /**
     * Consulta el caso de este lead contra USCIS, ahora.
     *
     * Existe porque el refresco programado va por tandas y quien está a punto de
     * llamar no puede esperar a la próxima pasada. **Gasta una consulta del cupo**, y
     * por eso es un botón y no algo que ocurra al abrir la ficha: abrir una ficha no
     * puede costarle dinero a la firma sin que nadie lo haya pedido.
     *
     * Pide `lfl.edit`: consultar escribe el estado en el lead.
     */
    public function caseRefresh($id = null)
    {
        $lead = $this->lead((int) $id, true);
        if (! $lead) {
            return $this->notFound();
        }

        $recibo = trim((string) ($lead['uscis_receipt'] ?? ''));
        if ($recibo === '') {
            $this->flash('error', lang('Lfl.uscLeadNone'));

            return $this->toLead((int) $lead['id']);
        }

        $res = (new \Components\LeadFlow\Libraries\Uscis\UscisService())
            ->status((int) $lead['company_id'], $recibo);

        if ($res['ok'] && $res['case'] !== null) {
            $leads = new LeadModel();
            $leads->skipValidation(true)->protect(false)->update((int) $lead['id'], [
                'uscis_status'     => $res['case']['status'] ?: null,
                'uscis_checked_at' => date('Y-m-d H:i:s'),
            ]);
            $leads->skipValidation(false);
        }

        $this->flash($res['ok'] ? 'success' : 'error', $res['message'] ?: lang('Lfl.uscLeadRefreshed'));

        return $this->toLead((int) $lead['id']);
    }

    private function postData(): array
    {
        $keys = ['first_name', 'middle_name', 'last_name', 'email', 'phone', 'whatsapp', 'country', 'city',
            'preferred_language', 'service_id', 'source_id', 'campaign_id', 'potential_value', 'summary',
            'requested_consultation', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer', 'landing_page',
            // El recibo de USCIS. `LeadService::normalize()` lo valida de forma y lo
            // descarta si no la cumple: aquí solo se recoge.
            'uscis_receipt'];
        $data = [];
        foreach ($keys as $k) {
            $data[$k] = $this->request->getPost($k);
        }
        if ($this->request->getPost('whatsapp_same')) {
            $data['whatsapp'] = $data['phone'];
        }

        return $data;
    }

    public function create()
    {
        $cid = $this->companyId();
        $a   = $this->access();
        if (! $cid || ! $a->canWrite) {
            $this->flash('error', lang($cid ? 'Lfl.errReadOnly' : 'Lfl.errNoCompany'));

            return redirect()->to(base_url('leadflow/leads'));
        }
        if ($block = (new PlanGuard())->blockReason($cid)) {
            $this->flash('error', $block);

            return redirect()->to(base_url('leadflow/leads'));
        }

        $dups = [];
        if ($this->isPost()) {
            $data = $this->postData();
            // Un asesor da de alta leads para sí mismo; quien ve todo puede asignarlos.
            $data['assigned_user_id'] = $a->canAssignOthers() ? $this->request->getPost('assigned_user_id') : $a->userId;
            $data['lawyer_user_id']   = $a->canAssignOthers() ? $this->request->getPost('lawyer_user_id') : null;

            $res = (new LeadService())->create($cid, $data, [
                'channel' => 'manual',
                'force'   => (bool) $this->request->getPost('force_duplicate'),
            ]);
            if ($res['ok']) {
                $this->flash('success', $res['message']);

                return $this->toLead((int) $res['id']);
            }
            $dups = $res['duplicates'] ?? [];
            if (! $dups) {
                $this->flash('error', $res['message']);

                return redirect()->back()->withInput();
            }
        }

        return $this->render('leads/form', $this->formOptions($cid) + [
            'lead'   => [],
            'dups'   => $dups,
            'action' => base_url('leadflow/leads/nuevo'),
            'title'  => lang('Lfl.leadNew'),
        ]);
    }

    public function edit($id = null)
    {
        $lead = $this->lead((int) $id, true);
        if (! $lead) {
            return $this->notFound();
        }

        if ($this->isPost()) {
            $res = (new LeadService())->update($lead, $this->postData());
            $this->result($res);

            return $res['ok'] ? $this->toLead((int) $lead['id'], 'info') : redirect()->back()->withInput();
        }

        return $this->render('leads/form', $this->formOptions((int) $lead['company_id']) + [
            'lead'   => $lead,
            'dups'   => [],
            'action' => base_url('leadflow/leads/editar/' . (int) $lead['id']),
            'title'  => lang('Lfl.leadEdit'),
        ]);
    }

    public function show($id = null)
    {
        $lead = $this->lead((int) $id);
        if (! $lead) {
            return $this->notFound();
        }
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];
        $a   = $this->access();

        $tab = (string) $this->request->getGet('tab');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'resumen';

        $stages = (new StageModel())->mapFor($cid);
        $data   = [
            'title'      => LeadModel::fullName($lead),
            'lead'       => $lead,
            'tab'        => $tab,
            'stages'     => $stages,
            'stage'      => $stages[(int) $lead['stage_id']] ?? null,
            'service'    => $lead['service_id'] ? (new ServiceModel())->find((int) $lead['service_id']) : null,
            'source'     => $lead['source_id'] ? (new SourceModel())->find((int) $lead['source_id']) : null,
            'campaign'   => $lead['campaign_id'] ? (new CampaignModel())->find((int) $lead['campaign_id']) : null,
            'team'       => Team::users($cid),
            'canEdit'    => $a->canEdit($lead) && $this->can('lfl.edit'),
            'canCreate'  => $a->canEdit($lead) && $this->can('lfl.create'),
            'canDelete'  => $a->canEdit($lead) && $this->can('lfl.delete'),
            'canConvert' => $a->canEdit($lead) && $this->can('lfl.convert'),
            'currency'   => (new SettingsModel())->forCompany($cid)['currency'] ?? 'USD',
            'conversion' => (new ConversionModel())->forLead($lid),
        ];

        switch ($tab) {
            case 'resumen':
                $data['breakdown']  = (new ScoringService())->breakdown($lead);
                $data['activities'] = (new ActivityModel())->forCompany($cid)->where('lead_id', $lid)->where('status', 'pending')->orderBy('due_at', 'asc')->findAll(5);
                $data['nextAppt']   = (new AppointmentModel())->forCompany($cid)->where('lead_id', $lid)
                    ->whereIn('status', ['scheduled', 'confirmed'])->where('scheduled_at >=', date('Y-m-d H:i:s'))->orderBy('scheduled_at', 'asc')->first();
                $data['timeline']   = (new TimelineModel())->forLead($lid, $cid, 6);
                break;

            case 'timeline':
                $data['timeline'] = (new TimelineModel())->forLead($lid, $cid);
                break;

            case 'precalificacion':
                $data['submissions'] = (new SubmissionModel())->forLead($lid, $cid);
                $data['forms']       = (new FormModel())->forCompany($cid)->where('status !=', 'closed')->orderBy('name', 'asc')->findAll();
                $data['formsById']   = array_column((new FormModel())->withDeleted()->forCompany($cid)->findAll(), null, 'id');
                break;

            case 'actividades':
                $data['activities'] = (new ActivityModel())->forLead($lid, $cid);
                break;

            case 'consultas':
                $data['appointments'] = (new AppointmentModel())->forLead($lid, $cid);
                break;

            case 'comunicaciones':
                $data['communications'] = (new CommunicationModel())->forLead($lid, $cid);
                $data['notes']          = (new NoteModel())->forLead($lid, $cid);
                break;

            case 'documentos':
                $data['documents'] = (new DocumentModel())->forLead($lid, $cid);
                $data['docTypes']  = (new DocumentTypeModel())->options($cid);
                break;

            case 'expediente':
                $data['matter']   = (new \Components\LeadFlow\Models\MatterModel())->ofLead($lid);
                $data['canOpen']  = $a->canEdit($lead) && $this->can('lfl.convert');
                break;

            case 'docketwise':
                $data['connected'] = (new IntegrationService())->isConnected($cid);
                $data['mappings']  = (new MappingModel())->forCompany($cid)->where('provider', 'docketwise')
                    ->groupStart()->whereIn('entity_type', ['contact', 'matter', 'summary'])->where('local_id', $lid)->groupEnd()->findAll();
                $data['logs']      = (new IntegrationLogModel())->forCompany($cid)->where('lead_id', $lid)->orderBy('id', 'desc')->findAll(30);
                break;
        }

        return $this->render('leads/show', $data);
    }

    public function stage($id = null)
    {
        $lead = $this->lead((int) $id, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new LeadService())->moveStage($lead, (int) $this->request->getPost('stage_id'), [
            'reason'    => (string) $this->request->getPost('reason'),
            'note'      => (string) $this->request->getPost('note'),
            'won_value' => $this->request->getPost('won_value'),
        ], $this->userId()));

        return $this->toLead((int) $lead['id']);
    }

    public function assign($id = null)
    {
        $lead = $this->lead((int) $id, true);
        if (! $lead) {
            return $this->notFound();
        }
        $advisor = (int) $this->request->getPost('assigned_user_id') ?: null;
        $lawyer  = (int) $this->request->getPost('lawyer_user_id') ?: null;
        $this->result((new LeadService())->assign($lead, $advisor, $lawyer, $this->access()));

        return $this->toLead((int) $lead['id']);
    }

    public function priority($id = null)
    {
        $lead = $this->lead((int) $id, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new LeadService())->setPriority($lead, (string) $this->request->getPost('priority')));

        return $this->toLead((int) $lead['id']);
    }

    public function delete($id = null)
    {
        $lead = $this->lead((int) $id, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new LeadService())->delete($lead));

        return redirect()->to(base_url('leadflow/leads'));
    }

    public function demo()
    {
        $cid = $this->companyId();
        if (! $cid || ! $this->access()->canWrite) {
            return $this->notFound();
        }
        $this->result((new DemoData())->run($cid, $this->userId()));

        return redirect()->to(base_url('leadflow/leads'));
    }
}
