<?php

namespace Components\LeadFlow\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Motor de los formularios dinámicos (§13): el registro de tipos, el saneador del
 * árbol, la lógica condicional y la validación de respuestas.
 *
 * **La frontera de confianza está aquí.** El árbol que llega del constructor no se
 * limpia, se **reconstruye** campo por campo: un tipo inventado, una propiedad de
 * más o una regla que apunta a un campo que no existe (o que viene DESPUÉS) se
 * quedan fuera. Y al recibir respuestas, el servidor vuelve a evaluar la lógica:
 * un campo oculto no se exige, y **lo que llegue para él se descarta** — si no,
 * bastaría desactivar el JavaScript para colar datos en preguntas que la lógica
 * del formulario decía no hacer.
 */
class FormEngine
{
    public const TYPES = ['text', 'textarea', 'email', 'phone', 'number', 'date', 'select', 'multiselect', 'yesno', 'country', 'address', 'file'];
    public const CHOICE = ['select', 'multiselect'];
    public const OPERATORS = ['equals', 'not_equals', 'contains', 'is_empty', 'is_not_empty'];

    public const MAX_FIELDS  = 60;
    public const MAX_OPTIONS = 50;

    /** Tipo → clave de idioma de su nombre. */
    public const TYPE_LABELS = [
        'text' => 'ftText', 'textarea' => 'ftTextarea', 'email' => 'ftEmail', 'phone' => 'ftPhone', 'number' => 'ftNumber',
        'date' => 'ftDate', 'select' => 'ftSelect', 'multiselect' => 'ftMultiselect', 'yesno' => 'ftYesNo',
        'country' => 'ftCountry', 'address' => 'ftAddress', 'file' => 'ftFile',
    ];

    public const OPERATOR_LABELS = [
        'equals' => 'opEquals', 'not_equals' => 'opNotEquals', 'contains' => 'opContains',
        'is_empty' => 'opIsEmpty', 'is_not_empty' => 'opIsNotEmpty',
    ];

    /**
     * Reconstruye el árbol contra el registro.
     *
     * @param mixed $raw array o JSON
     */
    public function sanitize($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $ids = [];

        foreach (array_slice(array_values($raw), 0, self::MAX_FIELDS) as $f) {
            if (! is_array($f)) {
                continue;
            }
            $type = (string) ($f['type'] ?? '');
            if (! in_array($type, self::TYPES, true)) {
                continue;
            }
            $label = mb_substr(trim(strip_tags((string) ($f['label'] ?? ''))), 0, 200);
            if ($label === '') {
                continue;
            }

            $id = (string) ($f['id'] ?? '');
            if (! preg_match('/^f_[a-z0-9]{3,20}$/', $id) || isset($ids[$id])) {
                $id = 'f_' . bin2hex(random_bytes(4));
            }

            $field = [
                'id'       => $id,
                'type'     => $type,
                'label'    => $label,
                'help'     => mb_substr(trim(strip_tags((string) ($f['help'] ?? ''))), 0, 300),
                'required' => ! empty($f['required']) && $f['required'] !== '0' && $f['required'] !== 'false',
            ];

            if (in_array($type, self::CHOICE, true)) {
                $opts = $f['options'] ?? [];
                if (is_string($opts)) {
                    $opts = preg_split('/\r\n|\r|\n/', $opts);
                }
                $clean = [];
                foreach ((array) $opts as $o) {
                    $o = mb_substr(trim(strip_tags((string) $o)), 0, 120);
                    if ($o !== '' && ! in_array($o, $clean, true)) {
                        $clean[] = $o;
                    }
                }
                if (! $clean) {
                    continue; // una lista sin opciones no se puede contestar
                }
                $field['options'] = array_slice($clean, 0, self::MAX_OPTIONS);
            }

            // La regla solo puede mirar un campo ANTERIOR: es lo que el navegador
            // puede resolver al pintar de arriba abajo, y lo que evita ciclos.
            $rule = $f['rule'] ?? null;
            if (is_array($rule) && isset($ids[(string) ($rule['field'] ?? '')])
                && in_array((string) ($rule['op'] ?? ''), self::OPERATORS, true)) {
                $field['rule'] = [
                    'field' => (string) $rule['field'],
                    'op'    => (string) $rule['op'],
                    'value' => mb_substr(trim(strip_tags((string) ($rule['value'] ?? ''))), 0, 120),
                ];
            }

            $ids[$id] = true;
            $out[]    = $field;
        }

        return $out;
    }

    // ── Lógica condicional ────────────────────────────────────────────────────

