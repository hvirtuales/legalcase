<?php

namespace Components\LegalCase\Libraries;

use App\Models\CompanyModel;
use Components\LegalCase\Models\ContactModel;
use Components\LegalCase\Models\ParticipantModel;
use Components\LegalCase\Models\PortalAccountModel;
use Components\LegalCase\Models\PortalTokenModel;
use Components\LegalCase\Models\SettingsModel;

/**
 * Identidad del portal del cliente.
 *
 * Cuentas PROPIAS del componente (no `users` del núcleo): un cliente nunca puede
 * heredar un rol global, el contexto de empresa ni un permiso `lgc.*`.
 *
 * Acceso por **enlace de un solo uso** enviado al correo de la cuenta:
 *  · El enlace es `selector.verificador`. En BD se guarda el selector y el SHA-256 del
 *    verificador; quien lea la tabla no puede entrar. La comparación es en tiempo
 *    constante (`hash_equals`).
 *  · Abrir el enlace (GET) muestra un botón; consumirlo es un POST con CSRF. Así los
 *    escáneres de correo que «abren» los enlaces no gastan el acceso.
 *  · Pedir un enlace responde lo mismo exista o no la cuenta (no enumera correos), y
 *    está limitado por IP y por correo.
 *  · La sesión del portal es una clave aparte (`lgc_portal`) con caducidad por
 *    inactividad, y se regenera el id de sesión al entrar.
 */
class Portal
{
    public const SESSION_KEY  = 'lgc_portal';
    public const LOGIN_TTL    = 900;      // 15 min
    public const INVITE_TTL   = 259200;   // 72 h
    public const IDLE_TIMEOUT = 3600;     // 1 h sin actividad

