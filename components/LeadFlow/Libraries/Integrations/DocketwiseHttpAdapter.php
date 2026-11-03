<?php

namespace Components\LeadFlow\Libraries\Integrations;

/**
 * Docketwise REAL, según la documentación oficial v1
 * (https://docketwise.gitbook.io/docketwise-api-docs, revisada el 2026-09-11).
 *
 * **SIN PROBAR contra la API**: no hay sandbox y el acceso se solicita a
 * dev@docketwise.com. Está escrito contra lo documentado; al activar el modo
 * `live` hay que validar cada llamada con una cuenta real.
 *
 * Lo que dice la documentación y aquí se respeta:
 *  · OAuth 2.0: `/oauth/authorize` (response_type=code, scope «public write») y
 *    `/oauth/token` (authorization_code | refresh_token). El token dura 180 días.
 *  · Base `https://app.docketwise.com/api/v1/`, JSON, cada recurso bajo su clave
 *    raíz (`contact`, `matter`, `document`) — salvo las notas, cuyo ejemplo va sin
 *    clave raíz.
 *  · 200 registros por página, cabecera `X-Pagination`; 120 peticiones/minuto por
 *    token, con 429 y la espera en la cabecera de reintento.
 *  · Crear un Matter solo acepta `client_id`, `number`, `title`, `description` y
 *    `user_ids`: **no hay forma documentada de asignar el tipo**, por eso el tipo
 *    viaja en el título y la descripción.
 *  · Documentos en base64 estricto con `filename` con extensión.
 *  · No hay formato de error documentado: toda respuesta que no sea 2xx es un fallo.
 */
class DocketwiseHttpAdapter implements IntegrationAdapter
{
    private const BASE      = 'https://app.docketwise.com/api/v1/';
    private const AUTHORIZE = 'https://app.docketwise.com/oauth/authorize';
    private const TOKEN     = 'https://app.docketwise.com/oauth/token';

    public function __construct(private array $config, private ?string $accessToken = null)
    {
    }

    public function provider(): string
    {
        return 'docketwise';
    }

    public function mode(): string
    {
        return 'live';
    }

    private function client(): \CodeIgniter\HTTP\CURLRequest
    {
        return \Config\Services::curlrequest(['timeout' => 25, 'http_errors' => false], null, null, false);
    }

    private function redirectUri(): string
    {
        return (string) ($this->config['redirect_uri'] ?: base_url('leadflow/docketwise/callback'));
    }

