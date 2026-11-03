<?php

namespace Components\Servicios\Libraries;

use Components\Servicios\Models\QuoteItemModel;
use Components\Servicios\Models\QuoteModel;
use Components\Servicios\Models\ResponseModel;

/**
 * Enlace público de la cotización: el cliente la abre sin cuenta y responde
 * (aprobar / rechazar / pedir ajustes).
 *
 * Decisiones de seguridad:
 *  - El acceso va por un **token aleatorio de 32 bytes** (64 hex), no por el id:
 *    no se puede enumerar ni adivinar.
 *  - La contraseña es **opcional** y se guarda **hasheada** (`password_hash`),
 *    nunca en claro. Se compara con `password_verify`.
 *  - Se puede **desactivar** el enlace sin perder el token ni el histórico.
 *  - Aprobar/rechazar solo se admite una vez: una vez respondida, la cotización
 *    queda cerrada para el cliente (puede seguir pidiendo ajustes).
 */
class PublicLinkService
{
    private QuoteModel $quotes;
    private ResponseModel $responses;

    public function __construct()
    {
        $this->quotes    = new QuoteModel();
        $this->responses = new ResponseModel();
    }

    // ------------------------------------------------------------------ gestión

    /**
     * Activa (o regenera) el enlace público. La contraseña es opcional: si llega
     * vacía y `$keepPassword` es true, se conserva la que hubiera.
     *
     * @return array{ok:bool,message:string,token:string|null}
     */
    public function enable(int $companyId, int $quoteId, ?string $password = null, bool $keepPassword = true): array
    {
        $quote = $this->quotes->where('company_id', $companyId)->find($quoteId);
        if (!$quote) {
            return ['ok' => false, 'message' => lang('Quo.errNotFound'), 'token' => null];
        }

        $data = [
            'public_enabled' => 1,
            'public_token'   => $quote['public_token'] ?: bin2hex(random_bytes(32)),
        ];

        $password = trim((string) $password);
        if ($password !== '') {
            $data['public_password'] = password_hash($password, PASSWORD_DEFAULT);
        } elseif (!$keepPassword) {
            $data['public_password'] = null; // quitar la contraseña
        }

        $this->quotes->update($quoteId, $data);

        return ['ok' => true, 'message' => lang('Quo.okPublicEnabled'), 'token' => $data['public_token']];
    }

    /** Corta el acceso público (conserva token e histórico). */
    public function disable(int $companyId, int $quoteId): array
    {
        if (!$this->quotes->where('company_id', $companyId)->find($quoteId)) {
            return ['ok' => false, 'message' => lang('Quo.errNotFound')];
        }
        $this->quotes->update($quoteId, ['public_enabled' => 0]);

        return ['ok' => true, 'message' => lang('Quo.okPublicDisabled')];
    }

    public function url(?string $token): string
    {
        return $token ? base_url('servicios/p/' . $token) : '';
    }

    // ------------------------------------------------------------------ acceso

    /** Cotización viva por token (con sus líneas), o null si no existe o está cerrada. */
    public function findByToken(string $token): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null; // ni siquiera consultamos con un token mal formado
        }

        $quote = $this->quotes->where('public_token', $token)->where('public_enabled', 1)->first();
        if (!$quote) {
            return null;
        }
        $quote['items'] = (new QuoteItemModel())->forQuote((int) $quote['id']);

        return $quote;
    }

    public function needsPassword(array $quote): bool
    {
        return !empty($quote['public_password']);
    }

    /**
     * Comprueba la contraseña con freno anti fuerza bruta (5 intentos por minuto
     * y token, vía el throttler del framework).
     *
     * @return array{ok:bool,message:string}
     */
    public function checkPassword(array $quote, string $password): array
    {
        $throttler = service('throttler');
        if ($throttler->check('quo_pub_' . $quote['public_token'], 5, MINUTE) === false) {
            return ['ok' => false, 'message' => lang('Quo.errTooManyTries')];
        }

        if (!password_verify($password, (string) $quote['public_password'])) {
            return ['ok' => false, 'message' => lang('Quo.errWrongPassword')];
        }

        return ['ok' => true, 'message' => ''];
    }

    /** Marca la sesión del visitante como autorizada para esta cotización. */
    public function grantSession(string $token): void
    {
        $granted          = (array) (session('quo_public') ?? []);
        $granted[$token]  = time();
        session()->set('quo_public', $granted);
    }

    public function sessionGranted(string $token): bool
    {
        $granted = (array) (session('quo_public') ?? []);

        return isset($granted[$token]);
    }

    // ------------------------------------------------------------------ respuestas

    /**
     * Registra la respuesta del cliente. Aprobar y rechazar cambian el estado del
     * documento; pedir ajustes NO lo cambia (lo decide el vendedor), solo deja la
     * observación y marca la cotización como «con observaciones».
     *
     * @return array{ok:bool,message:string}
     */
    public function respond(array $quote, string $action, string $message, string $ip): array
    {
        if (!in_array($action, ['approved', 'rejected', 'changes'], true)) {
            return ['ok' => false, 'message' => lang('Quo.errBadStatus')];
        }

        // Pedir ajustes exige explicar qué ajustar.
        if ($action === 'changes' && mb_strlen(trim($message)) < 10) {
            return ['ok' => false, 'message' => lang('Quo.errNeedMessage')];
        }

        // Aprobar/rechazar es definitivo. Se consulta el HISTÓRICO, no la última
        // respuesta: si el cliente aprueba y después pide ajustes,
        // `client_response` pasa a «changes» y mirarla dejaría reabrir la decisión.
        if ($action !== 'changes' && $this->decision((int) $quote['id']) !== null) {
            return ['ok' => false, 'message' => lang('Quo.errAlreadyAnswered')];
        }

        $quoteId = (int) $quote['id'];

        $this->responses->insert([
            'quote_id'   => $quoteId,
            'action'     => $action,
            'message'    => mb_substr(trim($message), 0, 2000),
            'ip_address' => mb_substr($ip, 0, 45),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $update = [
            'client_response'     => $action,
            'client_responded_at' => date('Y-m-d H:i:s'),
        ];
        if ($action === 'approved') {
            $update['status'] = 'aprobada';
        } elseif ($action === 'rejected') {
            $update['status'] = 'rechazada';
        }
        $this->quotes->update($quoteId, $update);

        $messages = [
            'approved' => lang('Quo.okClientApproved'),
            'rejected' => lang('Quo.okClientRejected'),
            'changes'  => lang('Quo.okClientChanges'),
        ];

        return ['ok' => true, 'message' => $messages[$action]];
    }

    /**
     * Decisión firme del cliente sobre la cotización: 'approved', 'rejected' o
     * null si aún no ha decidido. Sale del histórico, así que pedir ajustes
     * después no la borra.
     */
    public function decision(int $quoteId): ?string
    {
        $row = $this->responses
            ->where('quote_id', $quoteId)
            ->whereIn('action', ['approved', 'rejected'])
            ->orderBy('id', 'asc')
            ->first();

        return $row['action'] ?? null;
    }

    /** Deja constancia de la primera apertura (una sola vez por token). */
    public function trackView(array $quote, string $ip): void
    {
        $already = $this->responses
            ->where('quote_id', (int) $quote['id'])
            ->where('action', 'viewed')
            ->countAllResults();

        if ($already === 0) {
            $this->responses->insert([
                'quote_id'   => (int) $quote['id'],
                'action'     => 'viewed',
                'message'    => '',
                'ip_address' => mb_substr($ip, 0, 45),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function history(int $quoteId): array
    {
        return $this->responses->forQuote($quoteId);
    }
}