    public static function url(string $path = ''): string
    {
        return base_url('legalcase-portal' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    // ------------------------------------------------------------------ cuentas

    /**
     * Activa el acceso de una parte al portal para un asunto: crea la cuenta si no
     * existe (respetando el cupo del plan) y envía la invitación.
     */
    public function grant(array $matter, array $participant, Access $acc, ?callable $mailer = null): Result
    {
        if (!in_array($participant['role'], ParticipantModel::PORTAL_ROLES, true)) {
            return Result::fail(lang('Lgc.errPortalRole'));
        }
        $settings = (new SettingsModel())->forCompanyOrDefault($acc->companyId);
        if (!(int) $settings['portal_enabled']) {
            return Result::fail(lang('Lgc.errPortalDisabled'));
        }
        $contact = (new ContactModel())->own($acc->companyId, (int) $participant['contact_id']);
        $email   = strtolower(trim((string) ($contact['email'] ?? '')));
        if (!$contact || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Result::fail(lang('Lgc.errPortalEmail'));
        }

        $accounts = new PortalAccountModel();
        $account  = $accounts->where('company_id', $acc->companyId)->where('email', $email)->first();

        if ($account && (int) $account['contact_id'] !== (int) $contact['id']) {
            // El mismo correo ya es de OTRO contacto: darle acceso mezclaría dos personas.
            return Result::fail(lang('Lgc.errPortalEmailTaken'));
        }

        if (!$account) {
            $left = (new PlanGuard())->portalLeft($acc->companyId);
            if ($left !== null && $left < 1) {
                return Result::fail(lang('Lgc.errQuotaPortal'));
            }
            $accounts->insert([
                'company_id' => $acc->companyId, 'contact_id' => (int) $contact['id'], 'email' => $email,
                'locale' => in_array($contact['locale'] ?? 'es', ['es', 'en'], true) ? $contact['locale'] : 'es',
                'status' => 'active', 'notify_email' => 1, 'invited_by' => $acc->userId ?: null, 'invited_at' => date('Y-m-d H:i:s'),
            ]);
            $account = $accounts->find((int) $accounts->getInsertID());
        } elseif ($account['status'] !== 'active') {
            $accounts->update((int) $account['id'], ['status' => 'active']);
            $account['status'] = 'active';
        }

        (new ParticipantModel())->update((int) $participant['id'], ['portal_access' => 1]);
        Audit::log($acc->companyId, (int) $matter['id'], 'portal.granted', 'account', (int) $account['id'], ['role' => $participant['role']]);

        $sent = $this->sendLink($account, 'invite', $mailer);

        return Result::ok($sent ? lang('Lgc.okPortalInvited', [$email]) : lang('Lgc.okPortalGrantedNoMail', [$email]), (int) $account['id']);
    }

    public function revoke(array $matter, array $participant, Access $acc): Result
    {
        (new ParticipantModel())->update((int) $participant['id'], ['portal_access' => 0]);
        Audit::log($acc->companyId, (int) $matter['id'], 'portal.revoked', 'contact', (int) $participant['contact_id']);

        return Result::ok(lang('Lgc.okPortalRevoked'));
    }

    /** Bloquea la cuenta entera (todos los asuntos). */
    public function blockAccount(int $companyId, int $accountId, Access $acc): Result
    {
        $model   = new PortalAccountModel();
        $account = $model->where('company_id', $companyId)->find($accountId);
        if (!$account) {
            return Result::fail(lang('Lgc.errNotFound'));
        }
        $model->update($accountId, ['status' => 'revoked']);
        (new PortalTokenModel())->where('account_id', $accountId)->where('used_at', null)->set(['used_at' => date('Y-m-d H:i:s')])->update();
        Audit::log($companyId, null, 'portal.account_blocked', 'account', $accountId);

        return Result::ok(lang('Lgc.okPortalBlocked'));
    }

    // ------------------------------------------------------------------ enlaces

    /**
     * Pide un enlace de acceso. Responde siempre igual hacia fuera; por dentro solo
     * envía a cuentas activas de empresas con el portal y el servicio habilitados.
     */
    public function requestLink(string $email, ?callable $mailer = null): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        foreach ((new PortalAccountModel())->where('email', $email)->where('status', 'active')->findAll(20) as $account) {
            if (!$this->companyOpen((int) $account['company_id'])) {
                continue;
            }
            // Freno por cuenta: como máximo 5 enlaces por hora.
            $recent = (new PortalTokenModel())->where('account_id', (int) $account['id'])
                ->where('created_at >', date('Y-m-d H:i:s', time() - 3600))->countAllResults();
            if ($recent >= 5) {
                continue;
            }
            $this->sendLink($account, 'login', $mailer);
        }
    }

    /** Crea un token y envía el correo. Devuelve si el proveedor aceptó el envío. */
    private function sendLink(array $account, string $purpose, ?callable $mailer = null): bool
    {
        $token   = $this->issue($account, $purpose);
        $locale  = in_array($account['locale'] ?? 'es', ['es', 'en'], true) ? $account['locale'] : 'es';
        $company = (new CompanyModel())->find((int) $account['company_id']) ?? [];
        $subject = lang($purpose === 'invite' ? 'Lgc.mailInviteSubject' : 'Lgc.mailLoginSubject', [(string) ($company['name'] ?? '')], $locale);
        $html    = view('Components\LegalCase\Views\email\access', [
            'company' => $company,
            'locale'  => $locale,
            'purpose' => $purpose,
            'url'     => self::url('entrar/' . $token),
            'minutes' => (int) (($purpose === 'invite' ? self::INVITE_TTL : self::LOGIN_TTL) / 60),
        ]);

        try {
            $res = $mailer
                ? $mailer((int) $account['company_id'], (string) $account['email'], $subject, $html)
                : (new \App\Libraries\CompanyMailer())->send((int) $account['company_id'], (string) $account['email'], $subject, $html);
        } catch (\Throwable $e) {
            log_message('error', 'LegalCase portal: no se pudo enviar el enlace de acceso: ' . $e->getMessage());

            return false;
        }

        return !empty($res['ok']);
    }

    /** @return string el token completo `selector.verificador` (solo existe en el correo) */
    public function issue(array $account, string $purpose = 'login'): string
    {
        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));

