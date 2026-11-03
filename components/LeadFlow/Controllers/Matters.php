<?php

namespace Components\LeadFlow\Controllers;

use App\Libraries\DataTable;
use Components\LeadFlow\Libraries\MatterService;
use Components\LeadFlow\Libraries\MatterSteps;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\DocumentTypeModel;
use Components\LeadFlow\Models\MatterFeeModel;
use Components\LeadFlow\Models\MatterModel;
use Components\LeadFlow\Models\MatterPaymentModel;
use Components\LeadFlow\Models\MatterRequirementModel;
use Components\LeadFlow\Models\MatterStageModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\TimelineModel;

/**
 * Expedientes: el caso ya contratado.
 *
 * ── La empresa y el alcance ──────────────────────────────────────────────────
 *
 * Todo entra por `BaseLfl::matter()`, que comprueba la empresa del expediente **y**
 * que su lead sea visible para quien pregunta. Ningún id de empresa viene del POST.
 *
 * En el listado se replica el patrón que ya usan `Activities::index()` y
 * `Appointments::index()`: JOIN a `cmp_lfl_leads` y `Access::scope()` con la tabla por
 * defecto, para que el expediente herede la visibilidad del lead sin escribir una
 * segunda versión del alcance.
 *
 * ── Abrir lleva `lfl.convert` ────────────────────────────────────────────────
 *
 * Ese permiso ya significa «convertir leads ganados en cliente», y el expediente es
 * esa conversión: Docketwise solo era un destino. Así hay una sola respuesta a quién
 * puede sacar un lead del embudo, que es la decisión que importa porque congela la
 * venta. `lfl.create` lo tiene cualquier asesor que pueda teclear un lead.
 */
