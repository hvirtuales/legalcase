<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\AutomationModel;
use Components\LeadFlow\Models\AutomationRunModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\StageModel;

/**
 * El motor de las automatizaciones (§19): DISPARADOR → CONDICIONES → ACCIONES.
 *
 * Lo llaman los servicios cuando ocurre algo (`fire()`), nunca los controladores:
 * así un lead que entra por el formulario público, por la importación o a mano
 * dispara lo mismo.
 *
 * **Tres frenos, y los tres hacen falta:**
 *
 * 1. **Profundidad.** Una acción puede provocar otro disparador (mover de etapa
 *    dispara «cambio de etapa»). Más de `MAX_DEPTH` niveles se corta y se registra:
 *    es un ciclo, y sin el corte se lleva por delante la petición entera.
 * 2. **Una automatización no se repite sobre el mismo lead dentro de la misma
 *    petición**, aunque otro disparador la vuelva a alcanzar. Evita el ping-pong
 *    entre dos automatizaciones que se llaman la una a la otra.
 * 3. **`once_per_lead`** (lo normal): la ejecución se guarda con una `ref`
 *    (`lead:12`, `activity:34`) y el UNIQUE de la tabla impide la segunda. Así el
 *    freno sobrevive entre peticiones, que es donde la memoria no llega.
 *
 * Un fallo de una acción **no aborta las demás** ni la operación del usuario: se
 * anota en la ejecución y en la bitácora. Una automatización no puede impedir que
 * alguien guarde un lead.
 */
class AutomationEngine
{
    public const MAX_DEPTH = 3;

    private static int $depth = 0;

    /** "automationId:leadId" ya ejecutados en esta petición. */
    private static array $done = [];

    /** Interruptor para procesos que no deben disparar nada (datos demo, importación masiva). */
    private static bool $enabled = true;

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function flush(): void
    {
        self::$done  = [];
        self::$depth = 0;
    }

