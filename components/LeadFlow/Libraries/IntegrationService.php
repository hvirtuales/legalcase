<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Libraries\Integrations\DocketwiseHttpAdapter;
use Components\LeadFlow\Libraries\Integrations\DocketwiseSimulatedAdapter;
use Components\LeadFlow\Libraries\Integrations\IntegrationAdapter;
use Components\LeadFlow\Libraries\Integrations\IntegrationException;
use Components\LeadFlow\Models\ExtContactModel;
use Components\LeadFlow\Models\IntegrationLogModel;
use Components\LeadFlow\Models\IntegrationModel;
use Components\LeadFlow\Models\ProviderModel;

/**
 * La conexión de cada firma con Docketwise: OAuth, tokens, el adaptador que toca
 * y la caché de contactos.
 *
 * El MODO (`simulated` | `live`) lo decide la plataforma en `cmp_lfl_providers` y
 * se congela en la conexión de la firma al conectar: cambiar el modo de la
 * plataforma no convierte en «real» una conexión que se hizo contra el simulado.
 */
class IntegrationService
{
    public const PROVIDER = 'docketwise';

    private IntegrationModel $integrations;
    private IntegrationLogModel $logs;

    /**
     * Modo impuesto para las PRUEBAS, que no toca la configuración de la plataforma.
     *
     * El autotest necesita el adaptador simulado, pero el modo vive en
     * `cmp_lfl_providers`, que es una fila global: si la prueba la cambiara, tocaría
     * la configuración real de la instalación —y si algo fallara a mitad, la
     * dejaría cambiada—. Con esto el escenario de prueba se aísla sin escribir en
     * esa tabla, igual que `AutomationEngine::disable()` apaga el motor mientras se
     * siembran los datos demo.
     */
    private static ?string $forcedMode = null;

    /** Solo para pruebas: impone el modo del proveedor. `null` lo devuelve a la configuración. */
    public static function forceMode(?string $mode): void
    {
        self::$forcedMode = in_array($mode, ProviderModel::MODES, true) ? $mode : null;
    }

    public function __construct()
    {
        $this->integrations = new IntegrationModel();
        $this->logs         = new IntegrationLogModel();
    }

    public function integration(int $companyId): array
    {
        return $this->integrations->of($companyId, self::PROVIDER);
    }

    public function isConnected(int $companyId): bool
    {
        return $this->integrations->isConnected($companyId, self::PROVIDER);
    }

    private function build(string $mode, int $companyId, ?string $token = null): IntegrationAdapter
    {
        if ($mode === 'live') {
            $cfg = (new ProviderModel())->config(self::PROVIDER);
            if (empty($cfg['client_id']) || empty($cfg['secret'])) {
                throw new IntegrationException(lang('Lfl.errProviderNotConfigured'));
            }

            return new DocketwiseHttpAdapter($cfg, $token);
        }

        return new DocketwiseSimulatedAdapter($companyId);
    }

    /** El adaptador de una firma CONECTADA, con el token renovado si está por vencer. */
    public function adapter(int $companyId): IntegrationAdapter
    {
        $row = $this->integration($companyId);
        if ($row['status'] !== 'connected') {
            throw new IntegrationException(lang('Lfl.errNotConnected'), 401);
        }

        $this->refreshIfNeeded($row);
        $row = $this->integration($companyId);

        return $this->build((string) $row['mode'], $companyId, IntegrationModel::accessToken($row));
    }

    /** Renueva el token si le quedan menos de 7 días (el de Docketwise dura 180). */
    public function refreshIfNeeded(array $row): void
    {
        if ($row['status'] !== 'connected' || empty($row['expires_at']) || strtotime($row['expires_at']) > time() + 7 * 86400) {
            return;
        }
        $refresh = IntegrationModel::refreshToken($row);
        if (! $refresh) {
            return;
        }

        try {
            $tokens = $this->build((string) $row['mode'], (int) $row['company_id'])->refresh($refresh);
            $this->integrations->storeTokens((int) $row['id'], $tokens);
            $this->logs->log((int) $row['company_id'], self::PROVIDER, 'refresh_token', true);
        } catch (\Throwable $e) {
            $this->integrations->update((int) $row['id'], ['last_error' => mb_substr($e->getMessage(), 0, 255)]);
            $this->logs->log((int) $row['company_id'], self::PROVIDER, 'refresh_token', false, $e->getMessage());
        }
    }

