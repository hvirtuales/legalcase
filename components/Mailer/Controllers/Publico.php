<?php

namespace Components\Mailer\Controllers;

use CodeIgniter\Controller;
use Components\Mailer\Libraries\SignupService;
use Components\Mailer\Libraries\SnsVerifier;
use Components\Mailer\Libraries\SuppressionService;
use Components\Mailer\Models\ClickModel;
use Components\Mailer\Models\ContactModel;
use Components\Mailer\Models\RecipientModel;

/**
 * Rutas PÚBLICAS del componente (sin auth):
 *  - Baja del suscriptor (enlace del pie de cada correo).
 *  - Webhook de Amazon SNS para rebotes/quejas (control de rebotes real).
 */
class Publico extends Controller
{
    /**
     * El idioma de las pantallas públicas.
     *
     * Este controlador NO extiende `BaseController` (una baja no exige sesión
     * ni permisos), así que nadie resuelve el idioma por él: sin esto la página
     * salía siempre en el idioma por defecto aunque el visitante ya hubiera
     * elegido otro en la plataforma. Si no hay sesión se queda el de la app,
     * que es lo correcto para quien llega desde un correo.
     */
    private function locale(): void
    {
        $lang = session()->get('language');
        if ($lang) {
            $this->request->setLocale((string) $lang);
        }
    }

    /** Página de baja: confirma y suprime por token del contacto. */
    public function unsubscribe($token = null)
    {
        $this->locale();
        $token   = (string) $token;
        $contact = $token !== '' ? (new ContactModel())->where('unsubscribe_token', $token)->first() : null;

        if (!$contact) {
            return view('Components\Mailer\Views\public\unsubscribe', ['state' => 'invalid', 'email' => '', 'token' => '', 'company' => []]);
        }

        // La marca de la empresa: quien abre esto llegó desde un correo y tiene
        // derecho a ver de quién se está dando de baja.
        $company = (new \App\Models\CompanyModel())->find((int) $contact['company_id']) ?? [];

        if ($this->request->getMethod() === 'POST') {
            (new SuppressionService())->suppress((int) $contact['company_id'], $contact['email'], 'unsubscribe', lang('Mailer.noteFromLink'));

            return view('Components\Mailer\Views\public\unsubscribe', ['state' => 'done', 'email' => $contact['email'], 'token' => $token, 'company' => $company]);
        }

        return view('Components\Mailer\Views\public\unsubscribe', ['state' => 'confirm', 'email' => $contact['email'], 'token' => $token, 'company' => $company]);
    }

    /**
     * Alta pública a una lista.
     *
     * Se entra por el token de la lista, no por su id: si no, se podrían
     * recorrer las listas de todas las empresas de la plataforma. Y el alta
     * queda **pendiente** hasta que la persona pulsa el enlace del correo —el
     * doble opt-in—, porque sin eso cualquiera podría suscribir la dirección de
     * otro y el registro de consentimiento sería una prueba falsa.
     */
    public function subscribe($token = null)
    {
        $this->locale();
        $svc  = new SignupService();
        $list = $svc->listByToken((string) $token);
        if (!$list) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $company = (new \App\Models\CompanyModel())->find((int) $list['company_id']) ?? [];

        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return view('Components\Mailer\Views\public\subscribe', [
                'state'   => 'form',
                'list'    => $list,
                'company' => $company,
                'stamp'   => $svc->stamp(),
                'error'   => '',
            ]);
        }

        $pinta = fn (string $error) => view('Components\Mailer\Views\public\subscribe', [
            'state'   => 'form',
            'list'    => $list,
            'company' => $company,
            'stamp'   => $svc->stamp(),
            'error'   => $error,
        ]);

        // Trampa: un campo invisible que solo rellena un robot. Se responde con
        // la pantalla de «revisa tu correo» y sin guardar nada: decirle que se
        // le ha pillado solo le enseña a esquivarlo la próxima vez.
        if (trim((string) $this->request->getPost('website')) !== '') {
            return view('Components\Mailer\Views\public\subscribe', ['state' => 'sent', 'list' => $list, 'company' => $company]);
        }
        if (!$svc->stampOk((string) $this->request->getPost('t'))) {
            return $pinta(lang('Mailer.signupTooFast'));
        }
        if (!$this->request->getPost('consent')) {
            return $pinta(lang('Mailer.signupNeedConsent'));
        }

