<?php

namespace Components\LegalCase\Controllers;

use Components\LegalCase\Libraries\Clock;
use Components\LegalCase\Libraries\Team;
use Components\LegalCase\Models\DeadlineModel;
use Components\LegalCase\Models\DeliveryModel;
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
            'threads'   => $threads,
            'reviews'   => $reviews,
            'numbers'   => $this->numbers(array_merge($deadlines, $tasks, $threads, $reviews)),
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

        return view('Components\LegalCase\Views\agenda', [
            'title'     => lang('Lgc.navAgenda'),
            'deadlines' => $deadlines,
            'tasks'     => $tasks,
            'mine'      => $mine,
            'numbers'   => $this->numbers(array_merge($deadlines, $tasks)),
            'users'     => Team::users($this->companyId()),
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
