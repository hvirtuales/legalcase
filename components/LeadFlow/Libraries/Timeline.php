<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\TimelineModel;

/**
 * La única puerta de escritura de la línea de tiempo (§12).
 *
 * La llaman los servicios, no los controladores: así un cambio hecho desde el
 * formulario público, la importación, el Kanban o la conversión deja el mismo
 * rastro, y ninguno se queda sin registrar porque un controlador lo olvidó.
 */
class Timeline
{
    public static function log(int $companyId, int $leadId, string $type, string $summary, array $meta = [], ?int $userId = null): void
    {
        if ($companyId <= 0 || $leadId <= 0) {
            return;
        }

        $userId ??= (int) (session('user_id') ?? 0) ?: null;

        (new TimelineModel())->insert([
            'company_id' => $companyId,
            'lead_id'    => $leadId,
            'type'       => mb_substr($type, 0, 30),
            'summary'    => mb_substr($summary, 0, 255),
            'meta'       => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'user_id'    => $userId ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
