<?php

namespace Components\LeadFlow\Libraries;

/**
 * Captcha propio, **sin CDN** (copia del de PQRSF, §0.4: un componente no depende
 * de otro). Tres barreras del lado del servidor: código en imagen dibujada con GD
 * (en la sesión solo su hash, de un solo uso), campo trampa invisible y tiempo
 * mínimo firmado con HMAC entre abrir el formulario y enviarlo.
 */
class Captcha
{
    private const KEY     = 'lfl_captcha';
    private const TTL     = 600;
    private const MIN_SEC = 3;

    public function issue(): string
    {
        $pool = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $pool[random_int(0, strlen($pool) - 1)];
        }
        session()->set(self::KEY, ['hash' => password_hash(strtolower($code), PASSWORD_DEFAULT), 'exp' => time() + self::TTL]);

        return $code;
    }

    public function check(?string $answer): bool
    {
        $d = session()->get(self::KEY);
        session()->remove(self::KEY);
        if (! is_array($d) || ($d['exp'] ?? 0) < time()) {
            return false;
        }

        return password_verify(strtolower(trim((string) $answer)), $d['hash']);
    }

    public function stamp(): string
    {
        $t = (string) time();

        return $t . '.' . hash_hmac('sha256', $t, (string) config('Encryption')->key);
    }

    public function humanSpeed(?string $stamp): bool
    {
        [$t, $sig] = array_pad(explode('.', (string) $stamp, 2), 2, '');
        if (! ctype_digit((string) $t) || ! hash_equals(hash_hmac('sha256', $t, (string) config('Encryption')->key), (string) $sig)) {
            return false;
        }

        return (time() - (int) $t) >= self::MIN_SEC;
    }

    /** PNG del reto como data URI. Sin GD devuelve '' y el formulario lo omite. */
    public function image(string $code): string
    {
        if (! extension_loaded('gd')) {
            return '';
        }
        [$w, $h] = [190, 60];
        $img  = imagecreatetruecolor($w, $h);
        $bg   = imagecolorallocate($img, 255, 255, 255);
        $ink  = imagecolorallocate($img, 17, 17, 17);
        $soft = imagecolorallocate($img, 190, 190, 190);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        for ($i = 0; $i < 6; $i++) {
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $soft);
        }
        for ($i = 0; $i < 180; $i++) {
            imagesetpixel($img, random_int(0, $w), random_int(0, $h), $soft);
        }
        $x = 18;
        foreach (str_split($code) as $ch) {
            imagestring($img, 5, $x, random_int(14, 26), $ch, $ink);
            $x += random_int(28, 34);
        }
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
