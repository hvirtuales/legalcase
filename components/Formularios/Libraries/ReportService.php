<?php

namespace Components\Formularios\Libraries;

use Components\Formularios\Models\AnswerModel;
use Components\Formularios\Models\ResponseModel;

/**
 * Las cifras del formulario, TODAS calculadas.
 *
 * La app de la que nace el componente pintaba una tasa de finalización fija del
 * 98,2 por ciento: una maqueta presentada como dato. Aquí cada número sale de
 * una consulta, y cuando no hay con qué calcularlo se dice que no lo hay en vez
 * de inventarlo.
 *
 * Las agregaciones van sobre `cmp_frm_answers` con SUM, AVG y COUNT, que
 * existen en los tres motores; nada de funciones de fecha propias de MySQL (el
 * agrupado por mes se hace en PHP, como en el panel de Contabilidad).
 */
class ReportService
{
    private ResponseModel $responses;
    private AnswerModel $answers;

    public function __construct()
    {
        $this->responses = new ResponseModel();
        $this->answers   = new AnswerModel();
    }

    /**
     * Cabecera del informe.
     *
     * `completion` es respuestas entre visitas: la tasa de verdad, que solo
     * tiene sentido si el formulario se ha visto alguna vez.
     */
    public function summary(array $form): array
    {
        $formId = (int) $form['id'];
        $total  = $this->responses->where('form_id', $formId)->countAllResults();

        $row = $this->responses->builder()
            ->select('AVG(duration_seconds) AS avg_secs, MAX(created_at) AS last_at')
            ->where('form_id', $formId)
            ->where('deleted_at IS NULL')
            ->get()->getRowArray();

        $views      = (int) ($form['views'] ?? 0);
        $completion = $views > 0 ? min(100, round($total * 100 / $views, 1)) : null;

        return [
            'total'      => $total,
            'views'      => $views,
            'completion' => $completion,
            'avgSeconds' => (int) round((float) ($row['avg_secs'] ?? 0)),
            'lastAt'     => $row['last_at'] ?? null,
            'devices'    => $this->devices($formId, $total),
        ];
    }

    /** Reparto por dispositivo, en porcentaje. */
    private function devices(int $formId, int $total): array
    {
        if ($total === 0) {
            return [];
        }

        $rows = $this->responses->builder()
            ->select('device, COUNT(*) AS n')
            ->where('form_id', $formId)
            ->where('deleted_at IS NULL')
            ->groupBy('device')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['device']] = round(((int) $r['n']) * 100 / $total, 1);
        }

        return $out;
    }

    /**
     * Una entrada por campo contestable, con la estadística que le corresponde
     * a su tipo. El tipo decide qué se puede decir de un campo: contar opciones
     * de un desplegable, promediar un NPS y listar textos de un párrafo.
     */
    public function fieldStats(array $schema, array $form): array
    {
        $formId = (int) $form['id'];
        $out    = [];

        foreach ($schema['fields'] ?? [] as $field) {
            $type = (string) $field['type'];
            if (FieldRegistry::isStructural($type)) {
                continue;
            }

            $id    = (string) $field['id'];
            $entry = [
                'id'    => $id,
                'label' => (string) ($field['label'] ?? ''),
                'type'  => $type,
                'kind'  => 'text',
            ];

            if (FieldRegistry::hasOptions($type) || $type === 'switch') {
                $entry['kind']   = 'options';
                $entry['counts'] = $this->labelledCounts($field, $formId);
                $entry['total']  = array_sum($entry['counts']);
            } elseif (in_array($type, ['rating', 'nps', 'slider', 'number'], true)) {
                $entry['kind']  = 'numeric';
                $entry['stats'] = $this->answers->numericStats($formId, $id);
                if ($type === 'nps') {
                    $entry['nps'] = $this->npsScore($formId, $id);
                }
            } else {
                $entry['texts'] = $this->answers->latestTexts($formId, $id, 15);
            }

            $out[] = $entry;
        }

        return $out;
    }

    /** Conteos con la etiqueta de la opción, no con su valor interno. */
    private function labelledCounts(array $field, int $formId): array
    {
        $counts = $this->answers->countByOption($formId, (string) $field['id']);

        if ((string) $field['type'] === 'switch') {
            return [
                lang('Frm.yes') => $counts['1'] ?? 0,
                lang('Frm.no')  => $counts['0'] ?? 0,
            ];
        }

        $out = [];
        foreach ($field['options'] ?? [] as $opt) {
            $out[(string) $opt['label']] = $counts[(string) $opt['value']] ?? 0;
            unset($counts[(string) $opt['value']]);
        }
        // Lo que quede son respuestas a opciones que ya no existen en el
        // formulario: se muestran igual, porque alguien las contestó.
        foreach ($counts as $value => $n) {
            $out[(string) $value] = $n;
        }

        return $out;
    }

    /**
     * NPS de verdad: promotores (9-10) menos detractores (0-6), en porcentaje.
     * Es la métrica que da nombre al campo, y promediar no la sustituye.
     */
    private function npsScore(int $formId, string $fieldId): ?array
    {
        $rows = $this->answers->builder()
            ->select('value_num, COUNT(*) AS n')
            ->where('form_id', $formId)
            ->where('field_id', $fieldId)
            ->where('value_num IS NOT NULL')
            ->groupBy('value_num')
            ->get()->getResultArray();

        $total = 0;
        $prom  = 0;
        $det   = 0;
        foreach ($rows as $r) {
            $v = (int) $r['value_num'];
            $n = (int) $r['n'];
            $total += $n;
            if ($v >= 9) {
                $prom += $n;
            } elseif ($v <= 6) {
                $det += $n;
            }
        }

        if ($total === 0) {
            return null;
        }

        return [
            'score'      => (int) round(($prom - $det) * 100 / $total),
            'promoters'  => $prom,
            'passives'   => $total - $prom - $det,
            'detractors' => $det,
            'total'      => $total,
        ];
    }
}
