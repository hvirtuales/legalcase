<?php

namespace Components\Formularios\Libraries;

use Components\Formularios\Models\FormModel;

/**
 * El ciclo de vida del formulario y, sobre todo, LA FRONTERA DE SEGURIDAD.
 *
 * `sanitizeTree()` no limpia el árbol que llega del editor: lo RECONSTRUYE
 * campo por campo contra `FieldRegistry`. Un tipo inventado, una propiedad de
 * más, un nivel de encabezado 99, un operador de lógica desconocido o
 * cuatrocientas opciones en un desplegable se quedan fuera, porque no hay
 * ninguna vía por la que puedan entrar: solo se copia lo que el registro
 * declara. Es el mismo criterio que `PageService::sanitizeTree()` de Páginas.
 *
 * Lo que está en la base de datos ya es seguro. El `Renderer` vuelve a escapar
 * de todos modos, porque una segunda barrera nunca sobra.
 */
class FormService
{
    /** Las dos presentaciones del formulario publicado. */
    public const DISPLAYS = ['card', 'fullscreen'];

    /** Multi-paso (una página por pantalla) o todo seguido. */
    public const FLOWS = ['wizard', 'single'];

    private FormModel $forms;

    public function __construct()
    {
        $this->forms = new FormModel();
    }

    /**
     * Ajustes por defecto de un formulario nuevo.
     *
     * `display` es la opción que se elige AL CREARLO: ficha centrada o pantalla
     * completa. Se puede cambiar después en la pestaña Formulario del inspector.
     */
    public function defaultSettings(string $display = 'card'): array
    {
        return [
            'display'         => in_array($display, self::DISPLAYS, true) ? $display : 'card',
            'flow'            => 'wizard',
            'primaryColor'    => '',
            'showProgressBar' => true,
            'showBrand'       => true,
            'description'     => '',
            'submitLabel'     => '',
            'successTitle'    => '',
            'successMessage'  => '',
            'redirectUrl'     => '',
            'notifyEmail'     => '',
            'ackFieldId'      => '',
            'consentText'     => '',
        ];
    }

    /** Un formulario recién creado: una página, ningún campo. */
    public function defaultSchema(): array
    {
        return [
            'pages'  => [[
                'id'          => 'p_' . bin2hex(random_bytes(4)),
                'title'       => lang('Frm.defaultPageTitle'),
                'description' => '',
            ]],
            'fields' => [],
        ];
    }

    /**
     * Reconstruye el árbol contra el registro. Nada que no esté declarado pasa.
     *
     * @return array{pages:array,fields:array}
     */
    public function sanitizeTree($tree): array
    {
        $tree  = is_array($tree) ? $tree : [];
        $pages = $this->sanitizePages($tree['pages'] ?? []);

        $pageIds = array_column($pages, 'id');
        $first   = $pageIds[0];

        $fields = [];
        $seen   = [];
        foreach (($tree['fields'] ?? []) as $raw) {
            if (count($fields) >= FieldRegistry::MAX_FIELDS) {
                break;
            }
            $field = $this->sanitizeField($raw, $pageIds, $first, $seen);
            if ($field !== null) {
                $seen[$field['id']] = true;
                $fields[]           = $field;
            }
        }

        // Una regla que apunte a un campo que ya no existe se retira: dejarla
        // ocultaría el campo para siempre sin que nada lo explique.
        $ids = array_column($fields, 'id');
        foreach ($fields as $i => $f) {
            if (isset($f['conditionalRule']) && !in_array($f['conditionalRule']['fieldId'], $ids, true)) {
                unset($fields[$i]['conditionalRule']);
            }
        }

        return ['pages' => $pages, 'fields' => array_values($fields)];
    }

