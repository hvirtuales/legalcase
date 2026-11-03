<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\EngagementService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\ActivityModel;

/** Actividades: la bandeja (mías, vencidas, hoy, todas) y las acciones sobre cada una. */
class Activities extends BaseLfl
{
    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('activities/index', ['rows' => null]);
        }
        $a    = $this->access();
        $view = (string) $this->request->getGet('vista');
        $view = in_array($view, ['mias', 'vencidas', 'hoy', 'todas', 'hechas'], true) ? $view : 'mias';

        $m = (new ActivityModel())
            ->select('cmp_lfl_activities.*, cmp_lfl_leads.first_name, cmp_lfl_leads.last_name')
            ->join('cmp_lfl_leads', 'cmp_lfl_leads.id = cmp_lfl_activities.lead_id')
            ->where('cmp_lfl_activities.company_id', $cid)
            ->where('cmp_lfl_leads.deleted_at', null);
        $a->scope($m);

        $now = date('Y-m-d H:i:s');
        match ($view) {
            'mias'     => $m->where('cmp_lfl_activities.assigned_user_id', $a->userId)->where('cmp_lfl_activities.status', 'pending'),
            'vencidas' => $m->where('cmp_lfl_activities.status', 'pending')->where('cmp_lfl_activities.due_at <', $now),
            'hoy'      => $m->where('cmp_lfl_activities.status', 'pending')
                ->where('cmp_lfl_activities.due_at >=', date('Y-m-d 00:00:00'))->where('cmp_lfl_activities.due_at <=', date('Y-m-d 23:59:59')),
            'hechas'   => $m->where('cmp_lfl_activities.status', 'done'),
            default    => $m->where('cmp_lfl_activities.status', 'pending'),
        };

        $rows = $view === 'hechas'
            ? $m->orderBy('cmp_lfl_activities.completed_at', 'desc')->findAll(200)
            : $m->orderBy('cmp_lfl_activities.due_at', 'asc')->findAll(200);

        return $this->render('activities/index', [
            'rows'    => $rows,
            'view'    => $view,
            'team'    => Team::users($cid),
            'canEdit' => $a->canWrite && $this->can('lfl.edit'),
        ]);
    }

    private function activity(int $id): ?array
    {
        $act = (new ActivityModel())->owned($id, $this->companyId());

        return $act && $this->lead((int) $act['lead_id'], true) ? $act : null;
    }

    public function store($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new EngagementService())->addActivity($lead, [
            'activity_type'    => $this->request->getPost('activity_type'),
            'title'            => $this->request->getPost('title'),
            'description'      => $this->request->getPost('description'),
            'due_date'         => $this->request->getPost('due_date'),
            'due_time'         => $this->request->getPost('due_time'),
            'priority'         => $this->request->getPost('priority'),
            'assigned_user_id' => $this->access()->canAssignOthers() ? $this->request->getPost('assigned_user_id') : $this->userId(),
        ]));

        return $this->toLead((int) $lead['id'], 'actividades');
    }

    public function complete($id = null)
    {
        $act = $this->activity((int) $id);
        if (! $act) {
            return $this->notFound('leadflow/actividades');
        }
        $this->result((new EngagementService())->completeActivity($act));

        return $this->back('leadflow/leads/ficha/' . (int) $act['lead_id'] . '?tab=actividades');
    }

    public function reopen($id = null)
    {
        $act = $this->activity((int) $id);
        if (! $act) {
            return $this->notFound('leadflow/actividades');
        }
        $this->result((new EngagementService())->reopenActivity($act));

        return $this->back('leadflow/leads/ficha/' . (int) $act['lead_id'] . '?tab=actividades');
    }

    public function delete($id = null)
    {
        $act = $this->activity((int) $id);
        if (! $act) {
            return $this->notFound('leadflow/actividades');
        }
        $this->result((new EngagementService())->deleteActivity($act));

        return $this->back('leadflow/leads/ficha/' . (int) $act['lead_id'] . '?tab=actividades');
    }
}
