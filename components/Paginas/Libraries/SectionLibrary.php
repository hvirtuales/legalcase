<?php

namespace Components\Paginas\Libraries;

/**
 * Secciones prearmadas: trozos de página **ya configurados** que se sueltan en
 * el lienzo y quedan listos, sin tener que montar la sección columna a columna
 * ni acertar con los ajustes.
 *
 * ¿Por qué en código y no en `cmp_pag_blocks`?
 *
 * Los bloques guardados de esa tabla son **de una empresa** (llevan sus textos,
 * sus enlaces y sus imágenes), así que sembrar ahí este catálogo obligaría a
 * duplicar las mismas filas para cada empresa de la plataforma y a mantenerlas
 * sincronizadas para siempre. Estas secciones no tienen dueño: son estructura
 * con texto de relleno, igual que las plantillas de arranque. Definirlas aquí
 * las hace aparecer en la paleta de todo el mundo, sin una sola fila en la base
 * de datos y sin nada que sembrar al instalar.
 *
 * La tabla `cmp_pag_blocks` sigue siendo la que guarda **las del usuario**: lo
 * que él arma y guarda con «Guardar como bloque reutilizable». Las dos listas
 * conviven en la paleta, en grupos distintos.
 *
 * Los textos son claves de idioma: se resuelven en `forJs()`, nunca se guardan
 * traducidos.
 */
class SectionLibrary
{
    /** Grupos de la paleta de secciones, en el orden en que se muestran. */
    public const GROUPS = ['banner', 'cards', 'content', 'footer'];

    /**
     * Catálogo completo. Cada entrada es `blocks`: una lista de bloques ya
     * configurados (normalmente una sola sección con sus columnas dentro).
     */
    public static function all(): array
    {
        $s   = new PageService();
        $out = array_merge(
            self::banners($s),
            self::cards($s),
            self::content($s),
            self::footers($s)
        );

        // **Toda sección se entrega envuelta en un bloque `section` de verdad.**
        //
        // Antes varias eran bloques sueltos en la raíz (un título, un texto y
        // unas tarjetas, por ejemplo). Se veían igual, pero no eran una unidad:
        // no se podían mover de una vez, no tenían ancho, fondo ni relleno
        // propios y en la vista en cascada aparecían como piezas desperdigadas
        // en vez de colgando de algo. Envueltas, una sección soltada **se edita
        // como una sección** y sus elementos siguen editándose uno a uno dentro.
        foreach ($out as $i => $sec) {
            $out[$i]['blocks'] = self::wrap($s, $sec['blocks'], $sec['wrap'] ?? []);
            unset($out[$i]['wrap']);
        }

        return $out;
    }

    /**
     * Envuelve los bloques en una sección de una columna, salvo que ya vengan
     * envueltos (una sola sección en la raíz, que es el caso de los banners y
     * de las que se arman con `columns()`).
     *
     * @param array $props ajustes de la sección envolvente
     */
    private static function wrap(PageService $s, array $blocks, array $props = []): array
    {
        // Ya es una única sección: no se envuelve otra vez, o quedaría una
        // sección dentro de otra sin motivo.
        if (count($blocks) === 1 && ($blocks[0]['type'] ?? '') === 'section') {
            if ($props) {
                $blocks[0]['props'] = array_merge($blocks[0]['props'], $props);
            }

            return $blocks;
        }

        // Varias secciones en la raíz (el pie: sus columnas y, debajo, la línea
        // legal a todo lo ancho) se entregan tal cual. Envolverlas metería una
        // sección dentro de una columna, que el saneador —con razón— descarta.
        $todasSecciones = $blocks && !array_filter($blocks, static fn ($b) => ($b['type'] ?? '') !== 'section');
        if ($todasSecciones) {
            return $blocks;
        }

        // El hero gobierna su propio ancho y relleno: envolverlo lo encogería y
        // le añadiría un margen que no le corresponde.
        if (count($blocks) === 1 && ($blocks[0]['type'] ?? '') === 'hero') {
            return $blocks;
        }

        $seccion = $s->newBlock('section', array_merge(['columns' => '1'], $props));
        $columna = $s->newBlock('column');
        $columna['children'] = $blocks;
        $seccion['children'] = [$columna];

        return [$seccion];
    }

