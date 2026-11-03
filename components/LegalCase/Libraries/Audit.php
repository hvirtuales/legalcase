<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\AuditModel;

/**
 * Bitácora del componente (§3, §6.9, §6.13-3): publicaciones, revocaciones,
 * invitaciones, accesos y descargas del portal, validaciones de plazos.
 *
 * Es propia y no `audit_logs` del núcleo porque el trait `Auditable` necesita una
 * petición HTTP (no sirve en el reloj) y porque esta bitácora la consulta el despacho
 * por asunto. El detalle nunca guarda el contenido de documentos ni mensajes: solo
 * identificadores y metadatos.
 */
class Audit
{
    public static function log(
        int $companyId,
        ?int $matterId,
        string $action,
        string $entity = '',
        ?int $entityId = null,
        array $detail = [],
        string $actorType = 'user',
        ?int $actorId = null
    ): void {
        if ($companyId <= 0) {
            return;
        }

        $ip = '';
        if (!is_cli()) {
            $ip = (string) service('request')->getIPAddress();
        }

        if ($actorType === 'user' && $actorId === null) {
            $actorId = (int) (session('user_id') ?? 0) ?: null;
            if ($actorId === null) {
                $actorType = 'system';
            }
        }

        (new AuditModel())->insert([
            'company_id' => $companyId,
            'matter_id'  => $matterId,
            'actor_type' => $actorType,
            'actor_id'   => $actorId,
            'action'     => mb_substr($action, 0, 60),
            'entity'     => mb_substr($entity, 0, 30),
            'entity_id'  => $entityId,
            'detail'     => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            'ip'         => mb_substr($ip, 0, 45),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
