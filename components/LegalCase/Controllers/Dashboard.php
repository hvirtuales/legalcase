<?php

namespace Components\LegalCase\Controllers;

use Components\LegalCase\Libraries\Clock;
use Components\LegalCase\Libraries\Team;
use Components\LegalCase\Models\DeadlineModel;
use Components\LegalCase\Models\DeliveryModel;
use Components\LegalCase\Models\HearingModel;
use Components\LegalCase\Models\MatterModel;
use Components\LegalCase\Models\TaskModel;
use Components\LegalCase\Models\ThreadModel;
use Components\LegalCase\Models\UpdateModel;

/** «Mi día», agenda y panel de comunicaciones (§10, §6.12 HC-C09). */
class Dashboard extends BaseLgc
{
    /** Ids de los asuntos que el usuario puede ver (todas las listas transversales se acotan a esto). */
    private function visibleIds(): array
    {
        $m = new MatterModel();
        $m->select('cmp_lgc_matters.id');
        $this->acc()->scopeMatters($m);

        return array_map('intval', array_column($m->findAll(5000), 'id')) ?: [0];
    }

    /** [id => número] de los asuntos citados en una lista. */
    private function numbers(array $rows): array
    {
        $ids = array_values(array_unique(array_map('intval', array_column($rows, 'matter_id'))));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach ((new MatterModel())->select('id, number, title')->where('company_id', $this->companyId())->whereIn('id', $ids)->findAll() as $m) {
            $out[(int) $m['id']] = $m;
        }

        return $out;
    }

    public function index()
    {
        $ids = $this->visibleIds();
        $uid = $this->acc()->userId;
        $now = date('Y-m-d H:i:s');

        $overdue = array_values(array_filter(
            Clock::overdueQuery($this->companyId())->get()->getResultArray(),
            fn ($r) => in_array((int) $r['matter_id'], $ids, true)
        ));

        $deadlines = (new DeadlineModel())->whereIn('matter_id', $ids)->whereIn('status', DeadlineModel::OPEN)
            ->where('due_at <=', date('Y-m-d H:i:s', strtotime('+14 days')))->orderBy('due_at', 'ASC')->findAll(30);

        $tasks = (new TaskModel())->whereIn('matter_id', $ids)->where('status', 'open')->where('assigned_to', $uid)
            ->orderBy('due_at', 'ASC')->findAll(30);

        $threads = (new ThreadModel())->whereIn('matter_id', $ids)->whereIn('status', ['new', 'assigned'])
            ->orderBy('first_response_due_at', 'ASC')->findAll(20);

        // Audiencias de hoy y los próximos 7 días (las aplazadas también: siguen pidiendo fecha).
        $hearings = (new HearingModel())->whereIn('matter_id', $ids)->whereIn('status', ['scheduled', 'postponed'])
            ->where('scheduled_at >=', date('Y-m-d 00:00:00'))->where('scheduled_at <=', date('Y-m-d 23:59:59', strtotime('+7 days')))
            ->orderBy('scheduled_at', 'ASC')->findAll(20);

        $reviews = (new UpdateModel())->whereIn('matter_id', $ids)->whereIn('status', ['pending_review', 'approved'])
            ->orderBy('submitted_at', 'ASC')->findAll(20);

        $metrics = [
            'active'    => (new MatterModel())->where('company_id', $this->companyId())->whereIn('id', $ids)->where('status', 'open')->countAllResults(),
            'overdue'   => count($overdue),
            'deadlines' => count(array_filter($deadlines, static fn ($d) => $d['status'] === 'to_validate' || $d['due_at'] < $now)),
            'threads'   => count($threads),
        ];

        return view('Components\LegalCase\Views\dashboard', [
            'title'     => lang('Lgc.navHome'),
            'metrics'   => $metrics,
            'overdue'   => array_slice($overdue, 0, 10),
            'deadlines' => $deadlines,
            'tasks'     => $tasks,
            'hearings'  => $hearings,
            'threads'   => $threads,
            'reviews'   => $reviews,
            'numbers'   => $this->numbers(array_merge($deadlines, $tasks, $hearings, $threads, $reviews)),
            'users'     => Team::users($this->companyId()),
        ]);
    }