    // ------------------------------------------------------------- banners

    private static function banners(PageService $s): array
    {
        return [
            [
                'code'   => 'cabecera',
                'label'  => 'Pag.secHeader',
                'hint'   => 'Pag.secHeaderHint',
                'icon'   => 'icon-buildings',
                'group'  => 'banner',
                'blocks' => [self::header($s, true)],
            ],
            [
                'code'   => 'cabecera-simple',
                'label'  => 'Pag.secHeaderSimple',
                'hint'   => 'Pag.secHeaderSimpleHint',
                'icon'   => 'icon-buildings',
                'group'  => 'banner',
                'blocks' => [self::header($s, false)],
            ],
            [
                'code'   => 'banner-imagen',
                'label'  => 'Pag.secBannerImage',
                'hint'   => 'Pag.secBannerImageHint',
                'icon'   => 'icon-image',
                'group'  => 'banner',
                'blocks' => [
                    $s->newBlock('hero', [
                        'title'       => lang('Pag.secBannerImageTitle'),
                        'subtitle'    => lang('Pag.secBannerImageSub'),
                        'image'       => '', 'overlay' => 'dark', 'height' => 'lg', 'align' => 'center',
                        'buttonLabel' => lang('Pag.secCtaButton'),
                        'buttonHref'  => '#contacto',
                    ]),
                ],
            ],
        ];
    }

    // ------------------------------------------- tarjetas de varias columnas

    private static function cards(PageService $s): array
    {
        $out = [];

        // 2, 3 y 4 columnas: la misma sección con distinto reparto. Es la
        // decisión que más se toca, así que va servida en vez de obligar a
        // crear la sección y cambiarle el desplegable.
        foreach ([2, 3, 4] as $n) {
            $items = [];

            for ($i = 1; $i <= $n; $i++) {
                $items[] = [
                    'image' => '', 'title' => lang('Pag.secCardTitle', [$i]),
                    'text' => lang('Pag.tplLoremShort'), 'linkLabel' => '', 'href' => '',
                ];
            }

            $out[] = [
                'code'   => 'tarjetas-' . $n,
                'label'  => 'Pag.secCards' . $n,
                'hint'   => 'Pag.secCardsHint',
                'icon'   => 'icon-box-seam',
                'group'  => 'cards',
                'blocks' => [
                    $s->newBlock('cards', ['columns' => (string) $n, 'items' => $items]),
                ],
            ];
        }

        $out[] = [
            'code'   => 'servicios',
            'label'  => 'Pag.secServices',
            'hint'   => 'Pag.secServicesHint',
            'icon'   => 'icon-box-seam',
            'group'  => 'cards',
            'blocks' => [
                $s->newBlock('heading', ['text' => lang('Pag.secServicesTitle'), 'level' => '2', 'align' => 'center']),
                $s->newBlock('text', [
                    'html' => '<p>' . lang('Pag.secServicesIntro') . '</p>', 'align' => 'center', 'size' => 'lg',
                ]),
                $s->newBlock('cards', [
                    'columns' => '3',
                    'items'   => [
                        ['image' => '', 'title' => lang('Pag.secService1'), 'text' => lang('Pag.tplLoremShort'), 'linkLabel' => lang('Pag.secMore'), 'href' => ''],
                        ['image' => '', 'title' => lang('Pag.secService2'), 'text' => lang('Pag.tplLoremShort'), 'linkLabel' => lang('Pag.secMore'), 'href' => ''],
                        ['image' => '', 'title' => lang('Pag.secService3'), 'text' => lang('Pag.tplLoremShort'), 'linkLabel' => lang('Pag.secMore'), 'href' => ''],
                    ],
                ]),
            ],
        ];

        // Cifras: es una sección de columnas, no tarjetas, porque un número
        // grande con su rótulo debajo no quiere el marco de una tarjeta.
        $out[] = [
            'code'   => 'cifras',
            'label'  => 'Pag.secFigures',
            'hint'   => 'Pag.secFiguresHint',
            'icon'   => 'icon-bar-chart',
            'group'  => 'cards',
            'blocks' => [
                self::columns($s, 4, static fn (int $i) => [
                    $s->newBlock('heading', ['text' => ['+25', '1.200', '98 %', '24 h'][$i - 1] ?? '', 'level' => '2', 'align' => 'center']),
                    $s->newBlock('text', [
                        'html'  => '<p>' . lang('Pag.secFigure' . $i) . '</p>',
                        'align' => 'center', 'size' => 'sm',
                    ]),
                ], ['padding' => 'lg', 'gap' => 'md', 'vAlign' => 'start']),
            ],
        ];

        $out[] = [
            'code'   => 'equipo',
            'label'  => 'Pag.secTeam',
            'hint'   => 'Pag.secTeamHint',
            'icon'   => 'icon-people',
            'group'  => 'cards',
            'blocks' => [
                $s->newBlock('heading', ['text' => lang('Pag.secTeamTitle'), 'level' => '2', 'align' => 'center']),
                $s->newBlock('cards', [
                    'columns' => '3',
                    'items'   => [
                        ['image' => '', 'title' => lang('Pag.secTeamName'), 'text' => lang('Pag.secTeamRole'), 'linkLabel' => '', 'href' => ''],
                        ['image' => '', 'title' => lang('Pag.secTeamName'), 'text' => lang('Pag.secTeamRole'), 'linkLabel' => '', 'href' => ''],
                        ['image' => '', 'title' => lang('Pag.secTeamName'), 'text' => lang('Pag.secTeamRole'), 'linkLabel' => '', 'href' => ''],
                    ],
                ]),
            ],
        ];

        return $out;
    }

