<?php

namespace App\Libraries;

/**
 * Emite y valida JWT (HS256) para la API — implementación nativa, SIN
 * dependencias externas. El payload lleva el usuario, los roles concedidos al
 * token y un jti (id del registro en api_tokens) para poder revocarlo aunque
 * el JWT siga vigente.
 */
class JwtService
{
    private string $secret;

    public function __construct()
    {
        // Secreto derivado de la clave de cifrado de la app (env encryption.key).
        $base         = (string) (env('encryption.key') ?: env('JWT_SECRET') ?: 'cuentas-fallback-secret');
        $this->secret = hash('sha256', $base);
    }

    /**
     * Genera un JWT.
     *
     * @param int   $userId  Usuario dueño del token.
     * @param int   $jti     id del registro en api_tokens (para revocación).
     * @param array $roleIds Roles concedidos al token.
     * @param int   $expTs   Timestamp de expiración (0 = sin expiración).
     */
    public function issue(int $userId, int $jti, array $roleIds, int $expTs = 0, ?int $companyId = null): string
    {
        $now     = time();
        $payload = [
            'iss'   => 'cuentas-api',
            'sub'   => $userId,
            'jti'   => $jti,
            'roles' => array_values(array_map('intval', $roleIds)),
            'iat'   => $now,
        ];
        if ($companyId) {
            $payload['company'] = $companyId;
        }
        if ($expTs > 0) {
            $payload['exp'] = $expTs;
        }

        $header  = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segs    = [$this->b64(json_encode($header)), $this->b64(json_encode($payload))];
        $signing = implode('.', $segs);
        $segs[]  = $this->b64(hash_hmac('sha256', $signing, $this->secret, true));

        return implode('.', $segs);
    }

    /**
     * Decodifica y valida un JWT (firma + expiración). Devuelve el payload como
     * array o null si es inválido/expirado.
     */
    public function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;

        $header = json_decode($this->unb64($h), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $expected = $this->b64(hash_hmac('sha256', $h . '.' . $p, $this->secret, true));
        if (!hash_equals($expected, $s)) {
            return null;
        }

        $payload = json_decode($this->unb64($p), true);
        if (!is_array($payload)) {
            return null;
        }
        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            return null; // expirado
        }

        return $payload;
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
