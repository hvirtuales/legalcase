<?php

namespace Components\Pqrsf\Controllers;

use App\Controllers\BaseController;
use Components\Pqrsf\Libraries\AttachmentService;
use Components\Pqrsf\Libraries\Captcha;
use Components\Pqrsf\Libraries\PqrService;
use Components\Pqrsf\Models\SettingsModel;
use Components\Pqrsf\Models\TypeModel;

/**
 * Lo que ve el CIUDADANO: el formulario del QR y la consulta de su ticket.
 *
 * Sin `auth` ni empresa activa: todo se resuelve desde el token de la URL. Cada
 * método vuelve a validarlo; nunca se confía en un id que venga del navegador.
 */
class PublicPqr extends BaseController
{
    private PqrService $service;

    public function __construct()
    {
        $this->service = new PqrService();
    }

    /** GET pqrsf/p/{token} — el formulario del punto. */
    public function form($token = null)
    {
        [$point, $company] = $this->service->resolvePoint((string) $token);
        if (!$point) {
            return $this->response->setStatusCode(404)->setBody(
                view('Components\Pqrsf\Views\public\invalid', ['pageTitle' => lang('Pqr.pubInvalidTitle')])
            );
        }

        $cfg     = (new SettingsModel())->forCompany((int) $company['id']);
        $captcha = new Captcha();

        return view('Components\Pqrsf\Views\public\form', [
            'pageTitle'   => lang('Pqr.pubTitle', [$company['name']]),
            'point'       => $point,
            'company'     => $company,
            'cfg'         => $cfg,
            // Los tipos son los que la empresa ofrece hoy, no una lista fija.
            'types'       => (new TypeModel())->activeFor((int) $company['id']),
            'token'       => $point['token'],
            'captchaImg'  => $cfg['captcha_enabled'] ? $captcha->image($captcha->issue()) : '',
            'stamp'       => $captcha->stamp(),
        ]);
    }