    // ------------------------------------------------------------ contenido

    private static function content(PageService $s): array
    {
        return [
            [
                'code'   => 'texto-imagen',
                'label'  => 'Pag.secTextImage',
                'hint'   => 'Pag.secTextImageHint',
                'icon'   => 'icon-list-columns-reverse',
                'group'  => 'content',
                'blocks' => [
                    self::columns($s, 2, static fn (int $i) => $i === 1
                        ? [
                            $s->newBlock('heading', ['text' => lang('Pag.secTextImageTitle'), 'level' => '2']),
                            $s->newBlock('text', ['html' => '<p>' . lang('Pag.tplLorem') . '</p>']),
                            $s->newBlock('button', [
                                'label' => lang('Pag.secMore'), 'href' => '#', 'style' => 'primary',
                                'size' => 'md', 'align' => 'left', 'newTab' => '0',
                            ]),
                        ]
                        : [$s->newBlock('image', ['alt' => lang('Pag.tplImageAlt'), 'size' => 'full', 'radius' => 'lg'])],
                        ['vAlign' => 'center']),
                ],
            ],
            [
                'code'   => 'institucional-qr',
                'label'  => 'Pag.secAboutQr',
                'hint'   => 'Pag.secAboutQrHint',
                'icon'   => 'icon-qr-code',
                'group'  => 'content',
                'blocks' => [
                    self::columns($s, 2, static fn (int $i) => $i === 1
                        ? [
                            $s->newBlock('heading', ['text' => lang('Pag.tplCompanyAbout'), 'level' => '2']),
                            $s->newBlock('text', ['html' => '<p>' . lang('Pag.tplLorem') . '</p>']),
                            $s->newBlock('list', [
                                'style' => 'check',
                                'items' => [
                                    ['text' => lang('Pag.tplCompanyPoint1')],
                                    ['text' => lang('Pag.tplCompanyPoint2')],
                                    ['text' => lang('Pag.tplCompanyPoint3')],
                                ],
                            ]),
                        ]
                        : [
                            $s->newBlock('companyQr', [
                                'target' => 'page', 'url' => '',
                                'title' => lang('Pag.tplCompanyQrTitle'),
                                'caption' => lang('Pag.tplCompanyQrCaption'),
                                'size' => 'md', 'frame' => 'card', 'align' => 'center',
                            ]),
                        ],
                        ['vAlign' => 'center']),
                ],
            ],
            [
                'code'   => 'galeria',
                'label'  => 'Pag.secGallery',
                'hint'   => 'Pag.secGalleryHint',
                'icon'   => 'icon-image',
                'group'  => 'content',
                'blocks' => [
                    $s->newBlock('heading', ['text' => lang('Pag.secGalleryTitle'), 'level' => '2', 'align' => 'center']),
                    $s->newBlock('gallery', [
                        'columns' => '3', 'radius' => 'sm',
                        'items'   => array_fill(0, 6, ['src' => '', 'alt' => '', 'caption' => '']),
                    ]),
                ],
            ],
            [
                'code'   => 'cta',
                'label'  => 'Pag.secCta',
                'hint'   => 'Pag.secCtaHint',
                'icon'   => 'icon-check-circle',
                'group'  => 'content',
                'blocks' => [
                    self::columns($s, 1, static fn () => [
                        $s->newBlock('heading', ['text' => lang('Pag.secCtaTitle'), 'level' => '2', 'align' => 'center']),
                        $s->newBlock('text', [
                            'html' => '<p>' . lang('Pag.secCtaText') . '</p>', 'align' => 'center', 'size' => 'lg',
                        ]),
                        // Dos botones en fila: el sólido para la acción
                        // principal y el de borde para la alternativa. Es la
                        // pareja más común de un cierre de sección.
                        $s->newBlock('buttons', [
                            'size' => 'lg', 'align' => 'center', 'gap' => 'sm', 'stack' => '1',
                            'items' => [
                                ['label' => lang('Pag.secCtaButton'), 'href' => '#contacto', 'style' => 'primary', 'color' => '', 'newTab' => '0'],
                                ['label' => lang('Pag.secMore'), 'href' => '#servicios', 'style' => 'outline', 'color' => '', 'newTab' => '0'],
                            ],
                        ]),
                    ], ['padding' => 'xl', 'width' => 'narrow']),
                ],
            ],
            [
                'code'   => 'contacto',
                'label'  => 'Pag.secContact',
                'hint'   => 'Pag.secContactHint',
                'icon'   => 'icon-envelope',
                'group'  => 'content',
                'blocks' => [
                    self::columns($s, 2, static fn (int $i) => $i === 1
                        ? [
                            $s->newBlock('heading', ['text' => lang('Pag.tplWhereTitle'), 'level' => '3']),
                            $s->newBlock('list', [
                                'style' => 'bullet',
                                'items' => [
                                    ['text' => lang('Pag.tplWhereAddress')],
                                    ['text' => lang('Pag.tplWherePhone')],
                                    ['text' => lang('Pag.tplWhereHours')],
                                ],
                            ]),
                            $s->newBlock('companySocial', [
                                'title' => lang('Pag.secFollowUs'), 'style' => 'outline',
                                'size' => 'md', 'showWebsite' => '1', 'align' => 'left',
                            ]),
                        ]
                        : [
                            $s->newBlock('form', [
                                // Ancla: es el destino de los botones
                                // «Contáctanos» de las otras secciones.
                                'anchor'         => 'contacto',
                                'title'          => lang('Pag.tplFormTitle'),
                                'intro'          => lang('Pag.tplFormIntro'),
                                'submitLabel'    => lang('Pag.tplFormSubmit'),
                                'successMessage' => lang('Pag.tplFormSuccess'),
                                'fields'         => [
                                    ['label' => lang('Pag.tplFieldName'), 'type' => 'text', 'options' => '', 'required' => '1'],
                                    ['label' => lang('Pag.tplFieldEmail'), 'type' => 'email', 'options' => '', 'required' => '1'],
                                    ['label' => lang('Pag.tplFieldPhone'), 'type' => 'tel', 'options' => '', 'required' => '0'],
                                    ['label' => lang('Pag.tplFieldMessage'), 'type' => 'textarea', 'options' => '', 'required' => '1'],
                                ],
                            ]),
                        ],
                        ['vAlign' => 'start']),
                ],
            ],
        ];
    }

