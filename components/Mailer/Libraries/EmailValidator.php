<?php

namespace Components\Mailer\Libraries;

/**
 * Validación de correos para reducir spam/rebotes: sintaxis (RFC vía filter_var),
 * dominios desechables conocidos y (opcional) verificación de registro MX del
 * dominio. Todo local y sin CDN.
 */
class EmailValidator
{
    /**
     * Prefijos de **direcciones de rol**: no son de una persona, las lee todo un
     * equipo y cualquiera de ellos puede marcarlas como spam sin haberse
     * suscrito nunca. Son las que más quejas generan, y muchas ni existen.
     *
     * No se rechazan: se **avisan**. Una empresa puede tener razones legítimas
     * para escribir a `facturacion@`, y decidirlo es suyo, no nuestro.
     */
    public const ROLE_PREFIXES = [
        'abuse', 'admin', 'administrator', 'billing', 'compras', 'contact', 'contacto',
        'contabilidad', 'facturacion', 'help', 'hostmaster', 'info', 'informacion',
        'mail', 'marketing', 'no-reply', 'noc', 'noreply', 'postmaster', 'root',
        'sales', 'security', 'soporte', 'support', 'sysadmin', 'usenet', 'ventas',
        'webmaster', 'www',
    ];

    /** Los desechables ya leídos del archivo, una vez por petición. */
    private static ?array $disposable = null;

    /**
     * Dominios desechables, desde `Data/disposable.txt`.
     *
     * En un archivo y no en una constante para poder actualizarlo sin tocar
     * código: de estos aparecen nuevos cada semana, y la lista de catorce que
     * había escrita a mano daba sensación de más cobertura de la que tenía.
     */
    public static function disposableDomains(): array
    {
        if (self::$disposable !== null) {
            return self::$disposable;
        }
        $ruta  = dirname(__DIR__) . '/Data/disposable.txt';
        $lista = [];
        foreach (@file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
            $linea = strtolower(trim($linea));
            if ($linea !== '' && $linea[0] !== '#') {
                $lista[$linea] = true;
            }
        }

        return self::$disposable = $lista;
    }

    /** Errores de tecleo frecuentes en dominios de proveedores populares → dominio correcto. */
    private const TYPOS = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmil.com' => 'gmail.com', 'gmaill.com' => 'gmail.com',
        'gnail.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.cm' => 'gmail.com', 'gmailc.om' => 'gmail.com',
        'hotmial.com' => 'hotmail.com', 'hotmal.com' => 'hotmail.com', 'hotmil.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com',
        'hotnail.com' => 'hotmail.com', 'hormail.com' => 'hotmail.com', 'hotmail.co' => 'hotmail.com', 'hotmail.con' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outook.com' => 'outlook.com', 'outloo.com' => 'outlook.com', 'outlook.co' => 'outlook.com', 'outlookc.om' => 'outlook.com',
        'yahooo.com' => 'yahoo.com', 'yaho.com' => 'yahoo.com', 'yahho.com' => 'yahoo.com', 'yahoo.co' => 'yahoo.com', 'yahoo.con' => 'yahoo.com',
        'iclould.com' => 'icloud.com', 'iclod.com' => 'icloud.com', 'icloud.co' => 'icloud.com',
    ];

    /** Cache de resultados MX por dominio dentro de la petición. */
    private array $mxCache = [];

    /** Sintaxis válida (RFC). */
    public function isValidSyntax(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function domain(string $email): string
    {
        $parts = explode('@', strtolower(trim($email)));

        return count($parts) === 2 ? $parts[1] : '';
    }

    public function isDisposable(string $email): bool
    {
        return isset(self::disposableDomains()[$this->domain($email)]);
    }

    /** ¿Es una dirección de rol (`info@`, `ventas@`…)? */
    public function isRole(string $email): bool
    {
        $local = strtolower(trim(explode('@', trim($email))[0] ?? ''));
        // `ventas.bogota@` cuenta igual: lo que manda es la primera palabra.
        $raiz  = preg_split('/[.+_-]/', $local)[0] ?? $local;

        return in_array($local, self::ROLE_PREFIXES, true) || in_array($raiz, self::ROLE_PREFIXES, true);
    }

    /** El dominio resuelve a un servidor de correo (registro MX o A de respaldo). */
    public function hasMx(string $email): bool
    {
        $domain = $this->domain($email);
        if ($domain === '') {
            return false;
        }
        if (isset($this->mxCache[$domain])) {
            return $this->mxCache[$domain];
        }
        if (!function_exists('checkdnsrr')) {
            return $this->mxCache[$domain] = true; // no se puede comprobar → no bloquear
        }

        return $this->mxCache[$domain] = (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A'));
    }

    /**
     * Corrección sugerida para un typo de dominio conocido; '' si no aplica.
     * Ej.: juan@gmial.com → juan@gmail.com
     */
    public function suggest(string $email): string
    {
        $email = strtolower(trim($email));
        $parts = explode('@', $email);
        if (count($parts) !== 2 || $parts[0] === '') {
            return '';
        }
        [$local, $domain] = $parts;
        if (isset(self::TYPOS[$domain])) {
            return $local . '@' . self::TYPOS[$domain];
        }
        if (str_ends_with($domain, '.con')) { // .con → .com
            return $local . '@' . substr($domain, 0, -4) . '.com';
        }

        return '';
    }

    /**
     * Chequeo completo (sintaxis → desechable → typo de dominio → MX opcional).
     * $checkMx hace consulta DNS (más lento, cacheada por dominio).
     *
     * @return array{ok: bool, reason: string, suggestion?: string}
     */
    public function check(string $email, bool $checkMx = false): array
    {
        $email = trim($email);
        if ($email === '') {
            return ['ok' => false, 'reason' => lang('Mailer.vEmpty')];
        }
        if (!$this->isValidSyntax($email)) {
            return ['ok' => false, 'reason' => lang('Mailer.vSyntax')];
        }
        if ($this->isDisposable($email)) {
            return ['ok' => false, 'reason' => lang('Mailer.vDisposable')];
        }
        $sug = $this->suggest($email);
        if ($sug !== '') {
            return ['ok' => false, 'reason' => lang('Mailer.vTypo', [$sug]), 'suggestion' => $sug];
        }
        if ($checkMx && !$this->hasMx($email)) {
            return ['ok' => false, 'reason' => lang('Mailer.vNoMx')];
        }

        // La dirección es válida; `role` es un AVISO, no un rechazo: escribir a
        // `facturacion@` puede ser legítimo y decidirlo es de la empresa.
        return ['ok' => true, 'reason' => '', 'role' => $this->isRole($email)];
    }
}