class Matters extends BaseLfl
{
    public const TABS = ['resumen', 'requisitos', 'honorarios', 'tareas', 'timeline'];

    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('partials/_nocompany');
        }

        $a      = $this->access();
        $vista  = (string) ($this->request->getGet('vista') ?? 'activos');
        $vistas = ['todos', 'activos', 'mios', 'cerrados'];
        if (! in_array($vista, $vistas, true)) {
            $vista = 'activos';
        }

        $etapa = (int) ($this->request->getGet('etapa') ?? 0);

        $dt = (new DataTable(new MatterModel(), $this->request))
            ->searchable([
                'cmp_lfl_matters.number',
                'cmp_lfl_matters.title',
                'cmp_lfl_matters.client_first_name',
                'cmp_lfl_matters.client_last_name',
            ])
            ->sortable([
                'cmp_lfl_matters.number',
                'cmp_lfl_matters.opened_at',
                'cmp_lfl_matters.next_deadline_at',
            ])
            ->defaultSort('cmp_lfl_matters.opened_at', 'desc')
            ->query(function ($m) use ($cid, $a, $vista, $etapa) {
                $m->select('cmp_lfl_matters.*, st.name AS stage_name, st.color AS stage_color, st.kind AS stage_kind')
                    ->join('cmp_lfl_leads', 'cmp_lfl_leads.id = cmp_lfl_matters.lead_id')
                    ->join('cmp_lfl_matter_stages st', 'st.id = cmp_lfl_matters.stage_id', 'left')
                    ->where('cmp_lfl_matters.company_id', $cid)
                    ->where('cmp_lfl_leads.deleted_at', null);

                // El alcance va contra el LEAD unido, igual que en actividades y
                // consultas: el expediente no tiene visibilidad propia.
                $a->scope($m);

                // Cada filtro es un `where` suelto, nunca un `orWhere`: un OR aquí
                // dejaría el filtro de empresa dentro del grupo y cruzaría datos.
                if ($vista === 'activos') {
                    $m->whereIn('cmp_lfl_matters.status', ['active', 'on_hold']);
                } elseif ($vista === 'cerrados') {
                    $m->whereIn('cmp_lfl_matters.status', ['closed', 'cancelled']);
                } elseif ($vista === 'mios' && $this->userId()) {
                    $m->where('cmp_lfl_matters.lawyer_user_id', $this->userId());
                }
                if ($etapa > 0) {
                    $m->where('cmp_lfl_matters.stage_id', $etapa);
                }
            })
            ->paginate(20);

        return $this->render('matters/index', [
            'title'   => lang('Lfl.mtrListTitle'),
            'dt'      => $dt,
            'view'    => $vista,
            'views'   => $vistas,
            'etapa'   => $etapa,
            'stages'  => (new MatterStageModel())->ordered($cid, true),
            'counts'  => (new MatterModel())->countsByStatus($cid),
            'canEdit' => $a->canWrite && $this->can('lfl.edit'),
        ]);
    }

    /**
     * Abre el expediente de un lead.
     *
     * **El `(:num)` de esta ruta es un id de LEAD, no de expediente.** Es la única del
     * grupo que lo hace, porque el expediente todavía no existe.
     */
    public function open($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }

        $cid = (int) $lead['company_id'];
        $svc = new MatterService();

        // Si ya tiene expediente, se va a él en vez de enseñar un formulario que
        // fallaría: es la misma intención del usuario, ya satisfecha.
        $ya = (new MatterModel())->ofLead((int) $lead['id']);
        if ($ya) {
            return redirect()->to(base_url('leadflow/expedientes/ficha/' . (int) $ya['id']));
        }

        if ($this->isPost()) {
            $r = $svc->open($lead, [
                'lawyer_user_id'   => $this->request->getPost('lawyer_user_id'),
                'assigned_user_id' => $this->request->getPost('assigned_user_id'),
                'service_id'       => $this->request->getPost('service_id'),
                'title'            => $this->str('title', 160),
                'summary'          => (string) $this->request->getPost('summary'),
            ], $this->userId());

            $this->result($r);
            if ($r['ok']) {
                return redirect()->to(base_url('leadflow/expedientes/ficha/' . (int) $r['id']));
            }
        }

        return $this->render('matters/open', [
            'title'    => lang('Lfl.mtrOpenTitle'),
            'lead'     => $lead,
            'problems' => $svc->problems($lead),
            'team'     => Team::users($cid),
            'services' => (new ServiceModel())->options($cid),
            'stages'   => (new MatterStageModel())->ordered($cid, true),
        ]);
    }

    public function show($id = null)
    {
        $par = $this->matter((int) $id);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }
        [$matter, $lead] = [$par['matter'], $par['lead']];

        $cid = (int) $matter['company_id'];
        $tab = (string) ($this->request->getGet('tab') ?? 'resumen');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'resumen';
        }

        $a    = $this->access();
        $data = [
            'title'    => lang('Lfl.mtrTitle', [$matter['number']]),
            'matter'   => $matter,
            'lead'     => $lead,
            'tab'      => $tab,
            'stages'   => (new MatterStageModel())->ordered($cid, true),
            'stage'    => ! empty($matter['stage_id'])
                ? (new MatterStageModel())->owned((int) $matter['stage_id'], $cid)
                : null,
            'team'     => Team::users($cid),
            'services' => (new ServiceModel())->options($cid),
            'canEdit'  => $a->canEdit($lead) && $this->can('lfl.edit'),
            'canDelete' => $a->canEdit($lead) && $this->can('lfl.delete'),
        ];

        // Solo lo de su pestaña: la ficha no trae de base lo que no se mira.
        switch ($tab) {
            case 'resumen':
                $data['timeline'] = (new TimelineModel())->forLead((int) $lead['id'], $cid, 8);
                // El diagnóstico sale de `MatterSteps`, la misma fuente que usan las
                // guardas de radicación y cierre: si el aviso aparece aquí, es porque
                // el servicio lo tendría en cuenta.
                $data['steps'] = MatterSteps::evaluate($matter, ['uscis_receipt' => $lead['uscis_receipt'] ?? null]);
                break;

            case 'requisitos':
                $data['requirements'] = (new MatterRequirementModel())->forMatter((int) $matter['id'], $cid);
                $data['docTypes']     = (new DocumentTypeModel())->options($cid);
                break;

            case 'honorarios':
                $data['fees']     = (new MatterFeeModel())->forMatter((int) $matter['id'], $cid);
                $data['byKind']   = (new MatterFeeModel())->byKind((int) $matter['id'], $cid);
                $data['payments'] = (new MatterPaymentModel())->forMatter((int) $matter['id'], $cid);
                break;

            case 'tareas':
                $data['activities'] = (new ActivityModel())->forLead((int) $lead['id'], $cid);
                break;

            case 'timeline':
                $data['timeline'] = (new TimelineModel())->forLead((int) $lead['id'], $cid);
                break;
        }

        return $this->render('matters/show', $data);
    }

    public function edit($id = null)
    {
        $par = $this->matter((int) $id, true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }
        [$matter, $lead] = [$par['matter'], $par['lead']];
        $cid = (int) $matter['company_id'];

        if ($this->isPost()) {
            $r = (new MatterService())->update($matter, [
                'title'            => $this->str('title', 160),
                'summary'          => (string) $this->request->getPost('summary'),
                'service_id'       => $this->request->getPost('service_id'),
                'lawyer_user_id'   => $this->request->getPost('lawyer_user_id'),
                'assigned_user_id' => $this->request->getPost('assigned_user_id'),
            ], $this->userId());

            $this->result($r);
            if ($r['ok']) {
                return redirect()->to(base_url('leadflow/expedientes/ficha/' . (int) $matter['id']));
            }
        }

        return $this->render('matters/form', [
            'title'    => lang('Lfl.mtrEditTitle'),
            'matter'   => $matter,
            'lead'     => $lead,
            'team'     => Team::users($cid),
            'services' => (new ServiceModel())->options($cid),
        ]);
    }

    public function stage($id = null)
    {
        $par = $this->matter((int) $id, true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }

        $this->result((new MatterService())->moveStage(
            $par['matter'],
            (int) $this->request->getPost('stage_id'),
            ['reason' => (string) $this->request->getPost('close_reason'), 'note' => $this->str('close_note')],
            $this->userId()
        ));

        return $this->back('leadflow/expedientes/ficha/' . (int) $id);
    }

    public function close($id = null)
    {
        $par = $this->matter((int) $id, true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }

        $this->result((new MatterService())->close(
            $par['matter'],
            (string) $this->request->getPost('close_reason'),
            $this->str('close_note'),
            $this->userId()
        ));

        return $this->back('leadflow/expedientes/ficha/' . (int) $id);
    }

    public function reopen($id = null)
    {
        $par = $this->matter((int) $id, true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }

        $this->result((new MatterService())->reopen($par['matter'], $this->userId()));

        return $this->back('leadflow/expedientes/ficha/' . (int) $id);
    }

    public function delete($id = null)
    {
        $par = $this->matter((int) $id, true);
        if (! $par) {
            return $this->notFound('leadflow/expedientes');
        }

        $this->result((new MatterService())->delete($par['matter']));

        return redirect()->to(base_url('leadflow/expedientes'));
    }
}
