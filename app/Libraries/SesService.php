<?php

namespace App\Libraries;

use App\Models\AppSettingModel;
use Aws\Exception\AwsException;
use Aws\Ses\SesClient;

/**
 * Cliente de Amazon SES (API v1).
 *
 * Alcance actual: configuración + prueba de conexión (GetSendQuota).
 * El envío real de correos de la app sigue usando la configuración SMTP.
 */
class SesService
{
    protected array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings ?? (new AppSettingModel())->getAllSettings();
    }

    public function isEnabled(): bool
    {
        return ! empty($this->settings['ses_enabled']);
    }

    public function client(): SesClient
    {
        $region = trim((string) ($this->settings['ses_region'] ?? '')) ?: 'us-east-1';

        return new SesClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => (string) ($this->settings['ses_key'] ?? ''),
                'secret' => (string) ($this->settings['ses_secret'] ?? ''),
            ],
        ]);
    }

    /**
     * Envía un correo HTML por SES.
     *
     * @return array{ok: bool, message: string}
     */
    public function sendEmail(string $to, string $subject, string $htmlBody, ?string $fromEmail = null, ?string $fromName = null): array
    {
        $from = $fromEmail ?: (string) ($this->settings['ses_from_email'] ?? '');
        if ($from === '') {
            return ['ok' => false, 'message' => 'Falta el email remitente verificado de SES.'];
        }
        $source = $fromName ? sprintf('%s <%s>', $fromName, $from) : $from;

        $args = [
            'Source'      => $source,
            'Destination' => ['ToAddresses' => [$to]],
            'Message'     => [
                'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                'Body'    => ['Html' => ['Data' => $htmlBody, 'Charset' => 'UTF-8']],
            ],
        ];

        // El conjunto de configuración también aquí, y no solo en las campañas:
        // es lo que hace que SES publique los rebotes y las quejas en SNS, y un
        // correo de recuperación que rebota interesa tanto como un boletín. Si
        // el ajuste está vacío, el envío sale igual, sin conjunto.
        $set = trim((string) ($this->settings['ses_configuration_set'] ?? ''));
        if ($set !== '') {
            $args['ConfigurationSetName'] = $set;
        }

        try {
            $this->client()->sendEmail($args);

            return ['ok' => true, 'message' => 'Correo enviado por SES.'];
        } catch (AwsException $e) {
            $code = $e->getAwsErrorCode() ?: (string) $e->getStatusCode();

            return ['ok' => false, 'message' => 'Error de SES (' . $code . '): ' . $e->getAwsErrorMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Prueba la conexión consultando la cuota de envío.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (empty($this->settings['ses_key']) || empty($this->settings['ses_secret'])) {
            return ['ok' => false, 'message' => 'Faltan las credenciales de SES (Access Key / Secret).'];
        }
        if (empty($this->settings['ses_region'])) {
            return ['ok' => false, 'message' => 'Falta la región de SES (ej. us-east-1).'];
        }

        try {
            $result = $this->client()->getSendQuota();
            $max    = (int) $result->get('Max24HourSend');
            $sent   = (int) $result->get('SentLast24Hours');

            return [
                'ok'      => true,
                'message' => sprintf('Conexión correcta. Cuota 24h: %d, enviados: %d.', $max, $sent),
            ];
        } catch (AwsException $e) {
            $code = $e->getAwsErrorCode() ?: (string) $e->getStatusCode();

            return ['ok' => false, 'message' => 'Error de SES (' . $code . '): ' . $e->getAwsErrorMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
