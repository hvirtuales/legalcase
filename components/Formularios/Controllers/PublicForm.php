<?php

namespace Components\Formularios\Controllers;

use App\Controllers\BaseController;
use App\Models\CompanyModel;
use Components\Formularios\Libraries\Captcha;
use Components\Formularios\Libraries\FormService;
use Components\Formularios\Libraries\PdfService;
use Components\Formularios\Libraries\PlanGuard;
use Components\Formularios\Libraries\QrService;
use Components\Formularios\Libraries\Renderer;
use Components\Formularios\Libraries\Validator;
use Components\Formularios\Libraries\ResponseService;
use Components\Formularios\Models\AttachmentModel;
use Components\Formularios\Models\FormModel;
use Components\Formularios\Models\ResponseModel;
use Components\Formularios\Models\SettingModel;

/**
 * El formulario publicado: `/formulario/{token}`.
 *
 * Sin sesión, pero con reglas, y todas comprobadas en el servidor: estado,
 * ventana de fechas, cupo del formulario, cupo y servicio del plan, contraseña,
 * captcha y freno por intentos. Ocultar el botón de enviar no es ninguna de
 * ellas.
 *
 * Se sirve en dos presentaciones, según `settings.display`:
 *   - `card`       una ficha centrada, la de siempre.
 *   - `fullscreen` ocupa la ventana entera, una página por pantalla, con botón
 *                  para entrar en pantalla completa de verdad (API Fullscreen).
 * Las dos comparten renderizador y hoja de estilos: cambia el envoltorio, no el
 * formulario.
 */
class PublicForm extends BaseController
{
    private FormModel $forms;
    private FormService $svc;

    public function __construct()
    {
        $this->forms = new FormModel();
        $this->svc   = new FormService();
    }

    /**
     * Resuelve el formulario por su token, o null.
     *
     * El 404 NO distingue entre «no existe», «está en borrador», «está
     * cerrado» y «el plan no lo incluye»: distinguirlos convertiría la URL en
     * una sonda de lo que tiene contratado cada empresa.
     */
    private function resolve(?string $token): ?array
    {
        $form = $this->forms->byToken((string) $token);
        if (!$form) {
            return null;
        }
        if (!(new PlanGuard())->serviceEnabled((int) $form['company_id'])) {
            return null;
        }

        return $form;
    }

    private function company(array $form): array
    {
        return (new CompanyModel())->find((int) $form['company_id']) ?? [];
    }


    /**
     * Clave del freno por intentos.
     *
     * La dirección va HASHEADA y no en crudo: una IPv6 como `::1` lleva dos
     * puntos, que son caracteres reservados en las claves de caché de CI4, y la
     * petición moría con un 500 antes de llegar a validar nada. Lo destapó la
     * primera prueba real por HTTP, no las pruebas de unidad.
     */
    private function throttleKey(string $prefix, array $form): string
    {
        return $prefix . $form['id'] . '_' . md5((string) $this->request->getIPAddress());
    }
    /** ¿Ya pasó la contraseña de este formulario en esta sesión? */
    private function unlocked(array $form): bool
    {
        if (empty($form['password_hash'])) {
            return true;
        }

        return (bool) session('frm_ok_' . $form['id']);
    }

    public function show($token = null)
    {
        $form = $this->resolve($token);
        if (!$form) {
            return $this->notFound();
        }

        $company = $this->company($form);
        $reason  = $this->svc->closedReason($form);

        if ($reason !== null) {
            return $this->page($form, $company, 'closed', ['reason' => $reason]);
        }
        if (!$this->unlocked($form)) {
            return $this->page($form, $company, 'password', []);
        }

        $this->forms->countView((int) $form['id']);

        return $this->render($form, $company, [], []);
    }

    /** Pinta el formulario en sí, con valores y errores si vuelve de un fallo. */
    private function render(array $form, array $company, array $values, array $errors)
    {
        $schema   = $this->svc->liveSchema($form);
        $settings = $this->svc->sanitizeSettings($form['settings'] ?? []);
        $captcha  = new Captcha();
        $conf     = (new SettingModel())->forCompany((int) $form['company_id']);

        $useCaptcha = !empty($conf['captcha_enabled']) && $captcha->available();
        $code       = $useCaptcha ? $captcha->issue() : '';

        return $this->page($form, $company, 'form', [
            'body'        => (new Renderer($schema, $settings, $values, $errors))->render(),
            'schema'      => $schema,
            'settings'    => $settings,
            'pages'       => count($schema['pages'] ?? []),
            'errors'      => $errors,
            'captcha'     => $useCaptcha ? $captcha->image($code) : '',
            'stamp'       => $captcha->stamp(),
            'consent'     => (string) ($conf['policy_text'] ?? ''),
            'consentOn'   => !empty($conf['require_consent']),
        ]);
    }

