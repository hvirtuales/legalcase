<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\AppointmentModel;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;
use Components\LeadFlow\Models\StageHistoryModel;
use Components\LeadFlow\Models\StageModel;

/**
 * Las dos lecturas de la operación comercial:
 *
 *  · `home()` — el Inicio responde las preguntas del §35: qué leads nuevos tengo,
 *    a quién contactar ahora, qué actividad está vencida, quién tiene consulta
 *    hoy, qué leads tienen más probabilidad y cuáles están listos para Docketwise.
 *  · `period()` — el tablero del §27 para un rango de fechas: embudo, primera
 *    respuesta, conversión por fuente/campaña/servicio/asesor/abogado, perdidos y
 *    motivos, valor potencial y ganado.
 *
 * Todo pasa por `Access::scope()`: un asesor ve las cifras de SUS leads.
 * Agregaciones portables (COUNT/SUM/MAX + GROUP BY); lo que depende de fechas se
 * calcula en PHP para no depender de funciones de fecha de un motor.
 */
class ReportService
{
    private function leads(Access $a): LeadModel
    {
        $m = new LeadModel();
        $a->scope($m);

        return $m;
    }

    public function home(Access $a): array
    {
        $cid   = $a->companyId;
        $now   = date('Y-m-d H:i:s');
        $new   = (new StageModel())->byCode($cid, 'new');
        $today = [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')];

        $overdue = (new ActivityModel())
            ->select('cmp_lfl_activities.*, cmp_lfl_leads.first_name, cmp_lfl_leads.last_name')
            ->join('cmp_lfl_leads', 'cmp_lfl_leads.id = cmp_lfl_activities.lead_id')
            ->where('cmp_lfl_activities.company_id', $cid)
            ->where('cmp_lfl_leads.deleted_at', null)
            ->where('cmp_lfl_activities.status', 'pending')
            ->where('cmp_lfl_activities.due_at <', $now);
        $a->scope($overdue);
        $overdueRows = $overdue->orderBy('cmp_lfl_activities.due_at', 'asc')->findAll(10);

        $appts = (new AppointmentModel())
            ->select('cmp_lfl_appointments.*, cmp_lfl_leads.first_name, cmp_lfl_leads.last_name')
            ->join('cmp_lfl_leads', 'cmp_lfl_leads.id = cmp_lfl_appointments.lead_id')
            ->where('cmp_lfl_appointments.company_id', $cid)
            ->where('cmp_lfl_leads.deleted_at', null)
            ->whereIn('cmp_lfl_appointments.status', ['scheduled', 'confirmed'])
            ->where('cmp_lfl_appointments.scheduled_at >=', $today[0])
            ->where('cmp_lfl_appointments.scheduled_at <=', $today[1]);
        $a->scope($appts);

        return [
            'newLeads'   => $new ? $this->leads($a)->where('stage_id', (int) $new['id'])->orderBy('created_at', 'desc')->findAll(8) : [],
            'contactNow' => $this->leads($a)->where('status', 'open')->where('first_response_at', null)->orderBy('created_at', 'asc')->findAll(8),
            'overdue'    => $overdueRows,
            'today'      => $appts->orderBy('cmp_lfl_appointments.scheduled_at', 'asc')->findAll(20),
            'topScore'   => $this->leads($a)->where('status', 'open')->orderBy('score', 'desc')->orderBy('created_at', 'asc')->findAll(8),
            'ready'      => $this->leads($a)->where('status', 'won')->orderBy('won_at', 'asc')->findAll(8),
            'counts'     => [
                'open'       => $this->leads($a)->where('status', 'open')->countAllResults(),
                'new'        => $new ? $this->leads($a)->where('stage_id', (int) $new['id'])->countAllResults() : 0,
                'unassigned' => $this->leads($a)->where('status', 'open')->where('assigned_user_id', null)->countAllResults(),
                'ready'      => $this->leads($a)->where('status', 'won')->countAllResults(),
            ],
        ];
    }

    /**
     * @return array{from:string,to:string,kpi:array,response:array,funnel:array,by:array,lost:array,campaigns:array}
     */
    public function period(Access $a, string $from, string $to): array
    {
        $cid   = $a->companyId;
        $start = $from . ' 00:00:00';
        $end   = $to . ' 23:59:59';

        $cohort = $this->leads($a)
            ->select('id, stage_id, status, created_at, first_response_at, source_id, campaign_id, service_id, assigned_user_id, lawyer_user_id, won_at, won_value, potential_value, lost_reason')
            ->where('created_at >=', $start)->where('created_at <=', $end)
            ->findAll();

        $stages   = (new StageModel())->mapFor($cid);
        $sortOf   = static fn (?int $sid) => $sid && isset($stages[$sid]) ? (int) $stages[$sid]['sort'] : 0;
        $codeSort = [];
        foreach ($stages as $s) {
            if ($s['code']) {
                $codeSort[$s['code']] = (int) $s['sort'];
            }
        }
        $qualSort = $codeSort['qualified'] ?? PHP_INT_MAX;
        $propSort = $codeSort['proposal'] ?? PHP_INT_MAX;

        // Hasta qué etapa llegó cada lead (aunque hoy esté perdido).
        $reached = [];
        $ids     = array_map('intval', array_column($cohort, 'id'));
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = (new StageHistoryModel())->builder()
                ->select('cmp_lfl_stage_history.lead_id, MAX(cmp_lfl_stages.sort) AS max_sort')
                ->join('cmp_lfl_stages', 'cmp_lfl_stages.id = cmp_lfl_stage_history.to_stage_id')
                ->where('cmp_lfl_stage_history.company_id', $cid)
                ->where('cmp_lfl_stages.kind', 'open')
                ->whereIn('cmp_lfl_stage_history.lead_id', $chunk)
                ->groupBy('cmp_lfl_stage_history.lead_id')
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $reached[(int) $r['lead_id']] = (int) $r['max_sort'];
            }
        }

