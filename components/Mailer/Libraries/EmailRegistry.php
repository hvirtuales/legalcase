<?php

namespace Components\Mailer\Libraries;

/**
 * La UNICA definición de qué bloques admite un correo y qué propiedades tiene
 * cada uno. De aquí beben las tres piezas que tocan un bloque: la paleta y el
 * inspector del constructor (via `forJs()`), el `EmailRenderer` que produce el
 * HTML que se envía, y el saneador que reconstruye el árbol al guardar.
 *
 * **Añadir un bloque es una entrada aquí y un `case` en `EmailRenderer`.**
 *
 * El modelo del diseño se conserva tal como estaba, para no romper las
 * plantillas ya guardadas:
 *
 *     { "sections": [ { "cols": 1..3, "bg": "#fff", "pad": 24,
 *                       "blocks": [ [ bloque, ... ], ... ] } ] }
 *
 * Cada propiedad declara su pestaña del inspector: `content` (lo que dice) o
 * `style` (cómo se ve). Dos pestañas y no cuatro: en un correo casi todo es una
 * de esas dos cosas.
 */
class EmailRegistry
{
    /** Grupos de la paleta, en el orden en que se pintan. */
    public const GROUPS = ['basic', 'media', 'company', 'layout'];

    /** Topes: un correo no es una web. */
    public const MAX_SECTIONS = 40;
    public const MAX_BLOCKS   = 120;

    public const ALIGNS = ['left', 'center', 'right'];

    /** Bloques que leen la ficha de la empresa en vez de volver a pedir los datos. */
    public const COMPANY_BLOCKS = ['logo', 'social', 'company'];

    /**
     * Los rótulos de los grupos de la paleta, ya traducidos. Viven aquí y no en
     * cada controlador: los pintan dos pantallas (el asistente y las plantillas)
     * y con el mapa copiado bastaba añadir un grupo para que saliera sin nombre
     * en una de las dos.
     */
    public static function groupLabels(): array
    {
        return [
            'basic'   => lang('Mailer.gBasic'),
            'media'   => lang('Mailer.gMedia'),
            'company' => lang('Mailer.gCompany'),
            'layout'  => lang('Mailer.gLayout'),
        ];
    }
    public static function types(): array
    {
        return array_merge(self::basic(), self::media(), self::company(), self::layout());
    }

    /** Título, texto y botón: el 80 por ciento de cualquier correo. */
    private static function basic(): array
    {
        return [
            'title' => [
                'label' => lang('Mailer.bTitle'), 'hint' => lang('Mailer.bTitleHint'),
                'icon' => 'icon-pencil-square', 'group' => 'basic',
                'fields' => [
                    ['name' => 'text', 'type' => 'text', 'label' => lang('Mailer.pText'), 'tab' => 'content'],
                    ['name' => 'level', 'type' => 'select', 'label' => lang('Mailer.pLevel'), 'tab' => 'content',
                     'options' => ['h1' => lang('Mailer.pLevelH1'), 'h2' => lang('Mailer.pLevelH2'), 'h3' => lang('Mailer.pLevelH3')]],
                    ['name' => 'size', 'type' => 'number', 'label' => lang('Mailer.pSize'), 'tab' => 'style'],
                    ['name' => 'color', 'type' => 'color', 'label' => lang('Mailer.pColor'), 'tab' => 'style'],
                    ['name' => 'align', 'type' => 'align', 'label' => lang('Mailer.pAlign'), 'tab' => 'style'],
                ],
                'defaults' => ['text' => lang('Mailer.dTitle'), 'level' => 'h2', 'size' => 24,
                               'color' => '#111827', 'align' => 'left'],
            ],
            'text' => [
                'label' => lang('Mailer.bText'), 'hint' => lang('Mailer.bTextHint'),
                'icon' => 'icon-journal-text', 'group' => 'basic',
                'fields' => [
                    ['name' => 'html', 'type' => 'rich', 'label' => lang('Mailer.pContent'), 'tab' => 'content',
                     'hint' => lang('Mailer.pContentHint')],
                    ['name' => 'size', 'type' => 'number', 'label' => lang('Mailer.pSize'), 'tab' => 'style'],
                    ['name' => 'color', 'type' => 'color', 'label' => lang('Mailer.pColor'), 'tab' => 'style'],
                    ['name' => 'lineHeight', 'type' => 'number', 'label' => lang('Mailer.pLineHeight'), 'tab' => 'style'],
                    ['name' => 'align', 'type' => 'align', 'label' => lang('Mailer.pAlign'), 'tab' => 'style'],
                ],
                'defaults' => ['html' => lang('Mailer.dText'), 'size' => 15,
                               'color' => '#374151', 'lineHeight' => 160, 'align' => 'left'],
            ],
            'button' => [
                'label' => lang('Mailer.bButton'), 'hint' => lang('Mailer.bButtonHint'),
                'icon' => 'icon-link', 'group' => 'basic',
                'fields' => [
                    ['name' => 'text', 'type' => 'text', 'label' => lang('Mailer.pText'), 'tab' => 'content'],
                    ['name' => 'href', 'type' => 'url', 'label' => lang('Mailer.pHref'), 'tab' => 'content'],
                    ['name' => 'bg', 'type' => 'color', 'label' => lang('Mailer.pBg'), 'tab' => 'style'],
                    ['name' => 'color', 'type' => 'color', 'label' => lang('Mailer.pTextColor'), 'tab' => 'style'],
                    ['name' => 'size', 'type' => 'select', 'label' => lang('Mailer.pBtnSize'), 'tab' => 'style',
                     'options' => ['sm' => lang('Mailer.pBtnSm'), 'md' => lang('Mailer.pBtnMd'), 'lg' => lang('Mailer.pBtnLg')]],
                    ['name' => 'radius', 'type' => 'number', 'label' => lang('Mailer.pRadius'), 'tab' => 'style'],
                    ['name' => 'fullWidth', 'type' => 'toggle', 'label' => lang('Mailer.pFullWidth'), 'tab' => 'style'],
                    ['name' => 'align', 'type' => 'align', 'label' => lang('Mailer.pAlign'), 'tab' => 'style'],
                ],
                'defaults' => ['text' => lang('Mailer.dButton'), 'href' => 'https://', 'bg' => '#4f46e5',
                               'color' => '#ffffff', 'size' => 'md', 'radius' => 6,
                               'fullWidth' => false, 'align' => 'center'],
            ],
        ];
    }

