<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\AppointmentService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\AppointmentModel;

/** Agenda de consultas (§18): la semana y las acciones sobre cada consulta. */
class Appointments extends BaseLfl
{
    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('appointments/index', ['days' => null]);
        }
        $a = $this->access();

        $week = (string) $this->request->getGet('semana');
        $ts   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) ? strtotime($week) : time();
        $mon  = strtotime('monday this week', $ts ?: time());
        $sun  = strtotime('+6 days', $mon);

        $lawyer = (int) $this->request->getGet('abogado');
        $status = (string) $this->request->getGet('estado');

        $m = (new AppointmentModel())
            ->select('cmp_lfl_appointments.*, cmp_lfl_leads.first_name, cmp_lfl_leads.last_name, cmp_lfl_leads.phone, cmp_lfl_leads.service_id')
            ->join('cmp_lfl_leads', 'cmp_lfl_leads.id = cmp_lfl_appointments.lead_id')
            ->where('cmp_lfl_appointments.company_id', $cid)
            ->where('cmp_lfl_leads.deleted_at', null)
            ->where('cmp_lfl_appointments.scheduled_at >=', date('Y-m-d 00:00:00', $mon))
            ->where('cmp_lfl_appointments.scheduled_at <=', date('Y-m-d 23:59:59', $sun));
        $a->scope($m);
        if ($lawyer) {
            $m->where('cmp_lfl_appointments.lawyer_user_id', $lawyer);
        }
        if (in_array($status, AppointmentModel::STATUSES, true)) {
            $m->where('cmp_lfl_appointments.status', $status);
        }

        $days = [];
        for ($d = 0; $d < 7; $d++) {
            $days[date('Y-m-d', strtotime("+{$d} days", $mon))] = [];
        }
        foreach ($m->orderBy('cmp_lfl_appointments.scheduled_at', 'asc')->findAll() as $appt) {
            $days[substr($appt['scheduled_at'], 0, 10)][] = $appt;
        }

        return $this->render('appointments/index', [
            'days'    => $days,
            'monday'  => date('Y-m-d', $mon),
            'prev'    => date('Y-m-d', strtotime('-7 days', $mon)),
            'next'    => date('Y-m-d', strtotime('+7 days', $mon)),
            'lawyer'  => $lawyer ?: '',
            'status'  => $status,
            'team'    => Team::users($cid),
            'canEdit'   => $a->canWrite && $this->can('lfl.edit'),
            'canDelete' => $a->canWrite && $this->can('lfl.delete'),
        ]);
    }

    /** @return array{0:?array,1:?array} consulta y su lead, si ambos son accesibles */
    private function appt(int $id): array
    {
        $appt = (new AppointmentModel())->owned($id, $this->companyId());
        $lead = $appt ? $this->lead((int) $appt['lead_id'], true) : null;

        return $lead ? [$appt, $lead] : [null, null];
    }

    private function backTo(array $appt)
    {
        return $this->back('leadflow/leads/ficha/' . (int) $appt['lead_id'] . '?tab=consultas');
    }

    public function store($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new AppointmentService())->schedule($lead, [
            'date'           => $this->request->getPost('date'),
            'time'           => $this->request->getPost('time'),
            'duration_min'   => $this->request->getPost('duration_min'),
            'mode'           => $this->request->getPost('mode'),
            'location'       => $this->request->getPost('location'),
            'lawyer_user_id' => $this->request->getPost('lawyer_user_id'),
            'notes'          => $this->request->getPost('notes'),
        ]));

        return $this->toLead((int) $lead['id'], 'consultas');
    }

    public function status($id = null)
    {
        [$appt, $lead] = $this->appt((int) $id);
        if (! $appt) {
            return $this->notFound('leadflow/consultas');
        }
        $this->result((new AppointmentService())->setStatus($appt, $lead, (string) $this->request->getPost('status')));

        return $this->backTo($appt);
    }

    public function delete($id = null)
    {
        [$appt, $lead] = $this->appt((int) $id);
        if (! $appt) {
            return $this->notFound('leadflow/consultas');
        }
        $this->result((new AppointmentService())->delete($appt, $lead));

        return $this->backTo($appt);
    }

    public function outcome($id = null)
    {
        [$appt, $lead] = $this->appt((int) $id);
        if (! $appt) {
            return $this->notFound('leadflow/consultas');
        }
        $this->result((new AppointmentService())->complete($appt, $lead, (string) $this->request->getPost('result'),
            (string) $this->request->getPost('notes')));

        return $this->backTo($appt);
    }

    public function reschedule($id = null)
    {
        [$appt, $lead] = $this->appt((int) $id);
        if (! $appt) {
            return $this->notFound('leadflow/consultas');
        }
        $this->result((new AppointmentService())->reschedule($appt, $lead, [
            'date' => $this->request->getPost('date'),
            'time' => $this->request->getPost('time'),
        ]));

        return $this->backTo($appt);
    }
}
