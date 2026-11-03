<?php

namespace Components\Formularios\Libraries;

/**
 * La UNICA definición de qué tipos de campo existen y qué propiedades tiene
 * cada uno. De aquí beben las cinco piezas que tocan un campo:
 *
 *   1. La paleta y el inspector del editor (via `forJs()`, ya traducido).
 *   2. `Renderer`  al pintar el formulario público.
 *   3. `Validator` al recibir una respuesta.
 *   4. `FormService::sanitizeTree()` al guardar el árbol.
 *   5. `ExportService` y `ReportService` al leerlo.
 *
 * Añadir un tipo de campo es UNA entrada aquí y un `case` en `Renderer`. Si se
 * añade en otro sitio, alguna de las cinco piezas se queda sin enterarse: es
 * exactamente el error que este registro existe para hacer imposible.
 *
 * Cada propiedad declara su `tab`, que es la pestaña del inspector donde sale
 * (`general`, `validation` o `layout`); la pestaña `logic` la gobierna la regla
 * condicional, que es común a todos y no se declara por tipo.
 */
class FieldRegistry
{
    /** Grupos de la paleta, en el orden en que se pintan. */
    public const GROUPS = ['text', 'choice', 'advanced', 'structure'];

    /** Anchos admitidos. Cualquier otro valor cae al 100 por ciento. */
    public const WIDTHS = ['100', '50', '33'];

    /** Cuánto aguanta un árbol antes de ser un ataque y no un formulario. */
    public const MAX_FIELDS  = 200;
    public const MAX_PAGES   = 20;
    public const MAX_OPTIONS = 100;

    /**
     * Propiedades que tienen TODOS los campos. Se declaran una vez y las heredan
     * los 19 tipos: el equivalente a los block supports de Gutenberg.
     */
    public const COMMON = [
        ['name' => 'label', 'type' => 'text', 'label' => 'Frm.pLabel', 'tab' => 'general'],
        ['name' => 'helperText', 'type' => 'textarea', 'label' => 'Frm.pHelper', 'tab' => 'general'],
        ['name' => 'width', 'type' => 'select', 'label' => 'Frm.pWidth', 'tab' => 'layout', 'options' => [
            '100' => 'Frm.w100', '50' => 'Frm.w50', '33' => 'Frm.w33',
        ]],
        ['name' => 'anchor', 'type' => 'text', 'label' => 'Frm.pAnchor', 'tab' => 'layout'],
    ];

    /**
     * Propiedades de los campos que RECOGEN un dato. Los estructurales
     * (encabezado, nota, separador) no las llevan: no hay nada que exigir.
     */
    public const ANSWERABLE = [
        ['name' => 'required', 'type' => 'toggle', 'label' => 'Frm.pRequired', 'tab' => 'validation'],
        ['name' => 'validationErrorMsg', 'type' => 'text', 'label' => 'Frm.pErrorMsg', 'tab' => 'validation'],
    ];

    /**
     * Los 19 tipos.
     *
     * `value`: 'none' no guarda nada, 'scalar' un valor, 'multi' una lista,
     * 'file' un archivo subido.
     */
    public static function types(): array
    {
        return array_merge(self::textTypes(), self::choiceTypes(), self::advancedTypes(), self::structureTypes());
    }