    /** Imagen y vídeo. */
    private static function media(): array
    {
        return [
            'image' => [
                'label' => lang('Mailer.bImage'), 'hint' => lang('Mailer.bImageHint'),
                'icon' => 'icon-image', 'group' => 'media',
                'fields' => [
                    ['name' => 'src', 'type' => 'image', 'label' => lang('Mailer.pImage'), 'tab' => 'content'],
                    ['name' => 'alt', 'type' => 'text', 'label' => lang('Mailer.pAlt'), 'tab' => 'content',
                     'hint' => lang('Mailer.pAltHint')],
                    ['name' => 'href', 'type' => 'url', 'label' => lang('Mailer.pImageHref'), 'tab' => 'content'],
                    ['name' => 'width', 'type' => 'number', 'label' => lang('Mailer.pWidth'), 'tab' => 'style'],
                    ['name' => 'radius', 'type' => 'number', 'label' => lang('Mailer.pRadius'), 'tab' => 'style'],
                    ['name' => 'align', 'type' => 'align', 'label' => lang('Mailer.pAlign'), 'tab' => 'style'],
                ],
                'defaults' => ['src' => '', 'alt' => '', 'href' => '', 'width' => 0,
                               'radius' => 0, 'align' => 'center'],
            ],
            'video' => [
                'label' => lang('Mailer.bVideo'), 'hint' => lang('Mailer.bVideoHint'),
                'icon' => 'icon-play-circle', 'group' => 'media',
                'fields' => [
                    ['name' => 'src', 'type' => 'image', 'label' => lang('Mailer.pThumb'), 'tab' => 'content'],
                    ['name' => 'href', 'type' => 'url', 'label' => lang('Mailer.pVideoHref'), 'tab' => 'content'],
                    ['name' => 'alt', 'type' => 'text', 'label' => lang('Mailer.pAlt'), 'tab' => 'content'],
                    ['name' => 'width', 'type' => 'number', 'label' => lang('Mailer.pWidth'), 'tab' => 'style'],
                    ['name' => 'align', 'type' => 'align', 'label' => lang('Mailer.pAlign'), 'tab' => 'style'],
                ],
                'defaults' => ['src' => '', 'href' => '', 'alt' => lang('Mailer.dVideoAlt'), 'width' => 0, 'align' => 'center'],
            ],
        ];
    }

