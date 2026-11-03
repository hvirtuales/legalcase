<?php

namespace App\Controllers;

use App\Models\AppSettingModel;
use App\Models\UserModel;

class SettingsController extends BaseController
{
    protected $settingModel;
    protected $userModel;

    public function __construct()
    {
        $this->settingModel = new AppSettingModel();
        $this->userModel = new UserModel();
    }

    /**
     * Show settings form
     */
    public function index()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'settings.edit')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->update();
        }

        $data = [
            'settings' => $this->settingModel->getAllSettings(),
            'title' => lang('App.settings'),
        ];

        return view('settings/index', $data);
    }

    /**
     * Update settings
     */
    /**
     * Guarda un ajuste **solo si su campo venía en la petición**.
     *
     * `getPost('x')` devuelve null tanto si el usuario borró el contenido como si el
     * campo no se envió, y escribir esa diferencia por igual es lo que convertía
     * cualquier envío incompleto en un borrado masivo de la configuración.
     *
     * Aquí se mira si la clave EXISTE en el cuerpo: si existe y está vacía, el usuario
     * la vació a propósito y se respeta; si no existe, no se toca.
     */
    private function setIfSent(string $key, ?string $type = null, ?string $field = null): void
    {
        $field = $field ?? $key;
        $body  = (array) $this->request->getPost();
        if (!array_key_exists($field, $body)) {
            return;
        }

        $value = $this->request->getPost($field);
        if ($type === null) {
            $this->settingModel->setSetting($key, $value);

            return;
        }

        $this->settingModel->setSetting($key, $value, $type);
    }

    private function update()
    {
        $rules = [
            'app_name' => 'permit_empty|max_length[100]',
            'app_description' => 'permit_empty|max_length[500]',
            'login_background' => 'permit_empty|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Update text settings
        $this->setIfSent('app_name');
        $this->setIfSent('app_description');
        $this->setIfSent('login_background');

        // Save Support Contact
        $this->setIfSent('support_phone');
        $this->setIfSent('support_email');

        // Save SMTP Config
        $this->setIfSent('smtp_host');
        $this->setIfSent('smtp_port');
        $this->setIfSent('smtp_user');

        // Only update password if provided
        $smtpPass = $this->request->getPost('smtp_pass');
        if (!empty($smtpPass)) {
            $this->settingModel->setSetting('smtp_pass', $smtpPass);
        }

        $this->setIfSent('smtp_crypto');
        $this->setIfSent('smtp_from_email');

        // Handle app logo upload
        $appLogo = $this->request->getFile('app_logo');
        if ($appLogo && $appLogo->isValid() && !$appLogo->hasMoved()) {
            $newName = 'logo_' . time() . '.' . $appLogo->getExtension();
            $stored = $this->storeImage($appLogo, $newName, [300, 1000, 90]);
            $this->settingModel->setSetting('app_logo', $stored, 'image');
        }

        // Save background colors
        $this->setIfSent('bg_color_primary');
        $this->setIfSent('bg_color_secondary');

        // Save theme colors
        $themePrimary = $this->request->getPost('theme_color_primary');
        if ($themePrimary && preg_match('/^#[0-9A-F]{6}$/i', $themePrimary)) {
            $this->settingModel->setSetting('theme_color_primary', $themePrimary, 'color');
        }

        // Update Auth Settings
        // CRITICAL: Use 'public_registration_enabled' to match AppSettingModel.isPublicRegistrationEnabled()
        /*
         * Las casillas de acceso solo se tocan si ESA pestaña viajó. Un checkbox sin
         * marcar no se envía, así que sin este testigo un envío parcial las apagaría
         * todas —incluido el registro público— sin que nadie lo pidiera.
         */
        $body = (array) $this->request->getPost();
        if (array_key_exists('auth_max_attempts', $body)) {
        $publicRegEnabled = $this->request->getPost('auth_registration_enabled') ? '1' : '0';
        log_message('debug', 'Saving public_registration_enabled: ' . $publicRegEnabled);
        $this->settingModel->setSetting('public_registration_enabled', $publicRegEnabled, 'boolean');

        $this->settingModel->setSetting('auth_magic_link_enabled', $this->request->getPost('auth_magic_link_enabled') ? '1' : '0', 'boolean');
        $this->settingModel->setSetting('auth_oauth_enabled', $this->request->getPost('auth_oauth_enabled') ? '1' : '0', 'boolean');

        $maxAttempts = $this->request->getPost('auth_max_attempts');
        if (is_numeric($maxAttempts)) {
            $this->settingModel->setSetting('auth_max_attempts', $maxAttempts);
        }
        }

        // Handle login image upload
        $loginImage = $this->request->getFile('login_image');
        if ($loginImage && $loginImage->isValid() && !$loginImage->hasMoved()) {
            $newName = 'login_' . time() . '.' . $loginImage->getExtension();
            $stored = $this->storeImage($loginImage, $newName, [400, 400, 85]);
            $this->settingModel->setSetting('login_image', $stored, 'image');
        }

        // Handle background image upload
        $bgImage = $this->request->getFile('background_image');
        if ($bgImage && $bgImage->isValid() && !$bgImage->hasMoved()) {
            $newName = 'bg_' . time() . '.' . $bgImage->getExtension();
            $stored = $this->storeImage($bgImage, $newName, [1920, 1080, 85]);
            $this->settingModel->setSetting('background_image', $stored, 'image');
        }

        // Handle favicon upload
        $favicon = $this->request->getFile('favicon');
        if ($favicon && $favicon->isValid() && !$favicon->hasMoved()) {
            $newName = 'favicon.' . $favicon->getExtension();
            $stored = $this->storeImage($favicon, $newName, null);
            $this->settingModel->setSetting('favicon', $stored, 'image');
        }

        // === Almacenamiento de objetos (S3 / Cloudflare R2) ===
        $this->settingModel->setSetting('storage_driver', $this->request->getPost('storage_driver') ?: 'local');
        $this->settingModel->setSetting('storage_key', $this->request->getPost('storage_key'));
        $this->settingModel->setSetting('storage_bucket', $this->request->getPost('storage_bucket'));
        $this->settingModel->setSetting('storage_region', $this->request->getPost('storage_region'));
        $this->settingModel->setSetting('storage_endpoint', $this->request->getPost('storage_endpoint'));
        $this->settingModel->setSetting('storage_public_url', $this->request->getPost('storage_public_url'));
        // El secreto solo se actualiza si se envía uno nuevo (se conserva en caso contrario)
        $storageSecret = $this->request->getPost('storage_secret');
        if (!empty($storageSecret)) {
            $this->settingModel->setSetting('storage_secret', $storageSecret);
        }

        // === WhatsApp (Meta Cloud API) ===
        $this->settingModel->setSetting('whatsapp_enabled', $this->request->getPost('whatsapp_enabled') ? '1' : '0', 'boolean');
        $this->settingModel->setSetting('whatsapp_phone_number_id', $this->request->getPost('whatsapp_phone_number_id'));
        $this->settingModel->setSetting('whatsapp_business_id', $this->request->getPost('whatsapp_business_id'));
        $this->settingModel->setSetting('whatsapp_api_version', $this->request->getPost('whatsapp_api_version') ?: 'v21.0');
        $whatsappToken = $this->request->getPost('whatsapp_token');
        if (!empty($whatsappToken)) {
            $this->settingModel->setSetting('whatsapp_token', $whatsappToken);
        }

        // === Amazon SES ===
        $this->settingModel->setSetting('ses_enabled', $this->request->getPost('ses_enabled') ? '1' : '0', 'boolean');
        $this->settingModel->setSetting('ses_key', $this->request->getPost('ses_key'));
        $this->settingModel->setSetting('ses_region', $this->request->getPost('ses_region'));
        $this->settingModel->setSetting('ses_from_email', $this->request->getPost('ses_from_email'));
        $this->settingModel->setSetting('ses_configuration_set', $this->request->getPost('ses_configuration_set'));
        $sesSecret = $this->request->getPost('ses_secret');
        if (!empty($sesSecret)) {
            $this->settingModel->setSetting('ses_secret', $sesSecret);
        }

        // === Motor de IA (Claude / Gemini / OpenAI) ===
        $provider = (string) $this->request->getPost('ai_provider');
        $this->settingModel->setSetting(
            'ai_provider',
            array_key_exists($provider, \App\Libraries\AiClient::PROVIDERS) ? $provider : 'gemini'
        );

        foreach (\App\Libraries\AiClient::PROVIDERS as $code => [, $defaultModel]) {
            $this->settingModel->setSetting($code . '_model', $this->request->getPost($code . '_model') ?: $defaultModel);

            // Los secretos solo se actualizan si se envía uno nuevo (si no, se conservan)
            $key = $this->request->getPost($code . '_api_key');
            if (!empty($key)) {
                $this->settingModel->setSetting($code . '_api_key', $key);
            }
        }

        log_message('debug', 'Settings saved successfully');
        /*
         * Lo que los componentes hayan puesto en sus ranuras de esta pantalla.
         *
         * Va al FINAL, cuando lo del núcleo ya está guardado, y cada handler corre en
         * su propio try dentro de `runSettingsHandlers()`: un componente que falle al
         * guardar lo suyo no puede tumbar el guardado de la Configuración del sistema.
         */
        $avisos = (new \App\Libraries\ComponentManager())->runSettingsHandlers('general', $this->request);

        return redirect()->to('/settings')->with('success',
            trim(lang('App.settingsUpdated') . ($avisos ? ' ' . implode(' ', $avisos) : '')));
    }

    /**
     * Probar conexión de una integración (S3/R2, WhatsApp, SES).
     * Usa la configuración YA GUARDADA. Responde JSON.
     */
    public function testConnection(string $service)
    {
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'settings.edit')) {
            return $this->response->setStatusCode(403)
                ->setJSON(['ok' => false, 'message' => lang('Auth.insufficientPermissions')]);
        }

        try {
            $result = match ($service) {
                'storage'  => (new \App\Libraries\StorageService())->testConnection(),
                'ses'      => (new \App\Libraries\SesService())->testConnection(),
                'whatsapp' => (new \App\Libraries\WhatsAppService())->testConnection(),
                'ai'       => (new \App\Libraries\AiClient())->testConnection(),
                default    => ['ok' => false, 'message' => 'Servicio desconocido.'],
            };
        } catch (\Throwable $e) {
            log_message('error', 'testConnection[' . $service . '] error: ' . $e->getMessage());
            $result = ['ok' => false, 'message' => 'Error interno: ' . $e->getMessage()];
        }

        // Token CSRF fresco (por si se activa la protección con regeneración)
        $result['csrf'] = csrf_hash();

        return $this->response->setJSON($result);
    }

    /**
     * Guarda una imagen subida: la mueve a public/uploads/settings, la
     * redimensiona si corresponde y —si hay un proveedor S3/R2 configurado—
     * la sube al bucket y devuelve su URL pública (con fallback local).
     *
     * @param \CodeIgniter\HTTP\Files\UploadedFile $file
     * @param array|null $resize [maxWidth, maxHeight, quality] o null para no redimensionar
     * @return string Valor a persistir (ruta local "/uploads/..." o URL remota)
     */
    private function storeImage($file, string $newName, ?array $resize = null): string
    {
        $uploadPath = WRITEPATH . '../public/uploads/settings/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $mime = $file->getMimeType();
        $file->move($uploadPath, $newName);
        $localPath = $uploadPath . $newName;

        if ($resize !== null) {
            helper('image');
            resize_image($localPath, $localPath, $resize[0], $resize[1], $resize[2]);
        }

        $storage = new \App\Libraries\StorageService();
        if ($storage->isRemote()) {
            try {
                $url = $storage->upload($localPath, 'settings/' . $newName, $mime ?: null);
                @unlink($localPath); // ya está en el bucket
                return $url;
            } catch (\Throwable $e) {
                log_message('error', 'Storage upload falló, se usa copia local: ' . $e->getMessage());
            }
        }

        return '/uploads/settings/' . $newName;
    }

    /**
     * Upload login image
     */
    public function uploadLoginImage()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'settings.edit')) {
            return $this->response->setJSON(['success' => false, 'message' => lang('Auth.insufficientPermissions')]);
        }

        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.invalidFile')]);
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.invalidFileType')]);
        }

        $newName = $file->getRandomName();
        $path = $this->storeImage($file, $newName, null);
        $this->settingModel->setSetting('login_image', $path, 'image');

        return $this->response->setJSON(['success' => true, 'path' => $path]);
    }

    /**
     * Upload favicon
     */
    public function uploadFavicon()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'settings.edit')) {
            return $this->response->setJSON(['success' => false, 'message' => lang('Auth.insufficientPermissions')]);
        }

        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.invalidFile')]);
        }

        // Validate file type
        if ($file->getExtension() !== 'ico') {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.invalidFileType')]);
        }

        $newName = 'favicon.ico';
        $path = $this->storeImage($file, $newName, null);
        $this->settingModel->setSetting('favicon', $path, 'image');

        return $this->response->setJSON(['success' => true, 'path' => $path]);
    }
}
