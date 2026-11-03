<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use Components\Contabilidad\Libraries\ReportService;

class Reports extends BaseController
{
    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Contabilidad\Views\reports\index', ['title' => 'Panel de Contabilidad', 'company' => null]);
        }

        $from = $this->request->getGet('from') ?: date('Y-01-01');
        $to   = $this->request->getGet('to') ?: date('Y-m-d');
        $svc  = new ReportService();

        return view('Components\Contabilidad\Views\reports\index', [
            'title'    => 'Panel de Contabilidad',
            'company'  => (new \App\Models\CompanyModel())->find($companyId),
            'from'     => $from,
            'to'       => $to,
            'summary'  => $svc->summary($companyId, $from, $to),
            'byType'   => $svc->byType($companyId, $from, $to),
            'monthly'  => $svc->monthly($companyId, $from, $to),
            'requests' => $svc->requestCounts($companyId),
        ]);
    }
}
