<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Libraries\Integrations\IntegrationAdapter;
use Components\LeadFlow\Libraries\Integrations\IntegrationException;
use Components\LeadFlow\Models\ConversionModel;
use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\IntegrationLogModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\MappingModel;
use Components\LeadFlow\Models\MemberModel;
use Components\LeadFlow\Models\NoteModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SubmissionModel;

/**
 * Lead ganado → Contact + Matter en Docketwise (§20, §22).
 *
 * **Idempotente de verdad**, en dos niveles:
 *
 *  1. Una conversión por lead (`cmp_lfl_conversions.lead_id` UNIQUE). Un doble
 *     clic o dos pestañas encuentran la misma fila; si ya terminó, no se repite, y
 *     si está en curso, la segunda se rechaza.
 *  2. Cada paso **consulta el mapeo antes de crear** y **guarda el id externo en
 *     cuanto lo obtiene**. Si el paso 4 falla, un reintento encuentra el Contact
 *     y el Matter ya creados y continúa desde el 4: nunca crea un segundo Matter.
 *
 * Pasos: validar → contacto (crear o vincular) → matter → resumen de
 * precalificación → notas → documentos autorizados → marcar convertido.
 */
class ConversionService
{
    private const PROVIDER = IntegrationService::PROVIDER;

    /** Una conversión «en curso» de hace más de esto se considera colgada y se puede reintentar. */
    private const STALE_SECONDS = 120;

    private MappingModel $maps;
    private IntegrationLogModel $logs;
    private ConversionModel $conversions;

    /** Usuarios de la cuenta de Docketwise por empresa, para no pedirlos dos veces. */
    private array $accountCache = [];

    public function __construct(private ?IntegrationService $integrations = null)
    {
        $this->integrations ??= new IntegrationService();
        $this->maps        = new MappingModel();
        $this->logs        = new IntegrationLogModel();
        $this->conversions = new ConversionModel();
    }

    /**
     * Lo que impide convertir, en frases. Vacío = se puede.
     *
     * @return string[]
     */
    public function problems(array $lead): array
    {
        $out  = [];
        $prev = $this->conversions->forLead((int) $lead['id']);

        /*
         * `converted` ya NO significa «esto se fue a Docketwise».
         *
         * Desde que existe el expediente interno, un lead sale del embudo por dos vías
         * y la mayoría de las firmas no usa Docketwise. Lo que impide volver a
         * convertir no es el estado del lead, es que la conversión YA SE HIZO.
         *
         * Un lead convertido sin conversión hecha es el caso normal de una firma que
         * abrió expediente y después decide sincronizarlo: tiene que poder.
         */
        if ($prev && $prev['status'] === 'done') {
            $out[] = lang('Lfl.cvAlready');
        } elseif (! in_array((string) $lead['status'], ['won', 'converted'], true)
            && ! ($prev && $prev['status'] === 'failed')) {
            $out[] = lang('Lfl.cvNotWon');
        }
        if (trim((string) $lead['first_name']) === '' || trim((string) $lead['last_name']) === '') {
            $out[] = lang('Lfl.cvNeedNames');
        }
        if (empty($lead['email_norm']) && empty($lead['phone_norm']) && empty($lead['whatsapp_norm'])) {
            $out[] = lang('Lfl.cvNeedContact');
        }
        if (! $this->integrations->isConnected((int) $lead['company_id'])) {
            $out[] = lang('Lfl.errNotConnected');
        }

        return $out;
    }