    /** POST pqrsf/p/{token} — radica la solicitud. */
    public function submit($token = null)
    {
        [$point, $company] = $this->service->resolvePoint((string) $token);
        if (!$point) {
            return redirect()->to('/');
        }

        $cfg     = (new SettingsModel())->forCompany((int) $company['id']);
        $captcha = new Captcha();
        $r       = $this->request;

        // 1. Trampa: un humano no llena un campo que no ve.
        if (trim((string) $r->getPost('website')) !== '') {
            return redirect()->back()->withInput()->with('error', lang('Pqr.errCaptcha'));
        }
        // 2. Velocidad: tampoco lo manda en un segundo.
        if (!$captcha->humanSpeed((string) $r->getPost('stamp'))) {
            return redirect()->back()->withInput()->with('error', lang('Pqr.errTooFast'));
        }
        // 3. El código de la imagen.
        if (!empty($cfg['captcha_enabled']) && !$captcha->check((string) $r->getPost('captcha'))) {
            return redirect()->back()->withInput()->with('error', lang('Pqr.errCaptcha'));
        }

        // El anonimato solo existe si la empresa lo permite.
        $anonymous = $r->getPost('mode') === 'anonima' && !empty($cfg['allow_anonymous']);

        $res = $this->service->create($point, $company, [
            'type'          => (string) $r->getPost('type'),
            'subject'       => (string) $r->getPost('subject'),
            'message'       => (string) $r->getPost('message'),
            'anonymous'     => $anonymous,
            'name'          => (string) $r->getPost('name'),
            'doc'           => (string) $r->getPost('doc'),
            'email'         => (string) $r->getPost('email'),
            'phone'         => (string) $r->getPost('phone'),
            'city'          => (string) $r->getPost('city'),
            'accept_terms'  => (bool) $r->getPost('accept_terms'),
            'consent_data'  => (bool) $r->getPost('consent_data'),
            'consent_comms' => (bool) $r->getPost('consent_comms'),
        ]);

        if (!$res['ok']) {
            return redirect()->back()->withInput()->with('error', $res['message']);
        }

        // Evidencias del ciudadano (opcionales).
        $files = $r->getFileMultiple('files') ?: [];
        $att   = new AttachmentService();

        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $att->store($file, 'request', (int) $res['request']['id']);
            }
        }

        return view('Components\Pqrsf\Views\public\done', [
            'pageTitle' => lang('Pqr.doneTitle'),
            'request'   => $res['request'],
            'company'   => $company,
            'point'     => $point,
            'typeName'  => (new TypeModel())->labelFor((int) $company['id'], (string) $res['request']['type']),
            'ticketUrl' => $this->service->ticketUrl($res['request']),
            // Sin identificación o sin autorización, el enlace es la única vía.
            'onlyLink'  => !empty($res['request']['is_anonymous']) || empty($res['request']['consent_comms']),
        ]);
    }

    /** GET pqrsf/captcha/{token} — refresca el reto sin recargar la página. */
    public function captcha($token = null)
    {
        [$point] = $this->service->resolvePoint((string) $token);
        if (!$point) {
            return $this->response->setStatusCode(404)->setJSON(['img' => '']);
        }

        $captcha = new Captcha();

        return $this->response->setJSON(['img' => $captcha->image($captcha->issue())]);
    }

    /** GET pqrsf/t/{token} — la consulta del ciudadano. */
    public function ticket($token = null)
    {
        $request = $this->service->resolveTicket((string) $token);
        if (!$request) {
            return $this->response->setStatusCode(404)->setBody(
                view('Components\Pqrsf\Views\public\invalid', ['pageTitle' => lang('Pqr.pubInvalidTitle')])
            );
        }

        $company = (new \App\Models\CompanyModel())->find((int) $request['company_id']);

        return view('Components\Pqrsf\Views\public\ticket', [
            'pageTitle' => $request['number'],
            'request'   => $request,
            'company'   => $company,
            'typeName'  => (new \Components\Pqrsf\Models\TypeModel())->labelFor((int) $request['company_id'], (string) $request['type']),
            // Solo lo público: las notas internas y los nombres de los gestores
            // no salen de la casa.
            'thread'    => (new \Components\Pqrsf\Models\EventModel())->threadFor((int) $request['id'], true),
            // Lo que la empresa decidió compartir al responder.
            'links'     => $this->service->answerLinks($request),
        ]);
    }

    /**
     * GET pqrsf/t/{token}/adjunto/{id} — descarga de un soporte de la respuesta.
     *
     * Sin sesión: el permiso lo da el token del ticket. Dos condiciones, las dos
     * necesarias: el adjunto tiene que ser **de esa misma solicitud** y estar
     * marcado como público. Sin la primera, un token válido serviría para bajarse
     * los adjuntos de cualquier otra.
     */
    public function ticketAttachment($token = null, $attId = null)
    {
        $request = $this->service->resolveTicket((string) $token);
        if (!$request) {
            return $this->response->setStatusCode(404)->setBody(
                view('Components\Pqrsf\Views\public\invalid', ['pageTitle' => lang('Pqr.pubInvalidTitle')])
            );
        }

        $svc = new AttachmentService();
        $att = $svc->find((int) $attId);

        if (!$att || $att['owner_type'] !== 'request' || (int) $att['owner_id'] !== (int) $request['id'] || empty($att['is_public'])) {
            return $this->response->setStatusCode(404)->setBody(
                view('Components\Pqrsf\Views\public\invalid', ['pageTitle' => lang('Pqr.pubInvalidTitle')])
            );
        }

        $path = $svc->absolutePath($att);
        if (!is_file($path)) {
            return $this->response->setStatusCode(404)->setBody(
                view('Components\Pqrsf\Views\public\invalid', ['pageTitle' => lang('Pqr.pubInvalidTitle')])
            );
        }

        return $this->response->download($path, null)->setFileName($att['name']);
    }
}
