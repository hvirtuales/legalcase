<?php

namespace Components\Pqrsf\Models;

use CodeIgniter\Model;

/** La solicitud PQRSF. */
class RequestModel extends Model
{
    /**
     * Los tipos ya no viven aquí: los gestiona cada empresa en `cmp_pqr_types`
     * (ver `TypeModel`). Esta lista es solo el respaldo de los cinco de siempre,
     * para validar cuando no hay empresa a mano.
     */
    public const TYPES = ['peticion', 'queja', 'reclamo', 'sugerencia', 'felicitacion'];

    /** Flujo: recibida → en_gestion → respondida → cerrada (y anulada). */
    public const STATUSES = ['recibida', 'en_gestion', 'respondida', 'cerrada', 'anulada'];

    /** `critica` existe para las plantillas de IA que clasifican en cuatro niveles. */
    public const PRIORITIES = ['baja', 'media', 'alta', 'critica'];

    protected $table            = 'cmp_pqr_requests';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'point_id', 'number', 'ticket_token', 'type', 'subject', 'message',
        'priority', 'status', 'assignee_id', 'due_at',
        'is_anonymous', 'citizen_name', 'citizen_doc', 'citizen_email', 'citizen_phone', 'citizen_city',
        'accept_terms', 'consent_data', 'consent_comms', 'consent_at', 'consent_ip', 'policy_version',
        'ai_summary', 'ai_category', 'ai_priority', 'ai_analysis', 'ai_actions', 'ai_draft',
        'answer', 'answer_link', 'answer_link_label',
        'answered_at', 'answered_by', 'closed_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        // Sin `in_list`: los tipos los define cada empresa. Quien valida que el
        // código exista y esté activo es `PqrService::create()`.
        'type'       => 'required|max_length[20]',
        'subject'    => 'required|min_length[3]|max_length[200]',
        'message'    => 'required|min_length[10]',
    ];

    protected $validationMessages = [
        'subject' => ['required' => 'El asunto es obligatorio.'],
        'message' => ['required' => 'Cuéntanos qué pasó para poder ayudarte.'],
    ];

    /** Solicitud de la empresa (o null): el permiso no basta, hay que ser dueño. */
    public function own(int $companyId, int $id): ?array
    {
        return $this->where('company_id', $companyId)->find($id) ?: null;
    }

    /** Métricas de la bandeja. */
    public function metrics(int $companyId): array
    {
        return [
            'open' => $this->where('company_id', $companyId)->whereIn('status', ['recibida', 'en_gestion'])->countAllResults(),
            'late' => $this->where('company_id', $companyId)
                ->whereIn('status', ['recibida', 'en_gestion'])
                ->where('due_at <', date('Y-m-d H:i:s'))
                ->countAllResults(),
            'month' => $this->where('company_id', $companyId)->where('answered_at >=', date('Y-m-01 00:00:00'))->countAllResults(),
            'total' => $this->where('company_id', $companyId)->countAllResults(),
        ];
    }
}
