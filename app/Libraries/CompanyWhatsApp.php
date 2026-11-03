<?php

namespace App\Libraries;

use App\Models\CompanyChannelModel;

/**
 * Envío de WhatsApp por la cuenta de una empresa (capacidad de núcleo, la usan
 * los componentes). Usa la config de `company_channels` (Meta Cloud API:
 * `wa_phone_id` + `wa_token`, endpoint opcional). Secretos descifrados por el
 * modelo. NOTA: la Cloud API solo permite **texto libre dentro de la ventana de
 * 24 h** desde el último mensaje del cliente; fuera de ella se requiere una
 * **plantilla aprobada** (no cubierto aquí) — la API devolverá un error claro.
 */
class CompanyWhatsApp
{
    /** ¿La empresa tiene WhatsApp (Meta Cloud API) configurado? */
    public function isConfigured(int $companyId): bool
    {
        $cfg = (new CompanyChannelModel())->getForCompany($companyId);

        return !empty($cfg['wa_phone_id']) && !empty($cfg['wa_token']);
    }

    /**
     * Envía un mensaje de texto por WhatsApp.
     *
     * @return array{ok:bool, message:string}
     */
    public function sendText(int $companyId, string $toPhone, string $text): array
    {
        $cfg = (new CompanyChannelModel())->getForCompany($companyId);

        if (($cfg['wa_provider'] ?? 'meta') !== 'meta') {
            return ['ok' => false, 'message' => 'El envío server-side por WhatsApp solo está cableado para Meta Cloud API.'];
        }
        if (empty($cfg['wa_phone_id']) || empty($cfg['wa_token'])) {
            return ['ok' => false, 'message' => 'La empresa no tiene WhatsApp configurado (Phone Number ID + token).'];
        }
        $phone = preg_replace('/\D/', '', $toPhone);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Teléfono del destinatario inválido.'];
        }

        $base = rtrim((string) ($cfg['wa_endpoint'] ?: 'https://graph.facebook.com/v21.0'), '/');
        $url  = $base . '/' . rawurlencode($cfg['wa_phone_id']) . '/messages';

        try {
            $resp = \Config\Services::curlrequest(['timeout' => 20, 'http_errors' => false])->post($url, [
                'headers' => ['Authorization' => 'Bearer ' . $cfg['wa_token'], 'Content-Type' => 'application/json'],
                'body'    => json_encode([
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $phone,
                    'type'              => 'text',
                    'text'              => ['preview_url' => false, 'body' => $text],
                ]),
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'WhatsApp: ' . $e->getMessage()];
        }

        $code = $resp->getStatusCode();
        $data = json_decode((string) $resp->getBody(), true);
        if ($code >= 200 && $code < 300 && !empty($data['messages'])) {
            return ['ok' => true, 'message' => 'Mensaje de WhatsApp enviado.'];
        }

        return ['ok' => false, 'message' => 'WhatsApp: ' . ($data['error']['message'] ?? ('respuesta HTTP ' . $code))];
    }
}