    // ------------------------------------------------------ pies de página

    private static function footers(PageService $s): array
    {
        return [
            [
                'code'   => 'pie-empresa',
                'label'  => 'Pag.secFooterFull',
                'hint'   => 'Pag.secFooterFullHint',
                'icon'   => 'icon-list-columns-reverse',
                'group'  => 'footer',
                'blocks' => self::footer($s),
            ],
            [
                'code'   => 'pie-simple',
                'label'  => 'Pag.secFooterSimple',
                'hint'   => 'Pag.secFooterSimpleHint',
                'icon'   => 'icon-share',
                'group'  => 'footer',
                'blocks' => [
                    $s->newBlock('divider'),
                    $s->newBlock('companySocial', [
                        'title' => '', 'style' => 'plain', 'size' => 'md',
                        'showWebsite' => '1', 'align' => 'center',
                    ]),
                    $s->newBlock('text', [
                        'html'  => '<p>' . lang('Pag.secFooterLegal') . '</p>',
                        'align' => 'center', 'size' => 'sm',
                    ]),
                    $s->newBlock('spacer', ['size' => 'md']),
                ],
            ],
            [
                'code'   => 'pie-contacto',
                'label'  => 'Pag.secFooterContactStrip',
                'hint'   => 'Pag.secFooterContactStripHint',
                'icon'   => 'icon-telephone',
                'group'  => 'footer',
                'blocks' => [
                    self::columns($s, 3, static fn (int $i) => [
                        $s->newBlock('heading', [
                            'text'  => [lang('Pag.secFooterContact'), lang('Pag.secFooterHours'), lang('Pag.secFollowUs')][$i - 1] ?? '',
                            'level' => '4', 'align' => 'center',
                        ]),
                        $i === 3
                            ? $s->newBlock('companySocial', [
                                'title' => '', 'style' => 'solid', 'size' => 'sm',
                                'showWebsite' => '1', 'align' => 'center',
                            ])
                            : $s->newBlock('text', [
                                'html'  => '<p>' . ($i === 1 ? lang('Pag.tplWherePhone') : lang('Pag.tplWhereHours')) . '</p>',
                                'align' => 'center', 'size' => 'sm',
                            ]),
                    ], ['padding' => 'lg', 'gap' => 'md', 'vAlign' => 'center']),
                ],
            ],
        ];
    }

