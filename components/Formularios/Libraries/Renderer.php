<?php

namespace Components\Formularios\Libraries;

/**
 * JSON del formulario => HTML. Es la ÚNICA autoridad sobre la salida.
 *
 * Todo lo que viene de datos pasa por `esc()`; el color se valida y se emite
 * crudo (la excepción explicada en `FormService::hexColor()`), y cualquier otro
 * valor se comprueba contra los que declara `FieldRegistry`. El árbol que llega
 * aquí ya pasó por el saneador al guardarse: esta es la segunda barrera, no la
 * primera, y por eso ninguna de las dos se puede quitar.
 *
 * Se pintan TODAS las páginas, siempre. El paso a paso lo hace el JavaScript
 * mostrando una cada vez; sin JavaScript el formulario se ve entero y se envía
 * igual, y el servidor valida lo mismo en los dos casos. Degradar solo, no
 * romperse.
 *
 * Nombres de los campos en el POST:
 *   f[<id>]     valor normal
 *   f[<id>][]   casillas múltiples
 *   file_<id>   archivo subido
 *   sig_<id>    firma (PNG en data URI, en un campo oculto)
 */
class Renderer
{
    private array $schema;
    private array $settings;
    private array $values;
    private array $errors;
    private LogicEngine $logic;

    public function __construct(array $schema, array $settings, array $values = [], array $errors = [])
    {
        $this->schema   = $schema;
        $this->settings = $settings;
        $this->values   = $values;
        $this->errors   = $errors;
        $this->logic    = new LogicEngine();
    }

    /** Todas las páginas, cada una en su sección. */
    public function render(): string
    {
        $pages = $this->schema['pages'] ?? [];
        $total = count($pages);
        $html  = '';

        foreach (array_values($pages) as $i => $page) {
            $fields = (new FormService())->fieldsOfPage($this->schema, (string) $page['id']);
            $html .= $this->page($page, $fields, $i, $total);
        }

        return $html;
    }

    private function page(array $page, array $fields, int $index, int $total): string
    {
        $head = '';
        if ($total > 1 || ($page['description'] ?? '') !== '') {
            $head = '<header class="ff-page-head">'
                . ($total > 1
                    ? '<span class="ff-step">' . esc(lang('Frm.stepOf', [$index + 1, $total])) . '</span>'
                    : '')
                . '<h2 class="ff-page-title">' . esc($page['title']) . '</h2>'
                . (($page['description'] ?? '') !== ''
                    ? '<p class="ff-page-desc">' . esc($page['description']) . '</p>'
                    : '')
                . '</header>';
        }

        $body = '';
        foreach ($fields as $f) {
            $body .= $this->field($f);
        }
        if ($body === '') {
            $body = '<p class="ff-empty">' . esc(lang('Frm.pageEmpty')) . '</p>';
        }

        // Solo se esconden las páginas siguientes cuando el recorrido es PASO A
        // PASO. Con «todo en una página» quedaban ocultas y sin nada que las
        // mostrara: el JavaScript no toca las páginas si no hay asistente, así
        // que la mitad del formulario era inalcanzable.
        $stepped = ($this->settings['flow'] ?? 'wizard') === 'wizard' && $total > 1;

        return '<section class="ff-page" data-ff-page="' . $index . '"'
            . ($stepped && $index > 0 ? ' hidden' : '') . '>'
            . $head . '<div class="ff-grid">' . $body . '</div></section>';
    }

    /**
     * Un valor de entre los declarados, o el de reserva.
     *
     * Existe porque el patrón «comprobar con `?? defecto` y luego leer la clave
     * en la rama verdadera» lee una clave que puede no existir: el valor por
     * defecto pasaba la comprobación y el acceso posterior fallaba. Lo destapó
     * el autotest con un campo sin `ratingIcon`.
     */
    private function pick(array $f, string $key, array $allowed, string $default): string
    {
        $v = (string) ($f[$key] ?? $default);

        return in_array($v, $allowed, true) ? $v : $default;
    }

