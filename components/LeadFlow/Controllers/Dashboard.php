<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\DemoData;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Libraries\ReportService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\ServiceModel;

/**
 * Inicio (§35) y Reportes (§27).
 */
class Dashboard extends BaseLfl
{
    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('dashboard', ['home' => null]);
        }
        $a = $this->access();

        return $this->render('dashboard', [
            'home'      => $a->scope === 'none' ? null : (new ReportService())->home($a),
            'services'  => (new ServiceModel())->options($cid, false),
            'team'      => Team::users($cid),
            'planBlock' => (new PlanGuard())->blockReason($cid),
            'leadsLeft' => (new PlanGuard())->leadsLeft($cid),
            'isEmpty'   => DemoData::isEmpty($cid),
            'canCreate' => $this->can('lfl.create') && $a->canWrite,
        ]);
    }

    public function reports()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('reports', ['report' => null]);
        }

        $from = (string) $this->request->getGet('desde');
        $to   = (string) $this->request->getGet('hasta');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $a = $this->access();

        return $this->render('reports', [
            'report'   => $a->scope === 'none' ? null : (new ReportService())->period($a, $from, $to),
            'from'     => $from,
            'to'       => $to,
            'currency' => (new \Components\LeadFlow\Models\SettingsModel())->forCompany($cid)['currency'] ?? 'USD',
        ]);
    }
}
