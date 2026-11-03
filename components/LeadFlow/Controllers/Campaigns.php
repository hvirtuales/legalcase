<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\SourceModel;

/** Marketing → Campañas. */
class Campaigns extends BaseCrud
{
    public function __construct()
    {
        $this->model   = new CampaignModel();
        $this->route   = 'leadflow/marketing/campanas';
        $this->viewDir = 'marketing/campaigns';
        $this->langKey = 'campaign';
    }

    public function index()
    {
        $cid  = $this->companyId();
        $rows = $cid ? (new CampaignModel())->select('cmp_lfl_campaigns.*, s.name AS source_name')
            ->join('cmp_lfl_sources s', 's.id = cmp_lfl_campaigns.source_id', 'left')
            ->where('cmp_lfl_campaigns.company_id', $cid)->orderBy('cmp_lfl_campaigns.id', 'desc')->findAll() : null;

        return $this->render('marketing/campaigns/index', ['rows' => $rows]);
    }

    protected function formData(int $companyId, ?array $row): array
    {
        return ['sources' => (new SourceModel())->options($companyId, false)];
    }

    protected function fields(int $companyId): array
    {
        $source = $this->postInt('source_id');
        $budget = trim(str_replace(',', '.', (string) $this->request->getPost('budget')));

        return [
            'name'         => $this->str('name', 120),
            'source_id'    => $source && (new SourceModel())->owned($source, $companyId) ? $source : null,
            'utm_campaign' => mb_strtolower($this->str('utm_campaign', 120)) ?: null,
            'budget'       => $budget !== '' && is_numeric($budget) ? round((float) $budget, 2) : null,
            'starts_on'    => $this->str('starts_on', 10) ?: null,
            'ends_on'      => $this->str('ends_on', 10) ?: null,
            'notes'        => $this->str('notes', 2000) ?: null,
            'is_active'    => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }
}