    /**
     * Bloques que leen `companies`.
     *
     * Misma decisión que tomaron Páginas y Servicios: **no se vuelven a pedir
     * datos que la plataforma ya tiene**. Quien monta el correo no teclea el
     * logo, la dirección ni las redes; salen de la ficha de la empresa y se
     * actualizan solos cuando esta cambia.
     */
    private static function company(): array
    {
        return [
            'logo' => [
                'label' => lang('Mailer.bLogo'), 'hint' => lang('Mailer.bLogoHint'),
                'icon' => 'icon-buildings', 'group' => 'company',
                'fields' => [
                    ['name' => 'width', 'type' => 'number', 'label' => lang('Mailer.pLogoWidth'), 'tab' => 'style'],
                    ['name' => 'align', 'type' => 'align', 'label' => lang('Mailer.pAlign'), 'tab' => 'style'],
                    ['name' => 'href', 'type' => 'url', 'label' => lang('Mailer.pLogoHref'), 'tab' => 'content'],
                ],
                'defaults' => ['width' => 160, 'align' => 'center', 'href' => ''],
            ],
            'social' => [
                'label' => lang('Mailer.bSocial'), 'hint' => lang('Mailer.bSocialHint'),
                'icon' => 'icon-share', 'group' => 'company',
                'fields' => [
                    ['name' => 'size', 'type' => 'number', 'label' => lang('Mailer.pSize'), 'tab' => 'style'],
                    ['name' => 'color', 'type' => 'color', 'label' => lang('Mailer.pColor'), 'tab' => 'style'],
                    ['name' => 'align', 'type' => 'align', 'label' => lang('Mailer.pAlign'), 'tab' => 'style'],
                ],
                'defaults' => ['size' => 28, 'color' => '#6b7280', 'align' => 'center'],
            ],
            'company' => [
                'label' => lang('Mailer.bCompany'), 'hint' => lang('Mailer.bCompanyHint'),
                'icon' => 'icon-geo-alt', 'group' => 'company',
                'fields' => [
                    ['name' => 'showNit', 'type' => 'toggle', 'label' => lang('Mailer.pShowNit'), 'tab' => 'content'],
                    ['name' => 'showAddress', 'type' => 'toggle', 'label' => lang('Mailer.pShowAddress'), 'tab' => 'content'],
                    ['name' => 'showContact', 'type' => 'toggle', 'label' => lang('Mailer.pShowContact'), 'tab' => 'content'],
                    ['name' => 'size', 'type' => 'number', 'label' => lang('Mailer.pSize'), 'tab' => 'style'],
                    ['name' => 'color', 'type' => 'color', 'label' => lang('Mailer.pColor'), 'tab' => 'style'],
                    ['name' => 'align', 'type' => 'align', 'label' => lang('Mailer.pAlign'), 'tab' => 'style'],
                ],
                'defaults' => ['showNit' => true, 'showAddress' => true, 'showContact' => true,
                               'size' => 12, 'color' => '#6b7280', 'align' => 'center'],
            ],
        ];
    }

    /** Separadores y HTML propio. */
    private static function layout(): array
    {
        return [
            'divider' => [
                'label' => lang('Mailer.bDivider'), 'hint' => lang('Mailer.bDividerHint'),
                'icon' => 'icon-dash-circle', 'group' => 'layout',
                'fields' => [
                    ['name' => 'color', 'type' => 'color', 'label' => lang('Mailer.pColor'), 'tab' => 'style'],
                    ['name' => 'thickness', 'type' => 'number', 'label' => lang('Mailer.pThickness'), 'tab' => 'style'],
                    ['name' => 'gap', 'type' => 'number', 'label' => lang('Mailer.pGap'), 'tab' => 'style'],
                ],
                'defaults' => ['color' => '#e5e7eb', 'thickness' => 1, 'gap' => 16],
            ],
            'spacer' => [
                'label' => lang('Mailer.bSpacer'), 'hint' => lang('Mailer.bSpacerHint'),
                'icon' => 'icon-arrow-down-up', 'group' => 'layout',
                'fields' => [
                    ['name' => 'height', 'type' => 'number', 'label' => lang('Mailer.pHeight'), 'tab' => 'style'],
                ],
                'defaults' => ['height' => 24],
            ],
            'html' => [
                'label' => lang('Mailer.bHtml'), 'hint' => lang('Mailer.bHtmlHint'),
                'icon' => 'icon-file-earmark', 'group' => 'layout',
                'fields' => [
                    ['name' => 'code', 'type' => 'code', 'label' => lang('Mailer.pCode'), 'tab' => 'content',
                     'hint' => lang('Mailer.pCodeHint')],
                ],
                'defaults' => ['code' => lang('Mailer.dHtml')],
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

    /** Un bloque nuevo del tipo pedido, con sus valores por defecto. */
    public static function make(string $type): ?array
    {
        $def = self::def($type);

        return $def ? array_merge(['type' => $type], $def['defaults']) : null;
    }

    /** Los valores admitidos de un `select`, SIEMPRE como cadenas. */
    public static function optionValues(array $prop): array
    {
        return array_map('strval', array_keys($prop['options'] ?? []));
    }

    /**
     * El registro tal como lo consume el constructor, con las opciones ya
     * desplegadas: el JavaScript no tiene que saber nada del catálogo.
     */
    public static function forJs(): array
    {
        $out = [];

        foreach (self::types() as $type => $def) {
            $props = [];
            foreach ($def['fields'] as $p) {
                $opts = null;
                if (($p['type'] ?? '') === 'select') {
                    $opts = [];
                    foreach ($p['options'] as $value => $label) {
                        $opts[] = ['value' => (string) $value, 'label' => $label];
                    }
                }
                $props[] = [
                    'name'    => $p['name'],
                    'type'    => $p['type'],
                    'label'   => $p['label'],
                    'hint'    => $p['hint'] ?? '',
                    'tab'     => $p['tab'],
                    'options' => $opts,
                ];
            }

            $out[$type] = [
                'label'    => $def['label'],
                'hint'     => $def['hint'],
                'icon'     => $def['icon'],
                'group'    => $def['group'],
                'company'  => in_array($type, self::COMPANY_BLOCKS, true),
                'defaults' => $def['defaults'],
                'props'    => $props,
            ];
        }

        return $out;
    }
}
