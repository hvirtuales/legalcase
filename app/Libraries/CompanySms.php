<?php

namespace App\Libraries;

use App\Models\CompanyChannelModel;

/**
 * Envío de SMS por la cuenta de una empresa (capacidad de núcleo, la usan los
 * componentes). Usa la config de `company_channels`. Cableado para **Twilio**
 * (Account SID + Auth Token + remitente) e **Infobip** (URL base en
 * `sms_endpoint` + API Key en `sms_auth_token` + remitente).
 */
class CompanySms
{
    /** ¿La empresa tiene SMS configurado? */
    public function isConfigured(int $companyId): bool
    {
        $cfg      = (new CompanyChannelModel())->getForCompany($companyId);
        $provider = $cfg['sms_provider'] ?? 'twilio';
        if ($provider === 'infobip') {
            return !empty($cfg['sms_endpoint']) && !empty($cfg['sms_auth_token']);
        }

        return !empty($cfg['sms_account_sid']) && !empty($cfg['sms_auth_token']);
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function send(int $companyId, string $toPhone, string $text): array
    {
        $cfg      = (new CompanyChannelModel())->getForCompany($companyId);
        $provider = $cfg['sms_provider'] ?? 'twilio';
        $digits   = preg_replace('/\D/', '', $toPhone);
        if ($digits === '') {
            return ['ok' => false, 'message' => 'Teléfono del destinatario inválido.'];
        }

        return match ($provider) {
            'twilio'  => $this->sendTwilio($cfg, $digits, $text),
            'infobip' => $this->sendInfobip($cfg, $digits, $text),
            default   => ['ok' => false, 'message' => 'El envío server-side por SMS está cableado para Twilio e Infobip.'],
        };
    }

    /** Twilio: Basic Auth (SID/token) + form params To/From/Body; teléfono en E.164 (+…). */
    private function sendTwilio(array $cfg, string $digits, string $text): array
    {
        if (empty($cfg['sms_account_sid']) || empty($cfg['sms_auth_token'])) {
            return ['ok' => false, 'message' => 'La empresa no tiene SMS configurado (Account SID + token).'];
        }
        $from = trim((string) ($cfg['sms_sender_id'] ?? ''));
        if ($from === '') {
            return ['ok' => false, 'message' => 'Falta el remitente (Sender ID / número Twilio).'];
        }
        $sid = (string) $cfg['sms_account_sid'];
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';

        try {
            $resp = \Config\Services::curlrequest(['timeout' => 20, 'http_errors' => false])->post($url, [
                'auth'    => [$sid, (string) $cfg['sms_auth_token']],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body'    => http_build_query(['To' => '+' . $digits, 'From' => $from, 'Body' => $text]),
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'SMS: ' . $e->getMessage()];
        }

        $code = $resp->getStatusCode();
        $data = json_decode((string) $resp->getBody(), true);
        if ($code >= 200 && $code < 300 && !empty($data['sid'])) {
            return ['ok' => true, 'message' => 'SMS enviado por Twilio.'];
        }

        return ['ok' => false, 'message' => 'SMS: ' . ($data['message'] ?? ('respuesta HTTP ' . $code))];
    }

    /** Infobip: API Key (`App {key}`) + JSON a {base}/sms/2/text/advanced; teléfono en formato internacional (sin +). */
    private function sendInfobip(array $cfg, string $digits, string $text): array
    {
        $base = trim((string) ($cfg['sms_endpoint'] ?? ''));
        if ($base === '') {
            return ['ok' => false, 'message' => 'Falta la URL base de Infobip (campo Endpoint).'];
        }
        if (empty($cfg['sms_auth_token'])) {
            return ['ok' => false, 'message' => 'Falta la API Key de Infobip (campo Auth Token).'];
        }
        if (!preg_match('#^https?://#i', $base)) {
            $base = 'https://' . $base;
        }
        $url  = rtrim($base, '/') . '/sms/2/text/advanced';
        $from = trim((string) ($cfg['sms_sender_id'] ?? '')) ?: 'InfoSMS';

        try {
            $resp = \Config\Services::curlrequest(['timeout' => 20, 'http_errors' => false])->post($url, [
                'headers' => ['Authorization' => 'App ' . $cfg['sms_auth_token'], 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body'    => json_encode(['messages' => [['from' => $from, 'destinations' => [['to' => $digits]], 'text' => $text]]]),
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'SMS (Infobip): ' . $e->getMessage()];
        }

        $code = $resp->getStatusCode();
        $data = json_decode((string) $resp->getBody(), true);
        if ($code >= 200 && $code < 300 && !empty($data['messages'][0]['messageId'])) {
            return ['ok' => true, 'message' => 'SMS enviado por Infobip.'];
        }

        $err = $data['requestError']['serviceException']['text']
            ?? ($data['messages'][0]['status']['description'] ?? ('respuesta HTTP ' . $code));

        return ['ok' => false, 'message' => 'SMS (Infobip): ' . $err];
    }
}