    /** La envoltura de un campo: ancho, ancla, regla condicional y error. */
    private function field(array $f): string
    {
        $id    = (string) $f['id'];
        $type  = (string) $f['type'];
        $width = $this->pick($f, 'width', FieldRegistry::WIDTHS, '100');

        $attrs = ' class="ff-item ff-w' . $width . '" data-ff-item="' . esc($id, 'attr') . '"';
        if (($f['anchor'] ?? '') !== '') {
            $attrs .= ' id="' . esc($this->anchor((string) $f['anchor']), 'attr') . '"';
        }

        // La regla viaja al navegador para que muestre y oculte mientras se
        // escribe. El servidor la vuelve a evaluar igualmente al recibir.
        $hidden = '';
        if (isset($f['conditionalRule'])) {
            $attrs .= ' data-ff-rule="' . esc(json_encode($f['conditionalRule'], JSON_UNESCAPED_UNICODE), 'attr') . '"';
            if (!$this->logic->isVisible($f, $this->values)) {
                $hidden = ' hidden';
            }
        }

        $error = $this->errors[$id] ?? '';
        if ($error !== '') {
            $attrs .= ' data-ff-invalid="1"';
        }

        $inner = FieldRegistry::isStructural($type)
            ? $this->control($f)
            : $this->label($f) . $this->control($f) . $this->help($f) . $this->error($error);

        return '<div' . $attrs . $hidden . '>' . $inner . '</div>';
    }

    private function anchor(string $raw): string
    {
        $a = preg_replace('/[^A-Za-z0-9_-]/', '', $raw);

        return $a === '' ? '' : mb_substr($a, 0, 60);
    }

    private function label(array $f): string
    {
        $text = (string) ($f['label'] ?? '');
        if ($text === '') {
            return '';
        }
        $req = !empty($f['required'])
            ? ' <span class="ff-req" aria-hidden="true">*</span>'
            : '';

        return '<label class="ff-label" for="' . $this->cid($f) . '">' . esc($text) . $req . '</label>';
    }

    private function help(array $f): string
    {
        $h = (string) ($f['helperText'] ?? '');

        return $h === '' ? '' : '<p class="ff-help">' . esc($h) . '</p>';
    }

    private function error(string $msg): string
    {
        return $msg === '' ? '' : '<p class="ff-error" role="alert">' . esc($msg) . '</p>';
    }

    /** El valor con el que se repinta tras un error de validación. */
    private function value(array $f)
    {
        $id = (string) $f['id'];

        return array_key_exists($id, $this->values) ? $this->values[$id] : ($f['defaultValue'] ?? null);
    }

    /**
     * El id del campo, COMPROBADO.
     *
     * El saneador ya garantiza la forma `f_` + hexadecimal, pero el renderizador
     * es la segunda barrera y no se fía: si el id no tiene esa forma, se
     * sustituye por uno derivado. Con esa garantía el id se puede emitir CRUDO
     * en los nombres y en los atributos.
     *
     * ¿Por qué crudo y no con `esc()`? Porque `esc('f[x]', 'attr')` produce
     * `f&#x5B;x&#x5D;`: el navegador lo interpreta bien, pero el HTML queda
     * ilegible y no hay forma de auditarlo de un vistazo. Es el mismo criterio
     * que con el color de marca, que también se valida y se emite tal cual.
     */
    private function fid(array $f): string
    {
        $id = (string) ($f['id'] ?? '');

        return preg_match('/^f_[a-z0-9]{2,32}$/', $id) === 1 ? $id : 'f_' . substr(md5($id), 0, 10);
    }

    private function name(array $f): string
    {
        return 'f[' . $this->fid($f) . ']';
    }

    /** El id del control en el HTML, para que la etiqueta lo pueda enfocar. */
    private function cid(array $f, string $suffix = ''): string
    {
        return 'c_' . $this->fid($f) . ($suffix !== '' ? '_' . preg_replace('/[^a-z0-9]/i', '', $suffix) : '');
    }

    /**
     * El control de cada tipo. Añadir un tipo al registro obliga a añadir su
     * `case` aquí: si falta, el campo no se pinta y se ve en la primera prueba.
     */
    private function control(array $f): string
    {
        return match ((string) $f['type']) {
            'text', 'email', 'phone' => $this->input($f),
            'number'    => $this->number($f),
            'textarea'  => $this->textarea($f),
            'select'    => $this->select($f),
            'radio'     => $this->radio($f),
            'checkbox'  => $this->checkbox($f),
            'switch'    => $this->switchControl($f),
            'rating'    => $this->rating($f),
            'nps'       => $this->nps($f),
            'slider'    => $this->slider($f),
            'date'      => $this->dateTime($f, 'date'),
            'time'      => $this->dateTime($f, 'time'),
            'file'      => $this->file($f),
            'signature' => $this->signature($f),
            'heading'   => $this->heading($f),
            'paragraph' => $this->paragraph($f),
            'divider'   => '<hr class="ff-divider">',
            default     => '',
        };
    }

