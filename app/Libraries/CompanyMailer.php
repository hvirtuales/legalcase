<?php

namespace App\Libraries;

use App\Models\CompanyChannelModel;

/**
 * Envío de correo por el **SMTP de una empresa** (capacidad de núcleo, la usan
 * los componentes). Usa la config de `company_channels` (secretos descifrados
 * por el modelo). Si la empresa no tiene SMTP configurado, hace **fallback** al
 * correo por defecto de la plataforma (`EmailLibrary`: SES/SMTP global).
 */
class CompanyMailer
{
    /** ¿La empresa tiene SMTP propio configurado? */
    public function hasCompanySmtp(int $companyId): bool
    {
        return !empty((new CompanyChannelModel())->getForCompany($companyId)['smtp_host']);
    }

    /**
     * @return array{ok:bool, message:string, via:string} via = company|platform
     */
    public function send(int $companyId, string $to, string $subject, string $html, ?string $replyTo = null): array
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Correo del destinatario inválido.', 'via' => 'none'];
        }

        $cfg = (new CompanyChannelModel())->getForCompany($companyId);

        // Sin SMTP de empresa → correo de la plataforma
        if (empty($cfg['smtp_host'])) {
            $ok = (new EmailLibrary())->send($to, $subject, $html);

            return ['ok' => $ok, 'message' => $ok ? 'Enviado con el correo de la plataforma (la empresa no tiene SMTP propio).' : 'No se pudo enviar por el correo de la plataforma.', 'via' => 'platform'];
        }

        try {
            $mail          = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host    = $cfg['smtp_host'];
            $mail->Port    = (int) ($cfg['smtp_port'] ?: 587);
            $mail->Timeout = 15;
            if (!empty($cfg['smtp_user'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $cfg['smtp_user'];
                $mail->Password = (string) ($cfg['smtp_pass'] ?? '');
            }
            $sec              = $cfg['smtp_security'] ?? 'starttls';
            $mail->SMTPSecure = $sec === 'ssl' ? 'ssl' : ($sec === 'starttls' ? 'tls' : '');
            if ($sec === 'none') {
                $mail->SMTPAutoTLS = false;
            }
            $from = $cfg['smtp_sender_email'] ?: ($cfg['smtp_user'] ?: 'noreply@localhost');
            $mail->setFrom($from, $cfg['smtp_sender_name'] ?: '');
            $mail->addAddress($to);
            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
            $mail->send();

            return ['ok' => true, 'message' => 'Correo enviado por el SMTP de la empresa.', 'via' => 'company'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Error del SMTP de la empresa: ' . $e->getMessage(), 'via' => 'company'];
        }
    }
}
