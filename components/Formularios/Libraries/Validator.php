<?php

namespace Components\Formularios\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Revalida TODO en el servidor a partir del esquema PUBLICADO.
 *
 * Este es el cambio de fondo respecto a la app de la que nace el componente:
 * allí el navegador validaba y guardaba, y la expresión regular, el mínimo y el
 * máximo se guardaban sin llegar a evaluarse nunca. Aquí el navegador propone y
 * el servidor decide.
 *
 * Tres reglas que no dependen del navegador:
 *
 *   1. Solo existen los campos que el esquema declara. Lo que llegue en el POST
 *      y no esté en el esquema se descarta sin mirarlo.
 *   2. Un campo OCULTO por la lógica no se exige, y su valor SE DESCARTA. Sin
 *      esto bastaría con desactivar el JavaScript para colar datos en preguntas
 *      que el propio formulario decía no hacer.
 *   3. Una opción que no esté entre las declaradas no es una respuesta: se
 *      rechaza en vez de guardarse.
 */
class Validator
{
    /** Tope de texto que se acepta antes de aplicarle una expresión regular. */
    private const REGEX_MAX_INPUT = 5000;

    private LogicEngine $logic;

    public function __construct()
    {
        $this->logic = new LogicEngine();
    }

    /**
     * @param array $post  el POST completo (se lee solo `f`, `sig_*`)
     * @param array $files archivos, ya como UploadedFile por campo (`file_*`)
     *
     * @return array{ok:bool,errors:array,values:array,files:array,signatures:array}
     */
    public function validate(array $schema, array $post, array $files = []): array
    {
        $raw    = is_array($post['f'] ?? null) ? $post['f'] : [];
        $fields = $schema['fields'] ?? [];

        $values     = [];
        $errors     = [];
        $keptFiles  = [];
        $signatures = [];

        foreach ($fields as $field) {
            $id   = (string) $field['id'];
            $type = (string) $field['type'];

            if (FieldRegistry::isStructural($type)) {
                continue;
            }

            // La visibilidad se resuelve con lo ya aceptado, de arriba abajo:
            // igual que lo pinta el navegador.
            if (!$this->logic->isVisible($field, $values)) {
                continue;   // oculto: ni se exige ni se guarda lo que traiga
            }

            $kind = FieldRegistry::valueKind($type);

            if ($kind === 'file') {
                $this->collectFile($field, $files, $post, $errors, $keptFiles, $signatures, $values);

                continue;
            }

            $value = $this->normalize($field, $raw[$id] ?? null);
            $error = $this->checkField($field, $value);

            if ($error !== null) {
                $errors[$id] = $error;
            }
            if ($value !== null && $value !== '' && $value !== []) {
                $values[$id] = $value;
            }
        }

        return [
            'ok'         => $errors === [],
            'errors'     => $errors,
            'values'     => $values,
            'files'      => $keptFiles,
            'signatures' => $signatures,
        ];
    }

    /** Archivo o firma: se comprueba aquí y se guarda después, ya con respuesta. */
    private function collectFile(array $field, array $files, array $post, array &$errors, array &$keep, array &$sigs, array &$values): void
    {
        $id       = (string) $field['id'];
        $required = !empty($field['required']);

        if ((string) $field['type'] === 'signature') {
            $data = (string) ($post['sig_' . $id] ?? '');
            if ($data === '') {
                if ($required) {
                    $errors[$id] = $this->msg($field, lang('Frm.errSignRequired'));
                }

                return;
            }
            if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $data)) {
                $errors[$id] = $this->msg($field, lang('Frm.errSignInvalid'));

                return;
            }
            $sigs[$id]   = $data;
            $values[$id] = lang('Frm.signedValue');

