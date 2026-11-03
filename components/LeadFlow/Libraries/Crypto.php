<?php

namespace Components\LeadFlow\Libraries;

/**
 * Cifrado en reposo de los secretos del componente: el `client_secret` de la app
 * OAuth y los tokens de acceso y refresco de cada firma (§31).
 *
 * Usa el `encrypter` del framework (la clave de `.env`), no una clase del núcleo:
 * así el componente cifra igual en cualquier instalación. El marcador `enc:lfl:`
 * distingue lo cifrado de lo que no, para que un valor heredado en claro no
 * reviente al descifrar.
 */
class Crypto
{
    private const MARK = 'enc:lfl:';

    public static function enc(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return self::MARK . base64_encode(service('encrypter')->encrypt($plain));
    }

    public static function dec(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        if (! str_starts_with($stored, self::MARK)) {
            return $stored;
        }

        try {
            return service('encrypter')->decrypt(base64_decode(substr($stored, strlen(self::MARK))));
        } catch (\Throwable $e) {
            log_message('error', 'LeadFlow: no se pudo descifrar un secreto: ' . $e->getMessage());

            return null;
        }
    }
}
