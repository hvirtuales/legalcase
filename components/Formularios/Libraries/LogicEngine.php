<?php

namespace Components\Formularios\Libraries;

/**
 * Visibilidad condicional: la ÚNICA implementación de los siete operadores.
 *
 * El JavaScript del formulario público replica esta lógica para que el campo
 * aparezca y desaparezca mientras se escribe, pero LA VERDAD ES ESTA: el
 * servidor vuelve a evaluarla al recibir la respuesta, y decide con ella dos
 * cosas que no se pueden dejar al navegador:
 *
 *   1. Un campo oculto NO se exige aunque sea obligatorio.
 *   2. El valor que llegue para un campo oculto SE DESCARTA. Si no, bastaría
 *      con desactivar el JavaScript para colar datos en preguntas que la propia
 *      lógica del formulario decía no hacer.
 */
class LogicEngine
{
    public const OPERATORS = [
        'equals', 'not_equals', 'contains',
        'is_checked', 'is_not_checked',
        'greater_than', 'less_than',
        'is_empty', 'is_not_empty',
    ];

    /**
     * ¿Se ve el campo con estos valores? Sin regla, siempre.
     *
     * @param array $values idCampo => valor ya recogido
     */
    public function isVisible(array $field, array $values): bool
    {
        $rule = $field['conditionalRule'] ?? null;
        if (!is_array($rule) || empty($rule['enabled']) || empty($rule['fieldId'])) {
            return true;
        }

        $target   = $values[$rule['fieldId']] ?? null;
        $expected = $rule['value'] ?? '';

        // Una lista (casillas múltiples) se compara por pertenencia, no como texto.
        if (is_array($target)) {
            return $this->evalList($rule['operator'] ?? 'equals', $target, (string) $expected);
        }

        return $this->evalScalar($rule['operator'] ?? 'equals', $target, $expected);
    }

    private function evalList(string $op, array $target, string $expected): bool
    {
        $has = in_array($expected, array_map('strval', $target), true);

        return match ($op) {
            'equals', 'contains', 'is_checked' => $has,
            'not_equals', 'is_not_checked'     => !$has,
            'is_empty'                         => $target === [],
            'is_not_empty'                     => $target !== [],
            default                            => true,
        };
    }

    private function evalScalar(string $op, $target, $expected): bool
    {
        $t = mb_strtolower(trim((string) ($target ?? '')));
        $e = mb_strtolower(trim((string) ($expected ?? '')));

        return match ($op) {
            'equals'         => $t === $e,
            'not_equals'     => $t !== $e,
            'contains'       => $e !== '' && str_contains($t, $e),
            'is_checked'     => $this->truthy($target),
            'is_not_checked' => !$this->truthy($target),
            'greater_than'   => is_numeric($t) && is_numeric($e) && (float) $t > (float) $e,
            'less_than'      => is_numeric($t) && is_numeric($e) && (float) $t < (float) $e,
            'is_empty'       => $t === '',
            'is_not_empty'   => $t !== '',
            default          => true,
        };
    }

    /** Un interruptor llega como '1', 'on', true... y todo eso es sí. */
    private function truthy($v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_array($v)) {
            return $v !== [];
        }

        return in_array(mb_strtolower(trim((string) $v)), ['1', 'true', 'on', 'si', 'sí', 'yes'], true);
    }

    /**
     * Recorre los campos EN ORDEN resolviendo la visibilidad de cada uno.
     *
     * El orden importa: una regla que mira un campo posterior no puede
     * resolverse aún, y se considera visible. Es lo mismo que hace el navegador
     * al pintar de arriba abajo.
     *
     * @return array<string,bool> idCampo => visible
     */
    public function visibilityMap(array $fields, array $values): array
    {
        $map = [];
        foreach ($fields as $f) {
            if (!isset($f['id'])) {
                continue;
            }
            $map[(string) $f['id']] = $this->isVisible($f, $values);
        }

        return $map;
    }
}
