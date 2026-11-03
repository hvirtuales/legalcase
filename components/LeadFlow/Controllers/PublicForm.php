<?php

namespace Components\LeadFlow\Controllers;

use App\Controllers\BaseController;
use Components\LeadFlow\Libraries\Captcha;
use Components\LeadFlow\Libraries\Catalog;
use Components\LeadFlow\Libraries\Dedupe;
use Components\LeadFlow\Libraries\DocumentService;
use Components\LeadFlow\Libraries\FormEngine;
use Components\LeadFlow\Libraries\LeadService;
use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Libraries\ScoringService;
use Components\LeadFlow\Libraries\Timeline;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\SettingsModel;
use Components\LeadFlow\Models\SubmissionModel;

/**
 * El formulario que llena el PROSPECTO (§13, §26). Sin sesión: todo se resuelve
 * desde el token aleatorio de la URL, y cada método vuelve a validarlo.
 *
 * Defensas antes de guardar nada: freno por IP (5/minuto), campo trampa, tiempo
 * mínimo firmado, captcha propio, consentimiento obligatorio y la revalidación
 * completa de las respuestas en el servidor. El 404 no distingue «no existe»,
 * «borrador», «cerrado» ni «el plan no lo incluye»: distinguirlos convertiría la
 * URL en una sonda de lo que tiene contratado cada firma.
 */
class PublicForm extends BaseController
{
    /** @return array{0:?array,1:?array} formulario y empresa, o nulls */
    private function resolve(string $token): array
    {
        $form = (new FormModel())->byToken($token);
        if (! $form || $form['status'] !== 'published' || (int) $form['is_public'] !== 1) {
            return [null, null];
        }
        $companies = new \App\Models\CompanyModel();
        $company   = $companies->find((int) $form['company_id']);
        if (! $company || empty($company['is_active'])) {
            return [null, null];
        }
        if (method_exists($companies, 'isUsable') && ! $companies->isUsable((int) $company['id'])) {
            return [null, null];
        }
        if (! (new PlanGuard())->serviceEnabled((int) $company['id'])) {
            return [null, null];
        }

        return [$form, $company];
    }

    private function invalid()
    {
        return $this->response->setStatusCode(404)->setBody(view('Components\LeadFlow\Views\public\invalid', ['pageTitle' => lang('Lfl.pubInvalidTitle')]));
    }

