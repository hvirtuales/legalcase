<?php

namespace Components\LegalCase\Controllers;

use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Libraries\DeadlineService;
use Components\LegalCase\Libraries\HearingService;
use Components\LegalCase\Models\DeadlineModel;
use Components\LegalCase\Models\HearingModel;
use Components\LegalCase\Models\EventModel;
use Components\LegalCase\Models\ProceedingModel;
use Components\LegalCase\Models\TaskModel;

/** El trabajo del asunto: procesos, actuaciones, plazos y tareas. */
class Work extends BaseLgc
{
    private DeadlineService $svc;

    public function __construct()
    {
        $this->svc = new DeadlineService();
    }

    private function closedGuard(array $matter)
    {
        return $matter['status'] === 'closed'
            ? redirect()->to($this->matterUrl($matter))->with('error', esc(lang('Lgc.errAlreadyClosed')))
            : null;
    }

    public function saveProceeding($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        if ($stop = $this->closedGuard($matter)) {
            return $stop;
        }

        return $this->done($this->svc->saveProceeding($matter, $this->request->getPost(), $this->acc()), $this->matterUrl($matter, 'procesos'));
    }

    public function updateProceeding($id = null)
    {
        [$row, $matter] = $this->findChild(ProceedingModel::class, $id);
        if (!$row) {
            return $this->notFound();
        }

        return $this->done($this->svc->saveProceeding($matter, $this->request->getPost(), $this->acc(), $row), $this->matterUrl($matter, 'procesos'));
    }

    public function deleteProceeding($id = null)
    {
        [$row, $matter] = $this->findChild(ProceedingModel::class, $id);
        if (!$row) {
            return $this->notFound();
        }
        (new ProceedingModel())->delete((int) $row['id']);
        Audit::log($this->companyId(), (int) $matter['id'], 'proceeding.deleted', 'proceeding', (int) $row['id']);

        return redirect()->to($this->matterUrl($matter, 'procesos'))->with('success', esc(lang('Lgc.okDeleted')));
    }

    public function saveEvent($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        $r = $this->svc->createEvent($matter, $this->request->getPost(), $this->acc());

        return $this->done($r, $this->matterUrl($matter, 'procesos'));
    }

    public function deleteEvent($id = null)
    {
        [$row, $matter] = $this->findChild(EventModel::class, $id);
        if (!$row) {
            return $this->notFound();
        }
        (new EventModel())->delete((int) $row['id']);
        Audit::log($this->companyId(), (int) $matter['id'], 'event.deleted', 'event', (int) $row['id']);

        return redirect()->to($this->matterUrl($matter, 'procesos'))->with('success', esc(lang('Lgc.okDeleted')));
    }

    public function saveDeadline($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        if ($stop = $this->closedGuard($matter)) {
            return $stop;
        }

        return $this->done($this->svc->createDeadline($matter, $this->request->getPost(), $this->acc()), $this->matterUrl($matter, 'plazos'));
    }

    public function validateDeadline($id = null)
    {
        [$row, $matter] = $this->findChild(DeadlineModel::class, $id);
        if (!$row) {
            return $this->notFound();
        }

        return $this->done($this->svc->validate($row, $this->acc()), $this->matterUrl($matter, 'plazos'));
    }

    public function deadlineStatus($id = null)
    {
        [$row, $matter] = $this->findChild(DeadlineModel::class, $id);
        if (!$row) {
            return $this->notFound();
        }

        return $this->done($this->svc->setDeadlineStatus($row, (string) $this->post('status'), $this->acc()), $this->matterUrl($matter, 'plazos'));
    }

    public function saveTask($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done($this->svc->createTask($matter, $this->request->getPost(), $this->acc()), $this->matterUrl($matter, 'plazos'));
    }

    public function taskStatus($id = null)
    {
        [$row, $matter] = $this->findChild(TaskModel::class, $id);
        if (!$row) {
            return $this->notFound();
        }
        $back = (string) $this->post('return_to') === 'agenda' ? 'legalcase/agenda' : $this->matterUrl($matter, 'plazos');

        return $this->done($this->svc->setTaskStatus($row, (string) $this->post('status'), $this->acc()), $back);
    }

    // ------------------------------------------------------------------ audiencias

    public function saveHearing($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        if ($stop = $this->closedGuard($matter)) {
            return $stop;
        }

        return $this->done((new HearingService())->save($matter, $this->request->getPost(), $this->acc()), $this->matterUrl($matter, 'plazos'));
    }

    public function updateHearing($id = null)
    {
        [$row, $matter] = $this->findChild(HearingModel::class, $id);
        if (!$row) {
            return $this->notFound();
        }

        return $this->done((new HearingService())->save($matter, $this->request->getPost(), $this->acc(), $row), $this->matterUrl($matter, 'plazos'));
    }

    public function hearingStatus($id = null)
    {
        [$row, $matter] = $this->findChild(HearingModel::class, $id);
        if (!$row) {
            return $this->notFound();
        }

        return $this->done((new HearingService())->setStatus($matter, $row, (string) $this->post('status'), $this->acc()), $this->matterUrl($matter, 'plazos'));
    }
}
