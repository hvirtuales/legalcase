<?php

namespace Components\LegalCase\Controllers;

use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Libraries\Portal;
use Components\LegalCase\Models\SettingsModel;

/** Configuración del despacho: numeración, políticas, cadencia, SLA y portal. */
class Settings extends BaseLgc
{
    public function index()
    {
        $model = new SettingsModel();
        $row   = $model->forCompanyOrDefault($this->companyId());

        if ($this->isPost()) {
            $data = [
                'matter_prefix'        => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->post('matter_prefix')) ?: 'LC'),
                'approval_policy'      => (string) $this->post('approval_policy'),
                'deadline_policy'      => (string) $this->post('deadline_policy'),
                'default_cadence_days' => (int) $this->post('default_cadence_days'),
                'response_sla_hours'   => (int) $this->post('response_sla_hours'),
                'portal_enabled'       => $this->post('portal_enabled') ? 1 : 0,
                'notify_email'         => $this->post('notify_email') ? 1 : 0,
                'portal_help'          => trim((string) $this->post('portal_help')),
            ];
            if (!$model->update((int) $row['id'], $data)) {
                return redirect()->back()->withInput()->with('error', esc(implode(' ', $model->errors())));
            }
            Audit::log($this->companyId(), null, 'settings.saved', 'settings', (int) $row['id'], array_diff_key($data, ['portal_help' => 1]));

            return redirect()->to('legalcase/configuracion')->with('success', esc(lang('Lgc.okSaved')));
        }

        return view('Components\LegalCase\Views\settings', [
            'title' => lang('Lgc.settings'), 'row' => $row, 'portalUrl' => Portal::url(),
        ]);
    }
}
