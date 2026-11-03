<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\MatterFeeModel;
use Components\LeadFlow\Models\MatterModel;
use Components\LeadFlow\Models\MatterRequirementModel;

/**
 * Los requisitos de cada fase del expediente, declarados en un solo sitio.
 *
 * Mismo patrón que `ConversionSteps`, y por la misma razón: un requisito que solo
 * conoce el código que lo comprueba no se puede diagnosticar ni enseñar. Aquí están
 * declarados, y de esta única fuente beben **tres consumidores**:
 *
 *   1. la pestaña de resumen del expediente, que los pinta con `partials/_diagnostic`;
 *   2. `MatterService::moveStage()`, que consulta `blocker('filing')` antes de dejar
 *      radicar;
 *   3. `MatterService::close()`, que mira los que impiden cerrar.
 *
 * **Añadir un requisito es una entrada aquí**, y los tres se enteran.
 *
 * ── Los tres niveles ─────────────────────────────────────────────────────────
 *
 * `ok` cumplido · `warn` no está, pero el caso puede seguir · `error` sin esto no se
 * avanza. La diferencia importa en un trámite legal: que falten documentos opcionales
 * al radicar es un aviso, no un impedimento —hay trámites que se radican con lo que
 * haya y se completan después—, mientras que radicar sin responsable asignado sí es
 * un error, porque no habría nadie a quien reclamarle la respuesta.
 *
 * Lo que NO está aquí es el checklist fila a fila: eso es dato de
 * `cmp_lfl_matter_requirements` y se pinta como una tabla normal. Esto evalúa el
 * AGREGADO («8 de 11 obligatorios»), que es lo que gobierna las decisiones.
 */
class MatterSteps
{
    /** Paso => [rótulo, icono]. En el orden del ciclo del expediente. */
    public const STEPS = [
        'intake'    => ['mtrStepIntake', 'icon-folder'],
        'documents' => ['mtrStepDocuments', 'icon-paperclip'],
        'fees'      => ['mtrStepFees', 'icon-cash-stack'],
        'filing'    => ['mtrStepFiling', 'icon-landmark'],
        'closing'   => ['mtrStepClosing', 'icon-check-circle'],
    ];

    private static function check(string $labelKey, string $status, string $detail): array
    {
        return ['label' => lang('Lfl.' . $labelKey), 'status' => $status, 'detail' => $detail];
    }

    private static function step(string $key, bool $runs, array $checks): array
    {
        [$labelKey, $icon] = self::STEPS[$key];

        return [
            'label'  => lang('Lfl.' . $labelKey),
            'icon'   => $icon,
            'runs'   => $runs,
            'ok'     => ! array_filter($checks, static fn ($c) => $c['status'] === 'error'),
            'checks' => $checks,
        ];
    }

