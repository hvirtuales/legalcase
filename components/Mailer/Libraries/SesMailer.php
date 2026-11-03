<?php

namespace Components\Mailer\Libraries;

use App\Models\AppSettingModel;
use Aws\Ses\SesClient;

/**
 * El emisor por Amazon SES del componente.
 *
 * **Por qué existe.** `MailerService` llamaba a `SesService::sendRawEmail()` y
 * `::getMaxSendRate()`, y **ninguno de los dos existe** en el `SesService` del
 * núcleo: solo expone `isEnabled`, `client`, `sendEmail` y `testConnection`. Con
 * SES apagado —como en desarrollo— nunca se llegaba a ejecutar, así que el fallo
 * estuvo escondido; en cuanto se activa la cuenta, la primera campaña muere con
 * «Call to undefined method». Y mientras faltó, **las cabeceras
 * `List-Unsubscribe` no viajaron en ningún correo**, que es justo lo que exigen
 * Gmail y Yahoo a los remitentes masivos.
 *
 * Vive en el componente y no en el núcleo por el contrato: un componente no
 * puede depender de una clase que él mismo añadiría al núcleo, porque en otra
 * instalación no estaría. Solo necesita el SDK de AWS —dependencia de la
 * plataforma— y los ajustes de `app_settings`.
 *
 * `SendRawEmail` y no `SendEmail` porque es la única forma de mandar cabeceras
 * propias: el mensaje se construye entero aquí.
 */
class SesMailer
{
    private array $settings;
    private ?SesClient $client = null;
    private ?\Aws\SesV2\SesV2Client $clientV2 = null;

