<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;
use Components\LeadFlow\Models\StageModel;

/**
 * La ÚNICA definición de qué disparadores, condiciones y acciones existen (§19).
 *
 * De aquí beben las tres piezas —el editor, el saneador y el motor—, así que
 * **añadir una acción es una entrada aquí y un `case` en `AutomationEngine`**. Es
 * el mismo criterio que `FieldRegistry` en Formularios y `BlockRegistry` en
 * Páginas, y lo que evita que el navegador pueda pedir algo que el motor no sepa
 * hacer: lo que no está en estas listas no se guarda.
 */
class AutomationRegistry
{
    public const MAX_CONDITIONS = 10;
    public const MAX_ACTIONS    = 8;

    /** Disparador => clave de idioma. Los del §19. */
    public const TRIGGERS = [
        'lead_created'          => 'trLeadCreated',
        'stage_changed'         => 'trStageChanged',
        'form_completed'        => 'trFormCompleted',
        'score_reached'         => 'trScoreReached',
        'activity_overdue'      => 'trActivityOverdue',
        'appointment_scheduled' => 'trApptScheduled',
        'appointment_done'      => 'trApptDone',
        'no_show'               => 'trNoShow',
        'lead_won'              => 'trLeadWon',
        // Lo provoca el RELOJ, como `activity_overdue`: nadie pulsa nada, lo detecta
        // `leadflow:tick` al refrescar el caso y ver que USCIS dice algo distinto.
        'case_status_changed'   => 'trCaseStatusChanged',
        /*
         * El expediente. Los dispara `MatterService`, nunca una pantalla, así que un
         * caso abierto a mano, por el comando de backfill o por una automatización
         * dispara exactamente lo mismo.
         *
         * `matter_stage_changed` lleva su `ref` con la etapa (`mstage:<caso>:<etapa>`)
         * por lo mismo que `stage_changed` lo lleva con la del lead: volver a entrar en
         * «Documentos» tras una solicitud de evidencia DEBE poder disparar otra vez,
         * pero no dos veces por la misma llegada.
         */
        'matter_opened'         => 'trMatterOpened',
        'matter_stage_changed'  => 'trMatterStageChanged',
        'matter_closed'         => 'trMatterClosed',
    ];

    /** Campo del lead => [tipo del valor, clave de idioma]. */
    public const FIELDS = [
        'service_id'             => ['service', 'service'],
        'source_id'              => ['source', 'source'],
        'campaign_id'            => ['campaign', 'campaign'],
        'stage_id'               => ['stage', 'stage'],
        'form_id'                => ['form', 'form'],
        'assigned_user_id'       => ['user', 'advisor'],
        'lawyer_user_id'         => ['user', 'lawyer'],
        'priority'               => ['priority', 'priority'],
        'status'                 => ['status', 'status'],
        'score'                  => ['number', 'score'],
        'potential_value'        => ['number', 'potentialValue'],
        'requested_consultation' => ['bool', 'requestedConsultation'],
        'country'                => ['text', 'country'],
        'city'                   => ['text', 'city'],
        'utm_source'             => ['text', 'utmSourceField'],
        'utm_campaign'           => ['text', 'utmCampaignField'],
        // El último estado que USCIS informó, para condicionar sobre él: «si el
        // estado contiene 'Request for Additional Evidence', crea una actividad».
        'uscis_status'           => ['text', 'uscisStatusField'],
    ];

    public const OPERATORS = ['equals', 'not_equals', 'greater_than', 'less_than', 'contains', 'is_empty', 'is_not_empty'];

    public const OPERATOR_LABELS = [
        'equals' => 'opEquals', 'not_equals' => 'opNotEquals', 'greater_than' => 'opGreater', 'less_than' => 'opLess',
        'contains' => 'opContains', 'is_empty' => 'opIsEmpty', 'is_not_empty' => 'opIsNotEmpty',
    ];

    /** Acción => [clave de idioma, parámetros => tipo]. */
    public const ACTIONS = [
        'assign_advisor'    => ['acAssign', ['mode' => 'assign_mode', 'user_id' => 'user']],
        'set_priority'      => ['acPriority', ['priority' => 'priority_auto']],
        'move_stage'        => ['acMoveStage', ['stage_id' => 'stage', 'reason' => 'lost_reason']],
        'create_activity'   => ['acActivity', ['activity_type' => 'activity_type', 'title' => 'text', 'due_in_hours' => 'number', 'assign_to' => 'assign_to', 'user_id' => 'user']],
        'add_note'          => ['acNote', ['body' => 'textarea']],
        'send_email'        => ['acEmail', ['subject' => 'text', 'body' => 'textarea']],
        'notify_user'       => ['acNotify', ['user_id' => 'user', 'subject' => 'text', 'body' => 'textarea']],
        'recalculate_score' => ['acScore', []],
    ];

    public const ASSIGN_MODES = ['round_robin' => 'amRoundRobin', 'service_owner' => 'amServiceOwner', 'user' => 'amUser'];
    public const ASSIGN_TO    = ['lead_owner' => 'atLeadOwner', 'user' => 'atUser'];

    /** Marcadores que se pueden usar en los textos de las acciones. */
    public const PLACEHOLDERS = ['nombre', 'apellido', 'nombre_completo', 'servicio', 'etapa', 'score', 'empresa', 'asesor', 'correo', 'telefono'];

    // ── Saneado ──────────────────────────────────────────────────────────────