    /**
     * Arranca el OAuth. Devuelve la URL a la que mandar al usuario.
     *
     * El `state` es aleatorio y se guarda en la conexión: el callback solo acepta
     * el que emitimos (protección CSRF del flujo OAuth).
     */
    public function startConnect(int $companyId): array
    {
        $cfg   = (new ProviderModel())->config(self::PROVIDER);
        $mode  = self::$forcedMode ?? $cfg['mode'];
        $state = bin2hex(random_bytes(16));

        try {
            $url = $this->build($mode, $companyId)->authorizeUrl($state);
        } catch (IntegrationException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'url' => null];
        }

        $row = $this->integration($companyId);
        $this->integrations->update((int) $row['id'], ['mode' => $mode, 'status' => 'pending', 'oauth_state' => $state, 'last_error' => null]);

        return ['ok' => true, 'message' => '', 'url' => $url];
    }

    public function finishConnect(int $companyId, string $code, string $state, ?int $userId): array
    {
        $row = $this->integration($companyId);

        if (empty($row['oauth_state']) || ! hash_equals((string) $row['oauth_state'], $state)) {
            return ['ok' => false, 'message' => lang('Lfl.errOauthState')];
        }

        try {
            $tokens = $this->build((string) $row['mode'], $companyId)->exchangeCode($code);
        } catch (\Throwable $e) {
            $this->integrations->update((int) $row['id'], ['status' => 'error', 'oauth_state' => null, 'last_error' => mb_substr($e->getMessage(), 0, 255)]);
            $this->logs->log($companyId, self::PROVIDER, 'oauth_callback', false, $e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $this->integrations->storeTokens((int) $row['id'], $tokens);
        $this->integrations->update((int) $row['id'], [
            'status' => 'connected', 'oauth_state' => null, 'connected_at' => date('Y-m-d H:i:s'),
            'connected_by' => $userId, 'last_error' => null,
        ]);
        $this->logs->log($companyId, self::PROVIDER, 'oauth_callback', true, lang('Lfl.logConnected', [$row['mode']]));

        return ['ok' => true, 'message' => lang('Lfl.okConnected')];
    }

    public function disconnect(int $companyId): array
    {
        $row = $this->integration($companyId);
        $this->integrations->update((int) $row['id'], [
            'status' => 'disconnected', 'access_token' => null, 'refresh_token' => null, 'expires_at' => null,
            'oauth_state' => null, 'last_error' => null,
        ]);
        $this->logs->log($companyId, self::PROVIDER, 'disconnect', true);

        return ['ok' => true, 'message' => lang('Lfl.okDisconnected')];
    }

    /**
     * Descarga los contactos de la cuenta a la caché local.
     *
     * La API no busca por email ni teléfono, así que para detectar duplicados hay
     * que tenerlos aquí. Se pagina a 200 y se respeta el límite de 120 peticiones
     * por minuto con una pausa entre páginas (en el modo real). Tope de 50 páginas
     * (10 000 contactos) para no dejar una petición colgada.
     */
    public function syncContacts(int $companyId): array
    {
        try {
            $adapter = $this->adapter($companyId);
            $all     = [];
            $page    = 1;
            for ($i = 0; $i < 50 && $page !== null; $i++) {
                $res  = $adapter->listContacts($page);
                $all  = array_merge($all, $res['items']);
                $page = $res['next'];
                if ($page !== null && $adapter->mode() === 'live') {
                    usleep(600000);
                }
            }
        } catch (\Throwable $e) {
            $this->logs->log($companyId, self::PROVIDER, 'sync_contacts', false, $e->getMessage(), null,
                $e instanceof IntegrationException ? $e->httpCode() : null);

            return ['ok' => false, 'message' => $e->getMessage(), 'count' => 0];
        }

        $model = new ExtContactModel();
        $now   = date('Y-m-d H:i:s');
        $model->where('company_id', $companyId)->where('provider', self::PROVIDER)->delete();

        $rows = [];
        foreach ($all as $c) {
            $rows[] = [
                'company_id'  => $companyId,
                'provider'    => self::PROVIDER,
                'external_id' => mb_substr((string) $c['id'], 0, 64),
                'first_name'  => mb_substr((string) ($c['first_name'] ?? ''), 0, 120) ?: null,
                'last_name'   => mb_substr((string) ($c['last_name'] ?? ''), 0, 120) ?: null,
                'email_norm'  => Dedupe::email($c['email'] ?? null),
                'phone_norm'  => Dedupe::phone($c['phone'] ?? null),
                'synced_at'   => $now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            $model->insertBatch($chunk);
        }

        $row = $this->integration($companyId);
        $this->integrations->update((int) $row['id'], ['contacts_synced_at' => $now]);
        $this->logs->log($companyId, self::PROVIDER, 'sync_contacts', true, lang('Lfl.logSynced', [count($rows)]));

        return ['ok' => true, 'message' => lang('Lfl.okContactsSynced', [count($rows)]), 'count' => count($rows)];
    }

    /**
     * Contactos de Docketwise que podrían ser este lead (§23), desde la caché.
     *
     * @return array<int,array{contact:array,reasons:string[]}>
     */
    public function candidates(array $lead): array
    {
        $cid   = (int) $lead['company_id'];
        $email = $lead['email_norm'] ?? null;
        $keys  = array_values(array_unique(array_filter([Dedupe::key10($lead['phone_norm'] ?? null), Dedupe::key10($lead['whatsapp_norm'] ?? null)])));
        $first = mb_strtolower(trim((string) $lead['first_name']));
        $last  = mb_strtolower(trim((string) $lead['last_name']));

        $m = new ExtContactModel();
        $m->where('company_id', $cid)->where('provider', self::PROVIDER)->groupStart();
        $m->groupStart()->where('first_name', $lead['first_name'])->where('last_name', $lead['last_name'])->groupEnd();
        if ($email) {
            $m->orWhere('email_norm', $email);
        }
        foreach ($keys as $k) {
            $m->orLike('phone_norm', $k, 'before');
        }
        $m->groupEnd();

        $out = [];
        foreach ($m->findAll(20) as $c) {
            $reasons = [];
            if ($email && $c['email_norm'] === $email) {
                $reasons[] = 'email';
            }
            if ($keys && in_array(Dedupe::key10($c['phone_norm']), $keys, true)) {
                $reasons[] = 'phone';
            }
            if (mb_strtolower((string) $c['first_name']) === $first && mb_strtolower((string) $c['last_name']) === $last) {
                $reasons[] = 'name';
            }
            if ($reasons) {
                $out[] = ['contact' => $c, 'reasons' => $reasons];
            }
        }

        return $out;
    }

    /** Catálogos del proveedor para las pantallas; vacíos si no hay conexión o falla. */
    public function lists(int $companyId): array
    {
        if (! $this->isConnected($companyId)) {
            return ['users' => [], 'matter_types' => [], 'error' => null];
        }
        try {
            $a = $this->adapter($companyId);

            return ['users' => $a->listUsers(), 'matter_types' => $a->listMatterTypes(), 'error' => null];
        } catch (\Throwable $e) {
            return ['users' => [], 'matter_types' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Diagnóstico de la conexión, paso a paso: qué está bien y qué falta.
     *
     * Existe porque «no funciona» tiene varias causas muy distintas —la app OAuth
     * de la plataforma, la autorización de la firma, el token vencido o la API
     * contestando mal— y mirando una pantalla no se distinguen. Cada paso dice
     * cuál de ellas es.
     *
     * **Solo lee**: comprueba credenciales sin canjear nada y llama a los tres
     * recursos de consulta de la API. No crea, no modifica y no guarda contactos;
     * para eso está «Sincronizar ahora».
     *
     * @param bool $appOnly solo la app OAuth de la plataforma (no hay firma que mirar)
     *
     * @return array{ok:bool,ms:int,checks:array<int,array{label:string,status:string,detail:string}>}
     */
    public function diagnose(int $companyId, bool $appOnly = false): array
    {
        $t0     = microtime(true);
        $cfg          = (new ProviderModel())->config(self::PROVIDER);
        $cfg['mode']  = self::$forcedMode ?? $cfg['mode'];
        $live         = $cfg['mode'] === 'live';
        $checks       = [];

        $checks[] = ['label' => lang('Lfl.diagMode'), 'status' => $live ? 'ok' : 'warn',
            'detail' => lang($live ? 'Lfl.diagModeLive' : 'Lfl.diagModeSim')];

        $missing = [];
        if (empty($cfg['client_id'])) {
            $missing[] = 'Client ID';
        }
        if (empty($cfg['secret'])) {
            $missing[] = 'Client secret';
        }
        $checks[] = ['label' => lang('Lfl.diagCredentials'), 'status' => $missing ? 'error' : 'ok',
            'detail' => $missing ? lang('Lfl.diagMissing', [implode(', ', $missing)]) : lang('Lfl.diagCredentialsOk')];

        // Sin fijar, el URI sale de `base_url()`: cambia con el puerto o el dominio
        // desde el que se entre, y Docketwise lo compara carácter por carácter.
        $fixed = trim((string) ($cfg['redirect_uri'] ?? '')) !== '';
        $uri   = $fixed ? (string) $cfg['redirect_uri'] : base_url('leadflow/docketwise/callback');
        $https = str_starts_with(mb_strtolower($uri), 'https://');
        $local = (bool) preg_match('~^https?://(localhost|127\.0\.0\.1)([:/]|$)~i', $uri);

        if (! $fixed) {
            $checks[] = ['label' => lang('Lfl.diagRedirect'), 'status' => 'warn', 'detail' => lang('Lfl.diagRedirectAuto', [$uri])];
        } else {
            $checks[] = ['label' => lang('Lfl.diagRedirect'), 'status' => $https || $local ? 'ok' : 'warn',
                'detail' => $https || $local ? $uri : $uri . ' — ' . lang('Lfl.diagRedirectHttp')];
        }

        // La sonda de credenciales solo tiene sentido contra la API real.
        if (! $live) {
            $checks[] = ['label' => lang('Lfl.diagApp'), 'status' => 'skip', 'detail' => lang('Lfl.diagAppSkipped')];
        } elseif (! $missing) {
            $probe    = (new DocketwiseHttpAdapter($cfg))->checkApp();
            $checks[] = ['label' => lang('Lfl.diagApp'), 'status' => $probe['status'], 'detail' => $probe['detail']];
        }

        if (! $appOnly && $companyId > 0) {
            $checks = array_merge($checks, $this->companyChecks($companyId, $cfg));
        }

        $ok = ! in_array('error', array_column($checks, 'status'), true);
        $ms = (int) round((microtime(true) - $t0) * 1000);

        if ($companyId > 0) {
            $this->logs->log($companyId, self::PROVIDER, 'diagnose', $ok, lang('Lfl.logDiagnosed', [count($checks), $ms]));
        }

        return ['ok' => $ok, 'ms' => $ms, 'checks' => $checks];
    }

    /** Los pasos que dependen de la firma: su autorización, su token y la API respondiéndole. */
    private function companyChecks(int $companyId, array $cfg): array
    {
        $row    = $this->integration($companyId);
        $status = (string) $row['status'];
        $mode   = lang($row['mode'] === 'live' ? 'Lfl.modeLive' : 'Lfl.modeSimulated');
        $out    = [];

        $detail = match ($status) {
            'connected' => lang('Lfl.diagConnectedAs', [$mode]),
            'pending'   => lang('Lfl.diagPending'),
            'error'     => (string) ($row['last_error'] ?: lang('Lfl.diagConnError')),
            default     => lang('Lfl.diagDisconnected'),
        };
        $out[] = ['label' => lang('Lfl.diagConnection'),
            'status' => ['connected' => 'ok', 'error' => 'error'][$status] ?? 'warn', 'detail' => $detail];

        if ($status !== 'connected') {
            $out[] = ['label' => lang('Lfl.diagApiCall'), 'status' => 'skip', 'detail' => lang('Lfl.diagApiSkipped')];

            return $out;
        }

        // El modo se congela al conectar: una conexión simulada no se vuelve real
        // porque la plataforma cambie de modo, y conviene decirlo.
        if ($row['mode'] !== $cfg['mode']) {
            $out[] = ['label' => lang('Lfl.diagModeFrozen'), 'status' => 'warn',
                'detail' => lang('Lfl.diagModeMismatch', [$mode, lang($cfg['mode'] === 'live' ? 'Lfl.modeLive' : 'Lfl.modeSimulated')])];
        }

        $expires = $row['expires_at'] ? strtotime((string) $row['expires_at']) : null;
        if (! $expires) {
            $out[] = ['label' => lang('Lfl.diagToken'), 'status' => 'warn', 'detail' => lang('Lfl.diagTokenUnknown')];
        } else {
            $days  = (int) floor(($expires - time()) / 86400);
            $out[] = ['label' => lang('Lfl.diagToken'),
                'status' => $days < 0 ? 'error' : ($days <= 7 ? 'warn' : 'ok'),
                'detail' => $days < 0 ? lang('Lfl.diagTokenExpired') : lang('Lfl.diagTokenDays', [$days])];
        }

        try {
            $adapter = $this->adapter($companyId);
        } catch (\Throwable $e) {
            $out[] = ['label' => lang('Lfl.diagApiCall'), 'status' => 'error', 'detail' => mb_substr($e->getMessage(), 0, 200)];

            return $out;
        }

        foreach ([
            ['diagUsers', static fn () => count($adapter->listUsers())],
            ['diagMatterTypes', static fn () => count($adapter->listMatterTypes())],
            ['diagContacts', static fn () => count($adapter->listContacts(1)['items'])],
        ] as [$key, $call]) {
            try {
                $out[] = ['label' => lang('Lfl.' . $key), 'status' => 'ok', 'detail' => lang('Lfl.diagCount', [$call()])];
            } catch (\Throwable $e) {
                $out[] = ['label' => lang('Lfl.' . $key), 'status' => 'error', 'detail' => mb_substr($e->getMessage(), 0, 200)];
            }
        }

        return $out;
    }
}