        (new PortalTokenModel())->insert([
            'company_id'    => (int) $account['company_id'],
            'account_id'    => (int) $account['id'],
            'selector'      => $selector,
            'verifier_hash' => hash('sha256', $verifier),
            'purpose'       => $purpose === 'invite' ? 'invite' : 'login',
            'expires_at'    => date('Y-m-d H:i:s', time() + ($purpose === 'invite' ? self::INVITE_TTL : self::LOGIN_TTL)),
            'ip'            => is_cli() ? '' : mb_substr((string) service('request')->getIPAddress(), 0, 45),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return $selector . '.' . $verifier;
    }

    /** El token si es válido y no se ha usado; no lo consume. */
    public function peek(string $token): ?array
    {
        if (!preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $token, $m)) {
            return null;
        }
        $row = (new PortalTokenModel())->where('selector', $m[1])->first();
        if (!$row || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        if (!hash_equals((string) $row['verifier_hash'], hash('sha256', $m[2]))) {
            return null;
        }

        return $row;
    }

    /** Consume el token y devuelve la cuenta, o null. */
    public function consume(string $token): ?array
    {
        $row = $this->peek($token);
        if (!$row) {
            return null;
        }
        // Marcado condicional: si dos peticiones llegan a la vez, solo una gana.
        $db = db_connect();
        $db->table('cmp_lgc_portal_tokens')->where('id', (int) $row['id'])->where('used_at', null)
            ->update(['used_at' => date('Y-m-d H:i:s')]);
        if ($db->affectedRows() !== 1) {
            return null;
        }

        $account = (new PortalAccountModel())->find((int) $row['account_id']);
        if (!$account || $account['status'] !== 'active' || !$this->companyOpen((int) $account['company_id'])) {
            return null;
        }

        return $account;
    }

    // ------------------------------------------------------------------ sesión

    public function login(array $account): void
    {
        session()->regenerate(true);
        session()->set(self::SESSION_KEY, [
            'account_id' => (int) $account['id'],
            'company_id' => (int) $account['company_id'],
            'seen'       => time(),
        ]);
        (new PortalAccountModel())->update((int) $account['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        Audit::log((int) $account['company_id'], null, 'portal.login', 'account', (int) $account['id'], [], 'portal', (int) $account['id']);
    }

    public function logout(): void
    {
        session()->remove(self::SESSION_KEY);
        session()->regenerate(true);
    }

    /**
     * La cuenta de la sesión del portal, revalidada en CADA petición: si el despacho la
     * bloqueó, apagó el portal o dejó vencer su suscripción, la sesión deja de valer.
     */
    public function current(): ?array
    {
        $s = session(self::SESSION_KEY);
        if (!is_array($s) || empty($s['account_id'])) {
            return null;
        }
        if (time() - (int) ($s['seen'] ?? 0) > self::IDLE_TIMEOUT) {
            $this->logout();

            return null;
        }
        $account = (new PortalAccountModel())->find((int) $s['account_id']);
        if (!$account || $account['status'] !== 'active' || (int) $account['company_id'] !== (int) $s['company_id']
            || !$this->companyOpen((int) $account['company_id'])) {
            $this->logout();

            return null;
        }
        $s['seen'] = time();
        session()->set(self::SESSION_KEY, $s);

        return $account;
    }

    /** ¿La empresa tiene el portal abierto? Suscripción vigente, servicio en el plan y portal encendido. */
    private function companyOpen(int $companyId): bool
    {
        if (!(new CompanyModel())->isUsable($companyId) || !(new PlanGuard())->serviceEnabled($companyId)) {
            return false;
        }

        return (int) ((new SettingsModel())->forCompanyOrDefault($companyId)['portal_enabled'] ?? 0) === 1;
    }
}