    private function input(array $f): string
    {
        $type = match ((string) $f['type']) {
            'email' => 'email',
            'phone' => 'tel',
            default => 'text',
        };

        $extra = '';
        if (($f['maxLength'] ?? 0) > 0) {
            $extra .= ' maxlength="' . (int) $f['maxLength'] . '"';
        }

        return '<input class="ff-input" type="' . $type . '"'
            . ' id="' . $this->cid($f) . '"'
            . ' name="' . $this->name($f) . '"'
            . ' value="' . esc((string) ($this->value($f) ?? ''), 'attr') . '"'
            . $this->placeholder($f) . $this->required($f) . $extra . '>';
    }

    private function number(array $f): string
    {
        $extra = '';
        foreach (['min' => 'min', 'max' => 'max', 'step' => 'step'] as $key => $attr) {
            if (isset($f[$key]) && is_numeric($f[$key])) {
                $extra .= ' ' . $attr . '="' . esc((string) $f[$key], 'attr') . '"';
            }
        }

        return '<input class="ff-input" type="number"'
            . ' id="' . $this->cid($f) . '"'
            . ' name="' . $this->name($f) . '"'
            . ' value="' . esc((string) ($this->value($f) ?? ''), 'attr') . '"'
            . $this->placeholder($f) . $this->required($f) . $extra . '>';
    }

    private function textarea(array $f): string
    {
        $rows = (int) ($f['rows'] ?? 4);
        $rows = $rows > 0 && $rows <= 20 ? $rows : 4;

        return '<textarea class="ff-input ff-textarea" rows="' . $rows . '"'
            . ' id="' . $this->cid($f) . '"'
            . ' name="' . $this->name($f) . '"'
            . $this->placeholder($f) . $this->required($f)
            . (($f['maxLength'] ?? 0) > 0 ? ' maxlength="' . (int) $f['maxLength'] . '"' : '')
            . '>' . esc((string) ($this->value($f) ?? '')) . '</textarea>';
    }

    private function placeholder(array $f): string
    {
        $p = (string) ($f['placeholder'] ?? '');

        return $p === '' ? '' : ' placeholder="' . esc($p, 'attr') . '"';
    }

    private function required(array $f): string
    {
        return !empty($f['required']) ? ' data-ff-required="1"' : '';
    }

    private function select(array $f): string
    {
        $current = (string) ($this->value($f) ?? '');
        $ph      = (string) ($f['placeholder'] ?? '') ?: lang('Frm.selectOne');

        $html = '<select class="ff-input ff-select" id="' . $this->cid($f) . '"'
            . ' name="' . $this->name($f) . '"' . $this->required($f) . '>'
            . '<option value="">' . esc($ph) . '</option>';

        foreach ($f['options'] ?? [] as $opt) {
            $v = (string) $opt['value'];
            $html .= '<option value="' . esc($v, 'attr') . '"'
                . ($v === $current ? ' selected' : '') . '>' . esc($opt['label']) . '</option>';
        }

        return $html . '</select>';
    }

    private function radio(array $f): string
    {
        $current = (string) ($this->value($f) ?? '');
        $html    = '<div class="ff-choices" role="radiogroup">';

        foreach ($f['options'] ?? [] as $i => $opt) {
            $v  = (string) $opt['value'];
            $cid = $this->cid($f, (string) $i);
            $html .= '<label class="ff-choice' . ($v === $current ? ' is-on' : '') . '">'
                . '<input type="radio" id="' . $cid . '" name="' . $this->name($f) . '"'
                . ' value="' . esc($v, 'attr') . '"' . ($v === $current ? ' checked' : '') . $this->required($f) . '>'
                . '<span>' . esc($opt['label']) . '</span></label>';
        }

        return $html . '</div>';
    }