    /**
     * Reconstruye las condiciones contra el registro.
     *
     * @param mixed $raw array o JSON
     */
    public function sanitizeConditions($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (array_slice(array_values($raw), 0, self::MAX_CONDITIONS) as $c) {
            if (! is_array($c)) {
                continue;
            }
            $field = (string) ($c['field'] ?? '');
            $op    = (string) ($c['op'] ?? '');
            if (! isset(self::FIELDS[$field]) || ! in_array($op, self::OPERATORS, true)) {
                continue;
            }
            $out[] = [
                'field' => $field,
                'op'    => $op,
                'value' => mb_substr(trim(strip_tags((string) ($c['value'] ?? ''))), 0, 120),
            ];
        }

        return $out;
    }

    /**
     * Reconstruye las acciones, comprobando además que lo que referencian sea de la
     * empresa: una etapa o un usuario de otra firma no llegan a guardarse.
     *
     * @param mixed $raw array o JSON
     */
    public function sanitizeActions($raw, int $companyId): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (array_slice(array_values($raw), 0, self::MAX_ACTIONS) as $a) {
            if (! is_array($a)) {
                continue;
            }
            $type = (string) ($a['type'] ?? '');
            if (! isset(self::ACTIONS[$type])) {
                continue;
            }

            $clean = ['type' => $type];
            foreach (self::ACTIONS[$type][1] as $param => $kind) {
                $v = $a[$param] ?? '';
                $clean[$param] = match ($kind) {
                    'user'          => Team::isMember($companyId, (int) $v) ? (int) $v : null,
                    'stage'         => (new StageModel())->owned((int) $v, $companyId) ? (int) $v : null,
                    'priority_auto' => in_array($v, array_merge(LeadModel::PRIORITIES, ['auto']), true) ? (string) $v : 'auto',
                    'lost_reason'   => in_array($v, LeadModel::LOST_REASONS, true) ? (string) $v : null,
                    'activity_type' => in_array($v, ActivityModel::TYPES, true) ? (string) $v : 'follow_up',
                    'assign_mode'   => isset(self::ASSIGN_MODES[$v]) ? (string) $v : 'round_robin',
                    'assign_to'     => isset(self::ASSIGN_TO[$v]) ? (string) $v : 'lead_owner',
                    'number'        => max(0, min(8760, (int) $v)),
                    'textarea'      => mb_substr(trim((string) $v), 0, 4000),
                    default         => mb_substr(trim(strip_tags((string) $v)), 0, 200),
                };
            }

            // Una acción que no puede hacer nada no se guarda: sería una trampa silenciosa.
            if ($type === 'move_stage' && ! $clean['stage_id']) {
                continue;
            }
            if (in_array($type, ['add_note', 'send_email', 'notify_user'], true) && trim((string) ($clean['body'] ?? '')) === '') {
                continue;
            }
            if ($type === 'notify_user' && ! $clean['user_id']) {
                continue;
            }

            $out[] = $clean;
        }

        return $out;
    }

    // ── Datos para el editor ─────────────────────────────────────────────────

    /**
     * Todo lo que el editor necesita, ya traducido: listas, etiquetas y opciones.
     * Una sola fuente para el formulario y para el resumen del listado.
     */
    public function forJs(int $companyId): array
    {
        $stages = [];
        foreach ((new StageModel())->ordered($companyId) as $s) {
            $stages[(string) $s['id']] = $s['name'];
        }

        return [
            'triggers'   => array_map(static fn ($k) => lang('Lfl.' . $k), self::TRIGGERS),
            'fields'     => array_map(static fn ($f) => lang('Lfl.' . $f[1]), self::FIELDS),
            'fieldTypes' => array_map(static fn ($f) => $f[0], self::FIELDS),
            'operators'  => array_map(static fn ($k) => lang('Lfl.' . $k), self::OPERATOR_LABELS),
            'actions'    => array_map(static fn ($a) => lang('Lfl.' . $a[0]), self::ACTIONS),
            'params'     => array_map(static fn ($a) => $a[1], self::ACTIONS),
            'options'    => [
                'service'       => array_map('strval', (new ServiceModel())->options($companyId, false)),
                'source'        => array_map('strval', (new SourceModel())->options($companyId, false)),
                'campaign'      => array_map('strval', (new CampaignModel())->options($companyId, false)),
                'form'          => array_map('strval', (new FormModel())->options($companyId)),
                'stage'         => $stages,
                'user'          => array_map('strval', Team::users($companyId)),
                'priority'      => Meta::options(Meta::PRIORITIES),
                'priority_auto' => ['auto' => lang('Lfl.priorityAuto')] + Meta::options(Meta::PRIORITIES),
                'status'        => Meta::options(Meta::STATUSES),
                'bool'          => ['1' => lang('Lfl.yes'), '0' => lang('Lfl.no')],
                'activity_type' => Meta::options(Meta::ACTIVITY_TYPES),
                'lost_reason'   => Meta::options(Meta::LOST_REASONS),
                'assign_mode'   => array_map(static fn ($k) => lang('Lfl.' . $k), self::ASSIGN_MODES),
                'assign_to'     => array_map(static fn ($k) => lang('Lfl.' . $k), self::ASSIGN_TO),
            ],
            'paramLabels' => [
                'mode' => lang('Lfl.mode'), 'user_id' => lang('Lfl.user'), 'priority' => lang('Lfl.priority'),
                'stage_id' => lang('Lfl.stage'), 'reason' => lang('Lfl.lostReason'), 'activity_type' => lang('Lfl.type'),
                'title' => lang('Lfl.title'), 'due_in_hours' => lang('Lfl.autDueHours'), 'assign_to' => lang('Lfl.owner'),
                'body' => lang('Lfl.message'), 'subject' => lang('Lfl.subject'),
            ],
        ];
    }
}