        // Freno por IP: un formulario público sin esto es una forma cómoda de
        // mandarle correos de confirmación a media agenda de alguien.
        $throttler = service('throttler');
        if ($throttler->check('mailer_signup_' . md5($this->request->getIPAddress()), 5, MINUTE) === false) {
            return $pinta(lang('Mailer.signupTooMany'));
        }

        $res = $svc->subscribe(
            $list,
            (string) $this->request->getPost('email'),
            trim((string) $this->request->getPost('name')),
            trim((string) $this->request->getPost('entity')),
            $this->request->getIPAddress()
        );
        if (!$res['ok']) {
            return $pinta($res['error']);
        }

        return view('Components\Mailer\Views\public\subscribe', ['state' => 'sent', 'list' => $list, 'company' => $company]);
    }

    /** Confirmación del alta: es la prueba fuerte del consentimiento. */
    public function confirmSignup($token = null)
    {
        $this->locale();
        $res = (new SignupService())->confirm((string) $token);

        return view('Components\Mailer\Views\public\subscribe', [
            'state'   => $res['ok'] ? 'done' : 'invalid',
            'list'    => [],
            'company' => $res['company'],
            'email'   => $res['email'],
        ]);
    }

    /**
     * El correo, en el navegador.
     *
     * Se entra por el `track_token` del destinatario y no por el id de la
     * campaña: así el enlace **no se puede recorrer** —es aleatorio de 16
     * bytes— y cada quien ve exactamente lo que le llegó, con su nombre y su
     * entidad, no una versión de muestra.
     *
     * Abrir esto **cuenta como apertura**, y es una medida mejor que el píxel:
     * el píxel lo bloquea medio mundo, y aquí hay una persona mirando el correo
     * con seguridad. Se registra igual que el píxel, sin duplicar si ya constaba.
     */
    public function view($token = null)
    {
        $this->locale();
        $token = (string) $token;
        $r     = $token !== '' ? (new RecipientModel())->where('track_token', $token)->first() : null;
        if (!$r) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $campaign = (new \Components\Mailer\Models\CampaignModel())->find((int) $r['campaign_id']);
        if (!$campaign) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $svc = new \Components\Mailer\Libraries\MailerService();

        // El MISMO cuerpo que se envió —personalizado y con el pie legal— pero
        // sin el píxel y sin reescribir los enlaces: aquí no hay nada que
        // rastrear que no sepamos ya, y un redirector de más solo estorba.
        $body = $svc->webBody($campaign, $r);

        $this->countOpen($r);

        return view('Components\Mailer\Views\public\campaign', [
            'subject' => $svc->personalize((string) $campaign['subject'], $r),
            'body'    => $body,
            'company' => (new \App\Models\CompanyModel())->find((int) $campaign['company_id']) ?? [],
        ]);
    }

    /** Registra la apertura del destinatario, sin contarla dos veces. */
    private function countOpen(array $r): void
    {
        $upd = ['open_count' => ((int) $r['open_count']) + 1];
        if (empty($r['opened_at'])) {
            $upd['opened_at'] = date('Y-m-d H:i:s');
        }
        (new RecipientModel())->update($r['id'], $upd);
    }
    /**
     * Baja en un clic (List-Unsubscribe-Post, RFC 8058). Va bajo api/v1/* para
     * quedar exento de CSRF (los proveedores hacen POST sin token). POST → da de
     * baja y responde 200; GET (clic del navegador) → lleva a la página de baja.
     */
    public function oneClick($token = null)
    {
        $token = (string) $token;
        if (strtoupper($this->request->getMethod()) === 'POST') {
            if ($token !== '') {
                $contact = (new ContactModel())->where('unsubscribe_token', $token)->first();
                if ($contact) {
                    (new SuppressionService())->suppress((int) $contact['company_id'], $contact['email'], 'unsubscribe', lang('Mailer.noteOneClick'));
                }
            }

            return $this->response->setStatusCode(200)->setBody('OK');
        }

        return redirect()->to(base_url('mailer-publico/baja/' . rawurlencode($token)));
    }

    /** Píxel de apertura: registra la apertura y devuelve un GIF 1x1 transparente. */
    public function open($token = null)
    {
        $token = (string) $token;
        if (str_ends_with($token, '.png')) {
            $token = substr($token, 0, -4);
        }
        if ($token !== '') {
            $r = (new RecipientModel())->where('track_token', $token)->first();
            if ($r) {
                $upd = ['open_count' => ((int) $r['open_count']) + 1];
                if (empty($r['opened_at'])) {
                    $upd['opened_at'] = date('Y-m-d H:i:s');
                }
                (new RecipientModel())->update($r['id'], $upd);
            }
        }
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return $this->response
            ->setHeader('Content-Type', 'image/gif')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->setHeader('Pragma', 'no-cache')
            ->setBody($gif);
    }

    /** Redirector de clics: registra el clic y redirige al destino original. */
    public function click($token = null)
    {
        $enc = (string) $this->request->getGet('u');
        $url = '';
        if ($enc !== '') {
            $dec = base64_decode(strtr($enc, '-_', '+/'), true);
            if ($dec !== false) {
                $url = $dec;
            }
        }
        // Solo destinos http(s) (evita esquemas peligrosos).
        if (!preg_match('#^https?://#i', $url)) {
            return redirect()->to(base_url());
        }

        $r = ($token !== null && $token !== '') ? (new RecipientModel())->where('track_token', (string) $token)->first() : null;
        if ($r) {
            $upd = ['click_count' => ((int) $r['click_count']) + 1];
            if (empty($r['clicked_at'])) {
                $upd['clicked_at'] = date('Y-m-d H:i:s');
            }
            if (empty($r['opened_at'])) { // un clic implica apertura
                $upd['opened_at']  = date('Y-m-d H:i:s');
                $upd['open_count'] = ((int) $r['open_count']) + 1;
            }
            (new RecipientModel())->update($r['id'], $upd);
            (new ClickModel())->insert([
                'company_id'   => $r['company_id'],
                'campaign_id'  => $r['campaign_id'],
                'recipient_id' => $r['id'],
                'url'          => mb_substr($url, 0, 500),
            ]);
        }

        return redirect()->to($url);
    }

    /**
     * Webhook de Amazon SNS (notificaciones de rebote/queja de SES).
     * Público y sin CSRF (va bajo api/v1/*). Confirma la suscripción SNS y
     * agrega a supresión los correos rebotados/quejados de la empresa emisora.
     */
    public function webhook()
    {
        $raw  = (string) $this->request->getBody();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'message' => 'Payload inválido.']);
        }

        // La firma, ANTES de mirar nada más. Esta ruta es pública y está exenta
        // de CSRF porque SNS no manda token; sin comprobar la firma, cualquiera
        // podría inventarse rebotes y vaciarle la lista de correo a una empresa.
        if (!(new SnsVerifier())->verify($data)) {
            log_message('warning', 'Mailer: mensaje SNS con firma inválida, descartado.');

            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Firma inválida.']);
        }

        $type = $data['Type'] ?? '';

        // 1) Confirmación de suscripción SNS: visita la SubscribeURL. El host ya
        //    quedó comprobado al verificar la firma, pero se vuelve a mirar aquí
        //    porque es OTRA url del mismo POST y es la que se va a descargar.
        if ($type === 'SubscriptionConfirmation' && !empty($data['SubscribeURL'])) {
            if (!SnsVerifier::isAmazonUrl((string) $data['SubscribeURL'])) {
                return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'message' => 'URL no permitida.']);
            }
            @file_get_contents(
                (string) $data['SubscribeURL'],
                false,
                stream_context_create(['http' => ['timeout' => 5]])
            );

            return $this->response->setJSON(['ok' => true, 'message' => 'Suscripción confirmada.']);
        }

        // 2) Notificación: el "Message" trae el evento de SES (bounce/complaint).
        if ($type === 'Notification') {
            $message = json_decode($data['Message'] ?? '[]', true) ?: [];
            $this->handleSesEvent($message);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Procesa un evento de SES. Distingue **rebote duro (Permanent)** de **blando
     * (Transient)**: solo los permanentes y las quejas suprimen el correo (para no
     * quemar contactos por problemas temporales como un buzón lleno). Los blandos
     * solo marcan el destinatario de esa campaña, sin suprimir ni tocar el contacto.
     */
    /**
     * Cuántas veces seguidas ha rebotado sin llegar a rebotar del todo.
     *
     * Al tercero se suprime: tres campañas seguidas sin entrar ya no es un
     * problema pasajero, y seguir intentándolo solo le cuesta reputación a la
     * cuenta. El contador se guarda en el contacto, no en el envío, porque lo
     * que interesa es la dirección a lo largo del tiempo.
     */
    public const SOFT_BOUNCE_LIMIT = 3;

    private function countSoftBounce(int $companyId, string $email): void
    {
        $cm = new ContactModel();
        foreach ($cm->where('company_id', $companyId)->where('email', $email)->findAll() as $c) {
            $veces = ((int) ($c['soft_bounces'] ?? 0)) + 1;
            $cm->update($c['id'], ['soft_bounces' => $veces]);

            if ($veces >= self::SOFT_BOUNCE_LIMIT) {
                (new SuppressionService())->suppress(
                    $companyId,
                    $email,
                    'bounce',
                    lang('Mailer.softBounceLimit', [$veces])
                );
            }
        }
    }

    private function handleSesEvent(array $message): void
    {
        $notifType = $message['notificationType'] ?? ($message['eventType'] ?? '');
        $emails    = [];
        $reason    = 'bounce';
        $soft      = false;

        if (stripos($notifType, 'bounce') !== false) {
            $reason = 'bounce';
            // 'Permanent' = rebote duro (suprime); 'Transient'/'Undetermined' = blando.
            $soft = strcasecmp((string) ($message['bounce']['bounceType'] ?? ''), 'Permanent') !== 0;
            foreach ($message['bounce']['bouncedRecipients'] ?? [] as $r) {
                if (!empty($r['emailAddress'])) {
                    $emails[] = $r['emailAddress'];
                }
            }
        } elseif (stripos($notifType, 'complaint') !== false) {
            $reason = 'complaint';
            foreach ($message['complaint']['complainedRecipients'] ?? [] as $r) {
                if (!empty($r['emailAddress'])) {
                    $emails[] = $r['emailAddress'];
                }
            }
        }

        $svc = new SuppressionService();
        foreach ($emails as $email) {
            $email  = strtolower(trim($email));
            $recips = (new RecipientModel())->select('company_id')->where('email', $email)->groupBy('company_id')->findAll();
            foreach ($recips as $rec) {
                $companyId = (int) $rec['company_id'];
                if ($soft) {
                    // Rebote blando: marca el envío, sin suprimir al contacto. Un
                    // buzón lleno un martes no es motivo para borrar a nadie.
                    (new RecipientModel())
                        ->where('email', $email)->where('company_id', $companyId)
                        ->whereIn('status', ['sent', 'pending'])
                        ->set(['status' => 'bounced', 'error' => lang('Mailer.softBounce')])
                        ->update();

                    // Pero se cuenta: sin contador, una dirección que rebota
                    // SIEMPRE se reintentaba en cada campaña, indefinidamente,
                    // sumando rebotes a la reputación de la cuenta. Al llegar al
                    // tope se suprime, que es lo que haría cualquiera a mano.
                    $this->countSoftBounce($companyId, $email);
                    continue;
                }
                // Rebote permanente o queja: suprime (cuenta para la reputación).
                $svc->suppress($companyId, $email, $reason, 'Reportado por Amazon SES/SNS');
                (new RecipientModel())
                    ->where('email', $email)->where('company_id', $companyId)
                    ->whereIn('status', ['sent', 'pending'])
                    ->set(['status' => $reason === 'complaint' ? 'complained' : 'bounced'])
                    ->update();
            }
        }
    }
}