    private function checkbox(array $f): string
    {
        $raw     = $this->value($f);
        $current = array_map('strval', is_array($raw) ? $raw : []);
        $html    = '<div class="ff-choices">';

        foreach ($f['options'] ?? [] as $i => $opt) {
            $v  = (string) $opt['value'];
            $on = in_array($v, $current, true);
            $html .= '<label class="ff-choice' . ($on ? ' is-on' : '') . '">'
                . '<input type="checkbox" id="' . $this->cid($f, (string) $i) . '"'
                . ' name="' . $this->name($f) . '[]"'
                . ' value="' . esc($v, 'attr') . '"' . ($on ? ' checked' : '') . $this->required($f) . '>'
                . '<span>' . esc($opt['label']) . '</span></label>';
        }

        return $html . '</div>';
    }

    private function switchControl(array $f): string
    {
        $on = $this->truthy($this->value($f));

        // El casillero oculto con valor 0 delante hace que un interruptor
        // apagado también viaje: sin él, no se distingue apagado de no enviado.
        return '<label class="ff-switch">'
            . '<input type="hidden" name="' . $this->name($f) . '" value="0">'
            . '<input type="checkbox" id="' . $this->cid($f) . '"'
            . ' name="' . $this->name($f) . '" value="1"'
            . ($on ? ' checked' : '') . $this->required($f) . '>'
            . '<span class="ff-switch-track" aria-hidden="true"></span>'
            . '<span class="ff-switch-text">' . esc((string) ($f['label'] ?? '')) . '</span>'
            . '</label>';
    }

    private function truthy($v): bool
    {
        if (is_bool($v)) {
            return $v;
        }

        return in_array(mb_strtolower(trim((string) $v)), ['1', 'true', 'on'], true);
    }

    private function rating(array $f): string
    {
        $max     = (int) ($f['maxStars'] ?? 5);
        $max     = $max >= 2 && $max <= 10 ? $max : 5;
        $icon    = $this->pick($f, "ratingIcon", ["star", "heart", "thumb"], "star");
        $current = (int) ($this->value($f) ?? 0);

        $html = '<div class="ff-rating" data-ff-rating="' . $this->fid($f) . '">'
            . '<input type="hidden" name="' . $this->name($f) . '"'
            . ' value="' . ($current > 0 ? $current : '') . '"' . $this->required($f) . '>';

        for ($i = 1; $i <= $max; $i++) {
            $html .= '<button type="button" class="ff-star' . ($i <= $current ? ' is-on' : '') . '"'
                . ' data-value="' . $i . '"'
                . ' aria-label="' . esc(lang('Frm.ratingOf', [$i, $max]), 'attr') . '">'
                . Icons::svg($icon) . '</button>';
        }

        return $html . '<span class="ff-rating-text">'
            . ($current > 0 ? esc(lang('Frm.ratingOf', [$current, $max])) : esc(lang('Frm.notRated')))
            . '</span></div>';
    }

    private function nps(array $f): string
    {
        $current = $this->value($f);
        $current = ($current === '' || $current === null) ? null : (int) $current;

        $html = '<div class="ff-nps" data-ff-nps="' . $this->fid($f) . '">'
            . '<input type="hidden" name="' . $this->name($f) . '"'
            . ' value="' . ($current === null ? '' : $current) . '"' . $this->required($f) . '>'
            . '<div class="ff-nps-row">';

        for ($i = 0; $i <= 10; $i++) {
            $tone = $i <= 6 ? 'low' : ($i <= 8 ? 'mid' : 'high');
            $html .= '<button type="button" class="ff-nps-btn ff-nps-' . $tone
                . ($current === $i ? ' is-on' : '') . '" data-value="' . $i . '">' . $i . '</button>';
        }

        return $html . '</div><div class="ff-nps-legend"><span>'
            . esc((string) ($f['lowLabel'] ?? '')) . '</span><span>'
            . esc((string) ($f['highLabel'] ?? '')) . '</span></div></div>';
    }

