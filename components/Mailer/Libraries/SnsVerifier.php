<?php

namespace Components\Mailer\Libraries;

/**
 * Comprueba que un mensaje de SNS viene de verdad de Amazon.
 *
 * **Por qué hace falta.** El webhook de rebotes es público y está exento de
 * CSRF —SNS no manda token, no puede—, así que sin esto acepta cualquier JSON
 * con la forma correcta: quien conozca la URL puede inventarse rebotes y
 * **dejar sin lista de correo a una empresa**, en silencio y sin rastro. AWS
 * firma cada mensaje precisamente para que se pueda distinguir.
 *
 * El procedimiento es el que publica AWS: se arma una cadena canónica con
 * campos fijos y en orden fijo, se descarga el certificado con el que se firmó,
 * y se verifica con la clave pública. Dos cuidados aparte de la firma:
 *
 *  - La **URL del certificado la elige quien envía**, así que hay que exigir que
 *    sea de Amazon antes de descargarla; si no, es una vía para que el servidor
 *    pida por ti cualquier URL (SSRF).
 *  - El certificado se **cachea**: sin eso, cada rebote de una campaña grande
 *    dispara una descarga.
 */
class SnsVerifier
{
    /** Campos que entran en la firma, por tipo de mensaje y EN ESTE ORDEN. */
    private const FIELDS = [
        'Notification'              => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
        'SubscriptionConfirmation'  => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
        'UnsubscribeConfirmation'   => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
    ];

    /** Certificados ya descargados, por URL. */
    private static array $certs = [];

    /**
     * ¿La URL es de SNS? Vale tanto para `SigningCertURL` como para
     * `SubscribeURL`: las dos las elige quien envía el POST.
     *
     * Se compara el host **completo** contra el patrón: mirar solo si «acaba
     * en amazonaws.com» dejaría pasar `sns.evil-amazonaws.com`.
     */
    public static function isAmazonUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        return (bool) preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/i', $parts['host']);
    }

    /**
     * ¿El mensaje está firmado por Amazon?
     *
     * Devuelve false ante cualquier duda —tipo desconocido, campo que falta,
     * certificado que no se puede leer—: en un webhook público, «no sé» y «no»
     * tienen que tratarse igual.
     */
    public function verify(array $msg): bool
    {
        $type = (string) ($msg['Type'] ?? '');
        if (!isset(self::FIELDS[$type])) {
            return false;
        }
        $firma = (string) ($msg['Signature'] ?? '');
        $certU = (string) ($msg['SigningCertURL'] ?? $msg['SigningCertUrl'] ?? '');
        if ($firma === '' || !self::isAmazonUrl($certU)) {
            return false;
        }
        // La versión 1 firma con SHA1 y la 2 con SHA256. Cualquier otra cosa, no.
        $version = (string) ($msg['SignatureVersion'] ?? '1');
        $algo    = $version === '2' ? OPENSSL_ALGO_SHA256 : ($version === '1' ? OPENSSL_ALGO_SHA1 : null);
        if ($algo === null) {
            return false;
        }

        $canon = '';
        foreach (self::FIELDS[$type] as $campo) {
            // Un campo ausente se salta (Subject es opcional); uno presente
            // entra siempre, aunque venga vacío.
            if (!array_key_exists($campo, $msg)) {
                continue;
            }
            $canon .= $campo . "\n" . $msg[$campo] . "\n";
        }

        $cert = $this->cert($certU);
        if ($cert === null) {
            return false;
        }
        $pub = @openssl_get_publickey($cert);
        if (!$pub) {
            return false;
        }

        return openssl_verify($canon, base64_decode($firma, true) ?: '', $pub, $algo) === 1;
    }

    /** Descarga el certificado (ya validado el host) y lo recuerda. */
    private function cert(string $url): ?string
    {
        if (array_key_exists($url, self::$certs)) {
            return self::$certs[$url];
        }
        $ctx = stream_context_create(['http' => ['timeout' => 5], 'ssl' => ['verify_peer' => true]]);
        $pem = @file_get_contents($url, false, $ctx);

        return self::$certs[$url] = ($pem !== false && str_contains($pem, 'BEGIN CERTIFICATE')) ? $pem : null;
    }
}
