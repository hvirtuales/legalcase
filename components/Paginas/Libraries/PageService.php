<?php

namespace Components\Paginas\Libraries;

use Components\Paginas\Models\PageModel;

/**
 * Reglas de negocio de las páginas. Los controladores quedan finos: aquí están
 * el saneado del árbol, la dirección única, la publicación, el duplicado y la
 * recogida de los mensajes del formulario.
 *
 * Lo importante que hace este servicio, y que no puede quedar en el navegador:
 * **el árbol que llega del editor se reconstruye desde cero** contra
 * `BlockRegistry` (`sanitizeTree()`). No se «limpia» el JSON recibido — se copia
 * a uno nuevo, campo por campo, y lo que no esté declarado se queda fuera. Un
 * POST manipulado no puede introducir un tipo de bloque inventado, una propiedad
 * de más ni un árbol de mil niveles.
 */
class PageService
{
    /** Techos del árbol. Un editor honesto no se acerca a ninguno. */
    private const MAX_DEPTH  = 6;
    private const MAX_BLOCKS = 500;

    private HtmlSanitizer $san;
    private int $count = 0;

    public function __construct()
    {
        $this->san = new HtmlSanitizer();
    }

    // ------------------------------------------------------- saneado del árbol

    /**
     * Reconstruye la lista de bloques admitiendo **solo** lo declarado en
     * `BlockRegistry`. Es la frontera entre el navegador y la base de datos.
     *
     * @param string|null $parentType tipo del contenedor, para hacer valer la
     *                                jerarquía (una columna solo vive dentro de
     *                                una sección, y una sección solo tiene
     *                                columnas). Null = raíz de la página.
     */
    public function sanitizeTree($raw, int $depth = 0, ?string $parentType = null): array
    {
        if (!is_array($raw) || $depth > self::MAX_DEPTH) {
            return [];
        }
        if ($depth === 0) {
            $this->count = 0;
        }

        $out = [];

        foreach ($raw as $node) {
            if (!is_array($node) || $this->count >= self::MAX_BLOCKS) {
                continue;
            }
            $type = (string) ($node['type'] ?? '');
            if (!BlockRegistry::exists($type)) {
                continue; // tipo inventado: fuera
            }
            if (!$this->fits($type, $parentType)) {
                continue; // tipo válido, pero no en este sitio del árbol
            }
            $this->count++;

            $block = [
                'id'    => $this->safeId($node['id'] ?? ''),
                'type'  => $type,
                'props' => $this->sanitizeProps($type, $node['props'] ?? []),
            ];

            if (BlockRegistry::isContainer($type)) {
                $block['children'] = $this->sanitizeTree($node['children'] ?? [], $depth + 1, $type);
            }

            $out[] = $block;
        }

        return $out;
    }

    /**
     * ¿Este tipo puede ir dentro de ese contenedor?
     *
     * Dos reglas, y no se dan por supuestas aunque el editor ya las respete: un
     * POST manipulado (o un bloque guardado de una versión antigua) podría traer
     * una columna suelta en la raíz —el renderizador la pintaría como un `div`
     * huérfano— o una sección con hijos que no son columnas, que rompería la
     * rejilla.
     */
    private function fits(string $type, ?string $parentType): bool
    {
        if ($type === 'column') {
            return $parentType === 'section';
        }
        if ($parentType === 'section') {
            return $type === 'column';
        }

        return true;
    }

    /** Copia las propiedades declaradas del tipo, saneando según su clase de campo. */
    private function sanitizeProps(string $type, $raw): array
    {
        if (!is_array($raw)) {
            return BlockRegistry::defaults($type);
        }

        $out = [];

        foreach (BlockRegistry::propNames($type) as $name) {
            $field = BlockRegistry::field($type, $name);
            $value = $raw[$name] ?? (BlockRegistry::defaults($type)[$name] ?? '');
            $out[$name] = $this->sanitizeValue($field, $value);
        }

        return $out;
    }

