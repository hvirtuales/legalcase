<?php

namespace Components\Pqrsf\Models;

use CodeIgniter\Model;

/**
 * El hilo de la solicitud: notas internas, cambios de estado, asignaciones,
 * respuestas y envíos.
 *
 * `is_private` decide qué ve el ciudadano en su ticket: las notas internas y los
 * nombres de los gestores **no** salen de la casa.
 */
class EventModel extends Model
{
    protected $table            = 'cmp_pqr_events';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['request_id', 'user_id', 'type', 'body', 'meta', 'is_private', 'created_at'];

    protected $useTimestamps = false;

    /** Anota algo en el hilo. `meta` va como TEXT + json (§8, sin JSON nativo). */
    public function log(int $requestId, string $type, ?string $body = null, array $meta = [], bool $private = true, ?int $userId = null): int
    {
        $this->insert([
            'request_id' => $requestId,
            'user_id'    => $userId ?? (session()->get('user_id') ? (int) session()->get('user_id') : null),
            'type'       => $type,
            'body'       => $body,
            'meta'       => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'is_private' => $private ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->getInsertID();
    }

    /** Hilo completo (gestión) o solo lo público (ticket del ciudadano). */
    public function threadFor(int $requestId, bool $onlyPublic = false): array
    {
        $b = $this->select("cmp_pqr_events.*, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS user_name")
            ->join('users u', 'u.id = cmp_pqr_events.user_id', 'left')
            ->where('request_id', $requestId);

        if ($onlyPublic) {
            $b->where('is_private', 0);
        }

        return $b->orderBy('cmp_pqr_events.id', 'asc')->findAll();
    }
}