    /** Campos de texto libre. */
    private static function textTypes(): array
    {
        return [
            'text' => [
                'label' => 'Frm.tText', 'hint' => 'Frm.tTextHint', 'icon' => 'icon-pencil-square', 'group' => 'text',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'placeholder', 'type' => 'text', 'label' => 'Frm.pPlaceholder', 'tab' => 'general'],
                    ['name' => 'defaultValue', 'type' => 'text', 'label' => 'Frm.pDefault', 'tab' => 'general'],
                    ['name' => 'minLength', 'type' => 'number', 'label' => 'Frm.pMinLength', 'tab' => 'validation'],
                    ['name' => 'maxLength', 'type' => 'number', 'label' => 'Frm.pMaxLength', 'tab' => 'validation'],
                    ['name' => 'validationRegex', 'type' => 'text', 'label' => 'Frm.pRegex', 'hint' => 'Frm.pRegexHint', 'tab' => 'validation'],
                ],
                'defaults' => ['label' => 'Frm.dText', 'placeholder' => 'Frm.dTextPh'],
            ],
            'textarea' => [
                'label' => 'Frm.tTextarea', 'hint' => 'Frm.tTextareaHint', 'icon' => 'icon-journal-text', 'group' => 'text',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'placeholder', 'type' => 'text', 'label' => 'Frm.pPlaceholder', 'tab' => 'general'],
                    ['name' => 'defaultValue', 'type' => 'textarea', 'label' => 'Frm.pDefault', 'tab' => 'general'],
                    ['name' => 'rows', 'type' => 'number', 'label' => 'Frm.pRows', 'tab' => 'layout'],
                    ['name' => 'maxLength', 'type' => 'number', 'label' => 'Frm.pMaxLength', 'tab' => 'validation'],
                ],
                'defaults' => ['label' => 'Frm.dTextarea', 'placeholder' => 'Frm.dTextareaPh', 'rows' => 4],
            ],
            'email' => [
                'label' => 'Frm.tEmail', 'hint' => 'Frm.tEmailHint', 'icon' => 'icon-envelope', 'group' => 'text',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'placeholder', 'type' => 'text', 'label' => 'Frm.pPlaceholder', 'tab' => 'general'],
                ],
                'defaults' => ['label' => 'Frm.dEmail', 'placeholder' => 'Frm.dEmailPh', 'required' => true],
            ],
            'number' => [
                'label' => 'Frm.tNumber', 'hint' => 'Frm.tNumberHint', 'icon' => 'icon-calculator', 'group' => 'text',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'placeholder', 'type' => 'text', 'label' => 'Frm.pPlaceholder', 'tab' => 'general'],
                    ['name' => 'defaultValue', 'type' => 'number', 'label' => 'Frm.pDefault', 'tab' => 'general'],
                    ['name' => 'min', 'type' => 'number', 'label' => 'Frm.pMin', 'tab' => 'validation'],
                    ['name' => 'max', 'type' => 'number', 'label' => 'Frm.pMax', 'tab' => 'validation'],
                    ['name' => 'step', 'type' => 'number', 'label' => 'Frm.pStep', 'tab' => 'validation'],
                ],
                'defaults' => ['label' => 'Frm.dNumber', 'width' => '50'],
            ],
            'phone' => [
                'label' => 'Frm.tPhone', 'hint' => 'Frm.tPhoneHint', 'icon' => 'icon-telephone', 'group' => 'text',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'placeholder', 'type' => 'text', 'label' => 'Frm.pPlaceholder', 'tab' => 'general'],
                ],
                'defaults' => ['label' => 'Frm.dPhone', 'placeholder' => 'Frm.dPhonePh', 'width' => '50'],
            ],
        ];
    }

    /** Campos de elección entre opciones declaradas. */
    private static function choiceTypes(): array
    {
        return [
            'select' => [
                'label' => 'Frm.tSelect', 'hint' => 'Frm.tSelectHint', 'icon' => 'icon-list-check', 'group' => 'choice',
                'value' => 'scalar', 'options' => true,
                'fields' => [
                    ['name' => 'placeholder', 'type' => 'text', 'label' => 'Frm.pPlaceholder', 'tab' => 'general'],
                    ['name' => 'options', 'type' => 'options', 'label' => 'Frm.pOptions', 'tab' => 'general'],
                ],
                'defaults' => ['label' => 'Frm.dSelect', 'options' => 'seed3'],
            ],
            'radio' => [
                'label' => 'Frm.tRadio', 'hint' => 'Frm.tRadioHint', 'icon' => 'icon-check-circle', 'group' => 'choice',
                'value' => 'scalar', 'options' => true,
                'fields' => [
                    ['name' => 'options', 'type' => 'options', 'label' => 'Frm.pOptions', 'tab' => 'general'],
                ],
                'defaults' => ['label' => 'Frm.dRadio', 'options' => 'seed3'],
            ],
            'checkbox' => [
                'label' => 'Frm.tCheckbox', 'hint' => 'Frm.tCheckboxHint', 'icon' => 'icon-check-circle-fill', 'group' => 'choice',
                'value' => 'multi', 'options' => true,
                'fields' => [
                    ['name' => 'options', 'type' => 'options', 'label' => 'Frm.pOptions', 'tab' => 'general'],
                    ['name' => 'minChoices', 'type' => 'number', 'label' => 'Frm.pMinChoices', 'tab' => 'validation'],
                    ['name' => 'maxChoices', 'type' => 'number', 'label' => 'Frm.pMaxChoices', 'tab' => 'validation'],
                ],
                'defaults' => ['label' => 'Frm.dCheckbox', 'options' => 'seed3'],
            ],
            'switch' => [
                'label' => 'Frm.tSwitch', 'hint' => 'Frm.tSwitchHint', 'icon' => 'icon-circle', 'group' => 'choice',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'defaultValue', 'type' => 'toggle', 'label' => 'Frm.pDefaultOn', 'tab' => 'general'],
                ],
                'defaults' => ['label' => 'Frm.dSwitch'],
            ],
        ];
    }

    /** Calificación, NPS, deslizador, fecha, hora, archivo y firma. */
    private static function advancedTypes(): array
    {
        return [
            'rating' => [
                'label' => 'Frm.tRating', 'hint' => 'Frm.tRatingHint', 'icon' => 'icon-star', 'group' => 'advanced',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'maxStars', 'type' => 'number', 'label' => 'Frm.pMaxStars', 'tab' => 'general'],
                    ['name' => 'ratingIcon', 'type' => 'select', 'label' => 'Frm.pRatingIcon', 'tab' => 'general', 'options' => [
                        'star' => 'Frm.iconStar', 'heart' => 'Frm.iconHeart', 'thumb' => 'Frm.iconThumb',
                    ]],
                ],
                'defaults' => ['label' => 'Frm.dRating', 'maxStars' => 5, 'ratingIcon' => 'star', 'helperText' => 'Frm.dRatingHelp'],
            ],
            'nps' => [
                'label' => 'Frm.tNps', 'hint' => 'Frm.tNpsHint', 'icon' => 'icon-bar-chart', 'group' => 'advanced',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'lowLabel', 'type' => 'text', 'label' => 'Frm.pLowLabel', 'tab' => 'general'],
                    ['name' => 'highLabel', 'type' => 'text', 'label' => 'Frm.pHighLabel', 'tab' => 'general'],
                ],
                'defaults' => ['label' => 'Frm.dNps', 'lowLabel' => 'Frm.dNpsLow', 'highLabel' => 'Frm.dNpsHigh'],
            ],
            'slider' => [
                'label' => 'Frm.tSlider', 'hint' => 'Frm.tSliderHint', 'icon' => 'icon-speedometer2', 'group' => 'advanced',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'min', 'type' => 'number', 'label' => 'Frm.pMin', 'tab' => 'general'],
                    ['name' => 'max', 'type' => 'number', 'label' => 'Frm.pMax', 'tab' => 'general'],
                    ['name' => 'step', 'type' => 'number', 'label' => 'Frm.pStep', 'tab' => 'general'],
                    ['name' => 'defaultValue', 'type' => 'number', 'label' => 'Frm.pDefault', 'tab' => 'general'],
                ],
                'defaults' => ['label' => 'Frm.dSlider', 'min' => 0, 'max' => 100, 'step' => 5, 'defaultValue' => 50],
            ],
            'date' => [
                'label' => 'Frm.tDate', 'hint' => 'Frm.tDateHint', 'icon' => 'icon-clock-history', 'group' => 'advanced',
                'value' => 'scalar',
                'fields' => [
                    ['name' => 'minDate', 'type' => 'text', 'label' => 'Frm.pMinDate', 'hint' => 'Frm.pDateHint', 'tab' => 'validation'],
                    ['name' => 'maxDate', 'type' => 'text', 'label' => 'Frm.pMaxDate', 'hint' => 'Frm.pDateHint', 'tab' => 'validation'],
                ],
                'defaults' => ['label' => 'Frm.dDate', 'width' => '50'],
            ],
            'time' => [
                'label' => 'Frm.tTime', 'hint' => 'Frm.tTimeHint', 'icon' => 'icon-clock', 'group' => 'advanced',
                'value' => 'scalar',
                'fields' => [],
                'defaults' => ['label' => 'Frm.dTime', 'width' => '50'],
            ],
            'file' => [
                'label' => 'Frm.tFile', 'hint' => 'Frm.tFileHint', 'icon' => 'icon-upload', 'group' => 'advanced',
                'value' => 'file',
                'fields' => [
                    ['name' => 'maxSizeMB', 'type' => 'number', 'label' => 'Frm.pMaxSize', 'tab' => 'validation'],
                    ['name' => 'allowedExtensions', 'type' => 'text', 'label' => 'Frm.pExtensions', 'hint' => 'Frm.pExtensionsHint', 'tab' => 'validation'],
                ],
                'defaults' => ['label' => 'Frm.dFile', 'maxSizeMB' => 5, 'allowedExtensions' => 'pdf, png, jpg, jpeg, docx', 'helperText' => 'Frm.dFileHelp'],
            ],
            'signature' => [
                'label' => 'Frm.tSignature', 'hint' => 'Frm.tSignatureHint', 'icon' => 'icon-pencil', 'group' => 'advanced',
                'value' => 'file',
                'fields' => [],
                'defaults' => ['label' => 'Frm.dSignature', 'helperText' => 'Frm.dSignatureHelp'],
            ],
        ];
    }

    /** Se pintan, no se contestan. */
    private static function structureTypes(): array
    {
        return [
            'heading' => [
                'label' => 'Frm.tHeading', 'hint' => 'Frm.tHeadingHint', 'icon' => 'icon-pencil-square', 'group' => 'structure',
                'value' => 'none',
                'fields' => [
                    ['name' => 'headingLevel', 'type' => 'select', 'label' => 'Frm.pLevel', 'tab' => 'general', 'options' => [
                        'h2' => 'Frm.levelH2', 'h3' => 'Frm.levelH3', 'h4' => 'Frm.levelH4',
                    ]],
                ],
                'defaults' => ['label' => 'Frm.dHeading', 'headingLevel' => 'h2'],
            ],
            'paragraph' => [
                'label' => 'Frm.tParagraph', 'hint' => 'Frm.tParagraphHint', 'icon' => 'icon-info-circle', 'group' => 'structure',
                'value' => 'none',
                'fields' => [
                    ['name' => 'content', 'type' => 'textarea', 'label' => 'Frm.pContent', 'tab' => 'general'],
                    ['name' => 'alertType', 'type' => 'select', 'label' => 'Frm.pAlertType', 'tab' => 'general', 'options' => [
                        'info' => 'Frm.alertInfo', 'warning' => 'Frm.alertWarning',
                        'success' => 'Frm.alertSuccess', 'note' => 'Frm.alertNote',
                    ]],
                ],
                'defaults' => ['label' => 'Frm.dParagraph', 'content' => 'Frm.dParagraphBody', 'alertType' => 'info'],
            ],
            'divider' => [
                'label' => 'Frm.tDivider', 'hint' => 'Frm.tDividerHint', 'icon' => 'icon-dash-circle', 'group' => 'structure',
                'value' => 'none',
                'fields' => [],
                'defaults' => ['label' => ''],
            ],
        ];
    }

    public static function has(string $type): bool
    {
        return isset(self::types()[$type]);
    }

    public static function def(string $type): ?array
    {
        return self::types()[$type] ?? null;
    }

    /** Encabezado, nota y separador: se pintan, no se contestan. */
    public static function isStructural(string $type): bool
    {
        return (self::def($type)['value'] ?? 'none') === 'none';
    }

    public static function valueKind(string $type): string
    {
        return self::def($type)['value'] ?? 'none';
    }

    public static function hasOptions(string $type): bool
    {
        return (bool) (self::def($type)['options'] ?? false);
    }

    /**
     * Todas las propiedades de un tipo: comunes + de respuesta + propias.
     */
    public static function propsOf(string $type): array
    {
        $def = self::def($type);
        if (!$def) {
            return [];
        }

        $props = self::COMMON;
        if (!self::isStructural($type)) {
            $props = array_merge($props, self::ANSWERABLE);
        }

        return array_merge($props, $def['fields'] ?? []);
    }

    /**
     * Los valores admitidos de un campo de opciones, SIEMPRE como cadenas.
     *
     * Gotcha documentado del proyecto: las claves numéricas de un array PHP se
     * convierten en enteros, así que comparar en modo estricto contra
     * `array_keys()` en crudo es falso siempre y toda opción numérica caería en
     * silencio al valor por defecto.
     *
     * @return string[]
     */
    public static function optionValues(array $field): array
    {
        $out = [];
        foreach (($field['options'] ?? []) as $opt) {
            if (is_array($opt) && isset($opt['value'])) {
                $out[] = (string) $opt['value'];
            }
        }

        return array_map('strval', $out);
    }

    /** Los valores de un `select` declarado en una propiedad del registro. */
    public static function propOptionValues(array $prop): array
    {
        return array_map('strval', array_keys($prop['options'] ?? []));
    }

    /**
     * Un campo nuevo del tipo pedido, con sus valores por defecto ya traducidos.
     */
    public static function makeField(string $type, string $pageId): ?array
    {
        $def = self::def($type);
        if (!$def) {
            return null;
        }

        $field = [
            'id'     => 'f_' . bin2hex(random_bytes(5)),
            'pageId' => $pageId,
            'type'   => $type,
            'width'  => '100',
        ];

        foreach (($def['defaults'] ?? []) as $key => $value) {
            if ($key === 'options' && $value === 'seed3') {
                $field['options'] = self::seedOptions();

                continue;
            }
            // Las etiquetas por defecto son claves de idioma; el resto, valores.
            $field[$key] = (is_string($value) && str_starts_with($value, 'Frm.')) ? lang($value) : $value;
        }

        return $field;
    }

    /** Tres opciones de arranque, para que un desplegable nazca utilizable. */
    public static function seedOptions(): array
    {
        $out = [];
        for ($i = 1; $i <= 3; $i++) {
            $out[] = [
                'id'    => 'o_' . bin2hex(random_bytes(3)),
                'label' => lang('Frm.optionN', [$i]),
                'value' => 'opcion_' . $i,
            ];
        }

        return $out;
    }

    /**
     * El registro tal como lo consume el editor: etiquetas ya traducidas, para
     * que el JavaScript no tenga que saber nada de idiomas.
     */
    public static function forJs(): array
    {
        $out = [];

        foreach (self::types() as $type => $def) {
            $props = [];
            foreach (self::propsOf($type) as $p) {
                $opts = null;
                if (($p['type'] ?? '') === 'select') {
                    $opts = [];
                    foreach (($p['options'] ?? []) as $k => $labelKey) {
                        $opts[] = ['value' => (string) $k, 'label' => lang($labelKey)];
                    }
                }
                $props[] = [
                    'name'    => $p['name'],
                    'type'    => $p['type'],
                    'label'   => lang($p['label']),
                    'hint'    => isset($p['hint']) ? lang($p['hint']) : '',
                    'tab'     => $p['tab'] ?? 'general',
                    'options' => $opts,
                ];
            }

            $out[$type] = [
                'label'      => lang($def['label']),
                'hint'       => lang($def['hint']),
                'icon'       => $def['icon'],
                'group'      => $def['group'],
                'value'      => $def['value'],
                'structural' => self::isStructural($type),
                'props'      => $props,
            ];
        }

        return $out;
    }
}