    /**
     * Evalúa los cinco pasos de este expediente.
     *
     * @return array<string, array{label:string,icon:string,ok:bool,runs:bool,checks:array}>
     */
    public static function evaluate(array $matter, array $ctx = []): array
    {
        $cid = (int) $matter['company_id'];
        $mid = (int) $matter['id'];

        $reqs     = new MatterRequirementModel();
        $progreso = $reqs->progress($mid, $cid);
        $faltan   = $reqs->missing($mid, $cid);
        $moneda   = (string) ($matter['currency'] ?: 'USD');

        $out = [];

        // ── 1. Apertura ──────────────────────────────────────────────────────
        $out['intake'] = self::step('intake', true, [
            self::check('mtrLawyer',
                $matter['lawyer_user_id'] ? 'ok' : 'error',
                $matter['lawyer_name'] ?: lang('Lfl.unassigned')),
            self::check('mtrService',
                $matter['service_id'] ? 'ok' : 'warn',
                $matter['service_name'] ?: lang('Lfl.noService')),
            self::check('mtrSummary',
                trim((string) ($matter['summary'] ?? '')) !== '' ? 'ok' : 'warn',
                trim((string) ($matter['summary'] ?? '')) !== ''
                    ? lang('Lfl.mtrStepOk')
                    : lang('Lfl.mtrStepEmptySummary')),
        ]);

        // ── 2. Documentos ────────────────────────────────────────────────────
        $sinChecklist = $progreso['required'] === 0;
        $completo     = ! $sinChecklist && $progreso['settled'] >= $progreso['required'];

        $checksDocs = [
            self::check('mtrStepRequired',
                $sinChecklist ? 'warn' : ($completo ? 'ok' : 'warn'),
                $sinChecklist
                    ? lang('Lfl.mtrStepNoChecklist')
                    : lang('Lfl.mtrStepProgress', [$progreso['settled'], $progreso['required']])),
        ];
        if ($faltan !== []) {
            $checksDocs[] = self::check('mtrStepMissing', 'warn',
                implode(', ', array_slice(array_column($faltan, 'label'), 0, 5)));
        }
        $out['documents'] = self::step('documents', true, $checksDocs);

        // ── 3. Honorarios ────────────────────────────────────────────────────
        $pactado = (float) ($matter['fee_total'] ?? 0);
        $cobrado = (float) ($matter['paid_total'] ?? 0);
        $saldo   = MatterModel::balance($matter);

        $out['fees'] = self::step('fees', true, [
            self::check('mtrFeeTotal',
                $pactado > 0 ? 'ok' : 'warn',
                $pactado > 0 ? Meta::money($pactado, $moneda) : lang('Lfl.mtrStepNoFees')),
            self::check('mtrBalance',
                $saldo <= 0 ? 'ok' : 'warn',
                Meta::money($saldo, $moneda)),
        ]);

        // ── 4. Radicación ────────────────────────────────────────────────────
        $recibo = trim((string) ($ctx['uscis_receipt'] ?? ''));

        $out['filing'] = self::step('filing', true, [
            // Único `error` del paso: sin responsable no se radica, porque no habría a
            // quién reclamarle la respuesta de la autoridad.
            self::check('mtrLawyer',
                $matter['lawyer_user_id'] ? 'ok' : 'error',
                $matter['lawyer_name'] ?: lang('Lfl.unassigned')),
            // Los obligatorios que falten avisan, no bloquean: hay trámites que se
            // radican con lo que hay y se completan después.
            self::check('mtrStepRequired',
                $completo ? 'ok' : 'warn',
                $sinChecklist
                    ? lang('Lfl.mtrStepNoChecklist')
                    : lang('Lfl.mtrStepProgress', [$progreso['settled'], $progreso['required']])),
            self::check('mtrStepReceipt',
                $recibo !== '' ? 'ok' : 'warn',
                $recibo !== '' ? $recibo : lang('Lfl.mtrStepNoReceipt')),
        ]);

        // ── 5. Cierre ────────────────────────────────────────────────────────
        $out['closing'] = self::step('closing', true, [
            self::check('mtrBalance',
                $saldo <= 0 ? 'ok' : 'warn',
                Meta::money($saldo, $moneda)),
            self::check('mtrStepRequired',
                $completo || $sinChecklist ? 'ok' : 'warn',
                $sinChecklist
                    ? lang('Lfl.mtrStepNoChecklist')
                    : lang('Lfl.mtrStepProgress', [$progreso['settled'], $progreso['required']])),
        ]);

        return $out;
    }

    /** Una línea: «3 de 4; falta: Abogado responsable». */
    public static function summarize(array $step): string
    {
        $total = count($step['checks']);
        $met   = count(array_filter($step['checks'], static fn ($c) => $c['status'] === 'ok'));
        $gaps  = array_column(array_filter($step['checks'], static fn ($c) => $c['status'] !== 'ok'), 'label');

        return $gaps === []
            ? lang('Lfl.reqAllMet', [$met, $total])
            : lang('Lfl.reqSomeMissing', [$met, $total, implode(', ', $gaps)]);
    }

    /** El primer requisito obligatorio que falta en este paso, ya redactado. */
    public static function blocker(array $steps, string $step): ?string
    {
        foreach ($steps[$step]['checks'] ?? [] as $c) {
            if ($c['status'] === 'error') {
                return $c['label'] . ': ' . $c['detail'];
            }
        }

        return null;
    }

    /**
     * Lo que la vista necesita para `partials/_diagnostic`, que espera
     * `['ok', 'ms', 'checks' => [['label','status','detail']]]`.
     *
     * Se aplana con el rótulo del paso delante, porque el parcial del componente pinta
     * una lista y no secciones. Así se reutiliza la pieza que ya existe en vez de
     * escribir otra que haga lo mismo.
     */
    public static function toDiagnostic(array $steps): array
    {
        $checks = [];
        foreach ($steps as $paso) {
            foreach ($paso['checks'] as $c) {
                $c['label'] = $paso['label'] . ' · ' . $c['label'];
                $checks[]   = $c;
            }
        }

        return [
            'ok'     => ! array_filter($checks, static fn ($c) => $c['status'] === 'error'),
            'ms'     => 0,
            'checks' => $checks,
        ];
    }
}