    /** Siempre devuelve al menos una página: un formulario sin páginas no existe. */
    private function sanitizePages($raw): array
    {
        $pages = [];
        foreach (is_array($raw) ? $raw : [] as $p) {
            if (count($pages) >= FieldRegistry::MAX_PAGES) {
                break;
            }
            if (!is_array($p)) {
                continue;
            }
            $id = $this->safeId((string) ($p['id'] ?? ''), 'p_');
            if ($id === '' || isset($pages[$id])) {
                continue;
            }
            $pages[$id] = [
                'id'          => $id,
                'title'       => $this->text($p['title'] ?? '', 180) ?: lang('Frm.defaultPageTitle'),
                'description' => $this->text($p['description'] ?? '', 400),
            ];
        }

        if (!$pages) {
            $d = $this->defaultSchema()['pages'][0];

            return [$d];
        }

        return array_values($pages);
    }

    /**
     * Un campo, reconstruido: id, tipo, página y SOLO las propiedades que el
     * registro declara para ese tipo, cada una pasada por su saneador.
     */
    private function sanitizeField($raw, array $pageIds, string $firstPage, array $seen): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $type = (string) ($raw['type'] ?? '');
        if (!FieldRegistry::has($type)) {
            return null;   // tipo inventado: fuera
        }

        $id = $this->safeId((string) ($raw['id'] ?? ''), 'f_');
        if ($id === '' || isset($seen[$id])) {
            $id = 'f_' . bin2hex(random_bytes(5));
        }

        $pageId = (string) ($raw['pageId'] ?? '');
        if (!in_array($pageId, $pageIds, true)) {
            $pageId = $firstPage;   // huérfano: a la primera página
        }

        $out = ['id' => $id, 'pageId' => $pageId, 'type' => $type];

        foreach (FieldRegistry::propsOf($type) as $prop) {
            $name = $prop['name'];
            if (!array_key_exists($name, $raw)) {
                continue;
            }
            $value = $this->sanitizeValue($prop, $raw[$name]);
            if ($value !== null) {
                $out[$name] = $value;
            }
        }

        // La etiqueta siempre existe, aunque venga vacía: el renderizador y la
        // instantánea de la respuesta cuentan con la clave.
        $out['label'] = (string) ($out['label'] ?? '');
        // El ancho se resuelve en una variable antes de compararlo: leyendo
        // `$out['width']` dentro de la rama verdadera se aceptaba el valor por
        // defecto y acto seguido se leía una clave que no existía.
        $width        = (string) ($out['width'] ?? '100');
        $out['width'] = in_array($width, FieldRegistry::WIDTHS, true) ? $width : '100';

        $rule = $this->sanitizeRule($raw['conditionalRule'] ?? null, $id);
        if ($rule !== null) {
            $out['conditionalRule'] = $rule;
        }

