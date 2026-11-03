<?php

namespace Components\Mailer\Libraries;

use App\Models\AppSettingModel;
use Components\Mailer\Models\CampaignModel;
use Components\Mailer\Models\ContactModel;
use Components\Mailer\Models\RecipientModel;

/**
 * Motor de envío de campañas por Amazon SES. Reglas server-side:
 *  - Construye una cola de destinatarios (reanudable) desde los contactos
 *    suscritos de la lista, excluyendo los de la lista de supresión.
 *  - Personaliza asunto/cuerpo ({{nombre}}, {{email}}) y agrega pie con enlace
 *    de baja (List-Unsubscribe) en cada correo — requisito anti-spam.
 *  - Envía en lotes (reanudable), registra estado por destinatario y actualiza
 *    los contadores de la campaña. Vuelve a comprobar la supresión en cada envío.
 */
class MailerService
{
    private CampaignModel $campaigns;
    private RecipientModel $recipients;
    private SuppressionService $suppression;
    private EmailValidator $validator;
    private ?SesMailer $sesClient = null;
    private ?array $sesSettings = null;

    /** Tamaño de lote por invocación (evita timeouts en XAMPP). */
    public const BATCH = 50;

    /** Fallos seguidos de un mismo dominio antes de saltarse el resto del lote. */
    public const DOMAIN_FAIL_LIMIT = 5;

    public function __construct()
    {
        $this->campaigns   = new CampaignModel();
        $this->recipients  = new RecipientModel();
        $this->suppression = new SuppressionService();
        $this->validator   = new EmailValidator();
    }

    /**
     * Encola los destinatarios de una campaña a partir de su lista (una sola vez).
     * Marca la campaña como 'sending'. Devuelve cuántos quedaron encolados.
     */
    public function buildQueue(int $campaignId): int
    {
        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            return 0;
        }
        // Ya encolada: no re-encolar (reanudable).
        if ($this->recipients->where('campaign_id', $campaignId)->countAllResults() > 0) {
            return (int) $campaign['total'];
        }

        $companyId  = (int) $campaign['company_id'];
        $suppressed = $this->suppression->emailSet($companyId);

        $contacts = (new ContactModel())
            ->where('company_id', $companyId)
            ->where('list_id', $campaign['list_id'])
            ->where('status', ContactModel::SENDABLE)
            ->findAll();

        $now   = date('Y-m-d H:i:s');
        $batch = [];
        // El MX se comprobó al dar de alta y al importar, pero entre eso y este
        // envío pueden haber pasado meses: dominios que caducan, empresas que
        // cierran. Se vuelve a mirar aquí, **una vez por dominio** y no por
        // destinatario, que es lo que lo hace asumible: una lista de mil
        // contactos suele tener treinta dominios.
        $mxPorDominio = [];
        // Solo si SES está activo: sin credenciales la consulta fallaría una vez
        // por contacto y solo serviría para hacer lento el encolado.
        $consultarAws = $this->ses()->isEnabled();

        foreach ($contacts as $c) {
            $email   = strtolower(trim($c['email']));
            $dominio = $this->validator->domain($email);
            if (!array_key_exists($dominio, $mxPorDominio)) {
                $mxPorDominio[$dominio] = $this->validator->hasMx($email);
            }
            $sinMx = !$mxPorDominio[$dominio];

            // La lista de supresión de la CUENTA de SES, distinta de la nuestra:
            // recoge lo que ha rebotado a cualquier cliente de AWS. Se consulta
            // aquí y no en cada envío porque es una llamada de red por
            // dirección; ante un fallo devuelve null y se envía igual.
            $enAws = $consultarAws ? $this->ses()->inAccountSuppressionList($email) : null;

            $batch[] = [
                'company_id'  => $companyId,
                'campaign_id' => $campaignId,
                'contact_id'  => $c['id'],
                'email'       => $email,
                'name'        => $c['name'],
                'entity'      => $c['entity'] ?? null,
                'status'      => (isset($suppressed[$email]) || $sinMx || $enAws === true) ? 'skipped' : 'pending',
                'error'       => isset($suppressed[$email])
                    ? lang('Mailer.vSuppressed')
                    : ($sinMx ? lang('Mailer.noMxQueue') : ($enAws === true ? lang('Mailer.awsSuppressed') : null)),
                'track_token' => bin2hex(random_bytes(16)),
                'created_at'  => $now,
            ];
        }
        if ($batch) {
            $this->recipients->insertBatch($batch);
        }

