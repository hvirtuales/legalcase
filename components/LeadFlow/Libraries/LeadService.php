<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\AppointmentModel;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\NoteModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;
use Components\LeadFlow\Models\StageHistoryModel;
use Components\LeadFlow\Models\StageModel;

/**
 * Las reglas del lead: alta (con duplicados y atribución), edición, etapas,
 * asignación, prioridad y baja.
 *
 * Todas las vías que crean o mueven un lead —alta manual, formulario público,
 * importación, Kanban, agenda, conversión— pasan por aquí, así que las reglas
 * (plan, duplicados, historial, línea de tiempo, score) no se pueden saltar
 * entrando por otra puerta.
 */
class LeadService
{
    private LeadModel $leads;

    public function __construct()
    {
        $this->leads = new LeadModel();
    }

    private static function ok(string $message, int $id = 0, array $extra = []): array
    {
        return ['ok' => true, 'message' => $message, 'id' => $id] + $extra;
    }

    private static function fail(string $message, array $extra = []): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0] + $extra;
    }

    private static function str($v, int $max): ?string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? null : mb_substr(strip_tags($v), 0, $max);
    }

    /** Recorta, limpia y calcula las columnas normalizadas. Solo toca lo que viene. */
    public static function normalize(array $d): array
    {
        $out = [];
        foreach (['first_name' => 80, 'middle_name' => 80, 'last_name' => 80, 'email' => 150, 'phone' => 30,
            'whatsapp' => 30, 'city' => 80, 'lost_note' => 255, 'landing_page' => 255, 'referrer' => 255,
            'utm_source' => 150, 'utm_medium' => 150, 'utm_campaign' => 150, 'utm_content' => 150, 'utm_term' => 150] as $k => $max) {
            if (array_key_exists($k, $d)) {
                $out[$k] = self::str($d[$k], $max);
            }
        }
        /*
         * El recibo de USCIS: forma correcta o nada.
         *
         * Un recibo mal escrito guardado "por si acaso" es peor que ninguno: el tick
         * lo consultaría cada día, gastaría cupo y devolvería siempre "no existe".
         * Se normaliza a mayúsculas sin espacios, que es como lo escribe USCIS.
         */
        if (array_key_exists('uscis_receipt', $d)) {
            $r = strtoupper(preg_replace('/\s+/', '', trim((string) $d['uscis_receipt'])) ?? '');
            $out['uscis_receipt'] = preg_match(\Components\LeadFlow\Libraries\Uscis\UscisService::RECEIPT_RE, $r) ? $r : null;
        }
        if (array_key_exists('summary', $d)) {
            $out['summary'] = self::str($d['summary'], 5000);
        }
        if (array_key_exists('email', $out)) {
            $out['email_norm'] = Dedupe::email($out['email']);
        }
        if (array_key_exists('phone', $out)) {
            $out['phone_norm'] = Dedupe::phone($out['phone']);
        }
        if (array_key_exists('whatsapp', $out)) {
            $out['whatsapp_norm'] = Dedupe::phone($out['whatsapp']);
        }
        if (array_key_exists('country', $d)) {
            $c              = strtoupper(trim((string) $d['country']));
            $out['country'] = Meta::isCountry($c) ? $c : null;
        }
        if (array_key_exists('preferred_language', $d)) {
            $out['preferred_language'] = in_array($d['preferred_language'], LeadModel::LANGUAGES, true) ? $d['preferred_language'] : null;
        }
        foreach (['potential_value', 'won_value'] as $k) {
            if (array_key_exists($k, $d)) {
                $v       = trim(str_replace(',', '.', (string) $d[$k]));
                $out[$k] = $v === '' || ! is_numeric($v) ? null : round((float) $v, 2);
            }
        }
        // `profile_id` va en esta lista y no en `checkRefs()`: el perfil del directorio
        // NO es de la firma que recibe el lead cuando la consulta llega de otra —es
        // global— y comprobarlo contra `company_id` lo descartaría siempre.
        foreach (['service_id', 'source_id', 'campaign_id', 'form_id', 'assigned_user_id', 'lawyer_user_id', 'profile_id'] as $k) {
            if (array_key_exists($k, $d)) {
                $out[$k] = (int) $d[$k] > 0 ? (int) $d[$k] : null;
            }
        }
        if (array_key_exists('requested_consultation', $d)) {
            $out['requested_consultation'] = empty($d['requested_consultation']) ? 0 : 1;
        }

        return $out;
    }

    /**
     * Anula las referencias que no son de la empresa: un id de servicio, campaña o
     * usuario de OTRA firma que llegue en el POST no se guarda.
     */
    private function checkRefs(int $companyId, array $d): array
    {
        $models = ['service_id' => ServiceModel::class, 'source_id' => SourceModel::class,
            'campaign_id' => CampaignModel::class, 'form_id' => FormModel::class];
        foreach ($models as $k => $class) {
            if (! empty($d[$k]) && ! (new $class())->owned((int) $d[$k], $companyId)) {
                $d[$k] = null;
            }
        }
        foreach (['assigned_user_id', 'lawyer_user_id'] as $k) {
            if (! empty($d[$k]) && ! Team::isMember($companyId, (int) $d[$k])) {
                $d[$k] = null;
            }
        }

        return $d;
    }

    /** Fuente y campaña a partir de las UTM, si no vinieron explícitas. */
    private function attribute(int $companyId, array $d, string $channel): array
    {
        if (empty($d['campaign_id']) && ! empty($d['utm_campaign'])) {
            $c = (new CampaignModel())->byUtm($companyId, $d['utm_campaign']);
            if ($c) {
                $d['campaign_id'] = (int) $c['id'];
                if (empty($d['source_id']) && ! empty($c['source_id'])) {
                    $d['source_id'] = (int) $c['source_id'];
                }
            }
        }
        if (empty($d['source_id']) && ! empty($d['utm_source'])) {
            $s = (new SourceModel())->byUtm($companyId, $d['utm_source']);
            if ($s) {
                $d['source_id'] = (int) $s['id'];
            }
        }
        if (empty($d['source_id']) && $channel === 'public') {
            $s = (new SourceModel())->byKind($companyId, 'web');
            if ($s) {
                $d['source_id'] = (int) $s['id'];
            }
        }

        return $d;
    }

    private function initialStage(int $companyId): ?array
    {
        $stages = new StageModel();

        return $stages->byCode($companyId, 'new')
            ?? $stages->forCompany($companyId)->where('kind', 'open')->where('is_active', 1)->orderBy('sort', 'asc')->first();
    }

    /**
     * Da de alta un lead.
     *
     * @param array $opts channel (manual|public|import|demo), force (no mirar
     *                    duplicados), user_id
     *
     * @return array{ok:bool,message:string,id:int,duplicates?:array}
     */
    public function create(int $companyId, array $data, array $opts = []): array
    {
        $channel = $opts['channel'] ?? 'manual';
        $userId  = $opts['user_id'] ?? ((int) (session('user_id') ?? 0) ?: null);

        Catalog::ensure($companyId);

        if ($block = (new PlanGuard())->blockReason($companyId)) {
            return self::fail($block);
        }

        $clean = self::normalize($data);
        if (empty($clean['first_name']) || empty($clean['last_name'])) {
            return self::fail(lang('Lfl.vNamesRequired'));
        }

        if (empty($opts['force'])) {
            $dups = (new Dedupe())->find($companyId, $clean);
            if ($dups) {
                return self::fail(lang('Lfl.errDuplicate'), ['duplicates' => $dups]);
            }
        }

        $clean = $this->checkRefs($companyId, $this->attribute($companyId, $clean, $channel));

        if (empty($clean['assigned_user_id']) && ! empty($clean['service_id'])) {
            $svc = (new ServiceModel())->owned((int) $clean['service_id'], $companyId);
            if ($svc && ! empty($svc['owner_user_id']) && Team::isMember($companyId, (int) $svc['owner_user_id'])) {
                $clean['assigned_user_id'] = (int) $svc['owner_user_id'];
            }
        }

        $stage = $this->initialStage($companyId);
        $now   = date('Y-m-d H:i:s');

        $row = $clean + [
            'company_id'       => $companyId,
            'stage_id'         => $stage['id'] ?? null,
            'stage_entered_at' => $now,
            'status'           => 'open',
            'priority'         => 'low',
            'score'            => 0,
            'created_by'       => $userId,
        ];
        if (! empty($data['consent'])) {
            $row['consent_at'] = $now;
            $row['consent_ip'] = mb_substr((string) ($data['consent_ip'] ?? ''), 0, 45) ?: null;
        }
        $row['company_id'] = $companyId;

        if (! $this->leads->insert($row)) {
            return self::fail(implode(' ', $this->leads->errors()));
        }
        $id = (int) $this->leads->getInsertID();

        (new StageHistoryModel())->insert([
            'company_id' => $companyId, 'lead_id' => $id, 'from_stage_id' => null,
            'to_stage_id' => $stage['id'] ?? null, 'user_id' => $userId, 'created_at' => $now,
        ]);

        Timeline::log($companyId, $id, 'created', lang('Lfl.tlCreated', [lang('Lfl.chan_' . $channel)]), ['channel' => $channel], $userId);
        (new ScoringService())->recalculate($id, $companyId, 'created');

        // §19: el lead ya está completo y puntuado, así que las automatizaciones ven
        // su score y su servicio. Un fallo aquí no puede tumbar el alta.
        $fresh = $this->leads->find($id);
        if ($fresh) {
            AutomationEngine::fire('lead_created', $fresh);
        }

        return self::ok(lang('Lfl.okLeadCreated'), $id, ['duplicates' => []]);
    }

    /** Edita los datos del lead (no la etapa ni la asignación: tienen su propia vía). */
    public function update(array $lead, array $data): array
    {
        $cid   = (int) $lead['company_id'];
        $clean = $this->checkRefs($cid, self::normalize($data));
        unset($clean['assigned_user_id'], $clean['lawyer_user_id']);

        if (array_key_exists('first_name', $clean) && empty($clean['first_name'])) {
            return self::fail(lang('Lfl.vNamesRequired'));
        }
        if (array_key_exists('last_name', $clean) && empty($clean['last_name'])) {
            return self::fail(lang('Lfl.vNamesRequired'));
        }

        $changed = [];
        foreach ($clean as $k => $v) {
            if ((string) ($lead[$k] ?? '') !== (string) ($v ?? '') && ! str_ends_with($k, '_norm')) {
                $changed[] = $k;
            }
        }
        if (! $changed) {
            return self::ok(lang('Lfl.okNoChanges'), (int) $lead['id']);
        }

        if (! $this->leads->update((int) $lead['id'], $clean)) {
            return self::fail(implode(' ', $this->leads->errors()));
        }

        Timeline::log($cid, (int) $lead['id'], 'updated', lang('Lfl.tlUpdated'), ['fields' => $changed]);
        (new ScoringService())->recalculate((int) $lead['id'], $cid, 'updated');

        return self::ok(lang('Lfl.okUpdated'), (int) $lead['id']);
    }

    /**
     * Mueve el lead de etapa.
     *
     * Reglas: la etapa tiene que ser de la empresa y estar activa; a «convertido»
     * solo se llega convirtiendo (un arrastre en el Kanban no crea nada en
     * Docketwise); un lead convertido ya no se mueve; y los estados alternativos
     * (perdido, no calificado…) exigen motivo, que es lo que alimenta el reporte
     * de «por qué se pierden prospectos».
     *
     * @param array $extra reason, note, won_value, system (bool, uso interno)
     */
    public function moveStage(array $lead, int $stageId, array $extra = [], ?int $userId = null): array
    {
        $cid    = (int) $lead['company_id'];
        $stage  = (new StageModel())->owned($stageId, $cid);
        $system = ! empty($extra['system']);

        if (! $stage || (int) $stage['is_active'] !== 1) {
            return self::fail(lang('Lfl.errStage'));
        }
        if ((int) $lead['stage_id'] === $stageId) {
            return self::ok(lang('Lfl.okNoChanges'), (int) $lead['id']);
        }
        if ($stage['kind'] === 'converted' && ! $system) {
            return self::fail(lang('Lfl.errConvertManual'));
        }
        if ($lead['status'] === 'converted' && ! $system) {
            return self::fail(lang('Lfl.errLeadConverted'));
        }

        $now  = date('Y-m-d H:i:s');
        $data = [
            'stage_id'         => $stageId,
            'stage_entered_at' => $now,
            'status'           => match ($stage['kind']) {
                'won'       => 'won',
                'converted' => 'converted',
                'lost'      => 'lost',
                default     => 'open',
            },
            'lost_reason' => null,
            'lost_note'   => null,
        ];

        if ($stage['kind'] === 'lost') {
            $reason = (string) ($extra['reason'] ?? '');
            if (! in_array($reason, LeadModel::LOST_REASONS, true)) {
                return self::fail(lang('Lfl.errLostReason'));
            }
            $data['lost_reason'] = $reason;
            $data['lost_note']   = self::str($extra['note'] ?? null, 255);
        }
        if ($stage['kind'] === 'won' && empty($lead['won_at'])) {
            $data['won_at'] = $now;
        }
        if ($stage['kind'] === 'won' && isset($extra['won_value']) && is_numeric($extra['won_value'])) {
            $data['won_value'] = round((float) $extra['won_value'], 2);
        } elseif ($stage['kind'] === 'won' && empty($lead['won_value']) && ! empty($lead['potential_value'])) {
            $data['won_value'] = $lead['potential_value'];
        }
        if ($stage['kind'] === 'converted') {
            $data['converted_at'] = $now;
        }

        $this->leads->skipValidation(true)->update((int) $lead['id'], $data);
        $this->leads->skipValidation(false);

        $prev  = ! empty($lead['stage_id']) ? (new StageModel())->find((int) $lead['stage_id']) : null;
        $spent = ! empty($lead['stage_entered_at']) ? max(0, time() - strtotime($lead['stage_entered_at'])) : null;

        (new StageHistoryModel())->insert([
            'company_id' => $cid, 'lead_id' => (int) $lead['id'], 'from_stage_id' => $lead['stage_id'] ?: null,
            'to_stage_id' => $stageId, 'user_id' => $userId ?? ((int) (session('user_id') ?? 0) ?: null),
            'seconds_in_previous' => $spent, 'created_at' => $now,
        ]);

        $summary = lang('Lfl.tlStage', [$prev['name'] ?? '—', $stage['name']]);
        if ($stage['kind'] === 'lost') {
            $summary .= ' · ' . Meta::label(Meta::LOST_REASONS, $data['lost_reason']);
        }
        Timeline::log($cid, (int) $lead['id'], 'stage', $summary, ['from' => $lead['stage_id'], 'to' => $stageId], $userId);

        // §19. El «una sola vez» del cambio de etapa es por ETAPA y no por lead: una
        // automatización de «llegó a Propuesta» tiene que poder dispararse de nuevo si
        // el lead vuelve a pasar por ahí, pero no dos veces por la misma llegada.
        $fresh = $this->leads->find((int) $lead['id']);
        if ($fresh) {
            AutomationEngine::fire('stage_changed', $fresh, [
                'ref'  => 'stage:' . (int) $lead['id'] . ':' . $stageId,
                'from' => $lead['stage_id'],
                'to'   => $stageId,
            ]);
            if ($stage['kind'] === 'won') {
                AutomationEngine::fire('lead_won', $fresh);
            }
        }

        return self::ok(lang('Lfl.okStageMoved'), (int) $lead['id']);
    }

    /**
     * Avanza a una etapa de sistema si el lead está antes que ella.
     *
     * La usan la agenda (consulta programada / realizada) y el primer contacto: son
     * hechos que dicen en qué punto está el lead, pero **nunca lo hacen retroceder**
     * ni sacan de un estado alternativo o ganado.
     */
    public function advanceTo(array $lead, string $code, ?int $userId = null): void
    {
        $cid    = (int) $lead['company_id'];
        $stages = new StageModel();
        $target = $stages->byCode($cid, $code);
        if (! $target || (int) $target['is_active'] !== 1 || $lead['status'] !== 'open') {
            return;
        }
        $current = ! empty($lead['stage_id']) ? $stages->find((int) $lead['stage_id']) : null;
        if ($current && ((int) $current['sort'] >= (int) $target['sort'] || $current['kind'] !== 'open')) {
            return;
        }

        $this->moveStage($lead, (int) $target['id'], ['system' => true], $userId);
    }

    /**
     * Saca el lead del embudo: queda en la etapa terminal y con `status='converted'`.
     *
     * Hay DOS maneras legítimas de llegar aquí y las dos pasan por este método:
     * abrir un expediente interno (`MatterService::open()`) y transferir a Docketwise
     * (`ConversionService::run()`). Estaba escrito en línea dentro de la conversión, y
     * al aparecer la segunda vía había que elegir entre copiarlo o extraerlo: copiado,
     * dentro de un mes una de las dos ramas habría dejado de escribir `converted_at`.
     *
     * El `else` no es defensivo de más: la etapa es configurable y una firma puede
     * haberla borrado. Sin él, el lead se quedaría `won` para siempre y el expediente
     * existiría sin que el embudo lo reflejara.
     *
     * Escribir `kind='converted'` sigue exigiendo `['system' => true]` en `moveStage()`,
     * y este método es justamente la puerta que lo pasa: ninguna pantalla puede.
     */
    public function markConverted(array $lead, ?int $userId = null): void
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];

        if (($lead['status'] ?? '') === 'converted') {
            return;
        }

        $stage = (new StageModel())->byCode($cid, 'converted');
        if ($stage) {
            $this->moveStage($lead, (int) $stage['id'], ['system' => true], $userId);

            return;
        }

        (new LeadModel())->skipValidation(true)
            ->update($lid, ['status' => 'converted', 'converted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Asigna asesor y/o abogado.
     *
     * Quien ve todos los leads asigna a cualquiera del equipo; un asesor solo
     * puede **tomar** un lead sin asignar (asignárselo a sí mismo).
     */
    public function assign(array $lead, ?int $advisor, ?int $lawyer, Access $access): array
    {
        $cid = (int) $lead['company_id'];

        foreach ([$advisor, $lawyer] as $u) {
            if ($u !== null && $u > 0 && ! Team::isMember($cid, $u)) {
                return self::fail(lang('Lfl.errUserNotMember'));
            }
        }

        if (! $access->canAssignOthers()) {
            $takingIt = $advisor === $access->userId && empty($lead['assigned_user_id']);
            if (! $takingIt || ($lawyer ?? 0) !== (int) ($lead['lawyer_user_id'] ?? 0)) {
                return self::fail(lang('Lfl.errCannotAssign'));
            }
        }

        $data = ['assigned_user_id' => $advisor ?: null, 'lawyer_user_id' => $lawyer ?: null];
        if ((int) ($lead['assigned_user_id'] ?? 0) === (int) ($advisor ?? 0) && (int) ($lead['lawyer_user_id'] ?? 0) === (int) ($lawyer ?? 0)) {
            return self::ok(lang('Lfl.okNoChanges'), (int) $lead['id']);
        }

        $this->leads->skipValidation(true)->update((int) $lead['id'], $data);
        $this->leads->skipValidation(false);

        $team = Team::users($cid);
        Timeline::log($cid, (int) $lead['id'], 'assigned', lang('Lfl.tlAssigned', [
            $advisor ? ($team[$advisor] ?? '#' . $advisor) : lang('Lfl.nobody'),
            $lawyer ? ($team[$lawyer] ?? '#' . $lawyer) : lang('Lfl.nobody'),
        ]), $data);

        return self::ok(lang('Lfl.okAssigned'), (int) $lead['id']);
    }

    /** Fija la prioridad a mano, o la devuelve al score (`auto`). */
    public function setPriority(array $lead, string $priority): array
    {
        $cid = (int) $lead['company_id'];

        if ($priority === 'auto') {
            // Sin la marca manual, el recálculo vuelve a poner la prioridad de su banda.
            $this->leads->skipValidation(true)->update((int) $lead['id'], ['priority_manual' => 0]);
            $this->leads->skipValidation(false);
            (new ScoringService())->recalculate((int) $lead['id'], $cid, 'priority_auto');
            Timeline::log($cid, (int) $lead['id'], 'priority', lang('Lfl.tlPriorityAuto'));

            return self::ok(lang('Lfl.okUpdated'), (int) $lead['id']);
        }

        if (! in_array($priority, LeadModel::PRIORITIES, true)) {
            return self::fail(lang('Lfl.errInvalid'));
        }

        $this->leads->skipValidation(true)->update((int) $lead['id'], ['priority' => $priority, 'priority_manual' => 1]);
        $this->leads->skipValidation(false);
        Timeline::log($cid, (int) $lead['id'], 'priority', lang('Lfl.tlPriority', [Meta::label(Meta::PRIORITIES, $priority)]));

        return self::ok(lang('Lfl.okUpdated'), (int) $lead['id']);
    }

    /**
     * Baja del lead. Un soft-delete **no** dispara el CASCADE de la base, así que lo
     * que cuelga con soft-delete propio se da de baja aquí a mano.
     */
    public function delete(array $lead): array
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];

        foreach ([ActivityModel::class, NoteModel::class, AppointmentModel::class, DocumentModel::class] as $class) {
            (new $class())->where('company_id', $cid)->where('lead_id', $lid)->delete();
        }
        $this->leads->delete($lid);

        return self::ok(lang('Lfl.okDeleted'), $lid);
    }

    /**
     * Hubo un contacto saliente con el prospecto (llamada, email, WhatsApp).
     *
     * Fija la primera respuesta —la métrica que el §27 pide medir en especial— y
     * la última fecha de contacto; y si el lead seguía en «Nuevo», pasa a
     * «Contactado», que es exactamente lo que acaba de ocurrir.
     */
    public function touchOutbound(int $companyId, int $leadId, ?string $when = null): void
    {
        $lead = $this->leads->owned($leadId, $companyId);
        if (! $lead) {
            return;
        }
        $when ??= date('Y-m-d H:i:s');

        $data = ['last_contact_at' => $when];
        if (empty($lead['first_response_at'])) {
            $data['first_response_at'] = $when;
        }
        $this->leads->skipValidation(true)->update($leadId, $data);
        $this->leads->skipValidation(false);

        $this->advanceTo($lead + $data, 'contacted');
        (new ScoringService())->recalculate($leadId, $companyId, 'outbound');
    }

    /** El prospecto respondió. */
    public function touchInbound(int $companyId, int $leadId, ?string $when = null): void
    {
        if (! $this->leads->owned($leadId, $companyId)) {
            return;
        }
        $this->leads->skipValidation(true)->update($leadId, ['last_inbound_at' => $when ?? date('Y-m-d H:i:s')]);
        $this->leads->skipValidation(false);
        (new ScoringService())->recalculate($leadId, $companyId, 'inbound');
    }

    public function refreshNextActivity(int $companyId, int $leadId): void
    {
        $this->leads->skipValidation(true)->update($leadId, [
            'next_activity_at' => (new ActivityModel())->nextDue($leadId, $companyId),
        ]);
        $this->leads->skipValidation(false);
    }

    /** El lead, solo si es de la empresa activa y visible para el usuario. */
    public function visible(int $id, ?Access $access = null): ?array
    {
        $access ??= Access::current();
        $lead = $this->leads->owned($id, $access->companyId);

        return $access->canSee($lead) ? $lead : null;
    }
}