        return $out;
    }

    /** Cada propiedad se comprueba contra el tipo que declaró el registro. */
    private function sanitizeValue(array $prop, $value)
    {
        switch ($prop['type']) {
            case 'text':
                return $this->text($value, 500);

            case 'textarea':
                return $this->text($value, 4000);

            case 'number':
                if ($value === '' || $value === null) {
                    return null;
                }

                return is_numeric($value) ? (float) $value + 0 : null;

            case 'toggle':
                return (bool) $value;

            case 'select':
                // strval en las claves: sin esto, un `select` con opciones
                // numéricas cae siempre al valor por defecto (gotcha del
                // proyecto, el que hacía que un h1 saliera como h2).
                $allowed = FieldRegistry::propOptionValues($prop);

                return in_array((string) $value, $allowed, true) ? (string) $value : null;

            case 'options':
                return $this->sanitizeOptions($value);

            default:
                return null;
        }
    }

    /** Opciones de un desplegable: etiqueta y valor, con tope y sin duplicados. */
    private function sanitizeOptions($value): array
    {
        $out  = [];
        $used = [];

        foreach (is_array($value) ? $value : [] as $opt) {
            if (count($out) >= FieldRegistry::MAX_OPTIONS) {
                break;
            }
            if (!is_array($opt)) {
                continue;
            }
            $label = $this->text($opt['label'] ?? '', 190);
            $val   = $this->text($opt['value'] ?? '', 190);
            if ($val === '') {
                $val = $this->slugify($label);
            }
            if ($label === '' && $val === '') {
                continue;
            }
            // Dos opciones con el mismo valor harían imposible saber cuál se
            // eligió: la segunda se desambigua en vez de descartarse.
            $base = $val;
            $n    = 2;
            while (isset($used[$val])) {
                $val = $base . '_' . $n++;
            }
            $used[$val] = true;

            $out[] = [
                'id'    => $this->safeId((string) ($opt['id'] ?? ''), 'o_') ?: 'o_' . bin2hex(random_bytes(3)),
                'label' => $label !== '' ? $label : $val,
                'value' => $val,
            ];
        }

        return $out;
    }

    /** La regla condicional, con su operador comprobado contra la lista. */
    private function sanitizeRule($raw, string $ownId): ?array
    {
        if (!is_array($raw) || empty($raw['enabled'])) {
            return null;
        }

        $target = $this->safeId((string) ($raw['fieldId'] ?? ''), 'f_');
        // Un campo no puede depender de sí mismo: sería una regla que nunca se
        // puede resolver.
        if ($target === '' || $target === $ownId) {
            return null;
        }

        $op = (string) ($raw['operator'] ?? 'equals');
        if (!in_array($op, LogicEngine::OPERATORS, true)) {
            $op = 'equals';
        }

        return [
            'enabled'  => true,
            'fieldId'  => $target,
            'operator' => $op,
            'value'    => $this->text($raw['value'] ?? '', 190),
        ];
    }

    /** Texto plano recortado. El componente no acepta HTML en ningún campo. */
    private function text($v, int $max): string
    {
        if (is_array($v) || is_object($v)) {
            return '';
        }
        if (is_bool($v)) {
            $v = $v ? '1' : '';
        }

        return mb_substr(trim(strip_tags((string) $v)), 0, $max);
    }

    /**
     * Un id nuestro: prefijo + letras y números. Cualquier otra cosa no es un id.
     *
     * Lo que hace seguro el id es el JUEGO DE CARACTERES, no su longitud: con
     * `[a-z0-9]` no cabe nada que pueda escaparse a un atributo ni a un selector.
     * El mínimo estuvo en 4 y descartaba en silencio ids perfectamente legítimos
     * como `p_rh1`, y con la página se iban sus campos a la primera. Lo destapó
     * una plantilla de arranque que perdía dos de sus tres pasos.
     */
    private function safeId(string $id, string $prefix): string
    {
        return preg_match('/^' . preg_quote($prefix, '/') . '[a-z0-9]{2,32}$/', $id) === 1 ? $id : '';
    }

    /**
     * Los ajustes, reconstruidos igual que el árbol.
     *
     * Aquí vive la opción de PANTALLA COMPLETA (`display`), que se elige al
     * crear el formulario y se puede cambiar después.
     */
    public function sanitizeSettings($raw, array $current = []): array
    {
        $raw  = is_array($raw) ? $raw : [];
        $base = array_merge($this->defaultSettings(), is_array($current) ? $current : []);

        $display = (string) ($raw['display'] ?? $base['display']);
        $flow    = (string) ($raw['flow'] ?? $base['flow']);

        return [
            'display'         => in_array($display, self::DISPLAYS, true) ? $display : 'card',
            'flow'            => in_array($flow, self::FLOWS, true) ? $flow : 'wizard',
            'primaryColor'    => $this->hexColor($raw['primaryColor'] ?? $base['primaryColor']),
            'showProgressBar' => (bool) ($raw['showProgressBar'] ?? $base['showProgressBar']),
            'showBrand'       => (bool) ($raw['showBrand'] ?? $base['showBrand']),
            'description'     => $this->text($raw['description'] ?? $base['description'], 600),
            'submitLabel'     => $this->text($raw['submitLabel'] ?? $base['submitLabel'], 80),
            'successTitle'    => $this->text($raw['successTitle'] ?? $base['successTitle'], 180),
            'successMessage'  => $this->text($raw['successMessage'] ?? $base['successMessage'], 600),
            'redirectUrl'     => $this->httpUrl($raw['redirectUrl'] ?? $base['redirectUrl']),
            'notifyEmail'     => $this->email($raw['notifyEmail'] ?? $base['notifyEmail']),
            'ackFieldId'      => $this->safeId((string) ($raw['ackFieldId'] ?? $base['ackFieldId']), 'f_'),
            'consentText'     => $this->text($raw['consentText'] ?? $base['consentText'], 1000),
        ];
    }

    /**
     * Un color solo si de verdad lo es. Se guarda validado para poder emitirlo
     * CRUDO en el CSS: `esc($hex, 'css')` produce `\23 ...` y `esc($hex,'attr')`
     * `&#x23;...`, y ninguno de los dos es un color válido (gotcha del proyecto).
     */
    public function hexColor($v): string
    {
        $v = trim((string) $v);

        return preg_match('/^#[0-9a-fA-F]{3}$|^#[0-9a-fA-F]{6}$/', $v) === 1 ? $v : '';
    }

    /** Solo http(s): un `javascript:` acabaría dentro de una redirección. */
    private function httpUrl($v): string
    {
        $v = trim((string) $v);
        if ($v === '' || !preg_match('#^https?://#i', $v)) {
            return '';
        }

        return filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 500) : '';
    }

    private function email($v): string
    {
        $v = trim((string) $v);

        return filter_var($v, FILTER_VALIDATE_EMAIL) ? mb_substr($v, 0, 190) : '';
    }

    public function slugify(string $text): string
    {
        $text = trim(mb_strtolower($text));
        $from = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'à', 'è', 'ì', 'ò', 'ù', 'ç'];
        $to   = ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'c'];
        $text = str_replace($from, $to, $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim((string) $text, '-');
    }

    /**
     * Slug libre dentro de la empresa. Como en Páginas, no se rechaza el
     * repetido: se entrega el siguiente que esté libre.
     */
    public function uniqueSlug(string $base, int $companyId, ?int $ignoreId = null): string
    {
        $base = $this->slugify($base) ?: 'formulario';
        $slug = $base;
        $n    = 2;

        while (true) {
            $q = $this->forms->where('company_id', $companyId)->where('slug', $slug);
            if ($ignoreId) {
                $q->where('id !=', $ignoreId);
            }
            if ($q->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    /** Token de URL pública: 32 bytes aleatorios, nunca el id. */
    public function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Crea un formulario y devuelve su id.
     *
     * `$display` es lo que se elige en el diálogo de alta: ficha o pantalla
     * completa.
     */
    public function create(int $companyId, ?int $userId, string $title, string $display = 'card', ?array $schema = null): int
    {
        $title  = $this->text($title, 180) ?: lang('Frm.untitled');
        $schema = $schema !== null ? $this->sanitizeTree($schema) : $this->defaultSchema();

        $this->forms->insert([
            'company_id'       => $companyId,
            'user_id'          => $userId,
            'title'            => $title,
            'slug'             => $this->uniqueSlug($title, $companyId),
            'public_token'     => $this->newToken(),
            'status'           => 'draft',
            'schema_json'      => $schema,
            'published_schema' => ['pages' => [], 'fields' => []],
            'settings'         => $this->defaultSettings($display),
            'version'          => 0,
        ]);

        return (int) $this->forms->getInsertID();
    }

    /**
     * Publicar: copia el esquema en edición al publicado y sube la versión.
     *
     * Es lo que evita que editar un formulario vivo le cambie las preguntas a
     * quien lo está contestando en ese momento.
     */
    public function publish(array $form): void
    {
        $this->forms->update((int) $form['id'], [
            'status'           => 'published',
            'published_schema' => $this->sanitizeTree($form['schema_json'] ?? []),
            'version'          => (int) ($form['version'] ?? 0) + 1,
            'published_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /** El esquema que ve quien responde: el publicado; en borrador, el de edición. */
    public function liveSchema(array $form): array
    {
        $pub = $form['published_schema'] ?? [];
        if (is_array($pub) && !empty($pub['fields'])) {
            return $this->sanitizeTree($pub);
        }
        if (is_array($pub) && !empty($pub['pages'])) {
            return $this->sanitizeTree($pub);
        }

        return $this->sanitizeTree($form['schema_json'] ?? []);
    }

    /** Los campos de una página, en orden. */
    public function fieldsOfPage(array $schema, string $pageId): array
    {
        $first = $schema['pages'][0]['id'] ?? '';
        $out   = [];
        foreach ($schema['fields'] ?? [] as $f) {
            if ((string) ($f['pageId'] ?? $first) === $pageId) {
                $out[] = $f;
            }
        }

        return $out;
    }

    /** idCampo => etiqueta. Es la instantánea que congela cada respuesta. */
    public function labelsOf(array $schema): array
    {
        $out = [];
        foreach ($schema['fields'] ?? [] as $f) {
            if (!FieldRegistry::isStructural((string) $f['type'])) {
                $out[(string) $f['id']] = (string) ($f['label'] ?? '');
            }
        }

        return $out;
    }

    /** Un campo del esquema por su id, o null. */
    public function findField(array $schema, string $fieldId): ?array
    {
        foreach ($schema['fields'] ?? [] as $f) {
            if ((string) ($f['id'] ?? '') === $fieldId) {
                return $f;
            }
        }

        return null;
    }

    /**
     * Duplica un formulario. Los ids de campos y páginas se conservan (son
     * internos al árbol y no se cruzan entre formularios); lo que cambia es el
     * token público, para que el enlace del original siga siendo suyo.
     */
    public function duplicate(array $form, int $companyId, ?int $userId): int
    {
        $title = mb_substr((string) $form['title'], 0, 160) . ' ' . lang('Frm.copySuffix');

        $this->forms->insert([
            'company_id'       => $companyId,
            'user_id'          => $userId,
            'title'            => $title,
            'slug'             => $this->uniqueSlug($title, $companyId),
            'public_token'     => $this->newToken(),
            'status'           => 'draft',
            'schema_json'      => $this->sanitizeTree($form['schema_json'] ?? []),
            'published_schema' => ['pages' => [], 'fields' => []],
            'settings'         => $this->sanitizeSettings($form['settings'] ?? []),
            'version'          => 0,
            'max_responses'    => (int) ($form['max_responses'] ?? 0),
            'allow_multiple'   => (int) ($form['allow_multiple'] ?? 1),
        ]);

        return (int) $this->forms->getInsertID();
    }

    /**
     * Baja del formulario, arrastrando lo que el soft-delete NO arrastra.
     *
     * Un soft-delete no dispara ON DELETE CASCADE, así que las respuestas, su
     * índice y sus archivos quedarían para siempre. Se limpian aquí y no en el
     * controlador porque es una regla del dominio, no de una pantalla.
     */
    public function deleteForm(int $formId): void
    {
        $responses = new \Components\Formularios\Models\ResponseModel();
        $answers   = new \Components\Formularios\Models\AnswerModel();
        $files     = new AttachmentService();

        foreach ($responses->where('form_id', $formId)->findAll() as $r) {
            $files->deleteForResponse((int) $r['id']);
            $answers->where('response_id', $r['id'])->delete();
        }

        $responses->where('form_id', $formId)->delete();
        $this->forms->delete($formId);
    }

    /** Cuántas respuestas admite todavía, o null si no hay tope propio. */
    public function seatsLeft(array $form): ?int
    {
        $max = (int) ($form['max_responses'] ?? 0);
        if ($max <= 0) {
            return null;
        }

        $used = (new \Components\Formularios\Models\ResponseModel())->countFor((int) $form['id']);

        return max(0, $max - $used);
    }

    /**
     * Por qué NO se puede responder este formulario, o null si sí se puede.
     *
     * Un solo punto de decisión para la pantalla pública y para el POST: si
     * estuvieran separados, cerrar un formulario dejaría el envío abierto.
     */
    public function closedReason(array $form): ?string
    {
        if ((string) $form['status'] !== 'published') {
            return lang('Frm.pubNotAvailable');
        }
        $now = date('Y-m-d H:i:s');
        if (!empty($form['opens_at']) && $form['opens_at'] > $now) {
            return lang('Frm.pubNotOpenYet');
        }
        if (!empty($form['closes_at']) && $form['closes_at'] < $now) {
            return lang('Frm.pubClosed');
        }
        $left = $this->seatsLeft($form);
        if ($left !== null && $left <= 0) {
            return lang('Frm.pubFull');
        }

        return null;
    }
}
