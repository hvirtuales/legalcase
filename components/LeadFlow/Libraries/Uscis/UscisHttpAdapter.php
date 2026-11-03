<?php

namespace Components\LeadFlow\Libraries\Uscis;

/**
 * USCIS de verdad, sobre `client_credentials`.
 *
 * ── Lo que condiciona el diseño ───────────────────────────────────────────────
 *
 * **1.000 consultas al día y 5 por segundo, por firma.** Este adaptador NO cachea ni
 * cuenta: es deliberado. Un adaptador que decide cuándo no llamar esconde la política
 * donde nadie la busca. De la caché y del cupo se encarga `UscisService`, que es quien
 * sabe de qué firma se trata; aquí solo se habla HTTP.
 *
 * **El token se reutiliza mientras viva.** `client_credentials` devuelve un token con
 * caducidad; pedir uno nuevo en cada consulta gastaría el doble de llamadas. Se guarda
 * con su expiración y se renueva solo cuando hace falta, con un margen de 60 s para no
 * usar uno que caduca por el camino.
 *
 * **Los errores se traducen.** Un 401 significa «tus credenciales están mal» y un 404
 * «ese caso no existe»: son cosas distintas para quien mira la pantalla, y mezclarlas
 * en un «error 4xx» obliga a la firma a adivinar si el problema es suyo.
 */
class UscisHttpAdapter implements CaseStatusProvider
{
    /** El sandbox. Producción usa otro host, y cambiarlo es una decisión consciente. */
    public const BASE = 'https://api-int.uscis.gov';

    private string $clientId;
    private string $clientSecret;
    private ?string $token = null;
    private int $expiresAt = 0;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function mode(): string
    {
        return 'live';
    }

    public function token(): string
    {
        // Margen de 60 s: un token que caduca mientras viaja la petición devolvería un
        // 401 que parecería un problema de credenciales.
        if ($this->token !== null && $this->expiresAt > time() + 60) {
            return $this->token;
        }

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new UscisException(lang('Lfl.uscNoCredentials'));
        }