    public function agenda()
    {
        $ids  = $this->visibleIds();
        $mine = (string) $this->request->getGet('mios') === '1';
        $uid  = $this->acc()->userId;

        $d = (new DeadlineModel())->whereIn('matter_id', $ids)->whereIn('status', DeadlineModel::OPEN);
        $t = (new TaskModel())->whereIn('matter_id', $ids)->where('status', 'open');
        if ($mine) {
            $d->where('assigned_to', $uid);
            $t->where('assigned_to', $uid);
        }
        $deadlines = $d->orderBy('due_at', 'ASC')->findAll(300);
        $tasks     = $t->orderBy('due_at', 'ASC')->findAll(300);
        $hearings  = (new HearingModel())->whereIn('matter_id', $ids)->whereIn('status', ['scheduled', 'postponed'])
            ->where('scheduled_at >=', date('Y-m-d 00:00:00'))->orderBy('scheduled_at', 'ASC')->findAll(100);

        return view('Components\LegalCase\Views\agenda', [
            'title'     => lang('Lgc.navAgenda'),
            'deadlines' => $deadlines,
            'tasks'     => $tasks,
            'hearings'  => $hearings,
            'mine'      => $mine,
            'numbers'   => $this->numbers(array_merge($deadlines, $tasks, $hearings)),
            'users'     => Team::users($this->companyId()),
        ]);
    }

    /**
     * Agenda en calendario: `GET legalcase/agenda/calendario?escala=mes|semana|dia&fecha=Y-m-d`.
     *
     * Es la MISMA agenda que la lista —plazos, audiencias y tareas de los asuntos visibles,
     * con «Solo míos»—, pintada por fechas. Dos diferencias a propósito:
     *
     *  · Consulta solo el rango visible (el mes se completa a semanas de lunes a domingo).
     *  · Trae también lo ya cumplido (plazos `done`, audiencias `held`, tareas `done`), que
     *    la vista atenúa: un calendario es también el historial de lo que pasó ese día. Lo
     *    cancelado no sale.
     *
     * Cada día lleva el motivo por el que no es hábil (`Calendar::reason()`: fin de semana,
     * festivo de Colombia, vacancia judicial o día propio del despacho), que es lo que un
     * abogado necesita ver al lado de un vencimiento.
     */
    public function calendar()
    {
        $scale = (string) $this->request->getGet('escala');
        $scale = in_array($scale, ['dia', 'semana', 'mes'], true) ? $scale : 'mes';
        $raw   = (string) $this->request->getGet('fecha');
        $date  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) && strtotime($raw) ? date('Y-m-d', strtotime($raw)) : date('Y-m-d');
        $mine  = (string) $this->request->getGet('mios') === '1';
        $uid   = $this->acc()->userId;

        $t = strtotime($date . ' 12:00:00');
        if ($scale === 'dia') {
            $from = $to = $date;
        } elseif ($scale === 'semana') {
            $from = date('Y-m-d', strtotime('-' . ((int) date('N', $t) - 1) . ' days', $t));
            $to   = date('Y-m-d', strtotime($from . ' +6 days'));
        } else {
            $first = date('Y-m-01', $t);
            $last  = date('Y-m-t', $t);
            $from  = date('Y-m-d', strtotime($first . ' -' . ((int) date('N', strtotime($first)) - 1) . ' days'));
            $to    = date('Y-m-d', strtotime($last . ' +' . (7 - (int) date('N', strtotime($last))) . ' days'));
        }
        [$fromDt, $toDt] = [$from . ' 00:00:00', $to . ' 23:59:59'];