        // Consultas del período (por fecha de la consulta).
        $apptQ = (new AppointmentModel())
            ->select('cmp_lfl_appointments.lead_id, cmp_lfl_appointments.status')
            ->join('cmp_lfl_leads', 'cmp_lfl_leads.id = cmp_lfl_appointments.lead_id')
            ->where('cmp_lfl_appointments.company_id', $cid)
            ->where('cmp_lfl_leads.deleted_at', null)
            ->where('cmp_lfl_appointments.scheduled_at >=', $start)
            ->where('cmp_lfl_appointments.scheduled_at <=', $end);
        $a->scope($apptQ);
        $appts = $apptQ->findAll();

        $withAppt = [];
        foreach ((new AppointmentModel())->select('lead_id')->where('company_id', $cid)->whereIn('status', AppointmentModel::ACTIVE)->findAll() as $r) {
            $withAppt[(int) $r['lead_id']] = true;
        }

        // Ganados en el período (por fecha de ganado, no de creación).
        $won = $this->leads($a)->select('id, won_value')->where('won_at >=', $start)->where('won_at <=', $end)->findAll();

        $buckets = ['lt5' => 0, 'lt30' => 0, 'lt60' => 0, 'lt240' => 0, 'gt240' => 0, 'none' => 0];
        $by      = ['source' => [], 'campaign' => [], 'service' => [], 'advisor' => [], 'lawyer' => []];
        $lost    = [];
        $funnel  = ['generated' => 0, 'qualified' => 0, 'consultation' => 0, 'proposal' => 0, 'won' => 0, 'converted' => 0];
        $camp    = [];
        $potential = 0.0;

