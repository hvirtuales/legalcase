<?php

namespace App\Libraries;

use App\Models\AppSettingModel;
use Config\Services;

class EmailLibrary
{
    protected $email;
    protected $settingModel;

    public function __construct()
    {
        $this->email = Services::email();
        $this->settingModel = new AppSettingModel();
        $this->initialize();
    }

    /**
     * Initialize email with dynamic settings
     */
    public function initialize()
    {
        $settings = $this->settingModel->getAllSettings();

        // If SMTP host is set, use it
        if (!empty($settings['smtp_host'])) {
            $config = [
                'protocol' => 'smtp',
                'SMTPHost' => $settings['smtp_host'],
                'SMTPUser' => $settings['smtp_user'] ?? '',
                'SMTPPass' => $settings['smtp_pass'] ?? '',
                'SMTPPort' => (int) ($settings['smtp_port'] ?? 587),
                'SMTPCrypto' => $settings['smtp_crypto'] ?? 'tls',
                'mailType' => 'html',
                'charset' => 'utf-8',
                'newline' => "\r\n",
                'wordWrap' => true,
            ];

            $this->email->initialize($config);
        }

        // Set From address
        $fromEmail = $settings['smtp_from_email'] ?? 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'example.com');
        $fromName = $settings['app_name'] ?? 'App System';

        $this->email->setFrom($fromEmail, $fromName);
    }

    /**
     * Envía un correo HTML. Usa Amazon SES si está habilitado; si no, SMTP.
     * Si SES falla, hace fallback a SMTP.
     */
    public function send(string $toEmail, string $subject, string $htmlMessage): bool
    {
        $settings = $this->settingModel->getAllSettings();

        // Amazon SES (si está habilitado y con credenciales)
        if (!empty($settings['ses_enabled']) && !empty($settings['ses_key']) && !empty($settings['ses_secret'])) {
            $ses = new \App\Libraries\SesService($settings);
            $fromEmail = $settings['ses_from_email'] ?: ($settings['smtp_from_email'] ?? null);
            $result = $ses->sendEmail($toEmail, $subject, $htmlMessage, $fromEmail ?: null, $settings['app_name'] ?? null);

            if ($result['ok']) {
                return true;
            }
            log_message('error', 'SES falló, fallback a SMTP: ' . $result['message']);
        }

        // SMTP (CI4 Email)
        $this->email->setTo($toEmail);
        $this->email->setSubject($subject);
        $this->email->setMessage($htmlMessage);

        if ($this->email->send()) {
            return true;
        }

        log_message('error', 'Email send failed: ' . $this->email->printDebugger(['headers']));
        return false;
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset($toEmail, $token)
    {
        $resetLink = base_url("reset-password/$token");

        $subject = 'Recuperación de Contraseña - ' . ($this->settingModel->getAppName());

        $message = "
        <h2>Recuperación de Contraseña</h2>
        <p>Has solicitado restablecer tu contraseña.</p>
        <p>Haz clic en el siguiente enlace para crear una nueva contraseña:</p>
        <p><a href='$resetLink' style='background-color: #e10e30; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Restablecer Contraseña</a></p>
        <p><small>Si no solicitaste esto, puedes ignorar este correo. El enlace expira en 1 hora.</small></p>
        ";

        return $this->send($toEmail, $subject, $message);
    }
}