        $res = $this->call('POST', self::BASE . '/oauth/accesstoken', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
        ]);

        if ($res['code'] === 401 || $res['code'] === 403) {
            throw new UscisException(lang('Lfl.uscBadCredentials'));
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new UscisException(lang('Lfl.uscTokenFailed', [$res['code']]));
        }

        $body = json_decode($res['body'], true);
        $tok  = is_array($body) ? ($body['access_token'] ?? null) : null;
        if (! is_string($tok) || $tok === '') {
            throw new UscisException(lang('Lfl.uscTokenFailed', [$res['code']]));
        }

        $this->token     = $tok;
        $this->expiresAt = time() + max(60, (int) ($body['expires_in'] ?? 1800));

        return $tok;
    }

    public function caseStatus(string $receipt): array
    {
        $receipt = strtoupper(trim($receipt));
        $res     = $this->call('GET', self::BASE . '/case-status/' . rawurlencode($receipt), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token(),
                'Accept'        => 'application/json',
            ],
        ]);

        if ($res['code'] === 404) {
            throw new UscisException(lang('Lfl.uscNotFound'));
        }
        if ($res['code'] === 429) {
            // El cupo lo vigila `UscisService`, pero si USCIS corta primero hay que
            // decirlo con sus palabras y no como un fallo genérico.
            throw new UscisException(lang('Lfl.uscRateLimited'));
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new UscisException(lang('Lfl.uscQueryFailed', [$res['code']]));
        }

        $body = json_decode($res['body'], true);
        if (! is_array($body)) {
            throw new UscisException(lang('Lfl.uscQueryFailed', [$res['code']]));
        }

        return self::normalize($receipt, $body);
    }

    /**
     * La respuesta de USCIS, con los nombres que usa el componente.
     *
     * Mapeada contra lo que el sandbox devuelve de verdad, no contra la documentación:
     * el caso viene envuelto en `case_status`, los campos son snake_case y el texto del
     * estado llega **en inglés y en español** (`..._text_en` / `..._text_es`).
     *
     * Se conservan los dos idiomas a propósito. El público de este producto es
     * hispanohablante, y enseñar el estado en español **con las palabras de USCIS** es
     * lo correcto: traducirlo nosotros sería inventar matices en un texto legal.
     *
     * @param array<string,mixed> $b
     *
     * @return array{receipt:string,form_type:?string,status:?string,status_es:?string,detail:?string,detail_es:?string,submitted_at:?string,updated_at:?string,history:array<int,array{date:?string,text:?string,text_es:?string}>}
     */
    public static function normalize(string $receipt, array $b): array
    {
        $c = (isset($b['case_status']) && is_array($b['case_status'])) ? $b['case_status'] : $b;

        $pick = static function (array $src, array $keys): ?string {
            foreach ($keys as $k) {
                if (isset($src[$k]) && $src[$k] !== '' && is_scalar($src[$k])) {
                    return trim((string) $src[$k]);
                }
            }

            return null;
        };

        $historia = [];
        foreach ((array) ($c['hist_case_status'] ?? []) as $h) {
            if (! is_array($h)) {
                continue;
            }
            $historia[] = [
                'date'    => self::date($pick($h, ['date'])),
                'text'    => $pick($h, ['completed_text_en']),
                'text_es' => $pick($h, ['completed_text_es']),
            ];
        }

        return [
            'receipt'      => $pick($c, ['receiptNumber', 'receipt_number']) ?? $receipt,
            'form_type'    => $pick($c, ['formType', 'form_type']),
            'status'       => $pick($c, ['current_case_status_text_en', 'currentCaseStatusTextEn']),
            'status_es'    => $pick($c, ['current_case_status_text_es', 'currentCaseStatusTextEs']),
            'detail'       => $pick($c, ['current_case_status_desc_en', 'currentCaseStatusDescEn']),
            'detail_es'    => $pick($c, ['current_case_status_desc_es', 'currentCaseStatusDescEs']),
            'submitted_at' => self::date($pick($c, ['submittedDate', 'receiptDate'])),
            'updated_at'   => self::date($pick($c, ['modifiedDate', 'lastUpdatedDate'])),
            'history'      => $historia,
        ];
    }

    /**
     * Una fecha de USCIS, en formato ISO.
     *
     * **USCIS usa `MM-DD-YYYY`**, y `strtotime()` lee una fecha con guiones como
     * `DD-MM-YYYY`: el 5 de septiembre se convertiría en el 9 de mayo sin que nada
     * falle visiblemente. Se parsea con el formato explícito y solo se cae a
     * `strtotime()` para las que ya vienen en ISO (el historial las da así).
     */
    public static function date(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }

        foreach (['m-d-Y H:i:s', 'm-d-Y', 'Y-m-d H:i:s', 'Y-m-d'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat($fmt, $v);
            if ($d instanceof \DateTimeImmutable && \DateTimeImmutable::getLastErrors() === false) {
                return $d->format('Y-m-d');
            }
        }

        $t = strtotime($v);

        return $t ? date('Y-m-d', $t) : null;
    }
    /**
     * Una llamada HTTP, con espera corta.
     *
     * 10 segundos y no más: esto se consulta desde una pantalla que alguien está
     * mirando, y una espera larga contra un servicio caído deja la página colgada en
     * vez de decir «no se pudo consultar».
     *
     * @return array{code:int,body:string}
     */
    private function call(string $method, string $url, array $opts): array
    {
        try {
            $client = \Config\Services::curlrequest(['timeout' => 10, 'http_errors' => false]);
            $res    = $client->request($method, $url, $opts);

            return ['code' => $res->getStatusCode(), 'body' => (string) $res->getBody()];
        } catch (\Throwable $e) {
            log_message('error', 'USCIS: ' . $method . ' ' . $url . ' falló: ' . $e->getMessage());

            throw new UscisException(lang('Lfl.uscUnreachable'));
        }
    }
}