            return;
        }

        $file = $files['file_' . $id] ?? null;
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            if ($required) {
                $errors[$id] = $this->msg($field, lang('Frm.errFileRequired'));
            }

            return;
        }

        $problem = (new AttachmentService())->check($file, $field);
        if ($problem !== null) {
            $errors[$id] = $problem;

            return;
        }

        $keep[$id]   = $file;
        $values[$id] = mb_substr((string) $file->getClientName(), 0, 190);
    }

    /** Deja el valor en la forma que le toca a su tipo. */
    private function normalize(array $field, $value)
    {
        $type = (string) $field['type'];

        if (FieldRegistry::valueKind($type) === 'multi') {
            $out = [];
            foreach (is_array($value) ? $value : [] as $v) {
                if (!is_array($v) && !is_object($v)) {
                    $out[] = (string) $v;
                }
            }

            return $out;
        }

        if (is_array($value) || is_object($value)) {
            return null;   // un escalar no llega como lista salvo que alguien juegue
        }

        $value = trim((string) ($value ?? ''));

        if ($type === 'switch') {
            return in_array(mb_strtolower($value), ['1', 'true', 'on'], true) ? '1' : '0';
        }

        return $value;
    }

    /** El mensaje propio del campo manda sobre el genérico. */
    private function msg(array $field, string $fallback): string
    {
        $own = trim((string) ($field['validationErrorMsg'] ?? ''));

        return $own !== '' ? $own : $fallback;
    }

    /** Todas las comprobaciones de un campo. Devuelve el error o null. */
    private function checkField(array $field, $value): ?string
    {
        $type  = (string) $field['type'];
        $empty = $value === null || $value === '' || $value === [];

        if (!empty($field['required']) && $empty) {
            return $this->msg($field, lang('Frm.errRequired'));
        }
        // Un interruptor obligatorio es una aceptación: apagado no vale.
        if ($type === 'switch' && !empty($field['required']) && $value !== '1') {
            return $this->msg($field, lang('Frm.errMustAccept'));
        }
        if ($empty) {
            return null;   // opcional y vacío: nada más que mirar
        }

        if (FieldRegistry::hasOptions($type)) {
            return $this->checkOptions($field, $value);
        }

        return match ($type) {
            'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : $this->msg($field, lang('Frm.errEmail')),
            'number'   => $this->checkNumber($field, $value),
            'slider'   => $this->checkNumber($field, $value),
            'rating'   => $this->checkRange($field, $value, 1, (int) ($field['maxStars'] ?? 5)),
            'nps'      => $this->checkRange($field, $value, 0, 10),
            'date'     => $this->checkDate($field, $value),
            'time'     => preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $value) === 1 ? null : $this->msg($field, lang('Frm.errTime')),
            'phone'    => mb_strlen((string) $value) <= 40 ? null : $this->msg($field, lang('Frm.errTooLong', [40])),
            default    => $this->checkText($field, (string) $value),
        };
    }

    private function checkOptions(array $field, $value): ?string
    {
        $allowed = FieldRegistry::optionValues($field);

        if (FieldRegistry::valueKind((string) $field['type']) === 'multi') {
            foreach ((array) $value as $v) {
                if (!in_array((string) $v, $allowed, true)) {
                    return $this->msg($field, lang('Frm.errOption'));
                }
            }
            $n   = count((array) $value);
            $min = (int) ($field['minChoices'] ?? 0);
            $max = (int) ($field['maxChoices'] ?? 0);
            if ($min > 0 && $n < $min) {
                return $this->msg($field, lang('Frm.errMinChoices', [$min]));
            }
            if ($max > 0 && $n > $max) {
                return $this->msg($field, lang('Frm.errMaxChoices', [$max]));
            }

            return null;
        }

        return in_array((string) $value, $allowed, true) ? null : $this->msg($field, lang('Frm.errOption'));
    }

    private function checkNumber(array $field, $value): ?string
    {
        if (!is_numeric($value)) {
            return $this->msg($field, lang('Frm.errNumber'));
        }
        $n = (float) $value;
        if (isset($field['min']) && is_numeric($field['min']) && $n < (float) $field['min']) {
            return $this->msg($field, lang('Frm.errMin', [$field['min']]));
        }
        if (isset($field['max']) && is_numeric($field['max']) && $n > (float) $field['max']) {
            return $this->msg($field, lang('Frm.errMax', [$field['max']]));
        }

        return null;
    }

    private function checkRange(array $field, $value, int $min, int $max): ?string
    {
        if (!is_numeric($value)) {
            return $this->msg($field, lang('Frm.errNumber'));
        }
        $n = (int) $value;

        return ($n >= $min && $n <= $max) ? null : $this->msg($field, lang('Frm.errRange', [$min, $max]));
    }

    private function checkDate(array $field, $value): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) !== 1) {
            return $this->msg($field, lang('Frm.errDate'));
        }
        [$y, $m, $d] = array_map('intval', explode('-', (string) $value));
        if (!checkdate($m, $d, $y)) {
            return $this->msg($field, lang('Frm.errDate'));
        }
        if (($field['minDate'] ?? '') !== '' && $value < $field['minDate']) {
            return $this->msg($field, lang('Frm.errDateMin', [$field['minDate']]));
        }
        if (($field['maxDate'] ?? '') !== '' && $value > $field['maxDate']) {
            return $this->msg($field, lang('Frm.errDateMax', [$field['maxDate']]));
        }

        return null;
    }

    private function checkText(array $field, string $value): ?string
    {
        $len = mb_strlen($value);
        $min = (int) ($field['minLength'] ?? 0);
        $max = (int) ($field['maxLength'] ?? 0);

        if ($min > 0 && $len < $min) {
            return $this->msg($field, lang('Frm.errTooShort', [$min]));
        }
        if ($max > 0 && $len > $max) {
            return $this->msg($field, lang('Frm.errTooLong', [$max]));
        }

        $regex = trim((string) ($field['validationRegex'] ?? ''));
        if ($regex !== '' && !$this->matchesRegex($regex, $value)) {
            return $this->msg($field, lang('Frm.errFormat'));
        }

        return null;
    }

    /**
     * Aplica la expresión regular del campo de forma defensiva.
     *
     * El patrón lo escribe quien arma el formulario, así que puede ser inválido
     * o catastróficamente lento. Tres precauciones:
     *   - se compila con `@` y se comprueba el resultado: un patrón roto NO
     *     invalida la respuesta de quien contesta (no es culpa suya), se ignora
     *     y se deja constancia en el registro;
     *   - el texto se recorta antes de aplicarla, para acotar el retroceso;
     *   - se mira `preg_last_error()`: si el motor se rindió, se da por buena.
     *
     * El patrón se envuelve entre delimitadores propios y se ancla, así que un
     * modificador como `e` no puede colarse desde el formulario.
     */
    private function matchesRegex(string $pattern, string $value): bool
    {
        $compiled = '/' . str_replace('/', '\/', $pattern) . '/u';
        $subject  = mb_substr($value, 0, self::REGEX_MAX_INPUT);

        $result = @preg_match($compiled, $subject);

        if ($result === false) {
            log_message('warning', 'Formularios: expresión regular inválida en un campo: ' . $pattern);

            return true;
        }
        if (preg_last_error() !== PREG_NO_ERROR) {
            log_message('warning', 'Formularios: la expresión regular no pudo evaluarse: ' . $pattern);

            return true;
        }

        return $result === 1;
    }
}