    /** Cuota de la cuenta, cacheada por petición: es una llamada de red. */
    private ?array $quota = null;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings ?? (new AppSettingModel())->getAllSettings();
    }

    public function isEnabled(): bool
    {
        return !empty($this->settings['ses_enabled']);
    }

    public function defaultFrom(): string
    {
        return trim((string) ($this->settings['ses_from_email'] ?? ''));
    }

    private function client(): SesClient
    {
        if ($this->client === null) {
            $this->client = new SesClient([
                'version'     => 'latest',
                'region'      => trim((string) ($this->settings['ses_region'] ?? '')) ?: 'us-east-1',
                'credentials' => [
                    'key'    => (string) ($this->settings['ses_key'] ?? ''),
                    'secret' => (string) ($this->settings['ses_secret'] ?? ''),
                ],
            ]);
        }

        return $this->client;
    }

    /**
     * Cuota de envío de la CUENTA (no la del plan, que es otra cosa).
     *
     * @return array{max24:float,sent24:float,rate:float}
     */
    public function sendQuota(): array
    {
        if ($this->quota !== null) {
            return $this->quota;
        }
        // Si SES no responde, se devuelve algo conservador en vez de reventar:
        // no poder consultar la cuota no puede impedir enviar.
        $this->quota = ['max24' => 0.0, 'sent24' => 0.0, 'rate' => 1.0];

        try {
            $r = $this->client()->getSendQuota();
            $this->quota = [
                'max24'  => (float) $r->get('Max24HourSend'),
                'sent24' => (float) $r->get('SentLast24Hours'),
                'rate'   => (float) $r->get('MaxSendRate'),
            ];
        } catch (\Throwable $e) {
            log_message('warning', 'Mailer: no se pudo leer la cuota de SES: ' . $e->getMessage());
        }

        return $this->quota;
    }

    /** Correos por segundo que admite la cuenta. Nunca menos de uno. */
    public function maxSendRate(): float
    {
        $rate = $this->sendQuota()['rate'];

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * Cuántos correos quedan hoy en la CUENTA, o `null` si no se pudo saber.
     * `Max24HourSend` a -1 significa cuenta sin límite diario.
     */
    public function dailyLeft(): ?int
    {
        $q = $this->sendQuota();
        if ($q['max24'] <= 0) {
            return null; // sin límite, o no se pudo consultar
        }

        return (int) max(0, $q['max24'] - $q['sent24']);
    }

    /** ¿La identidad del remitente está verificada en SES? `null` = no se sabe. */
    public function isVerified(string $email): ?bool
    {
        $email  = trim($email);
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($email === '') {
            return null;
        }

        try {
            $r = $this->client()->getIdentityVerificationAttributes([
                'Identities' => array_values(array_filter([$email, $domain])),
            ]);
            foreach ((array) $r->get('VerificationAttributes') as $attr) {
                if (strcasecmp((string) ($attr['VerificationStatus'] ?? ''), 'Success') === 0) {
                    return true;   // basta con que valga la dirección O su dominio
                }
            }

            return false;
        } catch (\Throwable $e) {
            log_message('warning', 'Mailer: no se pudo comprobar el remitente: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Envía un correo con cabeceras propias, vía `SendRawEmail`.
     *
     * @param  array<string,string> $headers cabeceras extra (List-Unsubscribe…)
     * @return array{ok:bool,message:string,messageId?:string}
     */
    public function send(
        string $to,
        string $subject,
        string $html,
        ?string $fromEmail = null,
        ?string $fromName = null,
        array $headers = []
    ): array {
        $from = trim((string) ($fromEmail ?: $this->defaultFrom()));
        if ($from === '') {
            return ['ok' => false, 'message' => lang('Mailer.sesNoFrom')];
        }

        try {
            $args = ['RawMessage' => ['Data' => $this->mime($to, $subject, $html, $from, $fromName, $headers)]];

            // El conjunto de configuración es lo que hace que SES publique los
            // eventos —rebotes, quejas, entregas— en el tema de SNS que lee
            // nuestro webhook. Sin él, la lista de supresión solo se alimenta de
            // las bajas manuales.
            $set = trim((string) ($this->settings['ses_configuration_set'] ?? ''));
            if ($set !== '') {
                $args['ConfigurationSetName'] = $set;
            }

            $res = $this->client()->sendRawEmail($args);

            return ['ok' => true, 'message' => '', 'messageId' => (string) $res->get('MessageId')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * El mensaje MIME completo.
     *
     * Se arma a mano y no con una librería porque lo que se manda es simple —un
     * cuerpo HTML y unas cabeceras— y traerse un dependiente entero para esto
     * sería desproporcionado. Las tres reglas que importan:
     *
     *   1. Todo lo que pueda llevar acentos va **codificado** (RFC 2047 en las
     *      cabeceras, base64 en el cuerpo); una `ñ` cruda en el asunto lo deja
     *      ilegible en medio cliente de correo.
     *   2. Las cabeceras se **limpian de saltos de línea** antes de escribirlas:
     *      un `\r\n` colado en un valor deja inyectar cabeceras arbitrarias.
     *   3. Líneas terminadas en `\r\n`, que es lo que exige el formato.
     */
    private function mime(string $to, string $subject, string $html, string $from, ?string $fromName, array $headers): string
    {
        $lines = [];
        $lines[] = 'From: ' . $this->address($from, $fromName);
        $lines[] = 'To: ' . $this->clean($to);
        $lines[] = 'Subject: ' . $this->encodeWord($subject);
        $lines[] = 'MIME-Version: 1.0';

        foreach ($headers as $name => $value) {
            // Una cabecera que traía un salto de línea se DESCARTA entera, no se
            // limpia: limpiarla pegaría el texto de después al valor y dejaría
            // una cabecera con contenido que nadie pidió. Y un salto ahí solo
            // puede ser un intento de inyectar cabeceras o un error nuestro;
            // en los dos casos, fuera.
            if ($this->hasBreak((string) $name) || $this->hasBreak((string) $value)) {
                log_message('warning', 'Mailer: cabecera con salto de línea descartada: ' . $this->clean((string) $name));
                continue;
            }
            $name  = $this->clean((string) $name);
            $value = $this->clean((string) $value);
            if ($name === '' || $value === '') {
                continue;
            }
            $lines[] = $name . ': ' . $value;
        }

        $lines[] = 'Content-Type: text/html; charset=UTF-8';
        $lines[] = 'Content-Transfer-Encoding: base64';
        $lines[] = '';

        return implode("\r\n", $lines) . "\r\n" . chunk_split(base64_encode($html), 76, "\r\n");
    }

    /** `Nombre <correo@dominio>`, con el nombre codificado si hace falta. */
    private function address(string $email, ?string $name): string
    {
        $email = $this->clean($email);
        $name  = $this->clean((string) $name);

        return $name === '' ? $email : $this->encodeWord($name) . ' <' . $email . '>';
    }

    /**
     * Codifica en RFC 2047 solo si hace falta. Se deja tal cual lo que es ASCII
     * imprimible: codificar un asunto en inglés solo lo hace ilegible en los
     * registros y no gana nada.
     */
    private function encodeWord(string $text): string
    {
        $text = $this->clean($text);
        if ($text === '' || preg_match('/^[\x20-\x7E]*$/', $text)) {
            return $text;
        }

        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    /** ¿Trae un salto de línea o un nulo? Es la firma de una inyección. */
    private function hasBreak(string $value): bool
    {
        return strpbrk($value, "\r\n\0") !== false;
    }

    /** Fuera saltos de línea y nulos: es lo que permite inyectar cabeceras. */
    private function clean(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }

    /**
     * ¿Está la dirección en la lista de supresión de la CUENTA de SES?
     *
     * Es distinta de la nuestra: AWS apunta ahí las direcciones que le han
     * rebotado a cualquiera de sus clientes, así que consultarla evita rebotes
     * que ya se sabe que van a ocurrir. `null` cuando no se pudo saber —cuenta
     * sin la lista activada, permiso que falta, SES caído—: ante la duda se
     * envía, porque no poder consultar no es motivo para no escribirle a nadie.
     */
    public function inAccountSuppressionList(string $email): ?bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        try {
            $this->clientV2()->getSuppressedDestination(['EmailAddress' => $email]);

            return true;
        } catch (\Aws\Exception\AwsException $e) {
            // «No está en la lista» es un 404, no un fallo: es la respuesta.
            if ($e->getAwsErrorCode() === 'NotFoundException' || $e->getStatusCode() === 404) {
                return false;
            }
            log_message('warning', 'Mailer: no se pudo consultar la supresión de la cuenta: ' . $e->getMessage());

            return null;
        } catch (\Throwable $e) {
            log_message('warning', 'Mailer: no se pudo consultar la supresión de la cuenta: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Cliente de la API v2, que es donde vive la lista de supresión de la
     * cuenta: la v1 (`SesClient`) no la tiene. Se crea aparte y solo si se usa,
     * para no pagar por ello en cada envío.
     */
    private function clientV2(): \Aws\SesV2\SesV2Client
    {
        if ($this->clientV2 === null) {
            $this->clientV2 = new \Aws\SesV2\SesV2Client([
                'version'     => 'latest',
                'region'      => trim((string) ($this->settings['ses_region'] ?? '')) ?: 'us-east-1',
                'credentials' => [
                    'key'    => (string) ($this->settings['ses_key'] ?? ''),
                    'secret' => (string) ($this->settings['ses_secret'] ?? ''),
                ],
            ]);
        }

        return $this->clientV2;
    }
}
