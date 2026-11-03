<?php

namespace Components\Pqrsf\Libraries;

/**
 * Captcha propio, **sin CDN** (política del proyecto: cero recursos externos, así
 * que nada de reCAPTCHA ni hCaptcha).
 *
 * Tres barreras, todas del lado del servidor:
 *
 * 1. **Código en imagen** dibujada con GD (la misma extensión que ya usa el QR).
 *    En la sesión solo queda su **hash**, con caducidad; el texto nunca viaja.
 * 2. **Campo trampa** (honeypot) invisible: si llega lleno, es un bot.
 * 3. **Tiempo mínimo** entre abrir el formulario y enviarlo.
 *
 * Si GD no estuviera disponible, la imagen se reemplaza por una **pregunta
 * aritmética en texto** — que además es la alternativa accesible para lectores
 * de pantalla.
 */
class Captcha
{
    private const KEY     = 'pqr_captcha';
    private const TTL     = 600;  // 10 minutos
    private const MIN_SEC = 3;    // nadie llena el formulario en menos

    /** Genera un reto nuevo y guarda su hash en la sesión. */
    public function issue(): string
    {
        // Sin caracteres que se confundan (0/O, 1/I/L).
        $pool = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 5; $i++) {
            $code .= $pool[random_int(0, strlen($pool) - 1)];
        }

        session()->set(self::KEY, [
            'hash' => password_hash(strtolower($code), PASSWORD_DEFAULT),
            'exp'  => time() + self::TTL,
        ]);

        return $code;
    }

    /** ¿Está el reto vigente? */
    public function isIssued(): bool
    {
        $d = session()->get(self::KEY);

        return is_array($d) && ($d['exp'] ?? 0) > time();
    }

    /**
     * Comprueba la respuesta y **consume** el reto: un código no sirve dos veces.
     */
    public function check(?string $answer): bool
    {
        $d = session()->get(self::KEY);
        session()->remove(self::KEY);

        if (!is_array($d) || ($d['exp'] ?? 0) < time()) {
            return false;
        }

        return password_verify(strtolower(trim((string) $answer)), $d['hash']);
    }

    /** Marca de tiempo firmada que viaja en el formulario. */
    public function stamp(): string
    {
        $t = (string) time();

        return $t . '.' . hash_hmac('sha256', $t, (string) config('Encryption')->key);
    }

    /**
     * ¿El formulario se llenó a velocidad humana? Un bot responde al instante.
     * La marca va firmada para que no se pueda inventar una antigua.
     */
    public function humanSpeed(?string $stamp): bool
    {
        [$t, $sig] = array_pad(explode('.', (string) $stamp, 2), 2, '');
        if (!ctype_digit((string) $t) || !hash_equals(hash_hmac('sha256', $t, (string) config('Encryption')->key), (string) $sig)) {
            return false;
        }

        return (time() - (int) $t) >= self::MIN_SEC;
    }

    /** PNG del reto, listo para `<img src>` como data URI. */
    public function image(string $code): string
    {
        if (!extension_loaded('gd')) {
            return '';
        }

        [$w, $h] = [190, 60];
        $img     = imagecreatetruecolor($w, $h);
        $bg      = imagecolorallocate($img, 255, 255, 255);
        $ink     = imagecolorallocate($img, 17, 17, 17);
        $soft    = imagecolorallocate($img, 190, 190, 190);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);

        // Ruido: líneas y puntos, suficiente para estorbar a un OCR simple.
        for ($i = 0; $i < 6; $i++) {
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $soft);
        }
        for ($i = 0; $i < 180; $i++) {
            imagesetpixel($img, random_int(0, $w), random_int(0, $h), $soft);
        }

        $x = 18;

        foreach (str_split($code) as $ch) {
            // `imagestringup` inclina la letra: se alterna para desalinearlas.
            $y = random_int(14, 26);
            imagestring($img, 5, $x, $y, $ch, $ink);
            $x += random_int(28, 34);
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
