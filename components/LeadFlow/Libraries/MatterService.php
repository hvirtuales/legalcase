<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\MatterFeeModel;
use Components\LeadFlow\Models\MatterModel;
use Components\LeadFlow\Models\MatterPaymentModel;
use Components\LeadFlow\Models\MatterRequirementModel;
use Components\LeadFlow\Models\MatterStageModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\ServiceRequirementModel;
use Components\LeadFlow\Models\SettingsModel;

/**
 * Las reglas del expediente: abrirlo, moverlo de etapa procesal, cerrarlo y borrarlo.
 *
 * Es la segunda salida legítima del embudo, junto a `ConversionService`. La diferencia
 * es que esta no depende de nadie de fuera: una firma sin Docketwise también puede
 * cerrar una venta y seguir trabajando el caso.
 *
 * ── Qué le pasa al lead ──────────────────────────────────────────────────────
 *
 * Queda EXACTAMENTE igual que cuando se transfería a Docketwise: etapa terminal,
 * `status='converted'`, `converted_at`. Es deliberado: así ni el pipeline, ni el
 * Kanban, ni `ReportService` ni los reportes de conversión cambian una sola consulta.
 * El expediente es una razón nueva de llegar a ese estado, no un estado nuevo.
 *
 * A partir de ahí el expediente **no vuelve a tocar el lead**. Si el caso se niega, el
 * lead NO vuelve a `lost`: falsearía el reporte comercial, porque la firma sí vendió.
 * El embudo termina en `converted`; de ahí en adelante manda `matters.status`.
 *
 * ── Qué va dentro de la transacción y qué no ─────────────────────────────────
 *
 * Dentro: el consecutivo y el insert. Fuera: marcar el lead, la línea de tiempo y las
 * automatizaciones. El criterio es el mismo que ya rige en el componente —«una
 * automatización no puede impedir que alguien guarde»—: si el motor falla, no puede
 * revertir un expediente que ya consumió número.
 */
class MatterService
{
    private MatterModel $matters;
    private MatterStageModel $stages;

    public function __construct()
    {
        $this->matters = new MatterModel();
        $this->stages  = new MatterStageModel();
    }

    private static function ok(string $message, int $id = 0, array $extra = []): array
    {
        return ['ok' => true, 'message' => $message, 'id' => $id] + $extra;
    }

