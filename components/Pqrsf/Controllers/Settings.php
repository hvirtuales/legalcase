<?php

namespace Components\Pqrsf\Controllers;

use App\Controllers\BaseController;
use Components\Pqrsf\Libraries\PqrService;
use Components\Pqrsf\Models\RequestModel;
use Components\Pqrsf\Models\SettingsModel;

/** Configuración del componente por empresa. */
class Settings extends BaseController
{
    public function index()
    {
        $companyId = (int) (session()->get('active_company_id') ?? 0);
        if (!$companyId) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errNoCompany'));
        }

        $model = new SettingsModel();
        $cfg   = $model->forCompany($companyId);

        if (strtolower($this->request->getMethod()) === 'post') {
            $r    = $this->request;
            $data = [
                // Los plazos ya no están aquí: son de cada tipo (`/pqrsf/tipos`).
                'prefix'          => mb_substr(trim((string) $r->getPost('prefix')), 0, 10) ?: 'PQR',
                'allow_anonymous' => $r->getPost('allow_anonymous') ? 1 : 0,
                'captcha_enabled' => $r->getPost('captcha_enabled') ? 1 : 0,
                'privacy_url'     => trim((string) $r->getPost('privacy_url')),
                'privacy_text'    => trim((string) $r->getPost('privacy_text')),
                'terms_url'       => trim((string) $r->getPost('terms_url')),
                'terms_text'      => trim((string) $r->getPost('terms_text')),
                'policy_version'  => mb_substr(trim((string) $r->getPost('policy_version')), 0, 40) ?: 'v1',
                'ai_enabled'      => $r->getPost('ai_enabled') ? 1 : 0,
                'ai_tone'         => in_array($r->getPost('ai_tone'), ['formal', 'cercano', 'breve'], true) ? $r->getPost('ai_tone') : 'formal',
                'ai_instructions' => mb_substr(trim((string) $r->getPost('ai_instructions')), 0, 8000),
                'ack_template'    => trim((string) $r->getPost('ack_template')),
            ];

            $model->update((int) $cfg['id'], $data);

            return redirect()->to('pqrsf/configuracion')->with('success', lang('Pqr.okSettings'));
        }

        return view('Components\Pqrsf\Views\settings', [
            'title'    => lang('Pqr.settings'),
            'cfg'      => $cfg,
            'aiReady'  => (new PqrService())->aiAvailable(['ai_enabled' => 1]),
            // Para el botón «usar la de ejemplo» y los marcadores admitidos.
            'aiSample' => (new PqrService())->defaultInstructions(),
            'aiVars'   => PqrService::PLACEHOLDERS,
            'next'    => (string) ($cfg['prefix'] ?: 'PQR') . '-' . str_pad((string) ((int) $cfg['last_number'] + 1), 6, '0', STR_PAD_LEFT),
            'used'    => (new RequestModel())->where('company_id', $companyId)->countAllResults(),
        ]);
    }
}