        $ids = $this->visibleIds();
        $d   = (new DeadlineModel())->whereIn('matter_id', $ids)->where('status !=', 'cancelled')
            ->where('due_at >=', $fromDt)->where('due_at <=', $toDt);
        $tk  = (new TaskModel())->whereIn('matter_id', $ids)->where('status !=', 'cancelled')
            ->where('due_at >=', $fromDt)->where('due_at <=', $toDt);
        if ($mine) {
            $d->where('assigned_to', $uid);
            $tk->where('assigned_to', $uid);
        }
        $deadlines = $d->orderBy('due_at', 'ASC')->findAll(1000);
        $tasks     = $tk->orderBy('due_at', 'ASC')->findAll(1000);
        $hearings  = (new HearingModel())->whereIn('matter_id', $ids)->where('status !=', 'cancelled')
            ->where('scheduled_at >=', $fromDt)->where('scheduled_at <=', $toDt)->orderBy('scheduled_at', 'ASC')->findAll(1000);

        // [Y-m-d => eventos ordenados por hora]. Los tres tipos comparten la misma forma.
        $events = [];
        $push   = static function (string $type, array $r, string $at, bool $done, bool $alert) use (&$events) {
            $events[substr($at, 0, 10)][] = ['type' => $type, 'at' => $at, 'row' => $r, 'done' => $done, 'alert' => $alert];
        };
        foreach ($deadlines as $r) {
            $push('deadline', $r, (string) $r['due_at'], $r['status'] === 'done', (int) $r['is_critical'] === 1 || $r['status'] === 'to_validate');
        }
        foreach ($hearings as $r) {
            $push('hearing', $r, (string) $r['scheduled_at'], $r['status'] === 'held', $r['status'] === 'postponed');
        }
        foreach ($tasks as $r) {
            $push('task', $r, (string) $r['due_at'], $r['status'] === 'done', false);
        }
        foreach ($events as &$list) {
            usort($list, static fn ($a, $b) => strcmp($a['at'], $b['at']));
        }
        unset($list);

        $cal  = new \Components\LegalCase\Libraries\Calendar($this->companyId());
        $days = [];
        for ($x = strtotime($from . ' 12:00:00'); $x <= strtotime($to . ' 12:00:00'); $x += 86400) {
            $ymd        = date('Y-m-d', $x);
            $days[$ymd] = $cal->reason($ymd);
        }

        $step = ['dia' => '1 day', 'semana' => '7 days', 'mes' => '1 month'][$scale];
        // El mes se mueve desde el día 1: «31 de enero + 1 mes» en PHP cae en marzo.
        $anchor = $scale === 'mes' ? date('Y-m-01', $t) : $date;

        return view('Components\LegalCase\Views\calendar', [
            'title'   => lang('Lgc.navAgenda'),
            'scale'   => $scale,
            'date'    => $date,
            'from'    => $from,
            'to'      => $to,
            'prev'    => date('Y-m-d', strtotime($anchor . ' -' . $step)),
            'next'    => date('Y-m-d', strtotime($anchor . ' +' . $step)),
            'days'    => $days,
            'events'  => $events,
            'mine'    => $mine,
            'numbers' => $this->numbers(array_merge($deadlines, $tasks, $hearings)),
            'users'   => Team::users($this->companyId()),
        ]);
    }

    public function communications()
    {
        $ids = $this->visibleIds();

        $overdue = array_values(array_filter(
            Clock::overdueQuery($this->companyId())->get()->getResultArray(),
            fn ($r) => in_array((int) $r['matter_id'], $ids, true)
        ));
        $reviews = (new UpdateModel())->whereIn('matter_id', $ids)->whereIn('status', ['pending_review', 'approved'])->orderBy('submitted_at', 'ASC')->findAll(100);
        $failed  = (new DeliveryModel())->where('company_id', $this->companyId())->whereIn('matter_id', $ids)->where('status', 'failed')->orderBy('id', 'DESC')->findAll(100);

        return view('Components\LegalCase\Views\communications', [
            'title'   => lang('Lgc.navComms'),
            'overdue' => $overdue,
            'reviews' => $reviews,
            'failed'  => $failed,
            'numbers' => $this->numbers(array_merge($reviews, $failed)),
            'users'   => Team::users($this->companyId()),
        ]);
    }
}