    private function brand(array $company): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? '')) ? $company['brand_color'] : '#1f2937';
    }

    public function show($token = null)
    {
        [$form, $company] = $this->resolve((string) $token);
        if (! $form) {
            return $this->invalid();
        }
        Catalog::ensure((int) $company['id']);
        (new FormModel())->set('views', 'views + 1', false)->where('id', (int) $form['id'])->update();

        $captcha = new Captcha();
        $utm     = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
            $utm[$k] = mb_substr(trim(strip_tags((string) $this->request->getGet($k))), 0, 150);
        }

        return view('Components\LeadFlow\Views\public\form', [
            'pageTitle'  => $form['name'],
            'form'       => $form,
            'fields'     => FormModel::fields($form),
            'company'    => $company,
            'brand'      => $this->brand($company),
            'settings'   => (new SettingsModel())->forCompany((int) $company['id']),
            'countries'  => Meta::countries(),
            'captchaImg' => $captcha->image($captcha->issue()),
            'stamp'      => $captcha->stamp(),
            'utm'        => $utm,
            'landing'    => mb_substr((string) current_url(true), 0, 255),
            'referrer'   => mb_substr((string) ($this->request->getServer('HTTP_REFERER') ?? ''), 0, 255),
        ]);
    }

    public function captcha($token = null)
    {
        [$form] = $this->resolve((string) $token);
        if (! $form) {
            return $this->response->setStatusCode(404)->setJSON(['img' => '']);
        }
        $c = new Captcha();

        return $this->response->setJSON(['img' => $c->image($c->issue())]);
    }

    public function submit($token = null)
    {
        [$form, $company] = $this->resolve((string) $token);
        if (! $form) {
            return $this->invalid();
        }
        $r   = $this->request;
        $cid = (int) $company['id'];
        $ip  = (string) $r->getIPAddress();

        // 1. Freno por IP + formulario. La IP va hasheada: una IPv6 lleva «:», que
        //    es un carácter reservado en las claves de caché.
        if (! service('throttler')->check('lfl_pub_' . md5($ip . '|' . $form['token']), 5, MINUTE)) {
            return redirect()->back()->withInput()->with('pub_error', lang('Lfl.pubTooMany'));
        }
        // 2. Trampa: un humano no llena un campo que no ve.
        if (trim((string) $r->getPost('website')) !== '') {
            return redirect()->back()->withInput()->with('pub_error', lang('Lfl.pubCaptchaError'));
        }
        // 3. Velocidad humana y 4. captcha.
        $captcha = new Captcha();
        if (! $captcha->humanSpeed((string) $r->getPost('stamp'))) {
            return redirect()->back()->withInput()->with('pub_error', lang('Lfl.pubTooFast'));
        }
        if (extension_loaded('gd') && ! $captcha->check((string) $r->getPost('captcha'))) {
            return redirect()->back()->withInput()->with('pub_error', lang('Lfl.pubCaptchaError'));
        }
        // 5. Consentimiento (§31: consentimientos y avisos aplicables).
        if (! $r->getPost('consent')) {
            return redirect()->back()->withInput()->with('pub_error', lang('Lfl.pubConsentRequired'));
        }

        $contact = [
            'first_name'         => (string) $r->getPost('first_name'),
            'last_name'          => (string) $r->getPost('last_name'),
            'email'              => (string) $r->getPost('email'),
            'phone'              => (string) $r->getPost('phone'),
            'whatsapp'           => $r->getPost('whatsapp_same') ? (string) $r->getPost('phone') : (string) $r->getPost('whatsapp'),
            'country'            => (string) $r->getPost('country'),
            'city'               => (string) $r->getPost('city'),
            'preferred_language' => (string) $r->getPost('preferred_language'),
        ];
        $errors = [];
        if (trim($contact['first_name']) === '' || trim($contact['last_name']) === '') {
            $errors[] = lang('Lfl.vNamesRequired');
        }
        if (Dedupe::email($contact['email']) === null && Dedupe::phone($contact['phone']) === null) {
            $errors[] = lang('Lfl.pubNeedContact');
        }
        if (trim($contact['email']) !== '' && Dedupe::email($contact['email']) === null) {
            $errors[] = lang('Lfl.vEmail');
        }

        // 6. Las respuestas se revalidan contra el esquema PUBLICADO.
        $fields = FormModel::fields($form);
        $files  = [];
        foreach ($fields as $f) {
            if ($f['type'] === 'file') {
                $files[$f['id']] = $r->getFile('q.' . $f['id']);
            }
        }
        $res    = (new FormEngine())->collect($fields, (array) ($r->getPost('q') ?? []), $files);
        $errors = array_merge($errors, array_values($res['errors']));
        if ($errors) {
            return redirect()->back()->withInput()->with('pub_errors', $errors);
        }

        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'landing_page', 'referrer'] as $k) {
            $utm[$k] = (string) $r->getPost($k);
        }

        // ¿Ya existe? Entonces es el MISMO prospecto que vuelve: se le suman estas
        // respuestas en vez de crear un duplicado (§23), y no consume cupo.
        $dup  = (new Dedupe())->find($cid, $contact, null, 1);
        $leadId = 0;
        if ($dup) {
            $leadId = (int) $dup[0]['lead']['id'];
            $update = ['consent_at' => date('Y-m-d H:i:s'), 'consent_ip' => mb_substr($ip, 0, 45)];
            if ($r->getPost('request_consultation')) {
                $update['requested_consultation'] = 1;
            }
            (new LeadModel())->skipValidation(true)->update($leadId, $update);
            Timeline::log($cid, $leadId, 'duplicate', lang('Lfl.tlReturned', [$form['name']]), [], null);
        } else {
            $created = (new LeadService())->create($cid, $contact + $utm + [
                'service_id'             => $form['service_id'],
                'campaign_id'            => $form['campaign_id'],
                'form_id'                => $form['id'],
                'requested_consultation' => $r->getPost('request_consultation') ? 1 : 0,
                'consent'                => 1,
                'consent_ip'             => $ip,
            ], ['channel' => 'public', 'force' => true, 'user_id' => null]);

            if (! $created['ok']) {
                // Plan agotado o servicio desactivado: el prospecto no tiene la culpa,
                // pero tampoco se le puede prometer que alguien lo va a leer.
                log_message('warning', 'LeadFlow: lead público rechazado para la empresa ' . $cid . ': ' . $created['message']);

                return redirect()->back()->withInput()->with('pub_error', lang('Lfl.pubUnavailable'));
            }
            $leadId = (int) $created['id'];
        }

        $lead = (new LeadModel())->find($leadId);
        // Los documentos van ANTES de registrar la respuesta: el criterio «tiene
        // documentos» del scoring cuenta, y `record()` es quien recalcula y quien
        // dispara la automatización de «formulario completado» (§19).
        foreach ($res['files'] as $file) {
            (new DocumentService())->store($lead, $file, null, 'public', null);
        }
        (new SubmissionService())->record($lead, $form, $res, 'public', null, $ip);
        (new FormModel())->set('submissions', 'submissions + 1', false)->where('id', (int) $form['id'])->update();

        return view('Components\LeadFlow\Views\public\done', [
            'pageTitle' => $form['name'],
            'form'      => $form,
            'company'   => $company,
            'brand'     => $this->brand($company),
        ]);
    }
}