    public function isVisible(array $field, array $values): bool
    {
        $rule = $field['rule'] ?? null;
        if (! is_array($rule)) {
            return true;
        }

        $target   = $values[$rule['field']] ?? '';
        $expected = mb_strtolower(trim((string) ($rule['value'] ?? '')));
        $op       = $rule['op'];

        if (is_array($target)) {
            $has = in_array($expected, array_map(static fn ($v) => mb_strtolower((string) $v), $target), true);

            return match ($op) {
                'equals', 'contains' => $has,
                'not_equals'         => ! $has,
                'is_empty'           => $target === [],
                'is_not_empty'       => $target !== [],
                default              => true,
            };
        }

        $t = mb_strtolower(trim((string) $target));
        // Un Sí/No se puede comparar escribiendo «sí», «si», «yes» o «true».
        if (in_array($t, ['yes', 'no'], true)) {
            $expected = self::yesNo($expected) ?? $expected;
        }

        return match ($op) {
            'equals'       => $t === $expected,
            'not_equals'   => $t !== $expected,
            'contains'     => $expected !== '' && str_contains($t, $expected),
            'is_empty'     => $t === '',
            'is_not_empty' => $t !== '',
            default        => true,
        };
    }

    private static function yesNo(string $v): ?string
    {
        $v = mb_strtolower(trim($v));
        if (in_array($v, ['yes', 'si', 'sí', 'true', '1', 'y', 's'], true)) {
            return 'yes';
        }
        if (in_array($v, ['no', 'false', '0', 'n'], true)) {
            return 'no';
        }

        return null;
    }

    // ── Validación ────────────────────────────────────────────────────────────

    /**
     * Recoge y valida las respuestas.
     *
     * @param array<string,mixed>        $input id => valor (del POST `q[...]`)
     * @param array<string,UploadedFile> $files id => archivo
     *
     * @return array{values:array,labels:array,errors:array,complete:bool,files:array}
     */
    public function collect(array $fields, array $input, array $files = []): array
    {
        $values = [];
        $labels = [];
        $errors = [];
        $upload = [];
        $asked  = 0;

        foreach ($fields as $f) {
            $id          = $f['id'];
            $labels[$id] = $f['label'];

            if (! $this->isVisible($f, $values)) {
                continue; // oculto: ni se exige ni se guarda lo que llegue
            }
            $asked++;

            if ($f['type'] === 'file') {
                $file = $files[$id] ?? null;
                if ($file instanceof UploadedFile && $file->isValid() && ! $file->hasMoved()) {
                    $upload[$id] = $file;
                    $values[$id] = $file->getClientName();
                } elseif (! empty($f['required'])) {
                    $errors[$id] = lang('Lfl.vFieldRequired', [$f['label']]);
                }

                continue;
            }

            $raw = $input[$id] ?? null;

            if ($f['type'] === 'multiselect') {
                $v = array_values(array_intersect(array_map('strval', (array) $raw), $f['options'] ?? []));
                if ($v === []) {
                    if (! empty($f['required'])) {
                        $errors[$id] = lang('Lfl.vFieldRequired', [$f['label']]);
                    }

                    continue;
                }
                $values[$id] = $v;

                continue;
            }

            $v = is_array($raw) ? '' : trim((string) $raw);
            $v = mb_substr($v, 0, $f['type'] === 'textarea' || $f['type'] === 'address' ? 5000 : 500);

            if ($v === '') {
                if (! empty($f['required'])) {
                    $errors[$id] = lang('Lfl.vFieldRequired', [$f['label']]);
                }

                continue;
            }

            $ok = match ($f['type']) {
                'email'   => (bool) filter_var($v, FILTER_VALIDATE_EMAIL),
                'number'  => is_numeric(str_replace(',', '.', $v)),
                'date'    => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false,
                'select'  => in_array($v, $f['options'] ?? [], true),
                'yesno'   => in_array($v, ['yes', 'no'], true),
                'country' => Meta::isCountry($v),
                'phone'   => Dedupe::phone($v) !== null,
                default   => true,
            };
            if (! $ok) {
                $errors[$id] = lang('Lfl.vFieldInvalid', [$f['label']]);

                continue;
            }

            $values[$id] = match ($f['type']) {
                'country' => strtoupper($v),
                'number'  => str_replace(',', '.', $v),
                default   => $v,
            };
        }

        // «Formulario completo» (§15): todo lo que se le preguntó tiene respuesta.
        $answered = count($values);

        return [
            'values'   => $values,
            'labels'   => $labels,
            'errors'   => $errors,
            'complete' => $errors === [] && $asked > 0 && $answered >= $asked,
            'files'    => $upload,
        ];
    }

    /** Valor legible de una respuesta guardada. */
    public static function display(?array $field, $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        $value = (string) $value;
        $type  = $field['type'] ?? 'text';

        return match ($type) {
            'yesno'   => $value === 'yes' ? lang('Lfl.yes') : ($value === 'no' ? lang('Lfl.no') : $value),
            'country' => Meta::countryName($value),
            default   => $value,
        };
    }
}