    /** Sanea un valor según el tipo de campo del inspector. */
    private function sanitizeValue(?array $field, $value)
    {
        $kind = $field['type'] ?? 'text';

        switch ($kind) {
            case 'richtext':
                return $this->san->clean(is_string($value) ? $value : '');

            case 'textarea':
                // El bloque avanzado guarda HTML; el resto, texto plano.
                return ($field['name'] ?? '') === 'code'
                    ? $this->san->clean(is_string($value) ? $value : '')
                    : $this->plain($value, 4000);

            case 'select':
                // `strval` sobre las claves: PHP convierte en enteros las que
                // son numéricas («2» columnas, «3» niveles…), y sin esto la
                // comparación estricta rechazaba TODAS las opciones numéricas
                // y las dejaba en el valor por defecto (ver BlockRegistry).
                $allowed = array_map('strval', array_keys($field['options'] ?? []));
                $v       = is_scalar($value) ? (string) $value : '';

                return in_array($v, $allowed, true) ? $v : (string) ($allowed[0] ?? '');

            case 'toggle':
                return !empty($value) && $value !== '0' && $value !== 'false' ? '1' : '0';

            case 'color':
                // 3, 4, 6 u 8 dígitos: los de 4 y 8 llevan el canal alfa al
                // final (`#1a2b3c80` = ese color al 50 %), que es CSS estándar
                // y evita guardar la transparencia en un campo aparte.
                $v = is_scalar($value) ? trim((string) $value) : '';

                return preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v) ? $v : '';

            case 'number':
                return is_numeric($value) ? (string) (0 + $value) : '';

            case 'anchor':
                // El ancla acaba siendo un `id=` en el HTML y el destino de un
                // enlace `#…`, así que se normaliza a slug: sin acentos, sin
                // espacios y empezando por letra (un id que empieza por dígito
                // rompe los selectores CSS).
                //
                // **Rescata en vez de rechazar**: «Contáctanos Ya» → `contactanos-ya`
                // y «2contacto» → `contacto`. Quien escribe un ancla quiere poder
                // enlazarla; devolver vacío por un carácter de más obligaría a
                // adivinar la regla. Solo se descarta lo que no deja nada
                // aprovechable («123», «###»). Sea cual sea la entrada, la salida
                // siempre casa con la expresión de abajo, así que nada raro puede
                // acabar dentro del atributo `id`.
                $v = $this->slugify(is_scalar($value) ? (string) $value : '');
                $v = ltrim($v, '0123456789-');

                return preg_match('/^[a-z][a-z0-9-]{0,60}$/', $v) ? $v : '';

            case 'image':
            case 'link':
                $v = is_scalar($value) ? trim((string) $value) : '';

                return $this->san->safeUrl($v) ? mb_substr($v, 0, 500) : '';

            case 'repeater':
                return $this->sanitizeRepeater($field, $value);

            default: // text
                return $this->plain($value, 500);
        }
    }

    /** Repetidor: lista de filas, cada una con los sub-campos declarados. */
    private function sanitizeRepeater(array $field, $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];

        foreach (array_slice($value, 0, 50) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean = [];

            foreach ($field['fields'] ?? [] as $sub) {
                $clean[$sub['name']] = $this->sanitizeValue($sub, $row[$sub['name']] ?? '');
            }
            $out[] = $clean;
        }

        return $out;
    }

    /** Texto plano: sin etiquetas, sin saltos de control, acotado. */
    private function plain($value, int $max): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $v = strip_tags((string) $value);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';

        return mb_substr(trim($v), 0, $max);
    }

    /** Id del bloque: si viene raro, se genera uno nuevo. */
    private function safeId($id): string
    {
        $id = is_scalar($id) ? (string) $id : '';

        return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) ? $id : $this->newId();
    }

    public function newId(): string
    {
        return 'b' . bin2hex(random_bytes(5));
    }

    // ------------------------------------------------------------- direcciones

    /**
     * Dirección libre dentro de la empresa. En vez de rechazar el duplicado
     * —que obligaría al usuario a inventarse otro—, devuelve el siguiente libre:
     * `nosotros`, `nosotros-2`, `nosotros-3`…
     */
    public function uniqueSlug(int $companyId, string $desired, ?int $ignoreId = null): string
    {
        $base = $this->slugify($desired);
        if ($base === '') {
            $base = 'pagina';
        }

        $model = new PageModel();
        $slug  = $base;
        $n     = 1;

        while (true) {
            $q = $model->where('company_id', $companyId)->where('slug', $slug);
            if ($ignoreId) {
                $q->where('id !=', $ignoreId);
            }
            if ($q->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . '-' . (++$n);
        }
    }

    /**
     * Acentos y caracteres especiales → su equivalente ASCII.
     *
     * Va a mano y no con `iconv('ASCII//TRANSLIT')` porque el resultado de esa
     * función **depende de la configuración regional del servidor**: en este
     * equipo (Windows) «Quiénes» sale como `Qui'enes`, y el apóstrofo acaba
     * convertido en un guion — `qui-enes-somos`. Una dirección pública no puede
     * depender de en qué máquina se generó.
     */
    private const TRANSLIT = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss', 'æ' => 'ae',
    ];

    /** Texto → slug: sin acentos, minúsculas, guiones. */
    public function slugify(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, self::TRANSLIT);
        // Lo que no sea a-z0-9 (incluido cualquier acento que no esté en el mapa)
        // se convierte en separador y luego se colapsa.
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
        $text = preg_replace('/-+/', '-', $text) ?? '';

        return mb_substr(trim($text, '-'), 0, 180);
    }

    // ------------------------------------------------- estructura de la página

    /** Un bloque nuevo del tipo pedido, con sus valores por defecto. */
    public function newBlock(string $type, array $props = []): array
    {
        if (!BlockRegistry::exists($type)) {
            return [];
        }

        $block = [
            'id'    => $this->newId(),
            'type'  => $type,
            'props' => array_merge(BlockRegistry::defaults($type), $props),
        ];

        if (BlockRegistry::isContainer($type)) {
            $block['children'] = [];
            // Una sección nace con tantas columnas como diga su propiedad.
            if ($type === 'section') {
                $cols = max(1, min(4, (int) ($block['props']['columns'] ?? 1)));

                for ($i = 0; $i < $cols; $i++) {
                    $block['children'][] = $this->newBlock('column');
                }
            }
        }

        return $block;
    }

    /**
     * Vuelve a generar los ids de todo el árbol. Necesario al duplicar una
     * página o al insertar una plantilla: dos bloques con el mismo id harían que
     * el formulario de contacto no supiera de cuál vino el mensaje.
     */
    public function reindex(array $tree): array
    {
        foreach ($tree as $i => $node) {
            if (!is_array($node)) {
                unset($tree[$i]);

                continue;
            }
            $tree[$i]['id'] = $this->newId();
            if (isset($node['children']) && is_array($node['children'])) {
                $tree[$i]['children'] = $this->reindex($node['children']);
            }
        }

        return array_values($tree);
    }

    /** Busca un bloque por su id en todo el árbol (lo usa el formulario). */
    public function findBlock(array $tree, string $uid): ?array
    {
        foreach ($tree as $node) {
            if (!is_array($node)) {
                continue;
            }
            if ((string) ($node['id'] ?? '') === $uid) {
                return $node;
            }
            if (!empty($node['children']) && is_array($node['children'])) {
                $found = $this->findBlock($node['children'], $uid);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /** Cuenta los bloques del árbol (para el listado: «12 bloques»). */
    public function countBlocks(array $tree): int
    {
        $n = 0;

        foreach ($tree as $node) {
            if (!is_array($node)) {
                continue;
            }
            $n++;
            if (!empty($node['children']) && is_array($node['children'])) {
                $n += $this->countBlocks($node['children']);
            }
        }

        return $n;
    }

    // -------------------------------------------------- formulario de contacto

    /**
     * Recoge un envío del formulario de contacto.
     *
     * Los campos admitidos los dicta **el bloque**, no el POST: se recorre la
     * definición guardada y se leen únicamente esas posiciones (`f0`, `f1`…).
     * Lo que llegue de más se descarta sin más.
     *
     * @return array{ok:bool,error:string,title:string,data:array}
     */
    public function collectSubmission(array $block, array $post): array
    {
        $props  = is_array($block['props'] ?? null) ? $block['props'] : [];
        $fields = is_array($props['fields'] ?? null) ? $props['fields'] : [];
        $title  = (string) ($props['title'] ?? '');

        if (!$fields) {
            return ['ok' => false, 'error' => lang('Pag.formNoFields'), 'title' => $title, 'data' => []];
        }

        $data = [];

        foreach ($fields as $i => $f) {
            if (!is_array($f)) {
                continue;
            }
            $label = trim((string) ($f['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $value = trim((string) ($post['f' . $i] ?? ''));
            $req   = !empty($f['required']) && $f['required'] !== '0';

            if ($req && $value === '') {
                return [
                    'ok'    => false,
                    'error' => lang('Pag.formRequired', [$label]),
                    'title' => $title,
                    'data'  => [],
                ];
            }
            if ($value !== '' && ($f['type'] ?? '') === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return [
                    'ok'    => false,
                    'error' => lang('Pag.formBadEmail', [$label]),
                    'title' => $title,
                    'data'  => [],
                ];
            }

            // Se guarda etiqueta → valor: el mensaje se lee sin tener que
            // reconstruir el formulario que lo originó (que puede haber cambiado).
            $data[$label] = mb_substr($value, 0, 2000);
        }

        return ['ok' => true, 'error' => '', 'title' => $title, 'data' => $data];
    }
}
