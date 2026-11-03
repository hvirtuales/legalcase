<?php

namespace Components\Mailer\Libraries;

use App\Libraries\CompanyMailer;
use App\Models\CompanyModel;
use Components\Mailer\Models\ContactModel;
use Components\Mailer\Models\ListModel;

/**
 * El alta pública a una lista, con confirmación por correo.
 *
 * **Doble opt-in y no simple.** Un formulario abierto que da de alta sin
 * confirmar deja que cualquiera meta la dirección de otro. El registro de
 * consentimiento diría «formulario, esta IP, esta fecha» para alguien que no
 * pidió nada: una prueba **falsa**, que ante una revisión de AWS es peor que no
 * tener ninguna. La confirmación es lo que hace que ese registro signifique
 * algo, y de paso es lo que recomienda la propia documentación de SES.
 *
 * Toda la decisión vive aquí y no en el controlador: es la única forma de que
 * el formulario público y cualquier otra vía futura no acaben aplicando reglas
 * distintas.
 */
class SignupService
{
    /** Segundos mínimos entre pintar el formulario y enviarlo. */
    public const MIN_SECONDS = 3;

    /** Horas que vale el enlace de confirmación. */
    public const CONFIRM_HOURS = 72;

    private ListModel $lists;
    private ContactModel $contacts;
    private EmailValidator $validator;

    public function __construct()
    {
        $this->lists     = new ListModel();
        $this->contacts  = new ContactModel();
        $this->validator = new EmailValidator();
    }

    /** La lista de un token público, solo si el alta está abierta. */
    public function listByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $list = $this->lists->where('public_token', $token)->first();