    public function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => (string) $this->config['client_id'],
            'redirect_uri'  => $this->redirectUri(),
            'scope'         => (string) ($this->config['scope'] ?: 'public write'),
            'state'         => $state,
        ]);
    }

    private function token(array $params): array
    {
        $res  = $this->client()->post(self::TOKEN, ['form_params' => $params + [
            'client_id'     => (string) $this->config['client_id'],
            'client_secret' => (string) $this->config['secret'],
        ]]);
        $code = $res->getStatusCode();
        $body = json_decode((string) $res->getBody(), true);

        if ($code < 200 || $code >= 300 || empty($body['access_token'])) {
            throw new IntegrationException(lang('Lfl.errOauthToken', [$code]), $code);
        }

        return [
            'access_token'  => (string) $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? null,
            'expires_in'    => isset($body['expires_in']) ? (int) $body['expires_in'] : 180 * 86400,
        ];
    }

    public function exchangeCode(string $code): array
    {
        return $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri()]);
    }

    public function refresh(string $refreshToken): array
    {
        return $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    /**
     * Comprueba las credenciales de la app OAuth **sin usuario y sin token**.
     *
     * Pide un token con un `code` que sabemos inválido y lee QUÉ rechaza
     * Docketwise, que es la única forma de separar las dos cosas sin que alguien
     * autorice primero:
     *
     *  · `invalid_grant`  → la app se autenticó y lo único malo era el código:
     *    el client ID y el secreto son correctos.
     *  · `invalid_client` (o 401) → el client ID o el secreto no son los suyos.
     *  · `invalid_request` mencionando el redirect → la URI no coincide con la
     *    registrada.
     *
     * No crea ni cambia nada en la cuenta: un código inválido no otorga nada.
     *
     * @return array{status:string,http:int|null,detail:string}
     */
    public function checkApp(): array
    {
        if (empty($this->config['client_id']) || empty($this->config['secret'])) {
            return ['status' => 'error', 'http' => null, 'detail' => lang('Lfl.errProviderNotConfigured')];
        }

        try {
            $res = $this->client()->post(self::TOKEN, ['form_params' => [
                'grant_type'    => 'authorization_code',
                'code'          => 'lfl-probe-' . bin2hex(random_bytes(8)),
                'redirect_uri'  => $this->redirectUri(),
                'client_id'     => (string) $this->config['client_id'],
                'client_secret' => (string) $this->config['secret'],
            ]]);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'http' => null, 'detail' => lang('Lfl.errHttp', [$e->getMessage()])];
        }

        $code = $res->getStatusCode();
        $body = json_decode((string) $res->getBody(), true);
        $err  = is_array($body) ? mb_strtolower((string) ($body['error'] ?? '')) : '';
        $desc = is_array($body) ? mb_substr((string) ($body['error_description'] ?? ''), 0, 180) : '';

        if ($err === 'invalid_grant') {
            return ['status' => 'ok', 'http' => $code, 'detail' => lang('Lfl.diagAppOk')];
        }
        if ($code === 401 || $err === 'invalid_client' || $err === 'unauthorized_client') {
            return ['status' => 'error', 'http' => $code, 'detail' => lang('Lfl.diagAppBadCreds', [$desc ?: $err])];
        }
        if ($err === 'invalid_request' && mb_stripos($desc . $err, 'redirect') !== false) {
            return ['status' => 'error', 'http' => $code, 'detail' => lang('Lfl.diagAppBadRedirect', [$desc])];
        }
        if ($code >= 200 && $code < 300) {
            // Un código inventado no debería canjearse: si pasa, no es Docketwise quien responde.
            return ['status' => 'warn', 'http' => $code, 'detail' => lang('Lfl.diagAppUnexpected')];
        }

        return ['status' => 'warn', 'http' => $code, 'detail' => lang('Lfl.diagAppOther', [$code, $desc ?: ($err ?: mb_substr((string) $res->getBody(), 0, 120))])];
    }

    /** @return array{0:mixed,1:\CodeIgniter\HTTP\ResponseInterface} */
    private function request(string $method, string $path, ?array $json = null, array $query = []): array
    {
        if (! $this->accessToken) {
            throw new IntegrationException(lang('Lfl.errNotConnected'), 401);
        }

        $opts = ['headers' => ['Authorization' => 'Bearer ' . $this->accessToken, 'Accept' => 'application/json']];
        if ($json !== null) {
            $opts['json'] = $json;
        }
        if ($query) {
            $opts['query'] = $query;
        }

        try {
            $res = $this->client()->request($method, self::BASE . ltrim($path, '/'), $opts);
        } catch (\Throwable $e) {
            throw new IntegrationException(lang('Lfl.errHttp', [$e->getMessage()]), null, $e);
        }

        $code = $res->getStatusCode();
        $body = json_decode((string) $res->getBody(), true);

        if ($code === 429) {
            $wait = $res->getHeaderLine('Retry-After') ?: $res->getHeaderLine('Retry-Header') ?: '60';
            throw new IntegrationException(lang('Lfl.errRateLimit', [(int) $wait]), 429);
        }
        if ($code === 401) {
            throw new IntegrationException(lang('Lfl.errUnauthorized'), 401);
        }
        if ($code < 200 || $code >= 300) {
            $msg = is_array($body) ? (string) ($body['error'] ?? json_encode($body['errors'] ?? $body)) : mb_substr((string) $res->getBody(), 0, 200);
            throw new IntegrationException(lang('Lfl.errApi', [$code, mb_substr($msg, 0, 200)]), $code);
        }

        return [$body, $res];
    }

    private static function idOf($body): string
    {
        $id = is_array($body) ? ($body['id'] ?? null) : null;
        if ($id === null || $id === '') {
            throw new IntegrationException(lang('Lfl.errApiNoId'));
        }

        return (string) $id;
    }

    /**
     * El teléfono de un contacto, venga como venga.
     *
     * `lead_phone` solo lo traen los contactos marcados como lead; los demás
     * llevan sus números en `phone_numbers`, que es la misma forma en la que se
     * crean (`phone_numbers_attributes`). Leer solo el primero dejaba sin teléfono
     * a todos los contactos normales —incluidos los que crea la conversión—, y sin
     * teléfono no se detectan duplicados por ese criterio.
     */
    private static function phoneOf(array $c): ?string
    {
        foreach (['phone_numbers', 'phones'] as $key) {
            if (! is_array($c[$key] ?? null)) {
                continue;
            }
            $first = null;
            foreach ($c[$key] as $p) {
                $num = is_array($p) ? trim((string) ($p['number'] ?? $p['phone'] ?? '')) : trim((string) $p);
                if ($num === '') {
                    continue;
                }
                if (is_array($p) && (! empty($p['daytime']) || ! empty($p['primary']))) {
                    return $num;
                }
                $first ??= $num;
            }
            if ($first !== null) {
                return $first;
            }
        }

        foreach (['lead_phone', 'phone', 'mobile_phone', 'cell_phone', 'work_phone', 'home_phone'] as $k) {
            $v = trim((string) ($c[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }

    /** Igual que el teléfono: el correo puede venir suelto o en una lista. */
    private static function emailOf(array $c): ?string
    {
        $direct = trim((string) ($c['email'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        foreach (['emails', 'email_addresses'] as $key) {
            if (! is_array($c[$key] ?? null)) {
                continue;
            }
            foreach ($c[$key] as $e) {
                $addr = is_array($e) ? trim((string) ($e['address'] ?? $e['email'] ?? '')) : trim((string) $e);
                if ($addr !== '') {
                    return $addr;
                }
            }
        }

        return null;
    }

    public function listContacts(int $page): array
    {
        [$body, $res] = $this->request('GET', 'contacts', null, ['filter' => 'all', 'type' => 'Person', 'page' => $page]);
        $items = [];
        foreach ((array) $body as $c) {
            if (! is_array($c) || ! isset($c['id'])) {
                continue;
            }
            $items[] = ['id' => (string) $c['id'], 'first_name' => $c['first_name'] ?? null, 'last_name' => $c['last_name'] ?? null,
                'email' => self::emailOf($c), 'phone' => self::phoneOf($c)];
        }

        $next = null;
        $pag  = json_decode($res->getHeaderLine('X-Pagination'), true);
        if (is_array($pag) && ! empty($pag['next_page'])) {
            $next = (int) $pag['next_page'];
        } elseif (! $pag && count($items) >= 200) {
            $next = $page + 1;
        }

        return ['items' => $items, 'next' => $next];
    }

    public function getContact(string $id): ?array
    {
        try {
            [$body] = $this->request('GET', 'contacts/' . rawurlencode($id));
        } catch (IntegrationException $e) {
            if ($e->httpCode() === 404) {
                return null;
            }
            throw $e;
        }

        return is_array($body) ? $body : null;
    }

    public function createContact(array $data): string
    {
        $contact = [
            'first_name'  => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name'   => $data['last_name'],
            'email'       => $data['email'] ?? null,
            'type'        => 'Person',
            'lead'        => false,
        ];
        if (! empty($data['phone'])) {
            // Docketwise guarda cada número con su `number` **y** un objeto `data`
            // que repite el número y lleva las banderas como CADENAS («true»), tal
            // como se ve al leer un contacto creado desde su interfaz. Mandando
            // solo `number` el teléfono se perdía sin dar ningún error.
            $number = trim((string) $data['phone']);
            $contact['phone_numbers_attributes'] = [[
                'number' => $number,
                'data'   => ['number' => $number, 'daytime' => 'true', 'mobile' => 'true'],
            ]];
        }
        if (! empty($data['city'])) {
            $contact['addresses_attributes'] = [['city' => $data['city'], 'physical' => true, 'mailing' => false]];
        }

        [$body] = $this->request('POST', 'contacts', ['contact' => array_filter($contact, static fn ($v) => $v !== null)]);

        return self::idOf($body);
    }

    /**
     * Parche sobre un contacto existente.
     *
     * **Se intenta PATCH y, si el método no está permitido, PUT.** La documentación
     * v1 describe la creación pero no el verbo de la actualización, y esto no se ha
     * podido comprobar contra un sandbox porque no existe: probar los dos cuesta una
     * petición de más en el peor caso y evita que la reparación falle por un detalle
     * de forma. Un 404 se deja pasar: el contacto ya no está en la cuenta, y eso no
     * es un error de esta operación.
     */
    public function updateContact(string $id, array $data): void
    {
        $contact = [];
        foreach (['first_name', 'middle_name', 'last_name', 'email'] as $k) {
            if (array_key_exists($k, $data)) {
                $contact[$k] = $data[$k];
            }
        }
        if (! empty($data['phone'])) {
            $number = trim((string) $data['phone']);
            $contact['phone_numbers_attributes'] = [[
                'number' => $number,
                'data'   => ['number' => $number, 'daytime' => 'true', 'mobile' => 'true'],
            ]];
        }
        if (! empty($data['city'])) {
            $contact['addresses_attributes'] = [['city' => $data['city'], 'physical' => true, 'mailing' => false]];
        }
        if ($contact === []) {
            return;
        }

        $path = 'contacts/' . rawurlencode($id);

        try {
            $this->request('PATCH', $path, ['contact' => $contact]);
        } catch (IntegrationException $e) {
            if ($e->httpCode() === 404) {
                return;
            }
            if ($e->httpCode() !== 405 && $e->httpCode() !== 501) {
                throw $e;
            }
            $this->request('PUT', $path, ['contact' => $contact]);
        }
    }

    public function listUsers(): array
    {
        [$body] = $this->request('GET', 'users');
        $out = [];
        foreach ((array) $body as $u) {
            if (is_array($u) && isset($u['id'])) {
                $name = trim(($u['attorney_profile']['first_name'] ?? '') . ' ' . ($u['attorney_profile']['last_name'] ?? ''));
                $out[(string) $u['id']] = $name !== '' ? $name : (string) ($u['email'] ?? $u['id']);
            }
        }

        return $out;
    }

    public function listMatterTypes(): array
    {
        [$body] = $this->request('GET', 'matter_types');
        $out = [];
        foreach ((array) $body as $t) {
            if (is_array($t) && isset($t['id'])) {
                $out[(string) $t['id']] = (string) ($t['name'] ?? $t['id']);
            }
        }

        return $out;
    }

    public function createMatter(array $data): string
    {
        [$body] = $this->request('POST', 'matters', ['matter' => array_filter([
            'client_id'   => (int) $data['client_id'],
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'user_ids'    => array_values(array_map('intval', $data['user_ids'] ?? [])) ?: null,
        ], static fn ($v) => $v !== null)]);

        return self::idOf($body);
    }

    public function createNote(array $data): string
    {
        // El ejemplo de la documentación de notas va SIN clave raíz.
        [$body] = $this->request('POST', 'notes', array_filter([
            'matter_id' => $data['matter_id'] ?? null,
            'client_id' => $data['matter_id'] ? null : ($data['client_id'] ?? null),
            'title'     => $data['title'],
            'content'   => $data['content'],
        ], static fn ($v) => $v !== null));

        return self::idOf($body);
    }

    public function createDocument(array $data): string
    {
        [$body] = $this->request('POST', 'documents', ['document' => array_filter([
            'title'       => $data['title'],
            'filename'    => $data['filename'],
            'base64_data' => $data['base64'],
            'description' => $data['description'] ?? null,
            'client_id'   => isset($data['client_id']) ? (int) $data['client_id'] : null,
            'matter_id'   => isset($data['matter_id']) ? (int) $data['matter_id'] : null,
        ], static fn ($v) => $v !== null)]);

        return self::idOf($body);
    }
}