    private function slider(array $f): string
    {
        $min  = is_numeric($f['min'] ?? null) ? (float) $f['min'] : 0;
        $max  = is_numeric($f['max'] ?? null) ? (float) $f['max'] : 100;
        $step = is_numeric($f['step'] ?? null) && (float) $f['step'] > 0 ? (float) $f['step'] : 1;
        if ($max <= $min) {
            $max = $min + 1;
        }
        $val = $this->value($f);
        $val = is_numeric($val) ? (float) $val : $min;

        return '<div class="ff-slider" data-ff-slider="1">'
            . '<div class="ff-slider-head"><span>' . esc($this->num($min)) . '</span>'
            . '<output class="ff-slider-out">' . esc($this->num($val)) . '</output>'
            . '<span>' . esc($this->num($max)) . '</span></div>'
            . '<input type="range" class="ff-range" id="' . $this->cid($f) . '"'
            . ' name="' . $this->name($f) . '"'
            . ' min="' . esc($this->num($min), 'attr') . '" max="' . esc($this->num($max), 'attr') . '"'
            . ' step="' . esc($this->num($step), 'attr') . '" value="' . esc($this->num($val), 'attr') . '">'
            . '</div>';
    }

    /** Un número sin decimales cuando no los necesita. */
    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function dateTime(array $f, string $kind): string
    {
        $extra = '';
        if ($kind === 'date') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['minDate'] ?? '')) === 1) {
                $extra .= ' min="' . esc((string) $f['minDate'], 'attr') . '"';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['maxDate'] ?? '')) === 1) {
                $extra .= ' max="' . esc((string) $f['maxDate'], 'attr') . '"';
            }
        }

        return '<input class="ff-input" type="' . $kind . '"'
            . ' id="' . $this->cid($f) . '"'
            . ' name="' . $this->name($f) . '"'
            . ' value="' . esc((string) ($this->value($f) ?? ''), 'attr') . '"'
            . $this->required($f) . $extra . '>';
    }

    private function file(array $f): string
    {
        $exts = AttachmentService::extensionList((string) ($f['allowedExtensions'] ?? ''));
        $accept = $exts ? ' accept="' . esc('.' . implode(',.', $exts), 'attr') . '"' : '';
        $mb     = (int) ($f['maxSizeMB'] ?? 5);
        $mb     = $mb > 0 ? min($mb, AttachmentService::MAX_MB) : AttachmentService::MAX_MB;

        return '<label class="ff-file">'
            . '<input type="file" id="' . $this->cid($f) . '"'
            . ' name="' . 'file_' . $this->fid($f) . '"' . $accept . $this->required($f) . '>'
            . '<span class="ff-file-face">' . Icons::svg('upload')
            . '<span class="ff-file-text">' . esc(lang('Frm.fileDrop')) . '</span>'
            . '<span class="ff-file-hint">'
            . esc($exts ? implode(', ', $exts) : lang('Frm.fileAny')) . ' ' . esc(lang('Frm.fileMax', [$mb]))
            . '</span></span></label>';
    }

    /**
     * Firma: un lienzo y un campo oculto con el PNG en data URI.
     *
     * La app original guardaba la cadena literal 'signed_signature_data', que no
     * es una firma sino un rótulo. Aquí el trazo se decodifica en el servidor,
     * se comprueba que sea un PNG y se guarda como archivo de verdad.
     */
    private function signature(array $f): string
    {
        return '<div class="ff-sign" data-ff-sign="' . $this->fid($f) . '">'
            . '<div class="ff-sign-bar"><span>' . esc(lang('Frm.signHere')) . '</span>'
            . '<button type="button" class="ff-sign-clear">' . esc(lang('Frm.signClear')) . '</button></div>'
            . '<canvas class="ff-sign-pad" width="600" height="180"></canvas>'
            . '<input type="hidden" name="' . 'sig_' . $this->fid($f) . '" value=""'
            . $this->required($f) . '>'
            . '</div>';
    }

    private function heading(array $f): string
    {
        $level = $this->pick($f, "headingLevel", ["h2", "h3", "h4"], "h2");

        return '<' . $level . ' class="ff-heading ff-' . $level . '">' . esc((string) $f['label']) . '</' . $level . '>';
    }

    private function paragraph(array $f): string
    {
        $tone = $this->pick($f, "alertType", ["info", "warning", "success", "note"], "info");

        $title = (string) ($f['label'] ?? '');

        return '<div class="ff-note ff-note-' . $tone . '">'
            . Icons::svg($tone === 'warning' ? 'alert' : ($tone === 'success' ? 'check' : 'info'))
            . '<div>' . ($title !== '' ? '<strong>' . esc($title) . '</strong>' : '')
            . '<span>' . nl2br(esc((string) ($f['content'] ?? ''))) . '</span></div></div>';
    }
}