    private static function fail(string $message, array $extra = []): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0] + $extra;
    }

    /**
     * Lo que impide abrir expediente, en frases. Vacío = se puede.
     *
     * Mismo contrato que `ConversionService::problems()`, para que la pantalla de
     * apertura se pinte igual que la de conversión.
     *
     * El servicio NO bloquea: un caso puede abrirse antes de decidir el trámite exacto
     * y el checklist nace vacío. El responsable SÍ, porque un expediente sin abogado
     * responsable no es un expediente, es una carpeta.
     *
     * @return string[]
     */
    public function problems(array $lead, array $in = []): array
    {
        $out = [];

        if ($this->matters->existsForLead((int) $lead['id'])) {
            $out[] = lang('Lfl.mtrAlready');
        }
        if (! in_array((string) ($lead['status'] ?? ''), ['won', 'converted'], true)) {
            $out[] = lang('Lfl.mtrNotWon');
        }
        if (trim((string) $lead['first_name']) === '' || trim((string) $lead['last_name']) === '') {
            $out[] = lang('Lfl.mtrNeedNames');
        }
        if ($this->lawyerOf($lead, $in) === null) {
            $out[] = lang('Lfl.mtrNeedLawyer');
        }

        return $out;
    }

    /**
     * El consecutivo de la firma: `EXP-000001`.
     *
     * Calcado de `VoucherService::nextNumber()` de Contabilidad, y la forma importa:
     * el incremento es una EXPRESIÓN evaluada en el servidor (`matter_sequence + 1`,
     * con el tercer argumento `false` para que CI4 no la escape), así que el motor
     * bloquea la fila de ajustes de esa firma y dos aperturas simultáneas se
     * serializan ahí. Leer y luego escribir desde PHP sí daría números repetidos.
     *
     * Se llama DENTRO de la transacción del insert: CI4 cuenta las transacciones
     * anidadas, así que si el insert falla el consecutivo se revierte con él y no deja
     * un hueco en la numeración.
     */
    public function nextNumber(int $companyId): string
    {
        $db = db_connect();
        (new SettingsModel())->forCompany($companyId); // garantiza que la fila exista

        $db->transStart();
        $db->table('cmp_lfl_settings')->where('company_id', $companyId)
            ->set('matter_sequence', 'matter_sequence + 1', false)->update();
        $row = $db->table('cmp_lfl_settings')->select('matter_prefix, matter_sequence')
            ->where('company_id', $companyId)->get()->getRowArray();
        $db->transComplete();

        $prefijo = trim((string) ($row['matter_prefix'] ?? '')) !== '' ? (string) $row['matter_prefix'] : 'EXP';
        $seq     = (int) ($row['matter_sequence'] ?? 1);

        return $prefijo . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Abre el expediente del lead.
     *
     * @param array $in lawyer_user_id, assigned_user_id, service_id, title, summary
     */
    public function open(array $lead, array $in = [], ?int $userId = null): array
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];

        $problemas = $this->problems($lead, $in);
        if ($problemas !== []) {
            return self::fail(implode(' ', $problemas));
        }

        $stage = $this->stages->byCode($cid, 'intake')
            ?: ($this->stages->ordered($cid, true)[0] ?? null);

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->transStart();

        $data = [
            'company_id'       => $cid,
            'lead_id'          => $lid,
            'number'           => $this->nextNumber($cid),
            'stage_id'         => $stage ? (int) $stage['id'] : null,
            'stage_entered_at' => $stage ? $now : null,
            'status'           => $stage ? MatterStageModel::statusFor((string) $stage['kind']) : 'active',
            'opened_at'        => $now,
            'opened_by'        => $userId ?: null,
            'service_id'       => $this->serviceOf($lead, $in),
            'lawyer_user_id'   => $this->lawyerOf($lead, $in),
            'assigned_user_id' => $this->memberOrNull($cid, $in['assigned_user_id'] ?? ($lead['assigned_user_id'] ?? null)),
            'summary'          => trim((string) ($in['summary'] ?? ($lead['summary'] ?? ''))) ?: null,
        ] + $this->snapshot($lead, $in);

        // El título se compone DESPUÉS del snapshot porque sale de él.
        $data['title'] = mb_substr(trim((string) ($in['title'] ?? '')) ?: $this->defaultTitle($data), 0, 160);

        if (! $this->matters->insert($data)) {
            $db->transRollback();

            return self::fail(lang('Lfl.mtrOpenFailed'));
        }
        $id = (int) $this->matters->getInsertID();

        // El checklist y la primera línea de honorarios van DENTRO de la transacción:
        // un expediente a medio montar es peor que uno que no se abrió.
        $this->copyChecklist($id, $cid, (int) ($data['service_id'] ?? 0));
        $this->seedFee($id, $cid, $lead, (string) $data['currency']);

        $db->transComplete();

        if ($id <= 0) {
            return self::fail(lang('Lfl.mtrOpenFailed'));
        }

        // Los rollups se calculan desde las hijas ya insertadas, no se suponen.
        $this->refreshChecklist($id);
        $this->refreshTotals($id);

        /*
         * Fuera de la transacción: el expediente ya existe y consumió número. Lo que
         * sigue son efectos sobre OTRAS cosas y ninguno puede deshacerlo.
         */
        (new LeadService())->markConverted($lead, $userId);

        Timeline::log($cid, $lid, 'matter', lang('Lfl.tlMatterOpened', [$data['number']]),
            ['matter' => $id, 'number' => $data['number']], $userId);

        AutomationEngine::fire('matter_opened', (new LeadModel())->find($lid), [
            'ref'    => 'matter:' . $id,
            'matter' => $id,
        ]);

        return self::ok(lang('Lfl.mtrOpened', [$data['number']]), $id);
    }

    /**
     * Mueve el expediente de etapa procesal.
     *
     * A diferencia de `LeadService::advanceTo()`, aquí el movimiento es libre en los
     * dos sentidos entre etapas no terminales: un trámite SÍ retrocede. Una solicitud
     * de evidencia adicional te devuelve a recolección documental, y eso es el curso
     * normal del caso, no una corrección de un error.
     *
     * @param array $extra reason, note
     */
    public function moveStage(array $matter, int $stageId, array $extra = [], ?int $userId = null): array
    {
        $cid = (int) $matter['company_id'];

        if (MatterModel::isClosed($matter)) {
            return self::fail(lang('Lfl.mtrClosedLocked'));
        }

        $target = $this->stages->owned($stageId, $cid);
        if (! $target || (int) $target['is_active'] !== 1) {
            return self::fail(lang('Lfl.errStage'));
        }
        if ((int) ($matter['stage_id'] ?? 0) === $stageId) {
            return self::ok(lang('Lfl.okNoChanges'), (int) $matter['id']);
        }

        $kind = (string) $target['kind'];

        // Cerrar por etapa exige motivo, igual que perder un lead exige razón: un
        // cierre sin motivo no se puede leer después.
        if (MatterStageModel::isTerminal($kind)) {
            return $this->close($matter, (string) ($extra['reason'] ?? ''), $extra['note'] ?? null, $userId, $stageId);
        }

        /*
         * Radicar es el punto sin vuelta atrás del trámite, así que es el único
         * movimiento con requisitos propios. Los declara `MatterSteps`, y lo que
         * bloquea es solo lo marcado como `error` —hoy, no tener responsable—: los
         * documentos obligatorios que falten avisan, porque hay trámites que se
         * radican con lo que hay y se completan después.
         */
        if ($kind === 'filed') {
            $bloqueo = MatterSteps::blocker(MatterSteps::evaluate($matter), 'filing');
            if ($bloqueo !== null) {
                return self::fail(lang('Lfl.mtrCannotFile', [$bloqueo]));
            }
        }

        $now = date('Y-m-d H:i:s');
        // Null-safe a propósito: la etapa de origen puede estar borrada (soft-delete) y
        // `find()` devolvería null, que indexado reventaría el movimiento entero.
        $previa = ! empty($matter['stage_id']) ? $this->stages->find((int) $matter['stage_id']) : null;
        $desde  = $previa['name'] ?? '—';

        $this->matters->update((int) $matter['id'], [
            'stage_id'         => $stageId,
            'stage_entered_at' => $now,
            'status'           => MatterStageModel::statusFor($kind),
        ]);

        Timeline::log($cid, (int) $matter['lead_id'], 'matter',
            lang('Lfl.tlMatterStage', [$desde, $target['name']]),
            ['matter' => (int) $matter['id'], 'from' => $desde, 'to' => $target['name']], $userId);

        AutomationEngine::fire('matter_stage_changed', (new LeadModel())->find((int) $matter['lead_id']), [
            'ref'    => 'mstage:' . (int) $matter['id'] . ':' . $stageId,
            'matter' => (int) $matter['id'],
        ]);

        return self::ok(lang('Lfl.mtrStageMoved', [$target['name']]), (int) $matter['id']);
    }

    /**
     * Cierra el expediente con su motivo.
     *
     * El motivo es obligatorio y de la lista: `won` y `denied` son el resultado del
     * trámite, `withdrawn` lo retiró el cliente y `transferred` se fue a otra firma.
     * Solo el primero es un caso ganado, y mezclarlos haría ilegible cualquier
     * medición posterior.
     *
     * @param int|null $stageId etapa terminal concreta; si no, la de código `closed`
     */
    public function close(array $matter, string $reason, ?string $note = null, ?int $userId = null, ?int $stageId = null): array
    {
        $cid = (int) $matter['company_id'];

        if (MatterModel::isClosed($matter)) {
            return self::fail(lang('Lfl.mtrClosedLocked'));
        }
        if (! in_array($reason, MatterModel::CLOSE_REASONS, true)) {
            return self::fail(lang('Lfl.mtrNeedReason'));
        }

        $stage = $stageId ? $this->stages->owned($stageId, $cid) : $this->stages->byCode($cid, 'closed');
        $kind  = $stage ? (string) $stage['kind'] : 'closed';
        if (! MatterStageModel::isTerminal($kind)) {
            $kind = 'closed';
        }

        $now = date('Y-m-d H:i:s');
        $this->matters->update((int) $matter['id'], [
            'stage_id'         => $stage ? (int) $stage['id'] : $matter['stage_id'],
            'stage_entered_at' => $now,
            'status'           => MatterStageModel::statusFor($kind),
            'closed_at'        => $now,
            'close_reason'     => $reason,
            'close_note'       => $note !== null ? mb_substr(trim($note), 0, 255) : null,
        ]);

        Timeline::log($cid, (int) $matter['lead_id'], 'matter',
            lang('Lfl.tlMatterClosed', [$matter['number'], lang('Lfl.' . Meta::CLOSE_REASONS[$reason])]),
            ['matter' => (int) $matter['id'], 'reason' => $reason], $userId);

        AutomationEngine::fire('matter_closed', (new LeadModel())->find((int) $matter['lead_id']), [
            'ref'    => 'matter:' . (int) $matter['id'] . ':closed',
            'matter' => (int) $matter['id'],
        ]);

        return self::ok(lang('Lfl.mtrClosed'), (int) $matter['id']);
    }

    /**
     * Reabre un expediente cerrado.
     *
     * Existe porque un caso se reabre de verdad: llega una solicitud de evidencia
     * después de haberlo dado por terminado. Limpia el cierre pero **no borra el
     * rastro**: el evento de cierre sigue en la línea de tiempo.
     */
    public function reopen(array $matter, ?int $userId = null): array
    {
        if (! MatterModel::isClosed($matter)) {
            return self::fail(lang('Lfl.mtrNotClosed'));
        }

        $cid   = (int) $matter['company_id'];
        $stage = $this->stages->byCode($cid, 'evidence')
            ?: $this->stages->byCode($cid, 'intake')
            ?: ($this->stages->ordered($cid, true)[0] ?? null);

        $this->matters->update((int) $matter['id'], [
            'stage_id'         => $stage ? (int) $stage['id'] : null,
            'stage_entered_at' => date('Y-m-d H:i:s'),
            'status'           => $stage ? MatterStageModel::statusFor((string) $stage['kind']) : 'active',
            'closed_at'        => null,
            'close_reason'     => null,
            'close_note'       => null,
        ]);

        Timeline::log($cid, (int) $matter['lead_id'], 'matter',
            lang('Lfl.tlMatterReopened', [$matter['number']]),
            ['matter' => (int) $matter['id']], $userId);

        return self::ok(lang('Lfl.mtrReopened'), (int) $matter['id']);
    }

    /** Edita los datos propios del expediente. No toca número, etapa ni estado. */
    public function update(array $matter, array $in, ?int $userId = null): array
    {
        $cid  = (int) $matter['company_id'];
        $data = [
            'title'            => mb_substr(trim((string) ($in['title'] ?? '')), 0, 160) ?: null,
            'summary'          => trim((string) ($in['summary'] ?? '')) ?: null,
            'service_id'       => $this->ownedService($cid, $in['service_id'] ?? null),
            'lawyer_user_id'   => $this->memberOrNull($cid, $in['lawyer_user_id'] ?? null),
            'assigned_user_id' => $this->memberOrNull($cid, $in['assigned_user_id'] ?? null),
        ];

        if ($data['lawyer_user_id'] === null) {
            return self::fail(lang('Lfl.mtrNeedLawyer'));
        }

        // El nombre del abogado forma parte del snapshot, así que si cambia el
        // responsable hay que reescribirlo: si no, el expediente seguiría imprimiendo
        // el nombre del anterior.
        $data['lawyer_name'] = Team::users($cid)[$data['lawyer_user_id']] ?? null;

        $this->matters->update((int) $matter['id'], $data);

        Timeline::log($cid, (int) $matter['lead_id'], 'matter',
            lang('Lfl.tlMatterUpdated', [$matter['number']]),
            ['matter' => (int) $matter['id']], $userId);

        return self::ok(lang('Lfl.okUpdated'), (int) $matter['id']);
    }

    /**
     * Borra el expediente y sus hijas.
     *
     * El soft-delete NO dispara `ON DELETE CASCADE`, así que se limpian a mano, mismo
     * criterio que `LeadService::delete()`. Si se olvidara una, sus filas quedarían
     * vivas apuntando a un expediente borrado y volverían a aparecer el día que
     * alguien consultara por `company_id` en vez de por `matter_id`.
     */
    public function delete(array $matter): array
    {
        $mid = (int) $matter['id'];
        $cid = (int) $matter['company_id'];

        foreach ([new MatterPaymentModel(), new MatterFeeModel(), new MatterRequirementModel()] as $m) {
            $m->where('company_id', $cid)->where('matter_id', $mid)->delete();
        }
        $this->matters->delete($mid);

        return self::ok(lang('Lfl.okDeleted'), $mid);
    }

    // ── Checklist, dinero y plazos ──────────────────────────────────────────

    /**
     * Copia la plantilla del servicio al expediente recién abierto.
     *
     * Copia, no referencia: la firma afina su plantilla cada dos meses y un expediente
     * ya abierto no puede cambiar de requisitos porque alguien editó el catálogo. Es el
     * mismo criterio del snapshot de cliente y vendedor en una cotización.
     */
    private function copyChecklist(int $matterId, int $companyId, int $serviceId): void
    {
        if ($serviceId <= 0) {
            return;
        }

        $reqs = new MatterRequirementModel();
        foreach ((new ServiceRequirementModel())->forService($companyId, $serviceId, true) as $p) {
            $reqs->insert([
                'company_id'       => $companyId,
                'matter_id'        => $matterId,
                'requirement_id'   => (int) $p['id'],
                'label'            => $p['label'],
                'document_type_id' => $p['document_type_id'] ?: null,
                'is_required'      => (int) $p['is_required'],
                'applies_to'       => $p['applies_to'],
                'sort'             => (int) $p['sort'],
                'status'           => 'pending',
            ]);
        }
    }

    /**
     * Siembra la primera línea de honorarios con el valor ganado del lead.
     *
     * Es un punto de partida editable, no una verdad: el valor ganado es lo que se
     * cerró comercialmente, y el desglose real (honorario, tasas, gastos) lo hace la
     * firma después. Sin esta línea, todo expediente nacería en 0,00 y nadie la
     * crearía hasta que hiciera falta cobrar.
     */
    private function seedFee(int $matterId, int $companyId, array $lead, string $currency): void
    {
        $valor = (float) ($lead['won_value'] ?? 0);
        if ($valor <= 0) {
            return;
        }

        (new MatterFeeModel())->insert([
            'company_id' => $companyId,
            'matter_id'  => $matterId,
            'kind'       => MatterFeeModel::INCOME,
            'concept'    => lang('Lfl.mtrFeeFromLead'),
            'amount'     => $valor,
            'currency'   => $currency ?: 'USD',
            'sort'       => 10,
        ]);
    }

    /** Recalcula `docs_required` / `docs_received` desde las filas del checklist. */
    public function refreshChecklist(int $matterId): void
    {
        $m = $this->matters->find($matterId);
        if (! $m) {
            return;
        }

        $p = (new MatterRequirementModel())->progress($matterId, (int) $m['company_id']);
        $this->matters->skipValidation(true)->update($matterId, [
            'docs_required' => $p['required'],
            'docs_received' => $p['settled'],
        ]);
        $this->matters->skipValidation(false);
    }

    /**
     * Recalcula `fee_total` / `paid_total` desde las hijas.
     *
     * Se recalcula entero en vez de sumar deltas: un delta mal aplicado deja el total
     * desfasado para siempre y nadie lo nota. El SALDO no se guarda —es una columna
     * derivada de dos derivadas— y se calcula con `MatterModel::balance()`.
     */
    public function refreshTotals(int $matterId): void
    {
        $m = $this->matters->find($matterId);
        if (! $m) {
            return;
        }
        $cid = (int) $m['company_id'];

        $this->matters->skipValidation(true)->update($matterId, [
            'fee_total'  => (new MatterFeeModel())->totalFor($matterId, $cid),
            'paid_total' => (new MatterPaymentModel())->totalFor($matterId, $cid),
        ]);
        $this->matters->skipValidation(false);
    }

    /**
     * Recalcula `next_deadline_at`: el plazo más próximo del caso.
     *
     * Toma el mínimo entre dos fuentes, porque un plazo puede venir de las dos:
     * una actividad de tipo `deadline` del lead, o un requisito documental con fecha.
     * Precedente directo: `LeadService::refreshNextActivity()`, que hace lo mismo con
     * la próxima actividad del lead.
     *
     * Se desnormaliza para que el listado pueda ordenar por plazo sin dos subconsultas
     * por fila, y porque «¿qué vence primero?» es la pregunta que se hace al abrir la
     * pantalla.
     */
    public function refreshDeadline(int $matterId): void
    {
        $m = $this->matters->find($matterId);
        if (! $m) {
            return;
        }
        $cid = (int) $m['company_id'];

        $fechas = array_filter([
            (new ActivityModel())->nextDue((int) $m['lead_id'], $cid, 'deadline'),
            (new MatterRequirementModel())->forCompany($cid)
                ->where('matter_id', $matterId)
                ->where('due_at IS NOT NULL', null, false)
                ->whereNotIn('status', MatterRequirementModel::SETTLED)
                ->orderBy('due_at', 'asc')->first()['due_at'] ?? null,
        ]);

        $this->matters->skipValidation(true)->update($matterId, [
            'next_deadline_at' => $fechas === [] ? null : min($fechas),
        ]);
        $this->matters->skipValidation(false);
    }

    /**
     * Refresca lo que dependa del lead cuando algo cambia fuera del expediente.
     *
     * La llaman `EngagementService` al crear o cerrar una actividad y `Requirements`
     * al mover un requisito. Si el lead no tiene expediente no hace nada, que es el
     * caso normal: así `EngagementService` no tiene que saber si existe.
     */
    public static function touchByLead(int $leadId): void
    {
        $m = (new MatterModel())->ofLead($leadId);
        if ($m !== null) {
            (new self())->refreshDeadline((int) $m['id']);
        }
    }

    // ── Piezas internas ─────────────────────────────────────────────────────

    /**
     * El snapshot: FK y copia a la vez, como `QuoteService::clientSnapshot()`.
     *
     * La FK sirve para navegar; la copia, para imprimir. Si el servicio se renombra o
     * el abogado deja la firma, el expediente sigue diciendo lo que decía el día que
     * se abrió, que es lo que el cliente firmó.
     */
    private function snapshot(array $lead, array $in): array
    {
        $cid     = (int) $lead['company_id'];
        $svcId   = $this->serviceOf($lead, $in);
        $service = $svcId ? (new ServiceModel())->owned($svcId, $cid) : null;
        $lawyer  = $this->lawyerOf($lead, $in);

        return [
            'client_first_name' => $lead['first_name'] ?? null,
            'client_last_name'  => $lead['last_name'] ?? null,
            'client_email'      => $lead['email'] ?? null,
            'client_phone'      => $lead['phone'] ?? null,
            'client_whatsapp'   => $lead['whatsapp'] ?? null,
            'client_country'    => $lead['country'] ?? null,
            'client_city'       => $lead['city'] ?? null,
            'service_name'      => $service['name'] ?? null,
            'matter_type_name'  => $service['matter_type_name'] ?? null,
            'lawyer_name'       => $lawyer ? (Team::users($cid)[$lawyer] ?? null) : null,
            'currency'          => (new SettingsModel())->forCompany($cid)['currency'] ?? 'USD',
        ];
    }

    /**
     * «Visa familiar — María López», o el nombre del cliente si no hay servicio.
     *
     * Manda el nombre del SERVICIO, no `matter_type_name`. El tipo de Matter es un
     * código de mapeo de Docketwise y en esta instalación se ve por qué: dos servicios
     * distintos («Visa familiar» y «Asilo») comparten el tipo «Profesionales», así que
     * titular por ahí daría dos expedientes llamados igual y ninguno reconocible para
     * el cliente. El tipo queda como respaldo y en su propio campo del snapshot.
     */
    private function defaultTitle(array $data): string
    {
        $cliente = trim(($data['client_first_name'] ?? '') . ' ' . ($data['client_last_name'] ?? ''));
        $svc     = trim((string) ($data['service_name'] ?? '')) ?: trim((string) ($data['matter_type_name'] ?? ''));

        return $svc !== '' ? $svc . ' — ' . $cliente : ($cliente ?: lang('Lfl.navMatters'));
    }

    /** El servicio: el del POST si es de la firma, si no el del lead. */
    private function serviceOf(array $lead, array $in): ?int
    {
        $cid = (int) $lead['company_id'];

        return $this->ownedService($cid, $in['service_id'] ?? null)
            ?? $this->ownedService($cid, $lead['service_id'] ?? null);
    }

    private function ownedService(int $companyId, $id): ?int
    {
        $id = (int) $id;

        return $id > 0 && (new ServiceModel())->owned($id, $companyId) ? $id : null;
    }

    /**
     * El responsable: el del POST, el abogado del lead, o nada.
     *
     * Siempre contra `Team::isMember()`: un id que llega del formulario no se guarda
     * sin comprobar que esa persona es de la firma.
     */
    private function lawyerOf(array $lead, array $in): ?int
    {
        $cid = (int) $lead['company_id'];

        return $this->memberOrNull($cid, $in['lawyer_user_id'] ?? null)
            ?? $this->memberOrNull($cid, $lead['lawyer_user_id'] ?? null);
    }

    private function memberOrNull(int $companyId, $userId): ?int
    {
        $uid = (int) $userId;

        return $uid > 0 && Team::isMember($companyId, $uid) ? $uid : null;
    }
}
