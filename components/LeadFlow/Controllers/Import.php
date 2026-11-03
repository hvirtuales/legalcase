<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\ImportService;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;

/** Importación de leads desde CSV. */
class Import extends BaseLfl
{
    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return redirect()->to(base_url('leadflow/leads'));
        }

        return $this->render('leads/import', [
            'services'  => (new ServiceModel())->options($cid),
            'sources'   => (new SourceModel())->options($cid),
            'campaigns' => (new CampaignModel())->options($cid),
            'team'      => Team::users($cid),
            'result'    => session()->getFlashdata('lfl_import'),
            'planBlock' => (new PlanGuard())->blockReason($cid),
        ]);
    }

    public function process()
    {
        $cid = $this->companyId();
        $a   = $this->access();
        if (! $cid || ! $a->canWrite) {
            return $this->notFound();
        }

        $assigned = (int) $this->request->getPost('assigned_user_id');
        $res      = (new ImportService())->process($cid, $this->request->getFile('csv'), [
            'skip_duplicates'  => (bool) $this->request->getPost('skip_duplicates'),
            'service_id'       => (int) $this->request->getPost('service_id') ?: null,
            'source_id'        => (int) $this->request->getPost('source_id') ?: null,
            'campaign_id'      => (int) $this->request->getPost('campaign_id') ?: null,
            'assigned_user_id' => $a->canAssignOthers() ? ($assigned ?: null) : $a->userId,
        ]);

        $this->flash($res['ok'] ? 'success' : 'error', $res['message']);
        session()->setFlashdata('lfl_import', $res);

        return redirect()->to(base_url('leadflow/leads/importar'));
    }

    public function template()
    {
        return $this->response->download('leadflow-plantilla.csv', ImportService::template())->setContentType('text/csv; charset=utf-8');
    }
}
