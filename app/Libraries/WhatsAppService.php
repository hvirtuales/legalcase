<?php

namespace App\Libraries;

use App\Models\AppSettingModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Cliente de WhatsApp Business — Meta Cloud API (Graph API).
 *
 * Alcance actual: configuración + prueba de conexión. Se incluye sendText()
 * para uso futuro de la app (notificaciones), pero no se invoca aún.
 */
class WhatsAppService
{
    protected array $settings;
    protected string $apiVersion;

    public function __construct(?array $settings = null)
    {
        $this->settings   = $settings ?? (new AppSettingModel())->getAllSettings();
        $this->apiVersion = trim((string) ($this->settings['whatsapp_api_version'] ?? '')) ?: 'v21.0';
    }

    public function isEnabled(): bool
    {
        return ! empty($this->settings['whatsapp_enabled']);
    }

    protected function client(): Client
    {
        return new Client([
            'base_uri' => 'https://graph.facebook.com/' . $this->apiVersion . '/',
            'timeout'  => 15,
            'headers'  => [
                'Authorization' => 'Bearer ' . (string) ($this->settings['whatsapp_token'] ?? ''),
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    /**
     * Prueba la conexión leyendo los datos del número (valida token + Phone Number ID).
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $phoneId = (string) ($this->settings['whatsapp_phone_number_id'] ?? '');
        if ($phoneId === '' || empty($this->settings['whatsapp_token'])) {
            return ['ok' => false, 'message' => 'Faltan el Phone Number ID o el token.'];
        }

        try {
            $response = $this->client()->get($phoneId, [
                'query' => ['fields' => 'verified_name,display_phone_number,quality_rating'],
            ]);
            $data = json_decode((string) $response->getBody(), true) ?: [];

            $name  = $data['verified_name'] ?? '—';
            $phone = $data['display_phone_number'] ?? '—';

            return [
                'ok'      => true,
                'message' => sprintf('Conexión correcta. Número: %s (%s).', $phone, $name),
            ];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            $msg  = 'Error de Meta';
            if ($body !== '') {
                $decoded = json_decode($body, true);
                $msg     = $decoded['error']['message'] ?? $body;
            }

            return ['ok' => false, 'message' => $msg];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Envía el enlace de recuperación de contraseña por WhatsApp.
     *
     * Nota: la Cloud API de Meta requiere plantilla aprobada fuera de la
     * ventana de 24h de atención al cliente; como texto libre funciona
     * dentro de esa ventana.
     *
     * @param string $to Número en formato E.164 sin '+', ej. 573001234567
     */
    public function sendPasswordReset(string $to, string $token): array
    {
        $link    = base_url('reset-password/' . $token);
        $appName = (string) (($this->settings['app_name'] ?? '') ?: 'la aplicación');

        $message = "🔐 Recuperación de contraseña - {$appName}\n\n"
            . "Solicitaste restablecer tu contraseña. Abre este enlace para crear una nueva (expira en 1 hora):\n"
            . "{$link}\n\n"
            . 'Si no fuiste tú, ignora este mensaje.';

        return $this->sendText($this->normalize($to), $message);
    }

    /** Deja solo dígitos en el número (Meta espera E.164 sin '+'). */
    private function normalize(string $to): string
    {
        return preg_replace('/\D+/', '', $to) ?? $to;
    }

    /**
     * Envía un mensaje de texto simple.
     *
     * @param string $to Número destino en formato E.164 sin '+', ej. 573001234567
     */
    public function sendText(string $to, string $message): array
    {
        $phoneId = (string) ($this->settings['whatsapp_phone_number_id'] ?? '');

        try {
            $response = $this->client()->post($phoneId . '/messages', [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ],
            ]);

            return ['ok' => true, 'message' => 'Mensaje enviado.', 'response' => json_decode((string) $response->getBody(), true)];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

            return ['ok' => false, 'message' => $body];
        }
    }
}
