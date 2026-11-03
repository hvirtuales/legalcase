<?php

namespace App\Traits;

use App\Libraries\UploadService;
use App\Models\CompanyChannelModel;
use App\Models\CompanyModel;

/**
 * Editar **la ficha y los canales de la propia fila de `companies`**.
 *
 * Lo usan dos pantallas que hacen lo mismo con distinto alcance:
 *
 *   - «Mi empresa» (`MyCompanyController`, `/mi-empresa`) — el dueño o
 *     admin-miembro de la **empresa activa**.
 *   - «Mi grupo» (`TenantPanelController`, `/tenant/datos`) — el administrador
 *     del **grupo activo**, que es también una fila de `companies` con
 *     `type = 'tenant'`.
 *
 * Antes solo existía la primera, y un administrador de grupo no tenía **ninguna
 * forma** de tocar el nombre, el NIT, la marca ni los canales de su propio
 * grupo: el contexto de grupo limpia `active_company_id`, así que «Mi empresa»
 * lo rechazaba, y el panel global de Empresas es de plataforma (Super Admin).
 *
 * Va en un trait y no duplicado porque **son el mismo formulario**: la misma
 * vista, las mismas reglas y los mismos secretos cifrados. Lo único que cambia
 * es de qué fila se habla y a dónde se vuelve. Quien lo usa aporta:
 *
 *   - `manageableRow()`: la fila que se puede gestionar, o null.
 *   - `profileBase()`:   el prefijo de URL de sus pantallas (`mi-empresa`,
 *                        `tenant`), que también usan las vistas para sus
 *                        pestañas y su formulario.
 *
 * **No** permite tocar plan, dueño ni estado: eso es del panel global.
 */
trait ManagesOwnCompany
{
    /** Campos de la ficha que llegan del formulario, ya normalizados. */
    private function profileFields(): array
    {
        $r    = $this->request;
        $data = [
            'name'          => trim((string) $r->getPost('name')),
            'nit'           => trim((string) $r->getPost('nit')),
            'website'       => trim((string) $r->getPost('website')),
            'address'       => trim((string) $r->getPost('address')),
            'city'          => trim((string) $r->getPost('city')),
            'description'   => $r->getPost('description'),
            'brand_color'   => $r->getPost('brand_color') ?: null,
            'contact_email' => trim((string) $r->getPost('contact_email')),
            'contact_phone' => trim((string) $r->getPost('contact_phone')),
            'whatsapp'      => trim((string) $r->getPost('whatsapp')),
        ];

        // Sector y coordenadas, con las mismas reglas que el panel global.
        $data += CompanyModel::locationFields($r);

        // Redes sociales: se guardan ya normalizadas a URL absoluta (el
        // formulario acepta también solo el usuario).
        foreach (CompanyModel::SOCIAL_NETWORKS as $net) {
            $data[$net] = CompanyModel::socialUrl($net, $r->getPost($net));
        }

        // Marca. Solo se pisan si de verdad se subió un archivo: sin esto, un
        // guardado sin tocar la imagen borraría la que hay.
        $uploader = new UploadService();
        if ($logo = $uploader->storeImage($r->getFile('logo'), 'companies', 'company')) {
            $data['logo'] = $logo;
        }
        if ($banner = $uploader->storeImage($r->getFile('banner'), 'companies', 'banner')) {
            $data['banner'] = $banner;
        }

        return $data;
    }

    /**
     * Guarda la ficha. Devuelve los errores de validación, o [] si fue bien.
     *
     * La validación se hace **aquí y no en el modelo** por el gotcha de CI4 con
     * `is_unique[...,id,{id}]`: en `update()` el marcador `{id}` no se rellena,
     * el propio registro cuenta como duplicado y el guardado se pierde en
     * silencio. Por eso se salta la del modelo alrededor del `update()`.
     */
    private function saveProfile(int $companyId): array
    {
        if (!$this->validate([
            'name'          => 'required|max_length[150]',
            'contact_email' => 'permit_empty|valid_email',
        ])) {
            return $this->validator->getErrors();
        }

        $companies = new CompanyModel();
        $companies->skipValidation(true);
        $companies->update($companyId, $this->profileFields());
        $companies->skipValidation(false);

        return [];
    }

