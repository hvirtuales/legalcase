<?php

namespace App\Libraries;

use App\Models\AppSettingModel;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;

/**
 * Cliente de almacenamiento compatible con S3 (Amazon S3 y Cloudflare R2).
 *
 * Lee la configuración desde app_settings. Por ahora solo expone la
 * construcción del cliente y una prueba de conexión; el flujo de subidas
 * de la app aún no se enruta aquí (alcance: configuración + prueba).
 */
class StorageService
{
    protected array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings ?? (new AppSettingModel())->getAllSettings();
    }

    /** ¿Hay un proveedor remoto (no local) configurado? */
    public function isRemote(): bool
    {
        return in_array($this->settings['storage_driver'] ?? 'local', ['s3', 'r2', 's3compatible'], true);
    }

    /**
     * Construye el S3Client según el proveedor configurado.
     * Para R2/compatibles usa el endpoint y path-style.
     */
    public function client(): S3Client
    {
        $driver = $this->settings['storage_driver'] ?? 'local';
        $region = trim((string) ($this->settings['storage_region'] ?? ''));
        $endpoint = trim((string) ($this->settings['storage_endpoint'] ?? ''));

        // R2 no usa regiones reales: 'auto'
        if ($driver === 'r2' && $region === '') {
            $region = 'auto';
        }
        if ($region === '') {
            $region = 'us-east-1';
        }

        $config = [
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => (string) ($this->settings['storage_key'] ?? ''),
                'secret' => (string) ($this->settings['storage_secret'] ?? ''),
            ],
        ];

        if ($endpoint !== '') {
            $config['endpoint']                = $endpoint;
            $config['use_path_style_endpoint'] = true;
        }

        return new S3Client($config);
    }

    public function bucket(): string
    {
        return (string) ($this->settings['storage_bucket'] ?? '');
    }

    /**
     * Sube un archivo local al bucket y devuelve su URL pública.
     *
     * @param string $localFilePath Ruta absoluta del archivo en disco.
     * @param string $key           Clave/objeto destino (ej. "settings/logo_123.png").
     */
    public function upload(string $localFilePath, string $key, ?string $contentType = null): string
    {
        $key = ltrim($key, '/');

        $args = [
            'Bucket'     => $this->bucket(),
            'Key'        => $key,
            'SourceFile' => $localFilePath,
        ];
        if ($contentType !== null) {
            $args['ContentType'] = $contentType;
        }
        // S3 usa ACL public-read para servir directo; R2 lo ignora (acceso vía dominio público).
        if (($this->settings['storage_driver'] ?? '') === 's3') {
            $args['ACL'] = 'public-read';
        }

        $this->client()->putObject($args);

        return $this->publicUrl($key);
    }

    /**
     * URL pública de un objeto. Prioriza la URL/CDN configurada; si no,
     * usa la URL nativa del proveedor.
     */
    public function publicUrl(string $key): string
    {
        $key    = ltrim($key, '/');
        $public = trim((string) ($this->settings['storage_public_url'] ?? ''));

        if ($public !== '') {
            return rtrim($public, '/') . '/' . $key;
        }

        // Sin URL pública: intenta la URL nativa (válida para S3 con objeto público).
        try {
            return $this->client()->getObjectUrl($this->bucket(), $key);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Elimina un objeto del bucket (best-effort).
     */
    public function delete(string $key): bool
    {
        try {
            $this->client()->deleteObject(['Bucket' => $this->bucket(), 'Key' => ltrim($key, '/')]);

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'StorageService::delete error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Prueba la conexión validando acceso al bucket.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isRemote()) {
            return ['ok' => false, 'message' => 'El proveedor está en "Local". Selecciona S3 o R2 y guarda.'];
        }

        $bucket = $this->bucket();
        if ($bucket === '' || empty($this->settings['storage_key']) || empty($this->settings['storage_secret'])) {
            return ['ok' => false, 'message' => 'Faltan credenciales o el nombre del bucket.'];
        }

        try {
            $this->client()->headBucket(['Bucket' => $bucket]);

            return ['ok' => true, 'message' => 'Conexión correcta. Acceso al bucket "' . $bucket . '" verificado.'];
        } catch (AwsException $e) {
            $code = $e->getAwsErrorCode() ?: (string) $e->getStatusCode();

            return ['ok' => false, 'message' => 'Error de S3 (' . $code . '): ' . $e->getAwsErrorMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
