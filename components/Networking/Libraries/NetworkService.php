<?php

namespace Components\Networking\Libraries;

use Components\Networking\Models\MessageModel;
use Components\Networking\Models\ProfileModel;
use Components\Networking\Models\RequestModel;

/**
 * Reglas de la red de contactos. Los controladores quedan delgados: aquí viven las
 * validaciones de quién puede solicitar/responder qué, los contadores de las
 * pestañas y la resolución de datos de usuario.
 *
 * La red es GLOBAL: nada se filtra por empresa activa (ver Manifest).
 */
class NetworkService
{
    private RequestModel $requests;
    private ProfileModel $profiles;
    private MessageModel $messages;

    public function __construct()
    {
        $this->requests = new RequestModel();
        $this->profiles = new ProfileModel();
        $this->messages = new MessageModel();
    }

    // ------------------------------------------------------------------ perfiles

    /** Perfil de red del usuario, con valores por defecto si aún no lo configuró. */
    public function profileOf(int $userId): array
    {
        return $this->profiles->forUser($userId) ?? [
            'user_id'       => $userId,
            'company_id'    => null,
            'share_profile' => 0,
            'position'      => '',
            'branch'        => '',
            'bio'           => '',
            'interests'     => '',
            'show_email'    => 1,
            'show_phone'    => 0,
        ];
    }

    /**
     * Empresas a las que pertenece el usuario (dueño ∪ miembro). Sirve para que
     * elija cuál mostrar en su tarjeta si administra varias.
     */
    public function companiesOf(int $userId): array
    {
        return (new \App\Models\CompanyModel())->getAccessibleFor($userId, false);
    }

    /**
     * Empresa que acompaña al perfil: la elegida (`company_id`) si sigue siendo
     * suya, o la primera a la que pertenece. null si no tiene ninguna.
     */
    public function companyOf(int $userId, ?array $profile = null): ?array
    {
        $profile ??= $this->profileOf($userId);
        $mine      = $this->companiesOf($userId);
        if (!$mine) {
            return null;
        }

        $chosen = (int) ($profile['company_id'] ?? 0);
        if ($chosen) {
            foreach ($mine as $c) {
                if ((int) $c['id'] === $chosen) {
                    return $c;
                }
            }
        }

        return $mine[0]; // sin elección válida: la primera
    }

    /**
     * Todo lo que necesita una tarjeta de persona, ya resuelto: identidad de
     * `users`, marca y nombre de `companies`, y lo propio de la red.
     */
    public function card(int $userId, ?array $profile = null): array
    {
        $profile ??= $this->profileOf($userId);
        $user      = $this->userOf($userId);
        $company   = $this->companyOf($userId, $profile);

        return [
            'user_id'   => $userId,
            'name'      => $this->displayName($user),
            'user'      => $user,
            'profile'   => $profile,
            'company'   => $company,
            'position'  => (string) ($profile['position'] ?? ''),
            'branch'    => (string) ($profile['branch'] ?? ''),
            'interests' => (string) ($profile['interests'] ?? ''),
            'logo'      => $company['logo'] ?? null,
            'banner'    => $company['banner'] ?? null,
        ];
    }

    /** Datos públicos del usuario del núcleo (nunca password ni tokens). */
    public function userOf(int $userId): ?array
    {
        $row = db_connect()->table('users')
            ->select('id, username, first_name, last_name, email, phone, is_active')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->get()->getRowArray();

        return $row ?: null;
    }

    public function displayName(?array $user): string
    {
        if (!$user) {
            return 'Usuario';
        }
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        return $name !== '' ? $name : (string) ($user['username'] ?? 'Usuario');
    }

    /**
     * ¿El visitante puede ver el correo / teléfono del dueño? Sí si están conectados
     * o si el dueño lo publicó explícitamente en su perfil.
     */
    public function canSeeContact(int $viewerId, array $profile, string $field): bool
    {
        $ownerId = (int) ($profile['user_id'] ?? 0);
        if ($ownerId === 0) {
            return false;
        }
        if ($viewerId === $ownerId || $this->isConnected($viewerId, $ownerId)) {
            return true;
        }

        return !empty($profile[$field === 'email' ? 'show_email' : 'show_phone']);
    }

    // ------------------------------------------------------------------ solicitudes

    /** Estado de la relación entre dos usuarios (o null si nunca hubo solicitud). */
    public function relation(int $me, int $other): ?array
    {
        return $this->requests->between($me, $other);
    }

    public function isConnected(int $a, int $b): bool
    {
        $rel = $this->requests->between($a, $b);

        return $rel !== null && $rel['status'] === 'accepted';
    }