    /**
     * Pide la conversión con las opciones del modal y la ejecuta.
     *
     * @param array $opts contact_mode (new|existing), existing_contact_id,
     *                    lawyer_user_id, transfer_info, include_summary,
     *                    notes[], documents[]
     */
    public function start(array $lead, array $opts, ?int $userId): array
    {
        $cid = (int) $lead['company_id'];

        $prev = $this->conversions->forLead((int) $lead['id']);
        if ($prev && $prev['status'] === 'done') {
            return ['ok' => true, 'message' => lang('Lfl.cvAlready')];
        }
        if ($prev && $prev['status'] === 'running' && strtotime((string) $prev['started_at']) > time() - self::STALE_SECONDS) {
            return ['ok' => false, 'message' => lang('Lfl.cvInProgress')];
        }

        if ($problems = $this->problems($lead)) {
            return ['ok' => false, 'message' => implode(' ', $problems)];
        }

        $options = $this->cleanOptions($lead, $opts);
        $data    = [
            'company_id'   => $cid,
            'lead_id'      => (int) $lead['id'],
            'provider'     => self::PROVIDER,
            'status'       => 'running',
            'options'      => json_encode($options),
            'attempts'     => (int) ($prev['attempts'] ?? 0) + 1,
            'last_error'   => null,
            'requested_by' => $userId,
            'started_at'   => date('Y-m-d H:i:s'),
        ];

        if ($prev) {
            $this->conversions->update((int) $prev['id'], $data);
            $id = (int) $prev['id'];
        } else {
            try {
                $this->conversions->insert($data);
                $id = (int) $this->conversions->getInsertID();
            } catch (\Throwable $e) {
                // Otra petición ganó la carrera del UNIQUE: se trata como «en curso».
                return ['ok' => false, 'message' => lang('Lfl.cvInProgress')];
            }
        }

        return $this->run($id, $lead);
    }

    /** Reintenta una conversión fallida con las mismas opciones. */
    public function retry(array $conversion, ?int $userId): array
    {
        $lead = (new LeadModel())->owned((int) $conversion['lead_id'], (int) $conversion['company_id']);
        if (! $lead) {
            return ['ok' => false, 'message' => lang('Lfl.errNotFound')];
        }
        if ($conversion['status'] === 'done') {
            return ['ok' => true, 'message' => lang('Lfl.cvAlready')];
        }

        return $this->start($lead, json_decode((string) $conversion['options'], true) ?: [], $userId);
    }

