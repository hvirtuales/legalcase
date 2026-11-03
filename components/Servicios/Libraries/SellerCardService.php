<?php

namespace Components\Servicios\Libraries;

use App\Models\CompanyModel;
use Components\Servicios\Models\SellerModel;

/**
 * Tarjeta digital pública del vendedor: resolución por token, colores de marca y
 * vCard.
 *
 * La tarjeta es **deliberadamente pública** (es lo que se comparte con el
 * cliente), pero se abre por **token aleatorio, no por id**: así el enlace no se
 * puede enumerar para sacar el maestro de vendedores de todas las empresas.
 * Solo responde si el vendedor está **activo** y su empresa también: dar de baja
 * a alguien apaga su tarjeta sin borrar nada.
 */
class SellerCardService
{
    private SellerModel $sellers;

    public function __construct()
    {
        $this->sellers = new SellerModel();
    }

    /** Token del vendedor; lo crea y persiste la primera vez que se necesita. */
    public function ensureToken(array $seller): string
    {
        $token = (string) ($seller['public_token'] ?? '');
        if ($token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(16));
        $this->sellers->skipValidation(true)->update((int) $seller['id'], ['public_token' => $token]);
        $this->sellers->skipValidation(false);

        return $token;
    }

    /** URL absoluta que se comparte. */
    public function publicUrl(array $seller): string
    {
        return site_url('servicios/tarjeta/' . $this->ensureToken($seller));
    }

    /**
     * Vendedor del token, o null. Devuelve también su empresa porque la tarjeta
     * no existe sin ella (marca, web y datos de contacto salen de `companies`).
     *
     * @return array{0:?array,1:?array} [vendedor, empresa]
     */
    public function resolve(string $token): array
    {
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
            return [null, null];
        }

        $seller = $this->sellers->where('public_token', $token)->first();
        if (!$seller || ($seller['status'] ?? '') !== 'activo') {
            return [null, null];
        }

        $company = (new CompanyModel())->find((int) $seller['company_id']);
        if (!$company || empty($company['is_active'])) {
            return [null, null];
        }

        return [$seller, $company];
    }

    /**
     * Paleta de la tarjeta a partir del color corporativo.
     *
     * El hex se valida y se emite **crudo**: `esc($hex, 'css')` lo convierte en
     * `\23 0e39e1` y `esc($hex, 'attr')` en `&#x23;0e39e1`, y ninguno de los dos
     * es un color válido (deja el fondo transparente).
     *
     * @return array{base:string,dark:string,ink:string}
     */
    public function palette(?string $brandColor): array
    {
        $hex = strtolower(trim((string) $brandColor));
        if (!preg_match('/^#[0-9a-f]{6}$/', $hex)) {
            $hex = '#1f2937'; // gris pizarra: neutro si la empresa no fijó color
        }

        [$r, $g, $b] = [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];

        // Luminancia relativa (mismo criterio que ThemeController): decide si el
        // texto sobre el color de marca va en blanco o en tinta oscura.
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        $dark = sprintf('#%02x%02x%02x', (int) ($r * 0.68), (int) ($g * 0.68), (int) ($b * 0.68));

        return [
            'base' => $hex,
            'dark' => $dark,
            'ink'  => $luminance > 0.6 ? '#1f2937' : '#ffffff',
        ];
    }

    /**
     * vCard 3.0 (la versión que más agendas leen sin quejarse) para el botón
     * «Guardar contacto». Texto plano: no depende de ninguna librería.
     */
    public function vcard(array $seller, array $company): string
    {
        $esc = static fn ($v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], trim((string) $v));

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . $esc($seller['name']),
            'FN:' . $esc($seller['name']),
            'ORG:' . $esc($company['name']),
        ];

        if (!empty($seller['position'])) {
            $lines[] = 'TITLE:' . $esc($seller['position']);
        }
        if (!empty($seller['email'])) {
            $lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . $esc($seller['email']);
        }
        if (!empty($seller['phone'])) {
            $lines[] = 'TEL;TYPE=CELL,WORK:' . $esc($seller['phone']);
        }
        if (!empty($company['contact_phone'])) {
            $lines[] = 'TEL;TYPE=WORK:' . $esc($company['contact_phone']);
        }
        if (!empty($company['website'])) {
            $lines[] = 'URL:' . $esc($company['website']);
        }
        if (!empty($company['address']) || !empty($company['city'])) {
            $lines[] = 'ADR;TYPE=WORK:;;' . $esc($company['address']) . ';' . $esc($company['city']) . ';;;';
        }
        $lines[] = 'NOTE:' . $esc($this->publicUrl($seller));
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines) . "\r\n";
    }

    /** Teléfono en el formato que pide wa.me: solo dígitos, sin `+`. */
    public function whatsappNumber(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }
}
