<?php

namespace App\Libraries;

use App\Models\AppSettingModel;

/**
 * Cliente de IA de la plataforma (Claude / Gemini / OpenAI). Lee el proveedor y
 * las claves desde `app_settings` (cifradas en reposo) con fallback a `.env`
 * (`ANTHROPIC_API_KEY` / `GEMINI_API_KEY` / `OPENAI_API_KEY`). Las claves NUNCA
 * se exponen al navegador: solo el servidor llama a las APIs vía CURLRequest.
 *
 * Lo usan tanto el "Probar conexión" de Configuración → IA como los endpoints
 * de IA de los componentes (p. ej. el borrador de respuesta de PQRSF).
 */
class AiClient
{
    /** Proveedores admitidos: [código => [etiqueta, modelo por defecto]] */
    public const PROVIDERS = [
        'anthropic' => ['Anthropic Claude', 'claude-sonnet-5'],
        'gemini'    => ['Google Gemini', 'gemini-2.0-flash'],
        'openai'    => ['OpenAI', 'gpt-4o-mini'],
    ];

    /** Versión de la API de Anthropic; va en cabecera y es obligatoria. */
    private const ANTHROPIC_VERSION = '2023-06-01';

    private AppSettingModel $settings;

    public function __construct()
    {
        $this->settings = new AppSettingModel();
    }

    public function provider(): string
    {
        $p = $this->settings->getSetting('ai_provider');

        return array_key_exists((string) $p, self::PROVIDERS) ? (string) $p : 'gemini';
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider()][0];
    }

    /** Modelo configurado para el proveedor, o el de por defecto. */
    private function model(string $provider): string
    {
        return (string) ($this->settings->getSetting($provider . '_model') ?: self::PROVIDERS[$provider][1]);
    }

    private function apiKey(string $provider): ?string
    {
        if ($provider === 'anthropic') {
            return $this->settings->getSetting('anthropic_api_key') ?: (env('ANTHROPIC_API_KEY') ?: null);
        }
        if ($provider === 'openai') {
            return $this->settings->getSetting('openai_api_key') ?: (env('OPENAI_API_KEY') ?: null);
        }

        return $this->settings->getSetting('gemini_api_key') ?: (env('GEMINI_API_KEY') ?: null);
    }

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey($this->provider());
    }

    /**
     * Genera texto a partir de un prompt. Lanza RuntimeException con mensaje en
     * español si falta la clave o la API responde error.
     */
    public function generate(string $prompt, float $temperature = 0.7): string
    {
        $provider = $this->provider();
        $key      = $this->apiKey($provider);
        if (!$key) {
            throw new \RuntimeException('Falta la clave de API de ' . $this->providerLabel() . '. Configúrala en Configuración → IA.');
        }

        $client = \Config\Services::curlrequest(['timeout' => 60, 'http_errors' => false]);
        $model  = $this->model($provider);

        if ($provider === 'anthropic') {
            // La API de Mensajes exige `max_tokens` y la cabecera de versión; la
            // clave va en `x-api-key`, no en Authorization.
            $resp = $client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key'         => $key,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'Content-Type'      => 'application/json',
                ],
                'body' => json_encode([
                    'model'       => $model,
                    'max_tokens'  => 4096,
                    'temperature' => $temperature,
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                ]),
            ]);
            $data = json_decode((string) $resp->getBody(), true);

            // El contenido es una lista de bloques; se concatenan los de texto.
            $text = '';

            foreach ($data['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= $block['text'] ?? '';
                }
            }

            if ($text === '') {
                throw new \RuntimeException($data['error']['message'] ?? 'Respuesta inválida de Claude.');
            }

            return trim($text);
        }

        if ($provider === 'openai') {
            $resp = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                'body'    => json_encode(['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => $temperature]),
            ]);
            $data = json_decode((string) $resp->getBody(), true);
            $text = $data['choices'][0]['message']['content'] ?? null;
            if ($text === null) {
                throw new \RuntimeException($data['error']['message'] ?? 'Respuesta inválida de OpenAI.');
            }

            return trim((string) $text);
        }

        // Gemini
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
        $resp = $client->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => $temperature]]),
        ]);
        $data = json_decode((string) $resp->getBody(), true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text === null) {
            throw new \RuntimeException($data['error']['message'] ?? 'Respuesta inválida de Gemini.');
        }

        return trim((string) $text);
    }

    /** Prueba la conexión con una generación mínima. */
    public function testConnection(): array
    {
        try {
            $this->generate('Responde únicamente con la palabra: OK', 0.0);

            return ['ok' => true, 'message' => 'Conexión con ' . $this->providerLabel() . ' correcta.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