    /**
     * Ocurrió algo con un lead.
     *
     * @param array $ctx `ref` (para el «una sola vez» de algo que no es el lead) y
     *                   los datos del evento (etapa anterior, id de la actividad…)
     */
    public static function fire(string $trigger, array $lead, array $ctx = []): void
    {
        if (! self::$enabled || ! isset(AutomationRegistry::TRIGGERS[$trigger]) || empty($lead['id'])) {
            return;
        }

        $companyId = (int) $lead['company_id'];
        $leadId    = (int) $lead['id'];

        if (self::$depth >= self::MAX_DEPTH) {
            log_message('warning', "LeadFlow: automatizaciones detenidas por profundidad en el lead {$leadId} ({$trigger}).");

            return;
        }

        $model = new AutomationModel();
        $runs  = new AutomationRunModel();

        foreach ($model->activeFor($companyId, $trigger) as $auto) {
            $key = $auto['id'] . ':' . $leadId;
            if (isset(self::$done[$key])) {
                continue;
            }

            // El lead se relee en cada vuelta: una automatización anterior pudo cambiarlo.
            $fresh = (new LeadModel())->find($leadId);
            if (! $fresh || ! self::matches($auto, $fresh, $ctx)) {
                continue;
            }

            $ref = (int) $auto['once_per_lead'] === 1 ? (string) ($ctx['ref'] ?? 'lead:' . $leadId) : null;
            if ($ref !== null && $runs->ranAlready((int) $auto['id'], $ref)) {
                continue;
            }

            self::$done[$key] = true;
            self::$depth++;

            try {
                [$applied, $errors] = self::runActions($auto, $fresh, $ctx);
            } finally {
                self::$depth--;
            }

            try {
                $runs->insert([
                    'company_id'    => $companyId,
                    'automation_id' => (int) $auto['id'],
                    'lead_id'       => $leadId,
                    'ref'           => $ref,
                    'status'        => $errors ? 'error' : 'ok',
                    'actions'       => json_encode($applied, JSON_UNESCAPED_UNICODE),
                    'message'       => $errors ? mb_substr(implode(' · ', $errors), 0, 255) : null,
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                // Otra petición ganó la carrera del UNIQUE: la automatización ya corrió.
                continue;
            }

            $model->skipValidation(true)->update((int) $auto['id'], [
                'runs'        => (int) $auto['runs'] + 1,
                'last_run_at' => date('Y-m-d H:i:s'),
            ]);
            $model->skipValidation(false);

            Timeline::log($companyId, $leadId, 'automation',
                lang('Lfl.tlAutomation', [$auto['name'], count($applied)]), ['actions' => $applied], null);
        }
    }

    // ── Condiciones ──────────────────────────────────────────────────────────

    public static function matches(array $auto, array $lead, array $ctx = []): bool
    {
        $conditions = AutomationModel::conditions($auto);
        if (! $conditions) {
            return true; // sin condiciones, siempre
        }
        $any = ($auto['match_mode'] ?? 'all') === 'any';

        foreach ($conditions as $c) {
            $ok = self::test($lead[$c['field']] ?? null, $c['op'], $c['value']);
            if ($any && $ok) {
                return true;
            }
            if (! $any && ! $ok) {
                return false;
            }
        }

        return ! $any;
    }

    private static function test($actual, string $op, string $expected): bool
    {
        $a = is_scalar($actual) ? trim((string) $actual) : '';
        $e = trim($expected);

        return match ($op) {
            'equals'       => mb_strtolower($a) === mb_strtolower($e),
            'not_equals'   => mb_strtolower($a) !== mb_strtolower($e),
            'contains'     => $e !== '' && str_contains(mb_strtolower($a), mb_strtolower($e)),
            'greater_than' => is_numeric($a) && is_numeric($e) && (float) $a > (float) $e,
            'less_than'    => is_numeric($a) && is_numeric($e) && (float) $a < (float) $e,
            'is_empty'     => $a === '' || $a === '0',
            'is_not_empty' => $a !== '' && $a !== '0',
            default        => false,
        };
    }

    // ── Acciones ─────────────────────────────────────────────────────────────

    /** @return array{0:array,1:array} acciones aplicadas y errores */
    private static function runActions(array $auto, array $lead, array $ctx): array
    {
        $applied = [];
        $errors  = [];

        foreach (AutomationModel::actions($auto) as $action) {
            try {
                $lead   = (new LeadModel())->find((int) $lead['id']) ?: $lead;
                $result = self::runAction($auto, $action, $lead, $ctx);
                if ($result !== null) {
                    $applied[] = ['type' => $action['type'], 'detail' => $result];
                }
            } catch (\Throwable $e) {
                $errors[] = $action['type'] . ': ' . $e->getMessage();
                log_message('error', 'LeadFlow automatización ' . $auto['id'] . ' · ' . $action['type'] . ': ' . $e->getMessage());
            }
        }

        return [$applied, $errors];
    }

    /** @return string|null lo que hizo, o null si no había nada que hacer */
    private static function runAction(array $auto, array $action, array $lead, array $ctx): ?string
    {
        $companyId = (int) $lead['company_id'];
        $leads     = new LeadService();
        $system    = Access::system($companyId);

        switch ($action['type']) {
            case 'assign_advisor':
                $userId = self::pickAdvisor($auto, $action, $lead);
                if (! $userId || (int) ($lead['assigned_user_id'] ?? 0) === $userId) {
                    return null;
                }
                $r = $leads->assign($lead, $userId, $lead['lawyer_user_id'] ? (int) $lead['lawyer_user_id'] : null, $system);

                return $r['ok'] ? (Team::users($companyId)[$userId] ?? '#' . $userId) : null;

            case 'set_priority':
                $r = $leads->setPriority($lead, (string) $action['priority']);

                return $r['ok'] ? (string) $action['priority'] : null;

            case 'move_stage':
                $stage = (new StageModel())->owned((int) $action['stage_id'], $companyId);
                if (! $stage || (int) $lead['stage_id'] === (int) $stage['id']) {
                    return null;
                }
                $r = $leads->moveStage($lead, (int) $stage['id'], ['reason' => (string) ($action['reason'] ?? '')]);
                if (! $r['ok']) {
                    throw new \RuntimeException($r['message']);
                }

                return $stage['name'];

            case 'create_activity':
                $when = time() + ((int) ($action['due_in_hours'] ?? 24) * 3600);
                $to   = ($action['assign_to'] ?? 'lead_owner') === 'user'
                    ? (int) ($action['user_id'] ?? 0)
                    : (int) ($lead['assigned_user_id'] ?? 0);
                $r = (new EngagementService())->addActivity($lead, [
                    'activity_type'    => $action['activity_type'] ?? 'follow_up',
                    'title'            => self::render((string) ($action['title'] ?? ''), $lead),
                    'due_date'         => date('Y-m-d', $when),
                    'due_time'         => date('H:i', $when),
                    'assigned_user_id' => $to,
                    'priority'         => $lead['priority'] ?? 'medium',
                ]);
                if (! $r['ok']) {
                    throw new \RuntimeException($r['message']);
                }

                return date('d/m/Y H:i', $when);

            case 'add_note':
                (new EngagementService())->addNote($lead, self::render((string) $action['body'], $lead), false);

                return lang('Lfl.noteNew');

            case 'send_email':
                if (empty($lead['email'])) {
                    return null; // sin correo no hay nada que enviar; no es un error
                }
                $r = (new EngagementService())->sendEmail($lead,
                    self::render((string) $action['subject'], $lead),
                    self::render((string) $action['body'], $lead));
                if (! $r['ok']) {
                    throw new \RuntimeException($r['message']);
                }

                return (string) $lead['email'];

            case 'notify_user':
                return self::notify($action, $lead);

            case 'recalculate_score':
                return (string) (new ScoringService())->recalculate((int) $lead['id'], $companyId, 'automation');

            default:
                return null;
        }
    }

    /**
     * A quién asignar. El reparto por turnos usa `rr_index` de la propia
     * automatización: así dos leads seguidos no caen en el mismo asesor.
     */
    private static function pickAdvisor(array $auto, array $action, array $lead): ?int
    {
        $companyId = (int) $lead['company_id'];

        switch ($action['mode'] ?? 'round_robin') {
            case 'user':
                return Team::isMember($companyId, (int) ($action['user_id'] ?? 0)) ? (int) $action['user_id'] : null;

            case 'service_owner':
                $service = ! empty($lead['service_id']) ? (new ServiceModel())->owned((int) $lead['service_id'], $companyId) : null;
                $owner   = (int) ($service['owner_user_id'] ?? 0);

                return $owner && Team::isMember($companyId, $owner) ? $owner : null;

            default:
                $advisors = [];
                foreach ((new \Components\LeadFlow\Models\MemberModel())->mapFor($companyId) as $uid => $m) {
                    if ($m['role'] === 'advisor' && (int) $m['is_active'] === 1 && Team::isMember($companyId, (int) $uid)) {
                        $advisors[] = (int) $uid;
                    }
                }
                if (! $advisors) {
                    return null;
                }
                sort($advisors);
                $i = ((int) $auto['rr_index']) % count($advisors);
                (new AutomationModel())->skipValidation(true)->update((int) $auto['id'], ['rr_index' => $i + 1]);

                return $advisors[$i];
        }
    }

    /** Aviso interno por correo (al supervisor, al abogado…), no al prospecto. */
    private static function notify(array $action, array $lead): ?string
    {
        $companyId = (int) $lead['company_id'];
        $userId    = (int) ($action['user_id'] ?? 0);
        if (! Team::isMember($companyId, $userId)) {
            return null;
        }

        $user = \Config\Database::connect()->table('users')->select('email')->where('id', $userId)->get()->getRowArray();
        if (empty($user['email'])) {
            return null;
        }

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">'
            . nl2br(esc(self::render((string) $action['body'], $lead)))
            . '<p><a href="' . esc(base_url('leadflow/leads/ficha/' . (int) $lead['id']), 'attr') . '">'
            . esc(lang('Lfl.viewCard')) . '</a></p></div>';

        $r = (new \App\Libraries\CompanyMailer())->send($companyId, (string) $user['email'],
            self::render((string) $action['subject'], $lead), $html);
        if (empty($r['ok'])) {
            throw new \RuntimeException((string) ($r['message'] ?? ''));
        }

        return (string) $user['email'];
    }

    /** Sustituye los marcadores `{nombre}`, `{servicio}`… en los textos de las acciones. */
    public static function render(string $text, array $lead): string
    {
        if ($text === '' || ! str_contains($text, '{')) {
            return $text;
        }
        $companyId = (int) $lead['company_id'];
        $service   = ! empty($lead['service_id']) ? (new ServiceModel())->find((int) $lead['service_id']) : null;
        $stage     = ! empty($lead['stage_id']) ? (new StageModel())->find((int) $lead['stage_id']) : null;
        $company   = (new \App\Models\CompanyModel())->find($companyId);
        $team      = Team::users($companyId);

        $map = [
            'nombre'          => (string) $lead['first_name'],
            'apellido'        => (string) $lead['last_name'],
            'nombre_completo' => LeadModel::fullName($lead),
            'servicio'        => (string) ($service['name'] ?? ''),
            'etapa'           => (string) ($stage['name'] ?? ''),
            'score'           => (string) (int) $lead['score'],
            'empresa'         => (string) ($company['name'] ?? ''),
            'asesor'          => (string) ($team[(int) ($lead['assigned_user_id'] ?? 0)] ?? ''),
            'correo'          => (string) ($lead['email'] ?? ''),
            'telefono'        => (string) ($lead['phone'] ?? ''),
        ];

        foreach ($map as $k => $v) {
            $text = str_replace('{' . $k . '}', $v, $text);
        }

        return $text;
    }
}