        $total   = count($batch);
        $skipped = count(array_filter($batch, static fn ($r) => $r['status'] === 'skipped'));
        $this->campaigns->update($campaignId, [
            'status'     => 'sending',
            'total'      => $total,
            'started_at' => $now,
        ]);

        return $total - $skipped;
    }

    /**
     * Procesa un lote de destinatarios pendientes. Devuelve estadísticas.
     *
     * @return array{processed:int, sent:int, failed:int, remaining:int, done:bool}
     */
    public function processBatch(int $campaignId, int $batchSize = self::BATCH): array
    {
        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => 0, 'done' => true];
        }
        $companyId = (int) $campaign['company_id'];

        $pending = $this->recipients
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->orderBy('id', 'asc')
            ->findAll($batchSize);

        // Throttle: respeta la tasa máxima de envío de SES (evita errores de rate).
        $delay = 0;
        if ($this->ses()->isEnabled()) {
            $rate  = $this->ses()->maxSendRate();
            $delay = $rate > 0 ? (int) ceil(1_000_000 / $rate) : 0;

            // Y la cuota DIARIA de la cuenta, que es distinta del cupo del plan:
            // aquella es la regla de AWS y esta la nuestra. Pasarse de la suya
            // devuelve errores de throttling que cuentan como fallos y ensucian
            // la reputación, así que el lote se recorta a lo que quede.
            $left = $this->ses()->dailyLeft();
            if ($left !== null && $left < count($pending)) {
                $pending = array_slice($pending, 0, max(0, $left));
            }
        }

        $sent = 0;
        $failed = 0;
        // Fallos por dominio dentro del lote. Si el servidor de un dominio está
        // caído o nos ha bloqueado, seguir intentándolo con sus otras cincuenta
        // direcciones solo acumula rebotes que ya se sabían.
        $fallosPorDominio = [];

        foreach ($pending as $r) {
            @set_time_limit(30); // un lote con throttle puede tardar; evita que PHP lo mate
            // Guardia final de formato: si el correo no es válido, NO se envía
            // (se marca fallido) para no generar un rebote que cuente contra la cuenta.
            if (!$this->validator->isValidSyntax($r['email'])) {
                $this->recipients->update($r['id'], ['status' => 'failed', 'error' => lang('Mailer.vBadFormat')]);
                $failed++;
                continue;
            }
            // Re-chequeo de supresión (pudo agregarse tras encolar).
            if ($this->suppression->isSuppressed($companyId, $r['email'])) {
                $this->recipients->update($r['id'], ['status' => 'skipped', 'error' => lang('Mailer.vSuppressed')]);
                continue;
            }
            // Asegura un token de tracking (campañas encoladas antes de esta versión no lo tienen).
            if (empty($r['track_token'])) {
                $r['track_token'] = bin2hex(random_bytes(16));
                $this->recipients->update($r['id'], ['track_token' => $r['track_token']]);
            }
            // ¿Este dominio ya ha fallado demasiado en este lote?
            $dominio = $this->validator->domain($r['email']);
            if (($fallosPorDominio[$dominio] ?? 0) >= self::DOMAIN_FAIL_LIMIT) {
                $this->recipients->update($r['id'], ['status' => 'failed', 'error' => lang('Mailer.domainCut')]);
                $failed++;
                continue;
            }

            $token = $this->tokenFor($companyId, $r);
            $html  = $this->renderBody($campaign, $r, $token);
            $subj  = $this->personalize($campaign['subject'], $r);

            if ($delay > 0) {
                usleep($delay);
            }
            $res = $this->dispatch(
                $r['email'],
                $subj,
                $html,
                $campaign['from_email'] ?: null,
                $campaign['from_name'] ?: null,
                $this->unsubHeaders($campaign, $token) // cabeceras List-Unsubscribe (one-click)
            );

            if ($res['ok']) {
                $this->recipients->update($r['id'], ['status' => 'sent', 'error' => null, 'sent_at' => date('Y-m-d H:i:s')]);
                $sent++;
            } else {
                $this->recipients->update($r['id'], ['status' => 'failed', 'error' => mb_substr($res['message'], 0, 255)]);
                $fallosPorDominio[$dominio] = ($fallosPorDominio[$dominio] ?? 0) + 1;
                $failed++;
            }
        }

        // Recalcula contadores y estado.
        $counts = $this->recount($campaignId);
        $remaining = $counts['pending'];
        $done      = $remaining === 0;
        $update    = [
            'sent_count'   => $counts['sent'],
            'failed_count' => $counts['failed'],
        ];
        if ($done) {
            $update['status']      = $counts['sent'] > 0 || $counts['failed'] === 0 ? 'sent' : 'failed';
            $update['finished_at'] = date('Y-m-d H:i:s');
        }
        $this->campaigns->update($campaignId, $update);

        return ['processed' => count($pending), 'sent' => $sent, 'failed' => $failed, 'remaining' => $remaining, 'done' => $done];
    }

    /** Conteo de destinatarios por estado. */
    public function recount(int $campaignId): array
    {
        $out = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'bounced' => 0, 'complained' => 0];
        $rows = $this->recipients
            ->select('status, COUNT(*) as c')
            ->where('campaign_id', $campaignId)
            ->groupBy('status')
            ->findAll();
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['c'];
        }

        return $out;
    }

    /** Personaliza texto con los datos del destinatario. */
    public function personalize(string $text, array $recipient): string
    {
        $name   = $recipient['name'] ?: 'Estimado(a)';
        $entity = $recipient['entity'] ?? '';

        return strtr($text, [
            '{{nombre}}'  => $name,
            '{{name}}'    => $name,
            '{{email}}'   => $recipient['email'],
            '{{entidad}}' => $entity,
            '{{entity}}'  => $entity,
        ]);
    }

    /** Cuerpo final: personalizado + pie con enlace de baja (anti-spam). */
    public function renderBody(array $campaign, array $recipient, string $token): string
    {
        $body  = $this->personalize((string) $campaign['body_html'], $recipient);
        $track = (string) ($recipient['track_token'] ?? '');
        // Reescribe los enlaces del cuerpo para rastrear clics (solo envíos reales con token).
        if ($track !== '') {
            $body = $this->webViewLink($track) . $this->rewriteLinks($body, $track);
        }
        // El pie va DESPUÉS de reescribir los enlaces: el de baja no se rastrea.
        $out = $body . $this->footerFor($campaign, $token);

        // Píxel de apertura al final (solo envíos reales).
        if ($track !== '') {
            $out .= $this->trackingPixel($track);
        }

        return $out;
    }

    /**
     * El pie obligatorio: por qué recibes esto, cómo darte de baja, y quién
     * envía con su dirección postal (CAN-SPAM). Lo comparten el correo que sale
     * y la vista web; duplicarlo acabaría con los dos diciendo cosas distintas.
     */
    private function footerFor(array $campaign, string $token): string
    {
        // URL controlada por el sistema (token hex) → sin escapar para no romper el href.
        $unsubUrl = base_url('mailer-publico/baja/' . rawurlencode($token));

        return '<hr style="margin-top:24px;border:none;border-top:1px solid #e5e7eb">'
            . '<p style="font-size:12px;color:#6b7280;margin-top:8px">'
            . esc(lang('Mailer.mailWhy')) . ' '
            . '<a href="' . $unsubUrl . '" style="color:#6b7280">' . esc(lang('Mailer.mailUnsub')) . '</a>.'
            . '</p>'
            . $this->canSpamFooter((int) $campaign['company_id']);
    }
    /**
     * La franja de «verlo en el navegador», antes del cuerpo.
     *
     * Va la primera porque es donde la busca quien no ve bien el correo, y en
     * texto pequeño y gris para que no compita con el contenido. Se pinta solo
     * en envíos reales: sin `track_token` no hay página que abrir.
     */
    private function webViewLink(string $track): string
    {
        $url = base_url('mailer-publico/ver/' . rawurlencode($track));

        return '<p style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#9ca3af;'
            . 'text-align:center;margin:0 0 12px 0">'
            . '<a href="' . $url . '" style="color:#9ca3af">' . esc(lang('Mailer.webView')) . '</a>'
            . '</p>';
    }

    /**
     * El cuerpo tal como se ve en el navegador: el mismo que se envió —con su
     * personalización y su pie legal— pero **sin el píxel** (la visita ya se
     * cuenta como apertura al servir la página) y **sin reescribir los
     * enlaces**, que aquí no aportan nada y solo añaden un salto de más.
     * Tampoco repite la franja de «verlo en el navegador»: ya estás en ella.
     */
    public function webBody(array $campaign, array $recipient): string
    {
        $token = $this->tokenFor((int) $campaign['company_id'], $recipient);

        return $this->personalize((string) $campaign['body_html'], $recipient)
            . $this->footerFor($campaign, $token);
    }
    /**
     * Pie CAN-SPAM: identifica al remitente (empresa) e incluye su **dirección
     * postal física**, requisito legal en todo correo comercial. Los datos salen
     * de la empresa (`companies`); se editan en «Mi empresa».
     */
    private function canSpamFooter(int $companyId): string
    {
        static $cache = [];
        if (!array_key_exists($companyId, $cache)) {
            $cache[$companyId] = (new \App\Models\CompanyModel())->find($companyId);
        }
        $company = $cache[$companyId];
        if (!$company) {
            return '';
        }

        $name = trim((string) ($company['name'] ?? ''));
        $nit  = trim((string) ($company['nit'] ?? ''));
        $addr = trim((string) ($company['address'] ?? ''));
        if ($name === '' && $addr === '') {
            return '';
        }

        $line1 = esc(lang('Mailer.mailSentBy')) . ' <strong>' . esc($name ?: lang('Mailer.mailOurCompany')) . '</strong>' . ($nit !== '' ? ' · ' . esc(lang('Mailer.mailNit')) . ' ' . esc($nit) : '');
        $line2 = $addr !== '' ? '<br>' . esc($addr) : '';

        return '<p style="font-size:11px;color:#9ca3af;margin-top:6px">' . $line1 . $line2 . '</p>';
    }

    /** Reescribe los href http(s) del cuerpo para pasar por el redirector de clics. */
    private function rewriteLinks(string $html, string $token): string
    {
        $base = base_url('mailer-publico/clic/' . $token);

        return preg_replace_callback('/href=(["\'])(https?:\/\/[^"\']+)\1/i', static function ($mch) use ($base) {
            $enc = rtrim(strtr(base64_encode($mch[2]), '+/', '-_'), '='); // base64url del destino
            return 'href="' . $base . '?u=' . $enc . '"';
        }, $html) ?? $html;
    }

    /** Píxel transparente 1x1 para registrar la apertura. */
    private function trackingPixel(string $token): string
    {
        return '<img src="' . base_url('mailer-publico/abrir/' . $token . '.png') . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;max-height:1px" />';
    }

    /**
     * Métricas de interacción de una campaña.
     *
     * @return array{sent:int, opens_unique:int, opens_total:int, clicks_unique:int, clicks_total:int, open_rate:float, click_rate:float, ctr:float}
     */
    public function engagement(int $campaignId): array
    {
        $base = static fn () => (new RecipientModel())->where('campaign_id', $campaignId);

        $sent        = $base()->where('status', 'sent')->countAllResults();
        $opensUnique = $base()->where('opened_at IS NOT NULL')->countAllResults();
        $clicksUnique = $base()->where('clicked_at IS NOT NULL')->countAllResults();
        $sums        = $base()->select('COALESCE(SUM(open_count),0) AS oc, COALESCE(SUM(click_count),0) AS cc')->get()->getRowArray();

        $rate = static fn ($n, $d) => $d > 0 ? round($n / $d * 100, 1) : 0.0;

        return [
            'sent'          => $sent,
            'opens_unique'  => $opensUnique,
            'opens_total'   => (int) ($sums['oc'] ?? 0),
            'clicks_unique' => $clicksUnique,
            'clicks_total'  => (int) ($sums['cc'] ?? 0),
            'open_rate'     => $rate($opensUnique, $sent),
            'click_rate'    => $rate($clicksUnique, $sent),
            'ctr'           => $rate($clicksUnique, $opensUnique),
        ];
    }

    /** Enlaces más clicados de la campaña. */
    public function topLinks(int $campaignId, int $limit = 5): array
    {
        return (new \Components\Mailer\Models\ClickModel())
            ->select('url, COUNT(*) AS clicks')
            ->where('campaign_id', $campaignId)
            ->groupBy('url')
            ->orderBy('clicks', 'DESC')
            ->findAll($limit);
    }

    /** Destinatario de muestra para vista previa / prueba (personaliza las variables). */
    public function sampleRecipient(string $email = 'destinatario@ejemplo.com'): array
    {
        return ['email' => $email, 'name' => lang('Mailer.sampleName'), 'entity' => lang('Mailer.sampleEntity'), 'contact_id' => null];
    }

    /** HTML del correo para vista previa (cuerpo personalizado + pie de baja), con datos de muestra. */
    public function previewHtml(array $campaign, ?array $recipient = null): string
    {
        $recipient = $recipient ?: $this->sampleRecipient();

        return $this->renderBody($campaign, $recipient, 'preview');
    }

    /**
     * Envía un correo de PRUEBA a una dirección, sin tocar la cola ni los
     * contadores de la campaña. El asunto se antepone con [PRUEBA].
     *
     * @return array{ok:bool, message:string}
     */
    public function sendTest(array $campaign, string $to): array
    {
        $recipient = $this->sampleRecipient($to);
        $html      = $this->renderBody($campaign, $recipient, 'preview');
        $subject   = lang('Mailer.testPrefix') . ' ' . $this->personalize((string) $campaign['subject'], $recipient);

        return $this->dispatch($to, $subject, $html, $campaign['from_email'] ?: null, $campaign['from_name'] ?: null);
    }

    /** Obtiene (o genera) el token de baja del contacto. */
    private function tokenFor(int $companyId, array $recipient): string
    {
        if (empty($recipient['contact_id'])) {
            return '';
        }
        $contactModel = new ContactModel();
        $contact      = $contactModel->find($recipient['contact_id']);
        if (!$contact) {
            return '';
        }
        if (!empty($contact['unsubscribe_token'])) {
            return $contact['unsubscribe_token'];
        }
        $token = bin2hex(random_bytes(16));
        $contactModel->update($contact['id'], ['unsubscribe_token' => $token]);

        return $token;
    }

    /**
     * Envía un correo por SES. Aislado para poder simularlo en pruebas.
     *
     * @return array{ok:bool, message:string}
     */
    protected function dispatch(string $to, string $subject, string $html, ?string $fromEmail, ?string $fromName, array $headers = []): array
    {
        $ses = $this->ses();
        if (!$ses->isEnabled()) {
            return ['ok' => false, 'message' => lang('Mailer.sesOff')];
        }

        return $ses->send($to, $subject, $html, $fromEmail, $fromName, $headers);
    }

    /** Cliente SES + settings, cargados una sola vez por instancia. */
    private function ses(): SesMailer
    {
        if ($this->sesClient === null) {
            $this->sesSettings = (new AppSettingModel())->getAllSettings();
            $this->sesClient   = new SesMailer($this->sesSettings);
        }

        return $this->sesClient;
    }

    /** Cabeceras List-Unsubscribe (+ one-click, RFC 8058) para el destinatario. */
    private function unsubHeaders(array $campaign, string $unsubToken): array
    {
        if ($unsubToken === '') {
            return [];
        }
        $this->ses(); // asegura settings cargados
        $oneClick = base_url('api/v1/mailer/unsub/' . rawurlencode($unsubToken));
        $fromMail = $campaign['from_email'] ?: (string) ($this->sesSettings['ses_from_email'] ?? '');
        $list     = '<' . $oneClick . '>';
        if ($fromMail !== '') {
            $list = '<mailto:' . $fromMail . '?subject=unsubscribe>, ' . $list;
        }

        return [
            'List-Unsubscribe'      => $list,
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    }
}
