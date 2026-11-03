<?php

namespace Components\Formularios\Controllers;

use App\Controllers\BaseController;
use Components\Formularios\Libraries\Captcha;
use Components\Formularios\Models\SettingModel;

/**
 * Configuración del componente por empresa: a dónde avisar, captcha,
 * consentimiento y su versión.
 *
 * La versión de la política se guarda en cada respuesta, así que cambiar el
 * texto no reescribe lo que firmó quien respondió antes.
 */
class Settings extends BaseController
{
    private SettingModel $settings;

    public function __construct()
    {
        $this->settings = new SettingModel();
    }

    private function companyId(): int
    {
        return (int) (session('active_company_id') ?? 0);
    }

    public function index()
    {
        $companyId = $this->companyId();

        return view('Components\Formularios\Views\settings', [
            'title'    => lang('Frm.settings'),
            'conf'     => $companyId ? $this->settings->forCompany($companyId) : [],
            'hasGd'    => (new Captcha())->available(),
            'company'  => $companyId,
        ]);
    }

    public function save()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('formularios')->with('error', lang('Frm.noCompany'));
        }

        $conf  = $this->settings->forCompany($companyId);
        $email = trim((string) $this->request->getPost('notify_email'));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', lang('Frm.errEmail'));
        }

        $this->settings->update((int) $conf['id'], [
            'notify_email'    => mb_substr($email, 0, 190),
            'captcha_enabled' => $this->request->getPost('captcha_enabled') ? 1 : 0,
            'require_consent' => $this->request->getPost('require_consent') ? 1 : 0,
            'policy_text'     => mb_substr(trim(strip_tags((string) $this->request->getPost('policy_text'))), 0, 4000),
            'policy_version'  => mb_substr(trim((string) $this->request->getPost('policy_version')) ?: '1.0', 0, 20),
            'retention_days'  => max(0, (int) $this->request->getPost('retention_days')),
        ]);

        return redirect()->to('formularios/configuracion')->with('success', lang('Frm.settingsSaved'));
    }
}
