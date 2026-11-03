<?php

namespace Components\LeadFlow\Libraries\Directory;

use Components\LeadFlow\Libraries\Captcha;
use Components\LeadFlow\Models\ComplaintModel;

/**
 * El canal de denuncia: recoge, frena el abuso y entrega a la plataforma.
 *
 * **Es público a propósito.** Una opinión solo la deja un cliente convertido por
 * invitación, pero una denuncia tiene que poder escribirla cualquiera: quien detecta
 * una suplantación o una credencial falsa casi nunca es cliente de quien la comete, y
 * un canal que exigiera serlo no serviría para nada.
 *
 * Lo que lo hace seguro no es restringir quién escribe, sino **que no se publica**: va
 * a una bandeja que solo ve la plataforma, no aparece en ninguna ficha y no toca
 * ninguna media. Es un canal de aviso, no un tablón.
 *
 * Las defensas son las mismas que el resto del directorio —trampa oculta, sello de
 * tiempo firmado, captcha, freno por IP— más un tope de **una denuncia por huella y
 * firma**, que cuenta también las descartadas: si solo contara las pendientes, quien
 * viera la suya descartada podría repetirla sin fin y la bandeja sería el ataque.
 */
class ComplaintService
{
    /** @return array{ok:bool,message:string,id:int} */
    public function submit(array $company, array $in, ?array $profile = null): array
    {
        $cid = (int) $company['id'];
        $ip  = mb_substr((string) ($in['ip'] ?? ''), 0, 45);

        // 1. Freno por IP: la IP va hasheada en la clave porque el «::1» de IPv6 lleva
        //    caracteres reservados.
        if (! service('throttler')->check('lfl_rep_' . md5($ip . '|' . ($company['slug'] ?? $cid)), 3, MINUTE)) {
            return self::fail(lang('Lfl.pubTooMany'));
        }
        // 2. Trampa: un humano no llena un campo que no ve.
        if (trim((string) ($in['website'] ?? '')) !== '') {
            return self::fail(lang('Lfl.pubCaptchaError'));
        }
        // 3. Velocidad humana y 4. captcha, si la instalación tiene GD.
        $captcha = new Captcha();
        if (! $captcha->humanSpeed((string) ($in['stamp'] ?? ''))) {
            return self::fail(lang('Lfl.pubTooFast'));
        }
        if (extension_loaded('gd') && ! $captcha->check((string) ($in['captcha'] ?? ''))) {
            return self::fail(lang('Lfl.pubCaptchaError'));
        }

        $motivo = trim(strip_tags((string) ($in['reason'] ?? '')));
        if (mb_strlen($motivo) < ComplaintModel::MIN_REASON) {
            return self::fail(lang('Lfl.repVReason', [ComplaintModel::MIN_REASON]));
        }

        // 5. Declaración de veracidad. No es un trámite: es lo que convierte el envío
        //    en una afirmación de la que alguien responde, y lo que permite descartar
        //    sin más una denuncia que resulte falsa.
        if (empty($in['truthful'])) {
            return self::fail(lang('Lfl.repVTruth'));
        }

        $model  = new ComplaintModel();
        $ipHash = ComplaintModel::hashIp($ip);
        if ($model->already($cid, $ipHash)) {
            // Se responde como si hubiera entrado: decirle «ya denunciaste» a quien usa
            // una IP compartida —una oficina, un café— confirma lo que hizo otra
            // persona desde esa misma red.
            return self::ok(lang('Lfl.repDone'), 0);
        }

        $ok = $model->insert([
            'company_id' => $cid,
            'profile_id' => $profile ? (int) $profile['id'] : null,
            'category'   => ComplaintModel::categoryOf($in['category'] ?? null),
            'reason'     => mb_substr($motivo, 0, ComplaintModel::MAX_REASON),
            'contact'    => mb_substr(trim(strip_tags((string) ($in['contact'] ?? ''))), 0, 150) ?: null,
            'status'     => ComplaintModel::PENDING,
            'ip_hash'    => $ipHash,
        ]);

        if (! $ok) {
            return self::fail(implode(' ', $model->errors() ?: [lang('Lfl.errInvalid')]));
        }

        return self::ok(lang('Lfl.repDone'), (int) $model->getInsertID());
    }

    /**
     * La plataforma cierra la denuncia.
     *
     * Descartar **exige nota**: un descarte sin explicación es indistinguible de un
     * olvido, y aquí hay que poder decir qué se decidió y por qué.
     *
     * @return array{ok:bool,message:string,id:int}
     */
    public function handle(array $complaint, bool $handled, ?int $userId, string $note = ''): array
    {
        if (($complaint['status'] ?? '') !== ComplaintModel::PENDING) {
            return self::fail(lang('Lfl.repAlreadyClosed'));
        }

        $nota = mb_substr(trim(strip_tags($note)), 0, 255);
        if (! $handled && $nota === '') {
            return self::fail(lang('Lfl.repVNote'));
        }

        (new ComplaintModel())->update((int) $complaint['id'], [
            'status'       => $handled ? ComplaintModel::HANDLED : ComplaintModel::DISMISSED,
            'handled_at'   => date('Y-m-d H:i:s'),
            'handled_by'   => $userId,
            'handled_note' => $nota ?: null,
        ]);

        return self::ok(lang($handled ? 'Lfl.repHandled' : 'Lfl.repDismissed'), (int) $complaint['id']);
    }

    /** @return array{ok:bool,message:string,id:int} */
    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0];
    }

    /** @return array{ok:bool,message:string,id:int} */
    private static function ok(string $message, int $id): array
    {
        return ['ok' => true, 'message' => $message, 'id' => $id];
    }
}
