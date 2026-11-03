<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\MemberModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SubmissionModel;

/**
 * Los REQUISITOS de cada paso de la conversión, en un solo sitio.
 *
 * Nació de un fallo real: Docketwise rechazaba el Matter con un «Sorry, we are
 * experiencing issues with creating a matter» que no dice qué falta, y el dato
 * ausente —el responsable del caso— estaba a la vista en Configuración → Equipo.
 * Un requisito que solo conoce el proveedor no se puede diagnosticar; uno
 * declarado aquí, sí.
 *
 * De esta clase beben las tres piezas, igual que `AutomationRegistry` en las
 * automatizaciones: la pantalla de conversión (que los enseña antes de enviar),
 * los EVENTOS INFORMATIVOS de la bitácora (uno por paso, con lo que se cumplía en
 * ese momento) y el propio `ConversionService`, que se planta antes de llamar a la
 * API cuando falta algo obligatorio. **Añadir un requisito es una entrada aquí**,
 * y las tres se enteran.
 *
 * Tres niveles, y la diferencia importa:
 *  · `ok`    cumplido;
 *  · `warn`  no está, pero el paso sigue adelante (con respaldo o saltándose);
 *  · `error` obligatorio: sin él la llamada fallaría, así que no se llega a hacer.
 */
class ConversionSteps
{
    /** Paso => [rótulo, icono]. En el orden en que se ejecutan. */
    public const STEPS = [
        'contact'   => ['reqStepContact', 'icon-person-badge'],
        'matter'    => ['reqStepMatter', 'icon-briefcase'],
        'summary'   => ['reqStepSummary', 'icon-journal-text'],
        'notes'     => ['reqStepNotes', 'icon-chat'],
        'documents' => ['reqStepDocuments', 'icon-paperclip'],
    ];

    private static function check(string $labelKey, string $status, string $detail): array
    {
        return ['label' => lang('Lfl.' . $labelKey), 'status' => $status, 'detail' => $detail];
    }