    /**
     * ¿Puede $from enviarle una solicitud a $to?
     *
     * @return array{ok:bool,message:string}
     */
    public function canRequest(int $from, int $to): array
    {
        if ($from === $to) {
            return ['ok' => false, 'message' => lang('Net.errSelf')];
        }

        // Plan de la empresa activa: servicio habilitado y cupo disponible (§11).
        $guard = new PlanGuard();
        if (!$guard->serviceEnabled()) {
            return ['ok' => false, 'message' => lang('Net.errPlanDisabled')];
        }
        $left = $guard->connectionsLeft($from);
        if ($left !== null && $left <= 0) {
            return ['ok' => false, 'message' => lang('Net.errPlanQuota')];
        }

        $target = $this->userOf($to);
        if (!$target || empty($target['is_active'])) {
            return ['ok' => false, 'message' => lang('Net.errUnavailable')];
        }

        $profile = $this->profiles->forUser($to);
        if (!$profile || empty($profile['share_profile'])) {
            return ['ok' => false, 'message' => lang('Net.errNotSharing')];
        }

        $rel = $this->requests->between($from, $to);
        if ($rel) {
            if ($rel['status'] === 'accepted') {
                return ['ok' => false, 'message' => lang('Net.errAlreadyLinked')];
            }
            if ($rel['status'] === 'pending') {
                return ['ok' => false, 'message' => (int) $rel['requester_id'] === $from
                    ? lang('Net.errAlreadySent')
                    : lang('Net.errIncoming')];
            }
            // rejected / cancelled: se permite volver a intentarlo reusando la fila.
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * Crea (o reactiva) una solicitud.
     *
     * @return array{ok:bool,message:string}
     */
    public function send(int $from, int $to, string $message): array
    {
        $can = $this->canRequest($from, $to);
        if (!$can['ok']) {
            return $can;
        }

        $rel = $this->requests->between($from, $to);

        // Reusar la fila previa (el índice único no admite duplicar el par).
        if ($rel && (int) $rel['requester_id'] === $from) {
            $ok = $this->requests->update($rel['id'], [
                'message'      => $message,
                'status'       => 'pending',
                'responded_at' => null,
            ]);

            return $ok
                ? ['ok' => true, 'message' => lang('Net.okSent')]
                : ['ok' => false, 'message' => implode(' ', $this->requests->errors())];
        }
        if ($rel && (int) $rel['addressee_id'] === $from) {
            // La otra persona ya nos había escrito y se rechazó/canceló: invertimos
            // los papeles creando la solicitud en la dirección contraria.
            $ok = $this->requests->insert([
                'requester_id' => $from,
                'addressee_id' => $to,
                'message'      => $message,
                'status'       => 'pending',
            ]);

            return $ok
                ? ['ok' => true, 'message' => lang('Net.okSent')]
                : ['ok' => false, 'message' => implode(' ', $this->requests->errors())];
        }

        $ok = $this->requests->insert([
            'requester_id' => $from,
            'addressee_id' => $to,
            'message'      => $message,
            'status'       => 'pending',
        ]);

        return $ok
            ? ['ok' => true, 'message' => lang('Net.okSent')]
            : ['ok' => false, 'message' => implode(' ', $this->requests->errors())];
    }

    /** Acepta o rechaza: solo el destinatario de una solicitud pendiente. */
    public function respond(int $requestId, int $me, string $decision): array
    {
        if (!in_array($decision, ['accepted', 'rejected'], true)) {
            return ['ok' => false, 'message' => lang('Net.errBadDecision')];
        }
        $req = $this->requests->find($requestId);
        if (!$req || (int) $req['addressee_id'] !== $me) {
            return ['ok' => false, 'message' => lang('Net.errNotFound')];
        }
        if ($req['status'] !== 'pending') {
            return ['ok' => false, 'message' => lang('Net.errAlreadyAnswered')];
        }

        // Aceptar consume cupo del plan; rechazar nunca se bloquea.
        if ($decision === 'accepted') {
            $guard = new PlanGuard();
            if (!$guard->serviceEnabled()) {
                return ['ok' => false, 'message' => lang('Net.errPlanDisabled')];
            }
            $left = $guard->connectionsLeft($me);
            if ($left !== null && $left <= 0) {
                return ['ok' => false, 'message' => lang('Net.errPlanQuota')];
            }
        }

        $this->requests->update($requestId, [
            'status'       => $decision,
            'responded_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'message' => $decision === 'accepted' ? lang('Net.okAccepted') : lang('Net.okRejected')];
    }

    /** Cancela una solicitud pendiente: solo quien la envió. */
    public function cancel(int $requestId, int $me): array
    {
        $req = $this->requests->find($requestId);
        if (!$req || (int) $req['requester_id'] !== $me) {
            return ['ok' => false, 'message' => lang('Net.errNotFound')];
        }
        if ($req['status'] !== 'pending') {
            return ['ok' => false, 'message' => lang('Net.errOnlyPending')];
        }
        $this->requests->update($requestId, ['status' => 'cancelled', 'responded_at' => date('Y-m-d H:i:s')]);

        return ['ok' => true, 'message' => lang('Net.okCancelled')];
    }

    /** Elimina una conexión aceptada (cualquiera de las dos partes). Borra el hilo. */
    public function removeConnection(int $requestId, int $me): array
    {
        $conn = $this->connection($requestId, $me);
        if (!$conn) {
            return ['ok' => false, 'message' => lang('Net.errConnNotFound')];
        }
        $this->requests->delete($requestId); // los mensajes se van en cascada (FK)

        return ['ok' => true, 'message' => lang('Net.okConnRemoved')];
    }

    // ------------------------------------------------------------------ listados

    /** Solicitudes recibidas pendientes, con los datos de quien las envía. */
    public function received(int $me): array
    {
        $rows = $this->requests
            ->where('addressee_id', $me)
            ->where('status', 'pending')
            ->orderBy('id', 'desc')
            ->findAll();

        return array_map(fn ($r) => $r + ['other' => $this->userOf((int) $r['requester_id'])], $rows);
    }

    /** Solicitudes enviadas (todas, con su estado). */
    public function sent(int $me): array
    {
        $rows = $this->requests
            ->where('requester_id', $me)
            ->orderBy('id', 'desc')
            ->findAll();

        return array_map(fn ($r) => $r + ['other' => $this->userOf((int) $r['addressee_id'])], $rows);
    }

    /** Conexiones aceptadas, con el otro usuario y el conteo de no leídos. */
    public function connections(int $me): array
    {
        $rows = $this->requests
            ->where('status', 'accepted')
            ->groupStart()->where('requester_id', $me)->orWhere('addressee_id', $me)->groupEnd()
            ->orderBy('responded_at', 'desc')
            ->findAll();

        return array_map(function ($r) use ($me) {
            $otherId = $this->otherUserId($r, $me);

            return $r + [
                'other'   => $this->userOf($otherId),
                'profile' => $this->profileOf($otherId),
                'card'    => $this->card($otherId),
                'unread'  => $this->messages
                    ->where('request_id', $r['id'])
                    ->where('sender_id !=', $me)
                    ->where('read_at', null)
                    ->countAllResults(),
            ];
        }, $rows);
    }

    public function otherUserId(array $request, int $me): int
    {
        return (int) $request['requester_id'] === $me
            ? (int) $request['addressee_id']
            : (int) $request['requester_id'];
    }

    /** Una conexión aceptada de la que $me forma parte, o null. */
    public function connection(int $requestId, int $me): ?array
    {
        $req = $this->requests->find($requestId);
        if (!$req || $req['status'] !== 'accepted') {
            return null;
        }
        if ((int) $req['requester_id'] !== $me && (int) $req['addressee_id'] !== $me) {
            return null;
        }

        return $req;
    }

    // ------------------------------------------------------------------ mensajes

    /** Hilo de una conexión; marca como leídos los mensajes del otro. */
    public function thread(int $requestId, int $me): array
    {
        $this->messages
            ->where('request_id', $requestId)
            ->where('sender_id !=', $me)
            ->where('read_at', null)
            ->set('read_at', date('Y-m-d H:i:s'))
            ->update();

        return $this->messages->thread($requestId);
    }

    public function postMessage(int $requestId, int $me, string $body): array
    {
        if (!$this->connection($requestId, $me)) {
            return ['ok' => false, 'message' => lang('Net.errCannotWrite')];
        }
        $ok = $this->messages->insert([
            'request_id' => $requestId,
            'sender_id'  => $me,
            'body'       => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $ok
            ? ['ok' => true, 'message' => lang('Net.okMessageSent')]
            : ['ok' => false, 'message' => implode(' ', $this->messages->errors())];
    }

    // ------------------------------------------------------------------ contadores

    /** Badges de las pestañas. */
    public function counts(int $me): array
    {
        $connIds = array_column(
            $this->requests->select('id')
                ->where('status', 'accepted')
                ->groupStart()->where('requester_id', $me)->orWhere('addressee_id', $me)->groupEnd()
                ->findAll(),
            'id'
        );

        $unread = 0;
        if ($connIds) {
            $unread = $this->messages
                ->whereIn('request_id', $connIds)
                ->where('sender_id !=', $me)
                ->where('read_at', null)
                ->countAllResults();
        }

        return [
            'received'    => $this->requests->where('addressee_id', $me)->where('status', 'pending')->countAllResults(),
            'sent'        => $this->requests->where('requester_id', $me)->where('status', 'pending')->countAllResults(),
            'connections' => count($connIds),
            'unread'      => $unread,
        ];
    }
}