        foreach ($cohort as $l) {
            $id        = (int) $l['id'];
            $curKind   = $stages[(int) $l['stage_id']]['kind'] ?? '';
            $top       = max($reached[$id] ?? 0, $curKind === 'open' ? $sortOf((int) $l['stage_id']) : 0);
            $isWon     = in_array($l['status'], ['won', 'converted'], true);
            $qualified = $isWon || $top >= $qualSort;
            $hasAppt   = isset($withAppt[$id]);

            $funnel['generated']++;
            $funnel['qualified']    += $qualified ? 1 : 0;
            $funnel['consultation'] += $hasAppt ? 1 : 0;
            $funnel['proposal']     += ($isWon || $top >= $propSort) ? 1 : 0;
            $funnel['won']          += $isWon ? 1 : 0;
            $funnel['converted']    += $l['status'] === 'converted' ? 1 : 0;

            if ($l['status'] === 'open' && $l['potential_value'] !== null) {
                $potential += (float) $l['potential_value'];
            }

            if ($l['first_response_at']) {
                $min = (strtotime($l['first_response_at']) - strtotime($l['created_at'])) / 60;
                $buckets[$min < 5 ? 'lt5' : ($min < 30 ? 'lt30' : ($min < 60 ? 'lt60' : ($min < 240 ? 'lt240' : 'gt240')))]++;
            } else {
                $buckets['none']++;
            }

            foreach (['source' => 'source_id', 'campaign' => 'campaign_id', 'service' => 'service_id',
                'advisor' => 'assigned_user_id', 'lawyer' => 'lawyer_user_id'] as $dim => $col) {
                $k = (int) ($l[$col] ?? 0);
                $by[$dim][$k] ??= ['leads' => 0, 'qualified' => 0, 'won' => 0, 'value' => 0.0];
                $by[$dim][$k]['leads']++;
                $by[$dim][$k]['qualified'] += $qualified ? 1 : 0;
                $by[$dim][$k]['won']       += $isWon ? 1 : 0;
                $by[$dim][$k]['value']     += $isWon ? (float) ($l['won_value'] ?? 0) : 0;
            }

            if ($l['status'] === 'lost') {
                $lost[$l['lost_reason'] ?: 'other'] = ($lost[$l['lost_reason'] ?: 'other'] ?? 0) + 1;
            }

            if (! empty($l['campaign_id'])) {
                $c = (int) $l['campaign_id'];
                $camp[$c] ??= ['leads' => 0, 'qualified' => 0, 'consultation' => 0, 'won' => 0, 'revenue' => 0.0];
                $camp[$c]['leads']++;
                $camp[$c]['qualified']    += $qualified ? 1 : 0;
                $camp[$c]['consultation'] += $hasAppt ? 1 : 0;
                $camp[$c]['won']          += $isWon ? 1 : 0;
                $camp[$c]['revenue']      += $isWon ? (float) ($l['won_value'] ?? 0) : 0;
            }
        }
        arsort($lost);

        $names = [
            'source'   => (new SourceModel())->options($cid, false),
            'campaign' => (new CampaignModel())->options($cid, false),
            'service'  => (new ServiceModel())->options($cid, false),
            'advisor'  => Team::users($cid),
            'lawyer'   => Team::users($cid),
        ];
        // Lo que no tiene valor se nombra según la dimensión: «Sin asignar» sirve para
        // un asesor, pero para una campaña es «Sin campaña».
        $empty = ['source' => 'noDimSource', 'campaign' => 'noDimCampaign', 'service' => 'noDimService',
            'advisor' => 'unassignedDim', 'lawyer' => 'unassignedDim'];
        foreach ($by as $dim => $rows) {
            uasort($rows, static fn ($x, $y) => $y['leads'] <=> $x['leads']);
            $by[$dim] = [];
            foreach ($rows as $k => $r) {
                $by[$dim][] = $r + ['name' => $k === 0 ? lang('Lfl.' . $empty[$dim]) : ($names[$dim][$k] ?? '#' . $k)];
            }
        }

        $budgets = array_column((new CampaignModel())->forCompany($cid)->findAll(), 'budget', 'id');
        $campaigns = [];
        foreach ($camp as $k => $r) {
            $campaigns[] = $r + ['name' => $names['campaign'][$k] ?? '#' . $k, 'budget' => $budgets[$k] ?? null];
        }
        usort($campaigns, static fn ($x, $y) => $y['leads'] <=> $x['leads']);

        return [
            'from'      => $from,
            'to'        => $to,
            'kpi'       => [
                'generated'    => $funnel['generated'],
                'qualified'    => $funnel['qualified'],
                'consultations' => count($appts),
                'consultationsDone' => count(array_filter($appts, static fn ($x) => $x['status'] === 'completed')),
                'proposals'    => $funnel['proposal'],
                'wonPeriod'    => count($won),
                'conversion'   => $funnel['generated'] > 0 ? round($funnel['won'] * 100 / $funnel['generated'], 1) : 0,
                'potential'    => $potential,
                'wonValue'     => array_sum(array_map(static fn ($w) => (float) ($w['won_value'] ?? 0), $won)),
                'lost'         => array_sum($lost),
            ],
            'response'  => $buckets,
            'funnel'    => $funnel,
            'by'        => $by,
            'lost'      => $lost,
            'campaigns' => $campaigns,
        ];
    }
}
