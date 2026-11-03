<?php

namespace App\Models;

use CodeIgniter\Model;

class AppSettingModel extends Model
{
    protected $table = 'app_settings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'setting_key',
        'setting_value',
        'setting_type',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'setting_key' => 'required|is_unique[app_settings.setting_key,id,{id}]',
        'setting_value' => 'permit_empty',
        'setting_type' => 'required|in_list[text,image,boolean,color]',
    ];

    /** Claves cuyo valor se cifra en reposo. */
    private const SECRET_KEYS = ['smtp_pass', 'storage_secret', 'ses_secret', 'whatsapp_token', 'gemini_api_key', 'openai_api_key', 'anthropic_api_key'];

    /** Marcador de valor cifrado (permite convivir con texto plano heredado). */
    private const ENC_PREFIX = 'enc:v1:';

    private function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    private function encryptValue(string $value): string
    {
        if ($value === '' || strpos($value, self::ENC_PREFIX) === 0) {
            return $value; // vacío o ya cifrado
        }
        try {
            return self::ENC_PREFIX . base64_encode(service('encrypter')->encrypt($value));
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo cifrar setting: ' . $e->getMessage());

            return $value; // preferible guardar sin cifrar a perder el dato
        }
    }

    private function decryptValue(string $value): string
    {
        if (strpos($value, self::ENC_PREFIX) !== 0) {
            return $value; // texto plano heredado
        }
        try {
            return (string) service('encrypter')->decrypt(base64_decode(substr($value, strlen(self::ENC_PREFIX))));
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo descifrar setting: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Get a setting value by key
     */
    /**
     * Filas de `app_settings` cacheadas **por petición**: cada pantalla lee
     * varios ajustes (nombre, logo, favicon, colores…) y cada lectura era una
     * consulta. La tabla entera son unas decenas de filas: sale más barato
     * traerla una vez.
     *
     * @var array<string, array>|null
     */
    private static ?array $rowCache = null;

    public static function flushCache(): void
    {
        self::$rowCache = null;
    }

    /** Todas las filas indexadas por clave (una sola consulta por petición). */
    private function rows(): array
    {
        if (self::$rowCache === null) {
            self::$rowCache = [];
            foreach ($this->findAll() as $row) {
                self::$rowCache[$row['setting_key']] = $row;
            }
        }

        return self::$rowCache;
    }

    public function getSetting(string $key, $default = null)
    {
        $setting = $this->rows()[$key] ?? null;

        if (!$setting) {
            return $default;
        }

        // Secretos: descifrar y devolver como texto
        if ($this->isSecret($key)) {
            $val = $this->decryptValue((string) $setting['setting_value']);

            return $val === '' ? $default : $val;
        }

        // Parse boolean values
        if ($setting['setting_type'] === 'boolean') {
            return filter_var($setting['setting_value'], FILTER_VALIDATE_BOOLEAN);
        }

        // Parse JSON values
        if ($this->isJson($setting['setting_value'])) {
            return json_decode($setting['setting_value'], true);
        }

        return $setting['setting_value'] ?? $default;
    }

    /**
     * Set a setting value
     */
    public function setSetting(string $key, $value, string $type = 'text'): bool
    {
        $logValue = $this->isSecret($key) ? '***' : $value;
        log_message('debug', "AppSettingModel::setSetting called - key: {$key}, value: {$logValue}, type: {$type}");

        // Un ajuste sin valor se guarda como cadena vacía, nunca como NULL: la
        // tabla se lee en todas las pantallas y un NULL suelto rompía el parseo.
        $value ??= '';

        // Convert arrays/objects to JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        // Convert boolean to string
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
            $type = 'boolean';
        }

        // Cifrar secretos en reposo
        if ($this->isSecret($key) && is_string($value)) {
            $value = $this->encryptValue($value);
        }

        self::flushCache(); // lo que se guarda tiene que verse ya en esta misma petición

        $existing = $this->where('setting_key', $key)->first();
        log_message('debug', "Existing setting found: " . ($existing ? 'YES (id: ' . $existing['id'] . ')' : 'NO'));

        if ($existing) {
            $result = $this->update($existing['id'], [
                'setting_value' => $value,
                'setting_type' => $type,
            ]);
            log_message('debug', "Update result: " . ($result ? 'SUCCESS' : 'FAILED'));
            if (!$result) {
                log_message('error', "Update errors: " . json_encode($this->errors()));
            }
            return $result;
        }

        $insertResult = $this->insert([
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_type' => $type,
        ]);

        log_message('debug', "Insert result: " . ($insertResult !== false ? 'SUCCESS (id: ' . $insertResult . ')' : 'FAILED'));
        if ($insertResult === false) {
            log_message('error', "Insert errors: " . json_encode($this->errors()));
        }

        return $insertResult !== false;
    }

    /**
     * Get all settings as associative array
     */
    public function getAllSettings(): array
    {
        $settings = $this->findAll();
        $result = [];

        foreach ($settings as $setting) {
            $key = $setting['setting_key'];
            $value = $setting['setting_value'];

            // Secretos: descifrar
            if ($this->isSecret($key)) {
                $result[$key] = $this->decryptValue((string) $value);
                continue;
            }

            // Parse boolean
            if ($setting['setting_type'] === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            // Parse JSON
            elseif ($this->isJson($value)) {
                $value = json_decode($value, true);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Delete a setting
     */
    public function deleteSetting(string $key): bool
    {
        return $this->where('setting_key', $key)->delete();
    }

    /**
     * Check if string is valid JSON
     *
     * Acepta null: una fila con `setting_value` NULL es un ajuste sin configurar,
     * no un error. Con la firma estricta anterior, un solo NULL en la tabla
     * tumbaba con un 500 cualquier pantalla que leyera ese ajuste (incluido el
     * login, que lee `app_name`).
     */
    private function isJson(?string $string): bool
    {
        if ($string === null || $string === '') {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get app name
     */
    public function getAppName(): string
    {
        return $this->getSetting('app_name', 'Sistema de Autenticación');
    }

    /**
     * Get app description
     */
    public function getAppDescription(): string
    {
        return $this->getSetting('app_description', '');
    }

    /**
     * Get enabled authentication methods
     */
    public function getEnabledAuthMethods(): array
    {
        $methods = $this->getSetting('auth_methods_enabled', []);

        if (!is_array($methods)) {
            return [];
        }

        return array_filter($methods, function ($enabled) {
            return $enabled === true;
        });
    }

    /**
     * Check if public registration is enabled
     */
    public function isPublicRegistrationEnabled(): bool
    {
        // Check if there's a specific setting for public registration
        $publicRegistration = $this->getSetting('public_registration_enabled');

        // If explicitly set, use that value
        if ($publicRegistration !== null) {
            return (bool) $publicRegistration;
        }

        // Otherwise, check if password auth is enabled (implies registration is allowed)
        $authMethods = $this->getSetting('auth_methods_enabled', []);

        if (is_string($authMethods)) {
            $authMethods = json_decode($authMethods, true) ?? [];
        }

        // Default to true if password authentication is enabled
        return isset($authMethods['password']) && $authMethods['password'] === true;
    }
}