    // ------------------------------------------- cabecera y pie con elementos

    /**
     * Cabecera del sitio: logo a la izquierda, menú a la derecha.
     *
     * **Armada con elementos, no con un bloque monolítico.** Antes era un tipo
     * propio (`siteHeader`) con veinte campos: un repetidor para el menú y unos
     * interruptores para encender trozos. Nada de eso se podía mover, reordenar
     * ni editar como lo que era —una imagen y unos enlaces—, y en la cascada
     * aparecía como una sola fila opaca. Ahora es una sección de dos columnas
     * con una imagen y un bloque de botones dentro: se sigue soltando hecha,
     * pero cada pieza se edita por su cuenta y se puede quitar, sustituir o
     * completar con lo que sea.
     *
     * Lo suyo que no se podía reproducir con elementos ya vive en ellos:
     * `image.source = logo` (el logo sale de `companies`, no se vuelve a subir),
     * `buttons.collapse` (la hamburguesa sin JavaScript) y `section.sticky`.
     *
     * @param bool $completa con menú y botón destacado, o solo la marca
     */
    public static function header(PageService $s, bool $completa = true): array
    {
        $logo = $s->newBlock('image', [
            'source' => 'logo', 'src' => '', 'alt' => '', 'caption' => '',
            'size' => 'small', 'width' => '150px', 'align' => 'left',
            'radius' => 'none', 'ratio' => 'auto', 'href' => '#inicio',
            'marginTop' => 'none', 'marginBottom' => 'none',
        ]);

        $menu = $s->newBlock('buttons', [
            'items' => $completa
                ? [
                    ['label' => lang('Pag.secFooterLink1'), 'href' => '#inicio', 'style' => 'link', 'color' => '', 'newTab' => '0'],
                    ['label' => lang('Pag.secFooterLink2'), 'href' => '#servicios', 'style' => 'link', 'color' => '', 'newTab' => '0'],
                    ['label' => lang('Pag.tplCompanyAbout'), 'href' => '#nosotros', 'style' => 'link', 'color' => '', 'newTab' => '0'],
                    ['label' => lang('Pag.secCtaButton'), 'href' => '#contacto', 'style' => 'primary', 'color' => '', 'newTab' => '0'],
                ]
                : [
                    ['label' => lang('Pag.secFooterLink1'), 'href' => '#inicio', 'style' => 'link', 'color' => '', 'newTab' => '0'],
                    ['label' => lang('Pag.secFooterLink3'), 'href' => '#contacto', 'style' => 'link', 'color' => '', 'newTab' => '0'],
                ],
            // Fila, pegada a la derecha y plegable en el teléfono: eso es un
            // menú de cabecera.
            'direction' => 'row', 'size' => 'sm', 'radius' => 'sm', 'gap' => 'sm',
            'align' => 'right', 'stack' => '0', 'collapse' => '1',
            'marginTop' => 'none', 'marginBottom' => 'none',
        ]);

        $col1 = $s->newBlock('column', ['width' => '25%', 'align' => 'left']);
        $col1['children'] = [$logo];
        $col2 = $s->newBlock('column', ['width' => '75%', 'align' => 'right']);
        $col2['children'] = [$menu];

        $sec = $s->newBlock('section', [
            'columns' => '2', 'width' => 'boxed', 'padding' => 'sm', 'gap' => 'md',
            'sticky' => $completa ? '1' : '0', 'vAlign' => 'center',
            // Sin fondo la cabecera fija deja ver el contenido pasando por
            // debajo. Blanco es lo que espera cualquiera de una cabecera.
            'background' => '#ffffff', 'stackOnMobile' => '0',
            'anchor' => 'inicio',
        ]);
        $sec['children'] = [$col1, $col2];

        return $sec;
    }