        return ($list && !empty($list['public_enabled'])) ? $list : null;
    }

    /** Token público de la lista, creándolo la primera vez que se pide. */
    public function tokenFor(int $listId): string
    {
        $list = $this->lists->find($listId);
        if (!$list) {
            return '';
        }
        if (!empty($list['public_token'])) {
            return (string) $list['public_token'];
        }
        // 32 bytes: el mismo criterio que el resto de enlaces públicos de la
        // plataforma. No se puede adivinar ni recorrer.
        $token = bin2hex(random_bytes(32));
        $this->lists->update($listId, ['public_token' => $token]);

        return $token;
    }

    /**
     * Sello de tiempo firmado, para saber cuánto tardó en enviarse el
     * formulario. Va firmado porque si no, el que envía lo elige él.
     */
    public function stamp(): string
    {
        $t = (string) time();

        return $t . '.' . hash_hmac('sha256', $t, (string) config('Encryption')->key);
    }

    /** ¿El sello es nuestro y ha pasado el tiempo mínimo? */
    public function stampOk(string $value): bool
    {
        [$t, $mac] = array_pad(explode('.', trim($value), 2), 2, '');
        if ($t === '' || $mac === '' || !ctype_digit($t)) {
            return false;
        }
        if (!hash_equals(hash_hmac('sha256', $t, (string) config('Encryption')->key), $mac)) {
            return false;
        }
        $pasado = time() - (int) $t;

        // Ni demasiado rápido (un robot) ni de hace dos días (un formulario
        // reenviado desde una pestaña olvidada).
        return $pasado >= self::MIN_SECONDS && $pasado <= 86400;
    }

    /**
     * Da de alta a alguien, en estado **pendiente**, y le manda el correo de
     * confirmación. Solo cuando pulsa el enlace pasa a `subscribed`.
     *
     * Devuelve siempre el mismo mensaje tanto si la dirección era nueva como si
     * ya estaba: decir «esa dirección ya está en la lista» convierte el
     * formulario en un **detector de suscriptores** para cualquiera que pruebe
     * direcciones ajenas.
     *
     * @return array{ok:bool,error:string}
     */
    public function subscribe(array $list, string $email, string $name, string $entity, string $ip): array
    {
        $email = strtolower(trim($email));
        $check = $this->validator->check($email, true);
        if (!$check['ok']) {
            return ['ok' => false, 'error' => lang('Mailer.mBadEmailWhy', [$check['reason']])];
        }

        $companyId = (int) $list['company_id'];
        $listId    = (int) $list['id'];
        $ahora     = date('Y-m-d H:i:s');
        $token     = bin2hex(random_bytes(32));

        $consent = [
            'consent_source' => 'form',
            'consent_at'     => $ahora,
            'consent_ip'     => $ip,
            // Se guarda LO QUE ACEPTÓ, con las palabras que había en pantalla ese
            // día. Si mañana la empresa cambia el texto, la prueba de esta
            // persona sigue siendo la que ella leyó.
            'consent_note'   => mb_substr(trim((string) ($list['consent_text'] ?? '')) ?: lang('Mailer.signupDefaultConsent'), 0, 255),
        ];

        $ya = $this->contacts
            ->where('company_id', $companyId)
            ->where('list_id', $listId)
            ->where('email', $email)
            ->first();

        if ($ya) {
            // Ya confirmado: no se toca nada y se responde igual que a uno nuevo.
            if (($ya['status'] ?? '') === ContactModel::SENDABLE) {
                return ['ok' => true, 'error' => ''];
            }
            // Pendiente o dado de baja: se le vuelve a mandar el enlace. Volver a
            // pedirlo es un acto de consentimiento tan válido como el primero.
            $this->contacts->update($ya['id'], $consent + [
                'name'          => $name ?: $ya['name'],
                'entity'        => $entity ?: $ya['entity'],
                'status'        => 'pending',
                'confirm_token' => $token,
                'confirmed_at'  => null,
            ]);
        } else {
            $this->contacts->insert($consent + [
                'company_id'        => $companyId,
                'list_id'           => $listId,
                'email'             => $email,
                'name'              => $name ?: null,
                'entity'            => $entity ?: null,
                'status'            => 'pending',
                'confirm_token'     => $token,
                'unsubscribe_token' => bin2hex(random_bytes(16)),
                'subscribed_at'     => null,
            ]);
        }

        $this->sendConfirmation($companyId, $email, $name, $list, $token);

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Confirma el alta. Es la prueba fuerte del consentimiento: alguien con
     * acceso a ese buzón pulsó el enlace.
     *
     * @return array{ok:bool,email:string,company:array}
     */
    public function confirm(string $token): array
    {
        $token   = trim($token);
        $contact = $token !== '' ? $this->contacts->where('confirm_token', $token)->first() : null;
        if (!$contact) {
            return ['ok' => false, 'email' => '', 'company' => []];
        }

        $company = (new CompanyModel())->find((int) $contact['company_id']) ?? [];

        $estado = (string) ($contact['status'] ?? '');

        // Ya confirmado: se responde que sí, sin tocar nada. Pulsar el enlace dos
        // veces es lo más normal del mundo —se vuelve atrás, el cliente de correo
        // lo precarga— y no puede parecer un error después de haberse suscrito
        // bien. Por eso el token **no se gasta**.
        if ($estado === ContactModel::SENDABLE) {
            return ['ok' => true, 'email' => (string) $contact['email'], 'company' => $company];
        }

        // Y solo se confirma lo que está PENDIENTE. Si la persona se dio de baja
        // después, un enlace viejo reenviado no puede volver a suscribirla: una
        // confirmación no deshace una baja.
        if ($estado !== 'pending') {
            return ['ok' => false, 'email' => (string) $contact['email'], 'company' => $company];
        }

        $limite = strtotime((string) $contact['consent_at']) + self::CONFIRM_HOURS * 3600;
        if ($contact['consent_at'] && time() > $limite) {
            return ['ok' => false, 'email' => (string) $contact['email'], 'company' => $company];
        }

        $this->contacts->update($contact['id'], [
            'status'        => ContactModel::SENDABLE,
            'confirmed_at'  => date('Y-m-d H:i:s'),
            'subscribed_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'email' => (string) $contact['email'], 'company' => $company];
    }

    /**
     * El correo de confirmación, por el SMTP de la empresa con respaldo al de la
     * plataforma (`CompanyMailer`). NO va por SES a propósito: esto tiene que
     * salir aunque la cuenta de SES todavía no esté configurada, que es
     * justamente cuando se está montando la lista.
     */
    private function sendConfirmation(int $companyId, string $email, string $name, array $list, string $token): void
    {
        $url     = base_url('mailer-publico/confirmar/' . rawurlencode($token));
        $empresa = (new CompanyModel())->find($companyId)['name'] ?? '';

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.6">'
            . '<p>' . esc(lang('Mailer.confirmHi', [$name !== '' ? $name : $email])) . '</p>'
            . '<p>' . esc(lang('Mailer.confirmBody', [$empresa, $list['name']])) . '</p>'
            . '<p style="margin:24px 0"><a href="' . $url . '"'
            . ' style="display:inline-block;padding:12px 24px;background:#111827;color:#ffffff;'
            . 'text-decoration:none;border-radius:6px;font-weight:bold">'
            . esc(lang('Mailer.confirmButton')) . '</a></p>'
            . '<p style="font-size:12px;color:#6b7280">' . esc(lang('Mailer.confirmIgnore')) . '</p>'
            . '</div>';

        $res = (new CompanyMailer())->send($companyId, $email, lang('Mailer.confirmSubject', [$empresa]), $html);

        // Un fallo de correo NO deshace el alta pendiente: la persona ya dio su
        // consentimiento, y quien administra la lista puede reenviarle el enlace.
        if (empty($res['ok'])) {
            log_message('warning', 'Mailer: no se pudo enviar la confirmación a ' . $email);
        }
    }
}