    /**
     * Un solo punto de salida para las cuatro pantallas públicas, de modo que
     * todas hereden el mismo lienzo y la misma presentación (ficha o pantalla
     * completa) sin repetirla en cada vista.
     */
    private function page(array $form, array $company, string $mode, array $extra)
    {
        $settings = $this->svc->sanitizeSettings($form['settings'] ?? []);

        return view('Components\Formularios\Views\public\form', array_merge([
            'form'     => $form,
            'company'  => $company,
            'settings' => $settings,
            'display'  => $settings['display'],
            'mode'     => $mode,
            'action'   => base_url('formulario/' . $form['public_token']),
        ], $extra));
    }

    private function notFound()
    {
        return $this->response->setStatusCode(404)
            ->setBody(view('Components\Formularios\Views\public\not_found'));
    }

    /**
     * Envío. Ocho comprobaciones antes de guardar nada, todas en el servidor.
     */
    public function submit($token = null)
    {
        $form = $this->resolve($token);
        if (!$form) {
            return $this->notFound();
        }

        $company = $this->company($form);
        $post    = $this->request->getPost() ?? [];

        // 1. Contraseña: este POST puede ser el del propio formulario de acceso.
        if (!empty($form['password_hash']) && !$this->unlocked($form)) {
            return $this->checkPassword($form, $company, (string) ($post['password'] ?? ''));
        }

        // 2. Estado, ventana y cupo del formulario.
        $reason = $this->svc->closedReason($form);
        if ($reason !== null) {
            return $this->page($form, $company, 'closed', ['reason' => $reason]);
        }

        // 3. Cupo del plan de la empresa: también se consume al responder.
        $planReason = (new PlanGuard())->blockedForResponse((int) $form['company_id']);
        if ($planReason !== null) {
            return $this->page($form, $company, 'closed', ['reason' => $planReason]);
        }

        // 4. Freno por intentos: cinco envíos por minuto y token, como el
        //    enlace público de una cotización.
        if (!service('throttler')->check($this->throttleKey('frm_', $form), 5, MINUTE)) {
            return $this->page($form, $company, 'closed', ['reason' => lang('Frm.pubTooMany')]);
        }

        // 5. Campo trampa: invisible para una persona, irresistible para un bot.
        if (trim((string) ($post['website'] ?? '')) !== '') {
            return $this->page($form, $company, 'closed', ['reason' => lang('Frm.pubRejected')]);
        }

        $captcha = new Captcha();
        $conf    = (new SettingModel())->forCompany((int) $form['company_id']);

        // 6. Velocidad humana, con la marca firmada para que no se invente.
        if (!$captcha->humanSpeed((string) ($post['ts'] ?? ''))) {
            return $this->fail($form, $company, $post, lang('Frm.pubTooFast'));
        }

        // 7. Captcha, si la empresa lo tiene activo y GD está disponible.
        if (!empty($conf['captcha_enabled']) && $captcha->available() && !$captcha->check((string) ($post['captcha'] ?? ''))) {
            return $this->fail($form, $company, $post, lang('Frm.pubBadCaptcha'));
        }

        // 8. Consentimiento, si la empresa lo exige.
        if (!empty($conf['require_consent']) && empty($post['consent'])) {
            return $this->fail($form, $company, $post, lang('Frm.pubConsentNeeded'));
        }

        return $this->store($form, $company, $post);
    }