    // ---------------------------------------------------------------- canales

    /** La configuración de canales sin los secretos, y qué secretos hay puestos. */
    private function channelConfig(int $companyId): array
    {
        $cfg    = (new CompanyChannelModel())->getForCompany($companyId);
        $secret = [];

        foreach (CompanyChannelModel::SECRETS as $k) {
            $secret[$k] = !empty($cfg[$k]);
            unset($cfg[$k]);   // el valor del secreto NUNCA llega a la vista
        }

        return [$cfg, $secret];
    }

    private function saveChannelsFor(int $companyId): void
    {
        $r = $this->request;
        (new CompanyChannelModel())->saveForCompany($companyId, [
            'smtp_host'         => trim((string) $r->getPost('smtp_host')),
            'smtp_port'         => $r->getPost('smtp_port') !== '' ? (int) $r->getPost('smtp_port') : null,
            'smtp_user'         => trim((string) $r->getPost('smtp_user')),
            'smtp_pass'         => (string) $r->getPost('smtp_pass'),
            'smtp_sender_name'  => trim((string) $r->getPost('smtp_sender_name')),
            'smtp_sender_email' => trim((string) $r->getPost('smtp_sender_email')),
            'smtp_security'     => in_array($r->getPost('smtp_security'), ['ssl', 'starttls', 'none'], true) ? $r->getPost('smtp_security') : 'starttls',
            'wa_provider'       => in_array($r->getPost('wa_provider'), ['meta', 'twilio', 'other'], true) ? $r->getPost('wa_provider') : 'meta',
            'wa_endpoint'       => trim((string) $r->getPost('wa_endpoint')),
            'wa_phone_id'       => trim((string) $r->getPost('wa_phone_id')),
            'wa_token'          => (string) $r->getPost('wa_token'),
            'sms_provider'      => in_array($r->getPost('sms_provider'), ['twilio', 'infobip', 'other'], true) ? $r->getPost('sms_provider') : 'twilio',
            'sms_endpoint'      => trim((string) $r->getPost('sms_endpoint')),
            'sms_account_sid'   => trim((string) $r->getPost('sms_account_sid')),
            'sms_auth_token'    => (string) $r->getPost('sms_auth_token'),
            'sms_sender_id'     => trim((string) $r->getPost('sms_sender_id')),
        ]);
    }

    /** Prueba un canal de esa fila. Devuelve `['ok' => bool, 'message' => …]`. */
    private function probeChannel(int $companyId, ?string $service): array
    {
        $cfg = (new CompanyChannelModel())->getForCompany($companyId);

        try {
            return match ($service) {
                'smtp'     => $this->probeSmtp($cfg),
                'whatsapp' => (empty($cfg['wa_phone_id']) || empty($cfg['wa_token']))
                    ? ['ok' => false, 'message' => 'Faltan Phone Number ID y/o token de WhatsApp.']
                    : ['ok' => true, 'message' => 'WhatsApp configurado (' . esc($cfg['wa_provider'] ?? 'meta') . ').'],
                'sms'      => (new \App\Libraries\CompanySms())->isConfigured($companyId)
                    ? ['ok' => true, 'message' => 'SMS configurado (' . esc($cfg['sms_provider'] ?? 'twilio') . ').']
                    : ['ok' => false, 'message' => 'Faltan credenciales de SMS para ' . esc($cfg['sms_provider'] ?? 'twilio') . '.'],
                default    => ['ok' => false, 'message' => 'Servicio desconocido.'],
            };
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    private function probeSmtp(array $cfg): array
    {
        if (empty($cfg['smtp_host'])) {
            return ['ok' => false, 'message' => 'Falta el host SMTP.'];
        }
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host    = $cfg['smtp_host'];
        $mail->Port    = (int) ($cfg['smtp_port'] ?: 587);
        $mail->Timeout = 10;
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
        if ($mail->smtpConnect()) {
            $mail->smtpClose();

            return ['ok' => true, 'message' => 'Conexión SMTP correcta (' . esc($cfg['smtp_host']) . ').'];
        }

        return ['ok' => false, 'message' => 'No se pudo conectar al servidor SMTP.'];
    }
}
