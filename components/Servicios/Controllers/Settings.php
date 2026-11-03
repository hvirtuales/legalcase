<?php

namespace Components\Servicios\Controllers;

use App\Controllers\BaseController;
use Components\Servicios\Libraries\HtmlSanitizer;
use Components\Servicios\Models\QuoteModel;
use Components\Servicios\Models\SettingsModel;

/**
 * Ajustes del documento: cómo se llama, cómo se numera, sus textos fijos y la
 * carta de presentación. Los datos de la empresa (razón social, NIT, logo, color)
 * se editan en «Mi empresa» del núcleo, no aquí.
 */
class Settings extends BaseController
{
    public function index()
    {
        $companyId = (int) (session()->get('active_company_id') ?? 0);
        if (!$companyId) {
            return view('Components\Servicios\Views\settings', ['title' => lang('Quo.settings'), 'cfg' => null]);
        }

        $model = new SettingsModel();
        $cfg   = $model->forCompany($companyId);

        if (strtolower($this->request->getMethod()) === 'post') {
            $r     = $this->request;
            $clean = new HtmlSanitizer();

            $data = [
                'doc_type'              => trim((string) $r->getPost('doc_type')),
                'prefix'                => strtoupper(trim((string) $r->getPost('prefix'))),
                'initial_number'        => max(1, (int) $r->getPost('initial_number')),
                'default_currency'      => in_array($r->getPost('default_currency'), QuoteModel::CURRENCIES, true) ? $r->getPost('default_currency') : 'COP',
                'default_validity_days' => max(0, (int) $r->getPost('default_validity_days')),
                'default_retention'     => max(0, (float) $r->getPost('default_retention')),
                'terms_html'            => $clean->clean($r->getPost('terms_html')),
                'cover_enabled'         => $r->getPost('cover_enabled') ? 1 : 0,
                'cover_title'           => trim((string) $r->getPost('cover_title')),
                'cover_html'            => $clean->clean($r->getPost('cover_html')),
                'show_detail_default'   => $r->getPost('show_detail_default') ? 1 : 0,
            ];

            if (!$model->update((int) $cfg['id'], $data)) {
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }

            return redirect()->to('servicios/configuracion')->with('success', lang('Quo.okSettings'));
        }

        return view('Components\Servicios\Views\settings', [
            'title'   => lang('Quo.settings'),
            'cfg'     => $cfg,
            'company' => (new \App\Models\CompanyModel())->find($companyId),
            'preview' => $this->previewNumber($cfg),
        ]);
    }

    /** Próximo número según el prefijo y el último usado (solo informativo). */
    private function previewNumber(array $cfg): string
    {
        $prefix  = trim((string) $cfg['prefix']) ?: 'COT';
        $initial = max(1, (int) $cfg['initial_number']);
        $next    = ((string) $cfg['last_prefix'] === $prefix && (int) $cfg['last_number'] > 0)
            ? max((int) $cfg['last_number'] + 1, $initial)
            : $initial;

        return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