    /** Valida contra el esquema publicado y guarda. */
    private function store(array $form, array $company, array $post)
    {
        $schema = $this->svc->liveSchema($form);

        // Los archivos se piden por su nombre exacto: `file_<idCampo>`. Lo que
        // llegue con otro nombre no existe para el validador.
        $files = [];
        foreach ($schema['fields'] ?? [] as $f) {
            if (\Components\Formularios\Libraries\FieldRegistry::valueKind((string) $f['type']) !== 'file') {
                continue;
            }
            $file = $this->request->getFile('file_' . $f['id']);
            if ($file !== null) {
                $files['file_' . $f['id']] = $file;
            }
        }

        $result = (new Validator())->validate($schema, $post, $files);

        if (!$result['ok']) {
            return $this->render($form, $company, $this->rawValues($post), $result['errors']);
        }

        $saved = (new ResponseService())->submit($form, $schema, $result, [
            'ip'         => (string) $this->request->getIPAddress(),
            'user_agent' => (string) $this->request->getUserAgent(),
            'duration'   => (int) ($post['elapsed'] ?? 0),
            'user_id'    => session('user_id') ? (int) session('user_id') : null,
        ]);

        if (!$saved['ok']) {
            return $this->render($form, $company, $this->rawValues($post), ['__form' => $saved['message']]);
        }

        $settings = $this->svc->sanitizeSettings($form['settings'] ?? []);
        if (($settings['redirectUrl'] ?? '') !== '') {
            return redirect()->to($settings['redirectUrl']);
        }

        return $this->page($form, $company, 'done', [
            'reference' => $saved['reference'],
            'receipt'   => base_url('formulario/r/' . $saved['token']),
        ]);
    }

    /** Los valores tal como llegaron, para repintar el formulario tras un error. */
    private function rawValues(array $post): array
    {
        return is_array($post['f'] ?? null) ? $post['f'] : [];
    }

    private function fail(array $form, array $company, array $post, string $message)
    {
        return $this->render($form, $company, $this->rawValues($post), ['__form' => $message]);
    }

    /** Contraseña del formulario, con freno propio por fuerza bruta. */
    private function checkPassword(array $form, array $company, string $password)
    {
        if (!service('throttler')->check($this->throttleKey('frmpwd_', $form), 5, MINUTE)) {
            return $this->page($form, $company, 'password', ['error' => lang('Frm.pubTooMany')]);
        }

        if (!password_verify($password, (string) $form['password_hash'])) {
            return $this->page($form, $company, 'password', ['error' => lang('Frm.pubBadPassword')]);
        }

        session()->set('frm_ok_' . $form['id'], true);

        return redirect()->to('formulario/' . $form['public_token']);
    }

    /**
     * Comprobante de la respuesta: `/formulario/r/{token}`.
     *
     * El token es de la RESPUESTA, aleatorio y de 24 bytes: es lo único que se
     * le entrega a quien contestó, y no sirve para ver ninguna otra.
     */
    public function receipt($token = null)
    {
        $data = $this->receiptData((string) $token);
        if (!$data) {
            return $this->notFound();
        }

        return view('Components\Formularios\Views\public\receipt', $data + [
            'qr' => (new QrService())->dataUri(base_url('formulario/r/' . $token), 4),
        ]);
    }

    public function receiptPdf($token = null)
    {
        $data = $this->receiptData((string) $token);
        if (!$data) {
            return $this->notFound();
        }

        $html = view('Components\Formularios\Views\pdf\receipt', $data);
        $pdf  = (new PdfService())->render($html);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $data['response']['reference'] . '.pdf"')
            ->setBody($pdf);
    }

    /**
     * Los datos del comprobante.
     *
     * Se pintan las etiquetas CONGELADAS en la respuesta, no las del formulario
     * de hoy: quien contestó tiene derecho a ver lo que se le preguntó, no lo
     * que se pregunta ahora.
     */
    private function receiptData(string $token): ?array
    {
        $response = (new ResponseModel())->byToken($token);
        if (!$response) {
            return null;
        }

        $form = $this->forms->find((int) $response['form_id']);
        if (!$form) {
            return null;
        }

        $files = [];
        foreach ((new AttachmentModel())->forResponse((int) $response['id']) as $att) {
            $files[(string) $att['field_id']][] = $att['original_name'];
        }

        return [
            'form'     => $form,
            'company'  => $this->company($form),
            'response' => $response,
            'labels'   => is_array($response['labels'] ?? null) ? $response['labels'] : [],
            'values'   => is_array($response['data'] ?? null) ? $response['data'] : [],
            'files'    => $files,
            'settings' => $this->svc->sanitizeSettings($form['settings'] ?? []),
        ];
    }
}
