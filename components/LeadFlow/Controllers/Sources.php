<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Models\SourceModel;

/** Marketing → Fuentes. */
class Sources extends BaseCrud
{
    public function __construct()
    {
        $this->model   = new SourceModel();
        $this->route   = 'leadflow/marketing/fuentes';
        $this->viewDir = 'marketing/sources';
        $this->langKey = 'source';
    }

    public function index()
    {
        $cid = $this->companyId();
        $rows = $cid ? (new SourceModel())->forCompany($cid)->orderBy('name', 'asc')->findAll() : null;

        return $this->render('marketing/sources/index', ['rows' => $rows]);
    }

    protected function fields(int $companyId): array
    {
        $kind = (string) $this->request->getPost('kind');

        return [
            'name'       => $this->str('name', 80),
            'kind'       => in_array($kind, SourceModel::KINDS, true) ? $kind : 'other',
            'utm_source' => mb_strtolower($this->str('utm_source', 80)) ?: null,
            'is_active'  => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }
}
