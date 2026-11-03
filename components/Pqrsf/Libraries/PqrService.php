<?php

namespace Components\Pqrsf\Libraries;

use App\Libraries\AiClient;
use App\Libraries\CompanyMailer;
use App\Libraries\CompanySms;
use App\Libraries\CompanyWhatsApp;
use App\Models\CompanyModel;
use Components\Pqrsf\Models\EventModel;
use Components\Pqrsf\Models\PointModel;
use Components\Pqrsf\Models\RequestModel;
use Components\Pqrsf\Models\SettingsModel;
use Components\Pqrsf\Models\TypeModel;

/**
 * Reglas del componente: radicación, hilo, IA y envíos.
 *
 * Dos cosas que gobiernan todo y conviene tener presentes:
 *
 * 1. **La IA no le escribe sola al ciudadano.** Redacta un resumen y un borrador;
 *    la respuesta de fondo solo sale cuando una persona la aprueba. Lo único
 *    automático es el acuse de recibo, que es una plantilla.
 * 2. **La autorización de comunicaciones manda sobre el envío.** Si la solicitud
 *    es anónima o el ciudadano no la marcó, no se le escribe por ningún canal:
 *    la respuesta queda publicada en su ticket.
 */
class PqrService
{
    private RequestModel $requests;
    private EventModel $events;
    private SettingsModel $settings;

    public function __construct()
    {
        $this->requests = new RequestModel();
        $this->events   = new EventModel();
        $this->settings = new SettingsModel();
    }

    // ------------------------------------------------------------ enlaces

    public function pointUrl(array $point): string
    {
        return site_url('pqrsf/p/' . $point['token']);
    }

    public function ticketUrl(array $request): string
    {
        return site_url('pqrsf/t/' . $request['ticket_token']);
    }

    /**
     * Punto del token: activo y de una empresa activa.
     *
     * @return array{0:?array,1:?array} [punto, empresa]
     */
    public function resolvePoint(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{16,64}$/i', trim($token))) {
            return [null, null];
        }

        $point = (new PointModel())->where('token', trim($token))->first();
        if (!$point || empty($point['is_active'])) {
            return [null, null];
        }

        $company = (new CompanyModel())->find((int) $point['company_id']);