    /** Solo lo que es del lead y de la firma: lo demás del POST se descarta. */
    private function cleanOptions(array $lead, array $o): array
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];

        $notes = [];
        foreach ((array) ($o['notes'] ?? []) as $n) {
            $row = (new NoteModel())->owned((int) $n, $cid);
            if ($row && (int) $row['lead_id'] === $lid && (int) $row['is_transferable'] === 1) {
                $notes[] = (int) $row['id'];
            }
        }
        $docs = [];
        foreach ((array) ($o['documents'] ?? []) as $d) {
            $row = (new DocumentModel())->owned((int) $d, $cid);
            // Solo lo AUTORIZADO viaja a Docketwise (§24), aunque llegue marcado en el POST.
            if ($row && (int) $row['lead_id'] === $lid && (int) $row['is_authorized'] === 1) {
                $docs[] = (int) $row['id'];
            }
        }

        $lawyer = (int) ($o['lawyer_user_id'] ?? 0);

        return [
            'contact_mode'        => ($o['contact_mode'] ?? 'new') === 'existing' ? 'existing' : 'new',
            'existing_contact_id' => preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($o['existing_contact_id'] ?? '')),
            'lawyer_user_id'      => Team::isMember($cid, $lawyer) ? $lawyer : null,
            'transfer_info'       => ! empty($o['transfer_info']),
            'include_summary'     => ! empty($o['include_summary']),
            'notes'               => array_values(array_unique($notes)),
            'documents'           => array_values(array_unique($docs)),
        ];
    }

    /**
     * Los usuarios de la cuenta de Docketwise, una sola vez por conversión.
     *
     * No poder consultarlos no es motivo para parar: se sigue, y el requisito del
     * responsable lo dirá en su evento.
     */
    private function accountUsers(int $cid, IntegrationAdapter $a): array
    {
        if (! array_key_exists($cid, $this->accountCache)) {
            try {
                $this->accountCache[$cid] = $a->listUsers();
            } catch (\Throwable $e) {
                $this->accountCache[$cid] = [];
            }
        }

        return $this->accountCache[$cid];
    }

    /**
     * Deja un evento informativo por cada paso que se va a ejecutar y se planta
     * ante el primer requisito obligatorio que falte.
     *
     * El evento se guarda aunque todo esté bien: cuando una conversión falla, lo
     * que se quiere saber es qué había en ese momento, y eso no se puede
     * reconstruir después.
     */
    private function logRequirements(int $cid, int $lid, array $steps): void
    {
        foreach ($steps as $key => $step) {
            if (! $step['runs']) {
                continue;
            }

            $this->logs->log($cid, self::PROVIDER, 'req_' . $key, $step['ok'], ConversionSteps::summarize($step),
                $lid, null, ConversionSteps::detail($step));

            if (! $step['ok']) {
                throw new IntegrationException(lang('Lfl.cvMissingRequirement', [$step['label'], (string) ConversionSteps::blocker($step)]));
            }
        }
    }

    /** Ejecuta una llamada al proveedor dejando rastro en la bitácora. */
    private function call(int $cid, int $lid, string $action, callable $fn, string $summary = '')
    {
        try {
            $result = $fn();
            $this->logs->log($cid, self::PROVIDER, $action, true, is_string($result) ? lang('Lfl.logExternalId', [$result]) : null, $lid, null, $summary);

            return $result;
        } catch (\Throwable $e) {
            $this->logs->log($cid, self::PROVIDER, $action, false, $e->getMessage(), $lid,
                $e instanceof IntegrationException ? $e->httpCode() : null, $summary);
            throw $e;
        }
    }

    private function run(int $conversionId, array $lead): array
    {
        $cid  = (int) $lead['company_id'];
        $lid  = (int) $lead['id'];
        $conv = $this->conversions->find($conversionId);
        $opt  = json_decode((string) $conv['options'], true) ?: [];

        try {
            $adapter = $this->integrations->adapter($cid);

            // Un evento informativo por paso, con los requisitos que se cumplían al
            // enviar (`ConversionSteps`). Si falta uno obligatorio se para aquí, con
            // su nombre, en vez de dejar que el proveedor conteste un error genérico
            // que no dice qué dato es.
            $this->logRequirements($cid, $lid, ConversionSteps::evaluate($lead, $opt, [
                'accountUsers' => $this->accountUsers($cid, $adapter),
            ]));

            $contactId = $this->stepContact($adapter, $lead, $opt);
            $this->conversions->update($conversionId, ['contact_external_id' => $contactId]);

            $matterId = $this->stepMatter($adapter, $lead, $opt, $contactId);
            $this->conversions->update($conversionId, ['matter_external_id' => $matterId]);

            if (! empty($opt['include_summary'])) {
                $this->stepSummary($adapter, $lead, $contactId, $matterId);
            }
            foreach ($opt['notes'] ?? [] as $noteId) {
                $this->stepNote($adapter, $lead, (int) $noteId, $matterId);
            }
            foreach ($opt['documents'] ?? [] as $docId) {
                $this->stepDocument($adapter, $lead, (int) $docId, $contactId, $matterId);
            }
        } catch (\Throwable $e) {
            $this->conversions->update($conversionId, [
                'status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 255), 'finished_at' => date('Y-m-d H:i:s'),
            ]);
            Timeline::log($cid, $lid, 'error', lang('Lfl.tlConversionFailed', [mb_substr($e->getMessage(), 0, 150)]));

            return ['ok' => false, 'message' => lang('Lfl.cvFailed', [$e->getMessage()])];
        }

        $this->conversions->update($conversionId, ['status' => 'done', 'finished_at' => date('Y-m-d H:i:s')]);

        // Sacar el lead del embudo vive en un solo sitio: `MatterService::open()` hace
        // exactamente lo mismo cuando la firma abre expediente sin pasar por aquí.
        (new LeadService())->markConverted((new LeadModel())->find($lid));

        Timeline::log($cid, $lid, 'conversion', lang('Lfl.tlConverted', [$contactId, $matterId]),
            ['contact' => $contactId, 'matter' => $matterId, 'mode' => $adapter->mode()]);

        return ['ok' => true, 'message' => lang('Lfl.cvDone', [$contactId, $matterId])];
    }

    private function stepContact(IntegrationAdapter $a, array $lead, array $opt): string
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];

        if ($ext = $this->maps->external($cid, self::PROVIDER, 'contact', $lid)) {
            return $ext;
        }

        if (($opt['contact_mode'] ?? 'new') === 'existing' && ($opt['existing_contact_id'] ?? '') !== '') {
            $id = (string) $opt['existing_contact_id'];
            $found = $this->call($cid, $lid, 'get_contact', static fn () => $a->getContact($id), 'contact #' . $id);
            if (! $found) {
                throw new IntegrationException(lang('Lfl.cvContactNotFound', [$id]), 404);
            }
            $this->maps->put($cid, self::PROVIDER, 'contact', $lid, $id);
            Timeline::log($cid, $lid, 'sync', lang('Lfl.tlContactLinked', [$id]));

            return $id;
        }

        $info = ! empty($opt['transfer_info']);
        $data = [
            'first_name'  => $lead['first_name'],
            'middle_name' => $lead['middle_name'] ?: null,
            'last_name'   => $lead['last_name'],
            'email'       => $lead['email'] ?: null,
            'phone'       => $info ? ($lead['phone'] ?: $lead['whatsapp'] ?: null) : null,
            'city'        => $info ? ($lead['city'] ?: null) : null,
            'country'     => $info ? ($lead['country'] ?: null) : null,
        ];

        $id = (string) $this->call($cid, $lid, 'create_contact', static fn () => $a->createContact($data),
            LeadModel::fullName($lead));
        $this->maps->put($cid, self::PROVIDER, 'contact', $lid, $id);
        Timeline::log($cid, $lid, 'sync', lang('Lfl.tlContactCreated', [$id]));

        return $id;
    }

    private function stepMatter(IntegrationAdapter $a, array $lead, array $opt, string $contactId): string
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];

        if ($ext = $this->maps->external($cid, self::PROVIDER, 'matter', $lid)) {
            return $ext;
        }

        $service = ! empty($lead['service_id']) ? (new ServiceModel())->owned((int) $lead['service_id'], $cid) : null;
        $type    = $service['matter_type_name'] ?? null;
        $label   = $type ?: ($service['name'] ?? lang('Lfl.cvDefaultMatter'));

        // El tipo de Matter no se puede asignar por la API v1: viaja en el título y
        // en la descripción para que el abogado lo fije en Docketwise.
        $desc = [
            lang('Lfl.cvDescService', [$service['name'] ?? '—']),
            $type ? lang('Lfl.cvDescType', [$type . (! empty($service['matter_type_id']) ? ' (#' . $service['matter_type_id'] . ')' : '')]) : null,
            lang('Lfl.cvDescScore', [(int) $lead['score']]),
            lang('Lfl.cvDescRef', [$lid]),
        ];

        $users = [];
        if (! empty($opt['lawyer_user_id'])) {
            $dw = (new MemberModel())->docketwiseUserOf($cid, (int) $opt['lawyer_user_id']);
            if ($dw !== null && $dw !== '') {
                $users[] = $dw;
            }
        }

        // Un Matter sin responsable lo rechaza Docketwise con un 400 genérico
        // («issues with creating a matter»), que no dice qué falta. Si el abogado
        // no está relacionado con su usuario (Configuración → Equipo), se asigna
        // el primero de la cuenta y queda anotado en la línea de tiempo, en vez de
        // fallar la conversión entera por un dato de configuración.
        $account  = [];
        $fallback = null;
        if (! $users) {
            $account = $this->accountUsers($cid, $a);
            if ($account) {
                $fallback = (string) array_key_first($account);
                $users[]  = $fallback;
            }
        }

        $data = [
            'client_id'   => $contactId,
            'title'       => mb_substr($label . ' — ' . LeadModel::fullName($lead), 0, 190),
            'description' => implode("\n", array_filter($desc)),
            'user_ids'    => $users,
        ];

        $id = (string) $this->call($cid, $lid, 'create_matter', static fn () => $a->createMatter($data), $data['title']);
        $this->maps->put($cid, self::PROVIDER, 'matter', $lid, $id);
        Timeline::log($cid, $lid, 'sync', lang('Lfl.tlMatterCreated', [$id]));

        if ($fallback !== null) {
            Timeline::log($cid, $lid, 'sync', lang('Lfl.tlMatterDefaultUser', [$account[$fallback] ?? $fallback]));
        }

        return $id;
    }

    /** Resumen del caso + respuestas de precalificación, como una nota del Matter. */
    private function stepSummary(IntegrationAdapter $a, array $lead, string $contactId, string $matterId): void
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];
        if ($this->maps->external($cid, self::PROVIDER, 'summary', $lid)) {
            return;
        }

        $lines = [];
        if (! empty($lead['summary'])) {
            $lines[] = $lead['summary'];
            $lines[] = '';
        }
        foreach ((new SubmissionModel())->forLead($lid, $cid) as $s) {
            $form   = ! empty($s['form_id']) ? (new FormModel())->find((int) $s['form_id']) : null;
            $fields = [];
            foreach (FormModel::fields($form) as $f) {
                $fields[$f['id']] = $f;
            }
            $labels  = json_decode((string) $s['labels'], true) ?: [];
            $answers = json_decode((string) $s['answers'], true) ?: [];
            $lines[] = '# ' . ($form['name'] ?? lang('Lfl.form')) . ' (' . date('d/m/Y', strtotime($s['created_at'])) . ')';
            foreach ($answers as $fid => $val) {
                $lines[] = '- ' . ($labels[$fid] ?? $fid) . ': ' . FormEngine::display($fields[$fid] ?? null, $val);
            }
            $lines[] = '';
        }
        if (! $lines) {
            $lines[] = lang('Lfl.cvNoSummary');
        }

        $data = ['client_id' => $contactId, 'matter_id' => $matterId, 'title' => lang('Lfl.cvSummaryTitle'), 'content' => implode("\n", $lines)];
        $id   = (string) $this->call($cid, $lid, 'create_note', static fn () => $a->createNote($data), 'summary');
        $this->maps->put($cid, self::PROVIDER, 'summary', $lid, $id);
    }

    private function stepNote(IntegrationAdapter $a, array $lead, int $noteId, string $matterId): void
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];
        if ($this->maps->external($cid, self::PROVIDER, 'note', $noteId)) {
            return;
        }
        $note = (new NoteModel())->owned($noteId, $cid);
        if (! $note || (int) $note['lead_id'] !== $lid) {
            return;
        }

        $data = ['client_id' => null, 'matter_id' => $matterId, 'title' => lang('Lfl.cvNoteTitle', [date('d/m/Y', strtotime($note['created_at']))]),
            'content' => $note['body']];
        $id = (string) $this->call($cid, $lid, 'create_note', static fn () => $a->createNote($data), 'note #' . $noteId);
        $this->maps->put($cid, self::PROVIDER, 'note', $noteId, $id);
    }

    private function stepDocument(IntegrationAdapter $a, array $lead, int $docId, string $contactId, string $matterId): void
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];
        if ($this->maps->external($cid, self::PROVIDER, 'document', $docId)) {
            return;
        }
        $doc = (new DocumentModel())->owned($docId, $cid);
        if (! $doc || (int) $doc['lead_id'] !== $lid || (int) $doc['is_authorized'] !== 1) {
            return;
        }
        $path = DocumentService::path($doc);
        if (! $path) {
            throw new IntegrationException(lang('Lfl.cvDocMissing', [$doc['original_name']]));
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $doc['original_name']) ?: ('document-' . $docId);
        $data     = [
            'client_id'   => $contactId,
            'matter_id'   => $matterId,
            'title'       => mb_substr(pathinfo($doc['original_name'], PATHINFO_FILENAME) ?: $filename, 0, 190),
            'filename'    => $filename,
            'base64'      => base64_encode((string) file_get_contents($path)),
            'description' => lang('Lfl.cvDocDesc', [$lid]),
        ];
        $id = (string) $this->call($cid, $lid, 'create_document', static fn () => $a->createDocument($data), $filename);
        $this->maps->put($cid, self::PROVIDER, 'document', $docId, $id);
    }
}
