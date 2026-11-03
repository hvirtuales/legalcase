<?php

namespace Components\Mailer\Libraries;

/**
 * La frontera de seguridad del constructor: el árbol que llega del navegador
 * **no se limpia, se RECONSTRUYE** contra `EmailRegistry`, propiedad a
 * propiedad. Un tipo inventado, una propiedad de más, una alineación que no
 * existe o un `href="javascript:"` se quedan fuera porque no hay ninguna vía por
 * la que puedan entrar: solo se copia lo que el registro declara.
 *
 * Es el mismo criterio que `FormService::sanitizeTree()` en Formularios y que
 * `PageService::sanitizeTree()` en Páginas. Antes de esto, el navegador
 * generaba el HTML del correo y el servidor lo guardaba tal cual.
 */
class DesignSanitizer
{
    private HtmlSanitizer $html;

    public function __construct()
    {
        $this->html = new HtmlSanitizer();
    }

    /** Un diseño vacío pero válido: una sección de una columna. */
    public function blank(): array
    {
        return ['sections' => [['cols' => 1, 'bg' => '', 'pad' => 24, 'blocks' => [[]]]]];
    }

    /**
     * @param array|string|null $design árbol o su JSON
     */
    public function clean($design): array
    {
        if (is_string($design)) {
            $design = json_decode($design, true);
        }
        if (!is_array($design) || !isset($design['sections']) || !is_array($design['sections'])) {
            return $this->blank();
        }

        $sections = [];
        $blocks   = 0;

        foreach ($design['sections'] as $raw) {
            if (count($sections) >= EmailRegistry::MAX_SECTIONS) {
                break;
            }
            if (!is_array($raw)) {
                continue;
            }

            $cols = (int) ($raw['cols'] ?? 1);
            $cols = ($cols >= 1 && $cols <= 3) ? $cols : 1;

            $cells = [];
            for ($i = 0; $i < $cols; $i++) {
                $cell = [];
                foreach (($raw['blocks'][$i] ?? []) as $b) {
                    if ($blocks >= EmailRegistry::MAX_BLOCKS) {
                        break;
                    }
                    $clean = $this->block($b);
                    if ($clean !== null) {
                        $cell[] = $clean;
                        $blocks++;
                    }
                }
                $cells[] = $cell;
            }

            $sections[] = [
                'cols'   => $cols,
                'bg'     => $this->color($raw['bg'] ?? ''),
                'pad'    => $this->num($raw['pad'] ?? 24, 0, 80, 24),
                'blocks' => $cells,
            ];
        }

        return $sections ? ['sections' => $sections] : $this->blank();
    }

    /** Un bloque, reconstruido: tipo declarado y SOLO sus propiedades. */
    private function block($raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $type = (string) ($raw['type'] ?? '');
        $def  = EmailRegistry::def($type);
        if (!$def) {
            return null;   // tipo inventado: fuera
        }

        $out = ['type' => $type];

        foreach ($def['fields'] as $prop) {
            $name  = $prop['name'];
            $given = $raw[$name] ?? null;
            $fall  = $def['defaults'][$name] ?? '';

            $out[$name] = $given === null ? $fall : $this->value($prop, $given, $fall);
        }

        return $out;
    }

    /** Cada propiedad se comprueba contra el tipo que declaró el registro. */
    private function value(array $prop, $given, $fallback)
    {
        switch ($prop['type']) {
            case 'text':
                return $this->text($given, 300);

            case 'rich':
                // El texto enriquecido pasa por el saneador de HTML: es lo único
                // que puede traer marcado, y se limpia AL GUARDAR.
                return $this->html->clean(is_string($given) ? $given : '');

            case 'code':
                return $this->html->clean(is_string($given) ? $given : '');

            case 'url':
            case 'image':
                $url = $this->text($given, 900);

                return ($url === '' || $this->html->safeUrl($url)) ? $url : '';

            case 'number':
                return is_numeric($given) ? (int) $given : (int) $fallback;

            case 'toggle':
                return (bool) $given;

            case 'color':
                return $this->color($given) ?: (string) $fallback;

            case 'align':
                return in_array($given, EmailRegistry::ALIGNS, true) ? $given : (string) $fallback;

            case 'select':
                // strval en las claves: sin esto, una opción numérica caería
                // siempre al valor por defecto (gotcha conocido del proyecto).
                return in_array((string) $given, EmailRegistry::optionValues($prop), true)
                    ? (string) $given : (string) $fallback;

            default:
                return $fallback;
        }
    }

    private function text($v, int $max): string
    {
        if (is_array($v) || is_object($v)) {
            return '';
        }

        return mb_substr(trim(strip_tags((string) $v)), 0, $max);
    }

    private function num($v, int $min, int $max, int $fallback): int
    {
        if (!is_numeric($v)) {
            return $fallback;
        }

        return max($min, min($max, (int) $v));
    }

    /**
     * Un color solo si de verdad lo es. Se guarda validado para poder emitirlo
     * CRUDO en el estilo en línea: escaparlo produciría un color inválido.
     */
    private function color($v): string
    {
        $v = trim((string) $v);

        return preg_match('/^#[0-9a-fA-F]{3}$|^#[0-9a-fA-F]{6}$/', $v) === 1 ? $v : '';
    }
}