        return $company && !empty($company['is_active']) ? [$point, $company] : [null, null];
    }

    /** Solicitud del ticket público. */
    public function resolveTicket(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{16,64}$/i', trim($token))) {
            return null;
        }

        return $this->requests->where('ticket_token', trim($token))->first();
    }

    // ------------------------------------------------------------ radicación

    /**
     * Reserva el siguiente radicado de la empresa (`PQR-000001`).
     *
     * Va dentro de una transacción: dos ciudadanos radicando a la vez no pueden
     * llevarse el mismo número.
     */
    public function nextNumber(int $companyId): string
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $cfg    = $this->settings->forCompany($companyId);
        $prefix = trim((string) $cfg['prefix']) ?: 'PQR';
        $next   = (int) $cfg['last_number'] + 1;

        $db->table('cmp_pqr_settings')->where('company_id', $companyId)->update(['last_number' => $next]);
        $db->transComplete();

        return $prefix . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Radica una solicitud desde el formulario público.
     *
     * @param array $data ['type','subject','message','anonymous','name','doc','email','phone','city',
     *                     'accept_terms','consent_data','consent_comms']
     *
     * @return array{ok:bool,message:string,request:?array}
     */
    public function create(array $point, array $company, array $data): array
    {
        $companyId = (int) $company['id'];
        $cfg       = $this->settings->forCompany($companyId);
        $guard     = new PlanGuard();

        if (!$guard->serviceEnabled($companyId)) {
            return ['ok' => false, 'message' => lang('Pqr.errPlanDisabled'), 'request' => null];
        }
        $left = $guard->requestsLeft($companyId);
        if ($left !== null && $left <= 0) {
            return ['ok' => false, 'message' => lang('Pqr.errPlanQuota'), 'request' => null];
        }

        $anonymous = !empty($data['anonymous']) && !empty($cfg['allow_anonymous']);

        // Términos: obligatorios siempre.
        if (empty($data['accept_terms'])) {
            return ['ok' => false, 'message' => lang('Pqr.errTerms'), 'request' => null];
        }
        // Identificado: el tratamiento de datos también.
        if (!$anonymous && empty($data['consent_data'])) {
            return ['ok' => false, 'message' => lang('Pqr.errConsentData'), 'request' => null];
        }
        if (!$anonymous && trim((string) ($data['name'] ?? '')) === '') {
            return ['ok' => false, 'message' => lang('Pqr.errName'), 'request' => null];
        }

        // El tipo tiene que existir y estar activo EN ESTA EMPRESA: los tipos son
        // suyos, no una lista fija. Si llega uno inventado, cae al primero activo.
        $types = new TypeModel();
        $type  = (string) ($data['type'] ?? '');
        if (!$types->isValid($companyId, $type)) {
            $active = $types->activeFor($companyId);
            if (!$active) {
                return ['ok' => false, 'message' => lang('Pqr.errNoTypes'), 'request' => null];
            }
            $type = $active[0]['code'];
        }
        $days = $types->daysFor($companyId, $type);

        $row = [
            'company_id'     => $companyId,
            'point_id'       => (int) $point['id'],
            'number'         => $this->nextNumber($companyId),
            'ticket_token'   => bin2hex(random_bytes(16)),
            'type'           => $type,
            'subject'        => mb_substr(trim((string) $data['subject']), 0, 200),
            'message'        => trim((string) $data['message']),
            'priority'       => 'media',
            'status'         => 'recibida',
            'due_at'         => date('Y-m-d H:i:s', strtotime('+' . $days . ' days')),
            // En anónima NO se guarda identificación, aunque venga en el POST.
            'is_anonymous'   => $anonymous ? 1 : 0,
            'citizen_name'   => $anonymous ? '' : mb_substr(trim((string) ($data['name'] ?? '')), 0, 150),
            'citizen_doc'    => $anonymous ? '' : mb_substr(trim((string) ($data['doc'] ?? '')), 0, 40),
            'citizen_email'  => $anonymous ? '' : mb_substr(trim((string) ($data['email'] ?? '')), 0, 190),
            'citizen_phone'  => $anonymous ? '' : mb_substr(trim((string) ($data['phone'] ?? '')), 0, 40),
            'citizen_city'   => $anonymous ? '' : mb_substr(trim((string) ($data['city'] ?? '')), 0, 100),
            // El sello es lo que hace probable el consentimiento.
            'accept_terms'   => 1,
            'consent_data'   => $anonymous ? 0 : 1,
            'consent_comms'  => !$anonymous && !empty($data['consent_comms']) ? 1 : 0,
            'consent_at'     => date('Y-m-d H:i:s'),
            'consent_ip'     => (string) service('request')->getIPAddress(),
            'policy_version' => (string) ($cfg['policy_version'] ?: 'v1'),
        ];

        if (!$this->requests->insert($row)) {
            return ['ok' => false, 'message' => implode(' ', $this->requests->errors()), 'request' => null];
        }

        $id      = (int) $this->requests->getInsertID();
        $request = $this->requests->find($id);

        $this->events->log($id, 'estado', lang('Pqr.evCreated', [$point['name']]), [
            'status' => 'recibida', 'point_id' => (int) $point['id'],
        ], false, null);

        (new PointModel())->update((int) $point['id'], ['visits' => (int) $point['visits'] + 1]);

        // La IA clasifica y deja el borrador; nunca envía.
        $request = $this->runAi($request, $cfg);

        // Acuse de recibo: plantilla, solo si se puede escribir.
        $this->sendAck($request, $company, $cfg);

        return ['ok' => true, 'message' => lang('Pqr.okCreated', [$request['number']]), 'request' => $request];
    }

    // ------------------------------------------------------------ IA

    /** ¿Se puede usar la IA en esta empresa? */
    public function aiAvailable(array $cfg): bool
    {
        return !empty($cfg['ai_enabled']) && (new AiClient())->isConfigured();
    }

    /**
     * Marcadores que se pueden usar en la plantilla de análisis. Se sustituyen
     * antes de llamar a la IA.
     */
    public const PLACEHOLDERS = ['asunto', 'descripcion', 'mensaje', 'tipo', 'empresa', 'punto', 'fecha'];

    /** La plantilla que se usa si la empresa no escribió la suya. */
    public function defaultInstructions(): string
    {
        return "Eres un asistente experto en clasificación de solicitudes.\n"
            . "Tu trabajo es analizar cada solicitud y responder únicamente en formato JSON.\n\n"
            . "Reglas:\n"
            . "- No inventes datos.\n"
            . "- Resume en máximo 40 palabras.\n"
            . "- Sugiere máximo tres acciones.\n"
            . "- Si falta información, indícalo.\n"
            . "- Nunca escribas texto fuera del JSON.\n\n"
            . "Prioridades: Baja, Media, Alta, Crítica\n\n"
            . "Devuelve:\n"
            . "{\n  \"categoria\": \"\",\n  \"prioridad\": \"\",\n  \"resumen\": \"\",\n  \"analisis\": \"\",\n  \"acciones\": []\n}\n\n"
            . "Solicitud:\nAsunto: {{asunto}}\nDescripción: {{descripcion}}";
    }

    /**
     * Análisis + borrador de respuesta. Guarda en `ai_summary`, `ai_category`,
     * `ai_priority`, `ai_analysis`, `ai_actions` y `ai_draft`; **nunca en
     * `answer`** ni en `priority`: la respuesta al ciudadano y la prioridad real
     * las decide una persona. Lo de la IA son sugerencias.
     */
    public function runAi(array $request, array $cfg, string $instructions = ''): array
    {
        if (!$this->aiAvailable($cfg)) {
            return $request;
        }

        try {
            $ai   = new AiClient();
            $tone = (string) ($cfg['ai_tone'] ?: 'formal');

            // 1. Análisis con la plantilla de la empresa (o la de por defecto).
            $this->analyze($ai, $request, $cfg);

            // 2. Borrador de respuesta: otra llamada, en prosa y con el tono.
            $draft = $ai->generate(
                "Eres quien atiende las PQRSF de una empresa. Redacta en español un BORRADOR de respuesta "
                . "de tono {$tone}, entre 80 y 150 palabras, sin inventar hechos ni prometer plazos que no "
                . "aparezcan, sin firmar y sin encabezados de correo. Si falta información, pídela con "
                . "amabilidad.\n"
                . ($instructions !== '' ? "Instrucciones adicionales: {$instructions}\n" : '')
                . 'Tipo: ' . (new TypeModel())->labelFor((int) $request['company_id'], (string) $request['type']) . "\n"
                . "Asunto: {$request['subject']}\nMensaje: {$request['message']}",
                0.6
            );

            $this->requests->update((int) $request['id'], ['ai_draft' => trim($draft)]);

            $this->events->log((int) $request['id'], 'nota', lang('Pqr.evAiDraft'), ['ai' => true], true, null);

            return $this->requests->find((int) $request['id']);
        } catch (\Throwable $e) {
            // Que falle la IA no puede tumbar una radicación.
            log_message('error', 'PQRSF · IA: ' . $e->getMessage());
            $this->events->log((int) $request['id'], 'nota', lang('Pqr.evAiFailed', [$e->getMessage()]), [], true, null);

            return $request;
        }
    }

    /**
     * Paso de análisis: manda a la IA las instrucciones de la empresa (rol,
     * contexto y reglas) con los datos de la solicitud sustituidos.
     *
     * La respuesta se acepta de dos formas, porque las instrucciones las escribe
     * cada quien: si viene **JSON** se reparte en sus campos; si viene texto
     * suelto se guarda entero como resumen. Nada de esto toca `priority`: lo que
     * la IA propone queda en `ai_priority`, y aplicarlo es decisión de una persona.
     */
    private function analyze(AiClient $ai, array $request, array $cfg): void
    {
        $template = trim((string) ($cfg['ai_instructions'] ?? '')) ?: $this->defaultInstructions();
        $prompt   = strtr($template, $this->placeholderValues($request));

        // Si la plantilla no menciona la solicitud, se añade al final: sin los
        // datos la IA no tendría nada que analizar.
        if (!$this->mentionsRequest($template)) {
            $prompt .= "\n\nSolicitud:\nAsunto: {$request['subject']}\nDescripción: {$request['message']}";
        }

        $raw  = $ai->generate($prompt, 0.2);
        $data = $this->decodeJson($raw);

        if ($data === null) {
            // Texto suelto: es el resumen, y punto.
            $this->requests->update((int) $request['id'], ['ai_summary' => mb_substr(trim($raw), 0, 1000)]);

            return;
        }

        $actions = $data['acciones'] ?? $data['actions'] ?? [];
        $actions = is_array($actions) ? array_values(array_filter(array_map('strval', $actions))) : [];

        $this->requests->update((int) $request['id'], [
            'ai_summary'  => mb_substr(trim((string) ($data['resumen'] ?? $data['summary'] ?? '')), 0, 1000),
            'ai_category' => mb_substr(trim((string) ($data['categoria'] ?? $data['category'] ?? '')), 0, 60),
            'ai_priority' => $this->normalizePriority((string) ($data['prioridad'] ?? $data['priority'] ?? '')),
            'ai_analysis' => trim((string) ($data['analisis'] ?? $data['análisis'] ?? $data['analysis'] ?? '')),
            'ai_actions'  => $actions ? json_encode($actions, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /** Valores de los marcadores `{{...}}` de la plantilla. */
    private function placeholderValues(array $request): array
    {
        $company = (new CompanyModel())->find((int) $request['company_id']);
        $point   = $request['point_id'] ? (new PointModel())->find((int) $request['point_id']) : null;

        $values = [
            'asunto'      => (string) $request['subject'],
            'descripcion' => (string) $request['message'],
            'mensaje'     => (string) $request['message'],
            'tipo'        => (new TypeModel())->labelFor((int) $request['company_id'], (string) $request['type']),
            'empresa'     => (string) ($company['name'] ?? ''),
            'punto'       => (string) ($point['name'] ?? ''),
            'fecha'       => date('Y-m-d', strtotime((string) ($request['created_at'] ?? 'now'))),
        ];

        $map = [];

        foreach ($values as $k => $v) {
            // Se admiten `{{asunto}}` y `{{ asunto }}`, que es como se suele escribir.
            $map['{{' . $k . '}}']   = $v;
            $map['{{ ' . $k . ' }}'] = $v;
        }

        return $map;
    }

    /** ¿La plantilla ya trae los datos de la solicitud? */
    private function mentionsRequest(string $template): bool
    {
        foreach (['asunto', 'descripcion', 'mensaje'] as $k) {
            if (str_contains($template, '{{' . $k) || str_contains($template, '{{ ' . $k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * JSON de la respuesta, tolerante: los modelos suelen envolverlo en ``` o
     * añadir una frase antes. Devuelve null si no hay JSON aprovechable.
     */
    private function decodeJson(string $raw): ?array
    {
        $text = trim($raw);

        // Quita el vallado ```json … ```
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/is', $text, $m)) {
            $text = $m[1];
        }
        // Se queda con el primer objeto {...}
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }

    /** «Crítica», «ALTA», «media »… → el código interno, o '' si no encaja. */
    private function normalizePriority(string $value): string
    {
        $v = strtr(mb_strtolower(trim($value)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return in_array($v, RequestModel::PRIORITIES, true) ? $v : '';
    }

    // ------------------------------------------------------------ gestión

    public function assign(array $request, ?int $userId, ?string $userName = null): void
    {
        $this->requests->update((int) $request['id'], [
            'assignee_id' => $userId ?: null,
            'status'      => $request['status'] === 'recibida' ? 'en_gestion' : $request['status'],
        ]);

        $this->events->log((int) $request['id'], 'asignacion',
            $userId ? lang('Pqr.evAssigned', [$userName ?: '#' . $userId]) : lang('Pqr.evUnassigned'),
            ['assignee_id' => $userId]);
    }

    public function changeStatus(array $request, string $status): bool
    {
        if (!in_array($status, RequestModel::STATUSES, true)) {
            return false;
        }

        $data = ['status' => $status];
        if ($status === 'cerrada') {
            $data['closed_at'] = date('Y-m-d H:i:s');
        }
        $this->requests->update((int) $request['id'], $data);

        // El cambio de estado sí lo ve el ciudadano en su ticket.
        $this->events->log((int) $request['id'], 'estado', lang('Pqr.evStatus', [lang('Pqr.status_' . $status)]), ['status' => $status], false);

        return true;
    }

    public function addNote(array $request, string $body, bool $private = true): void
    {
        $this->events->log((int) $request['id'], 'nota', trim($body), [], $private);
    }

    // ------------------------------------------------------------ envíos

    /**
     * Qué canales se pueden usar con esta solicitud y, si no, por qué.
     *
     * @return array<string,array{available:bool,to:string,reason:string}>
     */
    public function channelsFor(array $request): array
    {
        $blocked = '';
        if (!empty($request['is_anonymous'])) {
            $blocked = lang('Pqr.chBlockedAnonymous');
        } elseif (empty($request['consent_comms'])) {
            $blocked = lang('Pqr.chBlockedConsent');
        }

        return [
            'email' => [
                'available' => $blocked === '' && trim((string) $request['citizen_email']) !== '',
                'to'        => (string) $request['citizen_email'],
                'reason'    => $blocked ?: (trim((string) $request['citizen_email']) === '' ? lang('Pqr.chNoEmail') : ''),
            ],
            'whatsapp' => [
                'available' => $blocked === '' && trim((string) $request['citizen_phone']) !== '',
                'to'        => (string) $request['citizen_phone'],
                'reason'    => $blocked ?: (trim((string) $request['citizen_phone']) === '' ? lang('Pqr.chNoPhone') : ''),
            ],
            'sms' => [
                'available' => $blocked === '' && trim((string) $request['citizen_phone']) !== '',
                'to'        => (string) $request['citizen_phone'],
                'reason'    => $blocked ?: (trim((string) $request['citizen_phone']) === '' ? lang('Pqr.chNoPhone') : ''),
            ],
        ];
    }

    /**
     * Aprueba la respuesta y avisa por los canales elegidos.
     *
     * La respuesta se guarda **siempre** (queda publicada en el ticket); los
     * envíos que fallen o estén bloqueados se anotan en el hilo sin deshacer la
     * aprobación.
     *
     * @param list<string>                    $channels
     * @param array{url?:string,label?:string} $link enlace que acompaña a la respuesta
     *
     * @return array{ok:bool,message:string,sent:array}
     */
    public function approveAndSend(array $request, array $company, string $answer, array $channels, array $link = []): array
    {
        $answer = trim($answer);
        if ($answer === '') {
            return ['ok' => false, 'message' => lang('Pqr.errAnswerEmpty'), 'sent' => []];
        }

        // Solo http/https: un `javascript:` en un enlace que se envía por correo
        // es exactamente lo que no queremos publicar.
        $url = trim((string) ($link['url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'message' => lang('Pqr.errLinkScheme'), 'sent' => []];
        }

        $this->requests->update((int) $request['id'], [
            'answer'            => $answer,
            'answer_link'       => mb_substr($url, 0, 500),
            'answer_link_label' => mb_substr(trim((string) ($link['label'] ?? '')), 0, 150),
            'answered_at'       => date('Y-m-d H:i:s'),
            'answered_by'       => (int) session()->get('user_id') ?: null,
            'status'            => 'respondida',
        ]);

        $request = $this->requests->find((int) $request['id']);

        // La respuesta es lo que el ciudadano ve en su ticket.
        $this->events->log((int) $request['id'], 'respuesta', $answer, [], false);

        $available = $this->channelsFor($request);
        $sent      = [];

        foreach ($channels as $channel) {
            if (!isset($available[$channel]) || !$available[$channel]['available']) {
                $sent[$channel] = ['ok' => false, 'message' => $available[$channel]['reason'] ?? lang('Pqr.chUnknown')];

                continue;
            }
            $sent[$channel] = $this->send($channel, $request, $company, $answer, $available[$channel]['to']);
        }

        foreach ($sent as $channel => $res) {
            $this->events->log((int) $request['id'], 'envio',
                lang($res['ok'] ? 'Pqr.evSentOk' : 'Pqr.evSentFail', [lang('Pqr.ch_' . $channel), $res['message'] ?? '']),
                ['channel' => $channel, 'ok' => (bool) $res['ok'], 'via' => $res['via'] ?? null]);
        }

        return ['ok' => true, 'message' => lang('Pqr.okAnswered', [$request['number']]), 'sent' => $sent];
    }

    /**
     * Enlaces que acompañan a la respuesta: el que escribió el gestor y los
     * adjuntos que marcó como visibles para el ciudadano.
     *
     * @return list<array{label:string,url:string}>
     */
    public function answerLinks(array $request): array
    {
        $out = [];

        if (trim((string) ($request['answer_link'] ?? '')) !== '') {
            $out[] = [
                'label' => trim((string) $request['answer_link_label']) ?: lang('Pqr.answerLinkDefault'),
                'url'   => (string) $request['answer_link'],
            ];
        }

        foreach ((new AttachmentService())->publicFor('request', (int) $request['id']) as $a) {
            $out[] = [
                'label' => (string) $a['name'],
                'url'   => site_url('pqrsf/t/' . $request['ticket_token'] . '/adjunto/' . (int) $a['id']),
            ];
        }

        return $out;
    }

    /** Envío por un canal concreto, con los canales de la empresa. */
    private function send(string $channel, array $request, array $company, string $answer, string $to): array
    {
        $companyId = (int) $company['id'];
        $subject   = lang('Pqr.msgSubject', [$request['number']]);
        $ticket    = $this->ticketUrl($request);
        $links     = $this->answerLinks($request);

        if ($channel === 'email') {
            $html = '<p>' . esc(lang('Pqr.msgGreeting', [$request['citizen_name'] ?: ''])) . '</p>'
                . '<p>' . nl2br(esc($answer)) . '</p>';

            if ($links) {
                $html .= '<p style="font-size:13px"><strong>' . esc(lang('Pqr.msgAttachments')) . '</strong><br>';

                foreach ($links as $l) {
                    $html .= '<a href="' . esc($l['url']) . '">' . esc($l['label']) . '</a><br>';
                }
                $html .= '</p>';
            }

            $html .= '<p style="color:#666;font-size:13px">' . esc(lang('Pqr.msgTicket')) . '<br>'
                . '<a href="' . esc($ticket) . '">' . esc($ticket) . '</a></p>'
                . '<p style="color:#666;font-size:13px">' . esc($company['name']) . '</p>';

            return (new CompanyMailer())->send($companyId, $to, $subject, $html);
        }

        $text = lang('Pqr.msgSubject', [$request['number']]) . "\n\n" . $answer;

        if ($links) {
            $text .= "\n\n" . lang('Pqr.msgAttachments');

            foreach ($links as $l) {
                $text .= "\n" . $l['label'] . ': ' . $l['url'];
            }
        }

        $text .= "\n\n" . lang('Pqr.msgTicket') . ' ' . $ticket;

        if ($channel === 'whatsapp') {
            return (new CompanyWhatsApp())->sendText($companyId, $to, $text);
        }
        if ($channel === 'sms') {
            // El SMS va recortado: no tiene sentido pagar tres mensajes.
            return (new CompanySms())->send($companyId, $to, mb_substr(lang('Pqr.msgSmsShort', [$request['number']]) . ' ' . $ticket, 0, 300));
        }

        return ['ok' => false, 'message' => lang('Pqr.chUnknown')];
    }

    /**
     * Acuse de recibo automático: plantilla + resumen de la IA si lo hay.
     * Solo sale si el ciudadano se identificó y autorizó comunicaciones.
     */
    private function sendAck(array $request, array $company, array $cfg): void
    {
        if (!empty($request['is_anonymous']) || empty($request['consent_comms'])) {
            return;
        }

        $template = trim((string) ($cfg['ack_template'] ?? '')) ?: lang('Pqr.ackDefault');
        $body     = strtr($template, [
            '{numero}'  => $request['number'],
            '{tipo}'    => (new TypeModel())->labelFor((int) $company['id'], (string) $request['type']),
            '{empresa}' => $company['name'],
            '{fecha}'   => date('Y-m-d', strtotime((string) $request['due_at'])),
            '{resumen}' => (string) ($request['ai_summary'] ?? ''),
        ]);

        $ticket = $this->ticketUrl($request);
        $sent   = [];

        if (trim((string) $request['citizen_email']) !== '') {
            $html = '<p>' . nl2br(esc($body)) . '</p>'
                . '<p style="color:#666;font-size:13px">' . esc(lang('Pqr.msgTicket')) . '<br>'
                . '<a href="' . esc($ticket, 'attr') . '">' . esc($ticket) . '</a></p>';
            $sent['email'] = (new CompanyMailer())->send((int) $company['id'], $request['citizen_email'],
                lang('Pqr.ackSubject', [$request['number']]), $html);
        }
        if (trim((string) $request['citizen_phone']) !== '') {
            $sent['whatsapp'] = (new CompanyWhatsApp())->sendText((int) $company['id'], $request['citizen_phone'],
                $body . "\n\n" . lang('Pqr.msgTicket') . ' ' . $ticket);
        }

        foreach ($sent as $channel => $res) {
            $this->events->log((int) $request['id'], 'envio',
                lang($res['ok'] ? 'Pqr.evAckOk' : 'Pqr.evAckFail', [lang('Pqr.ch_' . $channel), $res['message'] ?? '']),
                ['channel' => $channel, 'ack' => true, 'ok' => (bool) $res['ok']], true, null);
        }
    }
}
