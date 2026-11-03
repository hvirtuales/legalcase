<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/** Actividades y tareas sobre un lead (§17). */
class ActivityModel extends Model
{
    use CompanyScoped;

    /*
     * `deadline` es el ultimo a proposito: anadirlo en medio cambiaria el orden de
     * los desplegables ya existentes sin que nadie lo pidiera.
     *
     * Es un tipo de actividad y no una tabla aparte porque un plazo ES una tarea
     * con fecha: asi hereda la bandeja de vencidas, el recordatorio de
     * `leadflow:tick` y el disparador `activity_overdue` sin construir nada.
     */
    public const TYPES    = ['call', 'email', 'whatsapp', 'review', 'request_doc', 'schedule', 'follow_up', 'other', 'deadline'];
    public const STATUSES = ['pending', 'done', 'cancelled'];

    protected $table          = 'cmp_lfl_activities';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'lead_id', 'assigned_user_id', 'activity_type', 'title', 'description',
        'due_at', 'completed_at', 'status', 'priority', 'created_by'];

    protected $validationRules = [
        'company_id'    => 'required|is_natural_no_zero',
        'lead_id'       => 'required|is_natural_no_zero',
        // OJO: esta lista DUPLICA la constante TYPES porque `in_list` solo admite
        // un literal. Si se anade un tipo alli hay que anadirlo aqui, o el tipo
        // existe para los desplegables y el modelo lo rechaza al guardar.
        'activity_type' => 'required|in_list[call,email,whatsapp,review,request_doc,schedule,follow_up,other,deadline]',
        'title'         => 'required|max_length[150]',
        'status'        => 'permit_empty|in_list[pending,done,cancelled]',
        'priority'      => 'permit_empty|in_list[low,medium,high]',
    ];
    protected $validationMessages = [
        'title' => ['required' => 'Lfl.vActivityTitle'],
    ];

    public function forLead(int $leadId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('lead_id', $leadId)
            ->orderBy('status', 'desc')->orderBy('due_at', 'asc')->findAll();
    }

    /** La próxima actividad pendiente del lead (para `next_activity_at`). */
    public function nextDue(int $leadId, int $companyId, ?string $type = null): ?string
    {
        $b = $this->forCompany($companyId)->where('lead_id', $leadId)->where('status', 'pending')
            ->where('due_at IS NOT NULL', null, false);
        if ($type !== null) {
            $b->where('activity_type', $type);
        }
        $row = $b->orderBy('due_at', 'asc')->first();

        return $row['due_at'] ?? null;
    }
}