    /**
     * Pie de cuatro columnas: marca, dos listas de enlaces y contacto, con la
     * línea legal debajo.
     *
     * Son **dos secciones**, no una: la línea legal va a todo lo ancho bajo las
     * cuatro columnas, y una columna no puede contener otra sección. Se sueltan
     * juntas y se editan por separado.
     *
     * @return array<int,array> los bloques de raíz
     */
    public static function footer(PageService $s): array
    {
        $enlaces = static fn (array $items) => $s->newBlock('buttons', [
            'items' => array_map(static fn ($i) => [
                'label' => $i[0], 'href' => $i[1], 'style' => 'link', 'color' => '', 'newTab' => '0',
            ], $items),
            'direction' => 'column', 'size' => 'sm', 'radius' => 'none',
            'gap' => 'none', 'align' => 'left', 'stack' => '0', 'collapse' => '0',
        ]);

        $columnas = [
            // 1. la marca: logo de la empresa + descripción + redes
            [
                $s->newBlock('image', [
                    'source' => 'logo', 'src' => '', 'size' => 'small', 'width' => '140px',
                    'align' => 'left', 'radius' => 'none', 'ratio' => 'auto',
                    'marginBottom' => 'sm',
                ]),
                $s->newBlock('text', [
                    'html' => '<p>' . lang('Pag.tplLoremShort') . '</p>', 'align' => 'left', 'size' => 'sm',
                ]),
                $s->newBlock('companySocial', [
                    'title' => '', 'style' => 'plain', 'size' => 'sm',
                    'showWebsite' => '1', 'align' => 'left',
                ]),
            ],
            // 2 y 3. listas de enlaces
            [
                $s->newBlock('heading', ['text' => lang('Pag.secFooterLinks'), 'level' => '4', 'align' => 'left']),
                $enlaces([
                    [lang('Pag.secFooterLink1'), '#inicio'],
                    [lang('Pag.secFooterLink2'), '#servicios'],
                    [lang('Pag.secFooterLink3'), '#contacto'],
                ]),
            ],
            [
                $s->newBlock('heading', ['text' => lang('Pag.tplCompanyAbout'), 'level' => '4', 'align' => 'left']),
                $enlaces([
                    [lang('Pag.tplMissionTitle'), '#nosotros'],
                    [lang('Pag.secTeamTitle'), '#equipo'],
                ]),
            ],
            // 4. cómo contactar
            [
                $s->newBlock('heading', ['text' => lang('Pag.secFooterContact'), 'level' => '4', 'align' => 'left']),
                // Los datos salen de `companies`: no se vuelven a escribir, y
                // el teléfono y el correo quedan pulsables.
                $s->newBlock('companyContact', [
                    'showAddress' => '1', 'showPhone' => '1', 'showEmail' => '1', 'align' => 'left',
                ]),
                $s->newBlock('companyQr', ['target' => 'page', 'url' => '', 'size' => 'sm', 'caption' => '', 'align' => 'left']),
            ],
        ];

        $sec = self::columns($s, 4, static fn (int $i) => $columnas[$i - 1], [
            'padding' => 'lg', 'gap' => 'lg', 'vAlign' => 'start', 'background' => '#f5f6f8',
        ]);

        $legal = $s->newBlock('section', [
            'columns' => '1', 'width' => 'boxed', 'padding' => 'sm',
            'background' => '#f5f6f8',
        ]);
        $col = $s->newBlock('column', ['align' => 'center']);
        $col['children'] = [
            $s->newBlock('divider', ['style' => 'line', 'width' => 'full']),
            $s->newBlock('text', [
                'html' => '<p>' . lang('Pag.secFooterLegal') . '</p>', 'align' => 'center', 'size' => 'sm',
            ]),
        ];
        $legal['children'] = [$col];

        return [$sec, $legal];
    }