    /**
     * Evalúa los requisitos de todos los pasos para este lead y estas opciones.
     *
     * @param array $lead
     * @param array $opts  opciones ya saneadas por `ConversionService::cleanOptions()`
     * @param array $ctx   ['accountUsers' => array<string,string>] usuarios de la cuenta
     *                     de Docketwise; vacío si no se pudieron consultar
     *
     * @return array<string, array{label:string,icon:string,ok:bool,runs:bool,checks:array}>
     */
    public static function evaluate(array $lead, array $opts, array $ctx = []): array
    {
        $cid   = (int) $lead['company_id'];
        $lid   = (int) $lead['id'];
        $users = (array) ($ctx['accountUsers'] ?? []);
        $out   = [];

        // ── 1. Contact ───────────────────────────────────────────────────────
        $names  = trim((string) $lead['first_name']) !== '' && trim((string) $lead['last_name']) !== '';
        $reach  = array_filter([$lead['email'] ?? null, $lead['phone'] ?? null, $lead['whatsapp'] ?? null]);
        $linked = ($opts['contact_mode'] ?? 'new') === 'existing' && ! empty($opts['existing_contact_id']);

        $out['contact'] = self::step('contact', true, [
            self::check('reqNames', $names ? 'ok' : 'error', $names ? LeadModel::fullName($lead) : lang('Lfl.reqNamesMissing')),
            self::check('reqReach', $reach ? 'ok' : 'warn', $reach ? implode(' · ', $reach) : lang('Lfl.reqReachMissing')),
            self::check('reqContactMode', 'ok', $linked
                ? lang('Lfl.reqModeExisting', [$opts['existing_contact_id']])
                : lang('Lfl.reqModeNew')),
        ]);

        // ── 2. Matter ────────────────────────────────────────────────────────
        $lawyer = (int) ($opts['lawyer_user_id'] ?? 0);
        $dwUser = $lawyer > 0 ? (new MemberModel())->docketwiseUserOf($cid, $lawyer) : null;
        $first  = $users ? (string) array_key_first($users) : null;

        if ($dwUser !== null && $dwUser !== '') {
            $owner = self::check('reqOwner', 'ok', lang('Lfl.reqOwnerLinked', [$users[$dwUser] ?? ('#' . $dwUser)]));
        } elseif ($first !== null) {
            $owner = self::check('reqOwner', 'warn', lang('Lfl.reqOwnerFallback', [$users[$first] ?? ('#' . $first)]));
        } else {
            $owner = self::check('reqOwner', 'error', lang('Lfl.reqOwnerMissing'));
        }

        $service = ! empty($lead['service_id']) ? (new ServiceModel())->owned((int) $lead['service_id'], $cid) : null;
        $type    = $service['matter_type_name'] ?? null;

        $out['matter'] = self::step('matter', true, [
            self::check('reqClient', 'ok', lang('Lfl.reqClientFromStep')),
            $owner,
            self::check('reqService', $service ? 'ok' : 'warn',
                $service ? (string) $service['name'] : lang('Lfl.reqServiceMissing')),
            self::check('reqMatterType', $type ? 'ok' : 'warn',
                $type ? lang('Lfl.reqTypeInTitle', [$type]) : lang('Lfl.reqTypeMissing')),
        ]);

        // ── 3. Resumen del caso ──────────────────────────────────────────────
        $runsSummary = ! empty($opts['include_summary']);
        $answers     = (new SubmissionModel())->forCompany($cid)->where('lead_id', $lid)->countAllResults();
        $hasSummary  = trim((string) ($lead['summary'] ?? '')) !== '' || $answers > 0;

        $out['summary'] = self::step('summary', $runsSummary, [
            self::check('reqSummaryContent', $hasSummary ? 'ok' : 'warn',
                $hasSummary ? lang('Lfl.reqSummaryHas', [$answers]) : lang('Lfl.reqSummaryEmpty')),
        ]);

        // ── 4. Notas ─────────────────────────────────────────────────────────
        $notes = count((array) ($opts['notes'] ?? []));
        $out['notes'] = self::step('notes', $notes > 0, [
            self::check('reqNotesTransferable', 'ok', lang('Lfl.reqNotesCount', [$notes])),
        ]);

        // ── 5. Documentos ────────────────────────────────────────────────────
        $docs    = (array) ($opts['documents'] ?? []);
        $held    = (new DocumentModel())->forCompany($cid)->where('lead_id', $lid)->countAllResults();
        $missing = 0;
        foreach ($docs as $docId) {
            $row = (new DocumentModel())->owned((int) $docId, $cid);
            if (! $row || ! is_file(DocumentService::folder($cid) . DIRECTORY_SEPARATOR . $row['storage_key'])) {
                $missing++;
            }
        }

        $out['documents'] = self::step('documents', $docs !== [], [
            self::check('reqDocsAuthorized', 'ok', lang('Lfl.reqDocsCount', [count($docs), max(0, $held - count($docs))])),
            self::check('reqDocsFiles', $missing === 0 ? 'ok' : 'error',
                $missing === 0 ? lang('Lfl.reqDocsFilesOk') : lang('Lfl.reqDocsFilesMissing', [$missing])),
        ]);

        return $out;
    }

    private static function step(string $key, bool $runs, array $checks): array
    {
        [$labelKey, $icon] = self::STEPS[$key];

        return [
            'label'  => lang('Lfl.' . $labelKey),
            'icon'   => $icon,
            'runs'   => $runs,
            'ok'     => ! in_array('error', array_column($checks, 'status'), true),
            'checks' => $checks,
        ];
    }

    /** Una línea para el evento de la bitácora: «3 de 4; falta: Responsable del caso». */
    public static function summarize(array $step): string
    {
        $total = count($step['checks']);
        $met   = count(array_filter($step['checks'], static fn ($c) => $c['status'] === 'ok'));
        $gaps  = array_column(array_filter($step['checks'], static fn ($c) => $c['status'] !== 'ok'), 'label');

        return $gaps === []
            ? lang('Lfl.reqAllMet', [$met, $total])
            : lang('Lfl.reqSomeMissing', [$met, $total, implode(', ', $gaps)]);
    }

    /** El detalle completo, para el cuerpo del evento. */
    public static function detail(array $step): string
    {
        $lines = [];
        foreach ($step['checks'] as $c) {
            $lines[] = mb_strtoupper($c['status']) . ' · ' . $c['label'] . ': ' . $c['detail'];
        }

        return implode("\n", $lines);
    }

    /** El primer requisito obligatorio que falta en este paso, ya redactado. */
    public static function blocker(array $step): ?string
    {
        foreach ($step['checks'] as $c) {
            if ($c['status'] === 'error') {
                return $c['label'] . ': ' . $c['detail'];
            }
        }

        return null;
    }
}