    // -------------------------------------------------------------- utilidad

    /**
     * Sección de N columnas cuyo contenido produce `$fill($i)` (1-indexado).
     *
     * @param callable $fill fn(int $i): array — bloques de esa columna
     * @param array    $props ajustes de la sección
     */
    private static function columns(PageService $s, int $n, callable $fill, array $props = []): array
    {
        $section = $s->newBlock('section', array_merge(['columns' => (string) $n], $props));
        $section['children'] = [];

        for ($i = 1; $i <= $n; $i++) {
            $col = $s->newBlock('column');
            $content = $fill($i);
            // `$fill` puede devolver un bloque suelto o una lista.
            $col['children'] = isset($content['type']) ? [$content] : array_values($content);
            $section['children'][] = $col;
        }

        return $section;
    }

    /**
     * El catálogo listo para el navegador: etiquetas traducidas y el árbol de
     * cada sección tal cual se insertará (el editor le pone ids nuevos).
     */
    public static function forJs(): array
    {
        $out = [];

        foreach (self::all() as $sec) {
            $out[] = [
                'code'   => $sec['code'],
                'label'  => lang($sec['label']),
                'hint'   => isset($sec['hint']) ? lang($sec['hint']) : '',
                'icon'   => $sec['icon'] ?? 'icon-list-columns-reverse',
                'group'  => $sec['group'] ?? 'content',
                'blocks' => $sec['blocks'],
            ];
        }

        return $out;
    }

    /** Una sección por su código, o null. */
    public static function get(string $code): ?array
    {
        foreach (self::all() as $sec) {
            if ($sec['code'] === $code) {
                return $sec;
            }
        }

        return null;
    }
}
