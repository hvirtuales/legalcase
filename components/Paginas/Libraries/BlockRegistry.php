<?php

namespace Components\Paginas\Libraries;

/**
 * Catálogo de tipos de bloque: **la única definición** de qué bloques existen,
 * qué propiedades tiene cada uno y con qué valores nace.
 *
 * De aquí beben las tres piezas, y por eso no hay que repetir la lista en
 * ninguna otra parte:
 *   - la **paleta** y el **inspector** del editor (`forJs()` lo entrega ya
 *     traducido al navegador);
 *   - el **renderizador** (`Renderer`), que solo pinta tipos declarados aquí;
 *   - el **saneador** (`PageService::sanitizeTree()`), que descarta cualquier
 *     bloque o propiedad que no aparezca en este archivo. Añadir un bloque nuevo
 *     es añadir una entrada aquí y un `case` en `Renderer`.
 *
 * Tipos de campo del inspector: text, textarea, richtext, number, select, color,
 * image, link, toggle y repeater (lista de sub-campos).
 */
class BlockRegistry
{
    /** Bloques que contienen otros bloques. */
    public const CONTAINERS = ['section', 'column'];

    /** Grupos de la paleta, en el orden en que se muestran. */
    public const GROUPS = ['layout', 'content', 'company', 'action', 'advanced'];

    /**
     * Bloques que **leen la ficha de la empresa** en vez de pedir los datos otra
     * vez. Es la misma decisión que tomó el componente Servicios con la empresa
     * emisora: el logo, el banner, la descripción, el teléfono y las redes ya
     * están en `companies`, así que reescribirlos en cada página garantizaría
     * que un día dejen de coincidir.
     *
     * Los pinta `Renderer` con la empresa que recibe en su contexto.
     */
    public const COMPANY_BLOCKS = ['companyContact', 'companyQr', 'companySocial', 'whatsappFloat'];

    /** Alineaciones admitidas (valor => clase CSS del render). */
    public const ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * Campos que tiene **todo** bloque, se declaren o no en su ficha.
     *
     * Es el equivalente a los *block supports* de Gutenberg, donde `anchor`,
     * `className`, `color` o `spacing` se activan por bloque en vez de repetirse
     * en el esquema de cada uno. Aquí se aplican a todos y se añaden en un solo
     * sitio: `types()` los inyecta, así que `propNames()`, `field()` y
     * `defaults()` los ven sin tocar nada más.
     *
     * De momento solo `anchor`, y por un motivo concreto: sin ancla **no se
     * puede enlazar dentro de la propia página**. El botón «Contáctanos» de la
     * plantilla ejecutiva apuntaba a `#contacto` y no había forma de que ningún
     * bloque se llamara así — el enlace no llevaba a ninguna parte.
     */
    public const COMMON_FIELDS = [
        // --- Color del texto (el `color` support de Gutenberg) -------------
        //
        // Lo tenían solo cinco tipos (título, párrafo, botón, fila de botones y
        // el flotante de WhatsApp), así que una cita, una lista, unas tarjetas
        // o el contacto de la empresa **no había manera de aclararlos** sobre
        // un fondo oscuro; y como la opacidad vive dentro del control de color,
        // donde no había color tampoco había opacidad. Puesto en la sección y
        // en la columna **cascada a todo lo que lleven dentro**, que es como se
        // resuelve de una vez una banda oscura entera.
        ['name' => 'textColor', 'type' => 'color', 'label' => 'Pag.fTextColor', 'hint' => 'Pag.fTextColorHint'],

        // --- Espaciado (el `spacing` support de Gutenberg) -----------------
        // Escala cerrada en vez de píxeles libres: es lo mismo que gobierna el
        // bloque «Espacio», el relleno de la sección y la separación entre
        // columnas, así que una página sigue cuadrando aunque se toque bloque a
        // bloque. El valor vacío significa «lo que traiga el bloque»: sin él,
        // abrir el inspector de una página ya hecha la recompondría entera.
        ['name' => 'marginTop', 'type' => 'select', 'label' => 'Pag.fSpaceBefore', 'panel' => 'spacing', 'options' => [
            '' => 'Pag.spDefault', 'none' => 'Pag.sNone', 'sm' => 'Pag.sSmall',
            'md' => 'Pag.sMedium', 'lg' => 'Pag.sLarge', 'xl' => 'Pag.sXLarge',
        ]],
        ['name' => 'marginBottom', 'type' => 'select', 'label' => 'Pag.fSpaceAfter', 'panel' => 'spacing', 'options' => [
            '' => 'Pag.spDefault', 'none' => 'Pag.sNone', 'sm' => 'Pag.sSmall',
            'md' => 'Pag.sMedium', 'lg' => 'Pag.sLarge', 'xl' => 'Pag.sXLarge',
        ]],
        ['name' => 'padding', 'type' => 'select', 'label' => 'Pag.fPadding', 'hint' => 'Pag.fBlockPaddingHint', 'panel' => 'spacing', 'options' => [
            '' => 'Pag.spDefault', 'none' => 'Pag.sNone', 'sm' => 'Pag.sSmall',
            'md' => 'Pag.sMedium', 'lg' => 'Pag.sLarge', 'xl' => 'Pag.sXLarge',
        ]],

        // --- Avanzado -----------------------------------------------------
        ['name' => 'anchor', 'type' => 'anchor', 'label' => 'Pag.fAnchor', 'hint' => 'Pag.fAnchorHint', 'placeholder' => 'Pag.phAnchor', 'panel' => 'advanced'],
    ];

    /**
     * Tipos a los que NO se les inyecta un campo común, por nombre del campo.
     *
     * Un campo común es útil porque se declara una vez, pero universal no
     * quiere decir sensato: el separador ya tiene el color de SU línea (que es
     * todo lo que pinta), el espacio es invisible y el HTML avanzado trae su
     * propio estilo. Ofrecerles «color del texto» sería un control que no hace
     * nada, que es peor que no tenerlo.
     */
    public const COMMON_SKIP = [
        'textColor' => ['spacer', 'divider', 'html'],
    ];

    /** Escalas de espaciado admitidas. */
    public const SIZES = ['none', 'sm', 'md', 'lg', 'xl'];

    /**
     * Definición de cada tipo.
     *
     * label/hint y las etiquetas de las opciones son **claves de idioma**; se
     * resuelven en `forJs()` y en las vistas, nunca se guardan traducidas.
     */
    public static function types(): array
    {
        $types = self::definitions();

        // Los campos comunes se inyectan aquí, una sola vez, para que todo lo
        // que consulta el registro (paleta, inspector, saneador y render) los
        // vea sin repetirlos 20 veces.
        //
        // Un campo común **no pisa uno propio del bloque**: `section` ya tiene
        // su `padding` (con su propio significado: el aire vertical de la
        // banda), y duplicarlo dejaría dos controles «Relleno» en el inspector
        // y dos entradas del mismo nombre en el esquema.
        foreach ($types as $code => $def) {
            $propios = array_column($def['fields'] ?? [], 'name');
            $comunes = array_values(array_filter(
                self::COMMON_FIELDS,
                static fn ($f) => !in_array($f['name'], $propios, true)
                    && !in_array($code, self::COMMON_SKIP[$f['name']] ?? [], true)
            ));

            $types[$code]['fields'] = array_merge($def['fields'] ?? [], $comunes);

            foreach ($comunes as $f) {
                $types[$code]['defaults'][$f['name']] ??= '';
            }
        }

        return $types;
    }

    /** Las fichas tal cual se escriben, sin los campos comunes. */
    private static function definitions(): array
    {
        return [
            // ----------------------------------------------------- estructura
            'section' => [
                'label'  => 'Pag.bSection',
                'hint'   => 'Pag.bSectionHint',
                'icon'   => 'icon-list-columns-reverse',
                'group'  => 'layout',
                'fields' => [
                    ['name' => 'columns', 'type' => 'select', 'label' => 'Pag.fColumns', 'options' => [
                        '1' => 'Pag.oneColumn', '2' => 'Pag.twoColumns', '3' => 'Pag.threeColumns', '4' => 'Pag.fourColumns',
                    ]],
                    // Los tres anchos de Gutenberg (contenido / amplio / a todo
                    // lo ancho) más el estrecho de lectura. Las dos primeras
                    // medidas las define la página en sus ajustes, así que
                    // cambiarlas allí recompone todas las secciones a la vez.
                    // Las claves son las de antes (`boxed`, `full`, `narrow`)
                    // para que las páginas ya guardadas no cambien de sitio.
                    ['name' => 'width', 'type' => 'select', 'label' => 'Pag.fWidth', 'hint' => 'Pag.fWidthHint', 'options' => [
                        'boxed'  => 'Pag.widthContent',
                        'wide'   => 'Pag.widthWide',
                        'full'   => 'Pag.widthFull',
                        'narrow' => 'Pag.widthNarrow',
                    ]],
                    ['name' => 'padding', 'type' => 'select', 'label' => 'Pag.fPadding', 'options' => self::sizeOptions()],
                    ['name' => 'gap', 'type' => 'select', 'label' => 'Pag.fGap', 'options' => self::sizeOptions()],
                    // Fija al desplazar. Es lo que hacía la antigua «cabecera de
                    // sitio» y lo único suyo que no se podía reproducir con
                    // elementos sueltos; siendo propiedad de la sección, sirve
                    // además para una franja de avisos o un pie pegado.
                    ['name' => 'sticky', 'type' => 'toggle', 'label' => 'Pag.fSticky', 'hint' => 'Pag.fStickyHint'],
                    ['name' => 'background', 'type' => 'color', 'label' => 'Pag.fBackground', 'hint' => 'Pag.fBackgroundHint'],
                    // Equivalente al `core/cover` de Gutenberg: una sección con
                    // imagen de fondo y velo. Antes solo admitía color plano.
                    ['name' => 'backgroundImage', 'type' => 'image', 'label' => 'Pag.fBackgroundImage', 'hint' => 'Pag.fSectionBgImageHint'],
                    ['name' => 'overlay', 'type' => 'select', 'label' => 'Pag.fOverlay', 'options' => [
                        'none' => 'Pag.oNone', 'light' => 'Pag.oLight', 'dark' => 'Pag.oDark',
                    ]],
                    ['name' => 'vAlign', 'type' => 'select', 'label' => 'Pag.fVAlign', 'options' => [
                        'start' => 'Pag.vTop', 'center' => 'Pag.vCenter', 'stretch' => 'Pag.vStretch',
                    ]],
                    // `isStackedOnMobile` de `core/columns`. Antes las columnas
                    // se apilaban SIEMPRE en el teléfono, sin forma de evitarlo:
                    // una fila de dos iconos o dos cifras no quiere apilarse.
                    ['name' => 'stackOnMobile', 'type' => 'toggle', 'label' => 'Pag.fStackMobile', 'hint' => 'Pag.fStackMobileHint'],
                ],
                'defaults' => [
                    'columns' => '2', 'width' => 'boxed', 'padding' => 'lg', 'gap' => 'md',
                    'sticky' => '0', 'background' => '', 'backgroundImage' => '', 'overlay' => 'none',
                    'vAlign' => 'start', 'stackOnMobile' => '1',
                ],
            ],

            // `column` no está en la paleta: lo crea la sección al elegir cuántas
            // columnas tiene. Se declara para que el saneador lo reconozca.
            'column' => [
                'label'    => 'Pag.bColumn',
                'icon'     => 'icon-list-columns-reverse',
                'group'    => null,
                'fields'   => [
                    /*
                     * `width` de `core/column`, **en por ciento**.
                     *
                     * Antes eran seis fracciones fijas repartidas sobre una
                     * rejilla de 12 columnas, y ahí estaba el fallo: la rejilla
                     * llevaba 11 huecos de separación, así que en un ancho
                     * apretado —el lienzo del editor con los dos paneles
                     * abiertos— los huecos se comían el sitio, las doce pistas
                     * se encogían a casi nada y el contenido se salía de su
                     * celda amontonándose a la izquierda.
                     *
                     * Ahora es un porcentaje y la fila es flexible: la columna
                     * pide su parte y **cede lo que haga falta** para que quepa
                     * la separación, en vez de desbordarse.
                     *
                     * Se admite el valor a secas («25») y con signo («25 %»).
                     * Las seis palabras de antes siguen valiendo —hay páginas
                     * publicadas que las guardan— y se traducen a su porcentaje
                     * al pintar (`Renderer::columnWidth()`).
                     */
                    ['name' => 'width', 'type' => 'text', 'label' => 'Pag.fColWidth', 'hint' => 'Pag.fColWidthHint', 'placeholder' => 'Pag.phColWidth'],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                    /*
                     * Alineación VERTICAL del contenido dentro de la columna.
                     *
                     * El `vAlign` de la sección coloca las columnas dentro de
                     * la fila; este coloca lo que hay DENTRO de una columna,
                     * que es lo que hace falta cuando dos columnas tienen
                     * distinta altura y se quiere el texto centrado frente a
                     * una imagen alta, o pegado abajo.
                     */
                    ['name' => 'vAlign', 'type' => 'select', 'label' => 'Pag.fVAlignContent', 'hint' => 'Pag.fVAlignContentHint', 'options' => [
                        'top' => 'Pag.vTop', 'center' => 'Pag.vCenter', 'bottom' => 'Pag.vBottom', 'between' => 'Pag.vBetween',
                    ]],
                    // Fondo propio de la columna: para destacar una de las tres
                    // sin tener que meterla en otra sección.
                    ['name' => 'background', 'type' => 'color', 'label' => 'Pag.fBackground', 'hint' => 'Pag.fColumnBgHint'],
                    ['name' => 'radius', 'type' => 'select', 'label' => 'Pag.fRadius', 'options' => [
                        'none' => 'Pag.rNone', 'sm' => 'Pag.rSmall', 'lg' => 'Pag.rLarge',
                    ]],
                ],
                'defaults' => ['width' => '', 'align' => 'left', 'vAlign' => 'top', 'background' => '', 'radius' => 'none'],
            ],

            'spacer' => [
                'label'  => 'Pag.bSpacer',
                'hint'   => 'Pag.bSpacerHint',
                'icon'   => 'icon-arrow-down-up',
                'group'  => 'layout',
                'fields' => [
                    ['name' => 'size', 'type' => 'select', 'label' => 'Pag.fSize', 'options' => self::sizeOptions()],
                ],
                'defaults' => ['size' => 'md'],
            ],

            'divider' => [
                'label'  => 'Pag.bDivider',
                'icon'   => 'icon-dash-circle',
                'group'  => 'layout',
                'fields' => [
                    ['name' => 'style', 'type' => 'select', 'label' => 'Pag.fStyle', 'options' => [
                        'solid' => 'Pag.dSolid', 'dashed' => 'Pag.dDashed', 'dotted' => 'Pag.dDotted',
                    ]],
                    ['name' => 'color', 'type' => 'color', 'label' => 'Pag.fColor'],
                ],
                'defaults' => ['style' => 'solid', 'color' => ''],
            ],

            // ------------------------------------------------------ contenido
            'heading' => [
                'label'  => 'Pag.bHeading',
                'icon'   => 'icon-journal-text',
                'group'  => 'content',
                'fields' => [
                    ['name' => 'text', 'type' => 'text', 'label' => 'Pag.fText', 'placeholder' => 'Pag.phHeading'],
                    ['name' => 'level', 'type' => 'select', 'label' => 'Pag.fLevel', 'options' => [
                        '1' => 'Pag.h1', '2' => 'Pag.h2', '3' => 'Pag.h3', '4' => 'Pag.h4',
                    ]],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                    ['name' => 'color', 'type' => 'color', 'label' => 'Pag.fColor'],
                ],
                'defaults' => ['text' => '', 'level' => '2', 'align' => 'left', 'color' => ''],
            ],

            'text' => [
                'label'  => 'Pag.bText',
                'hint'   => 'Pag.bTextHint',
                'icon'   => 'icon-list',
                'group'  => 'content',
                'fields' => [
                    ['name' => 'html', 'type' => 'richtext', 'label' => 'Pag.fContent'],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                    ['name' => 'size', 'type' => 'select', 'label' => 'Pag.fTextSize', 'options' => [
                        'sm' => 'Pag.tSmall', 'md' => 'Pag.tNormal', 'lg' => 'Pag.tLarge',
                    ]],
                    // `core/paragraph` admite color de texto; el título ya lo
                    // tenía y el párrafo no, sin razón.
                    ['name' => 'color', 'type' => 'color', 'label' => 'Pag.fColor'],
                    // `dropCap` de `core/paragraph`: la capitular del primer
                    // párrafo, que es un recurso editorial clásico.
                    ['name' => 'dropCap', 'type' => 'toggle', 'label' => 'Pag.fDropCap', 'hint' => 'Pag.fDropCapHint'],
                ],
                'defaults' => ['html' => '', 'align' => 'left', 'size' => 'md', 'color' => '', 'dropCap' => '0'],
            ],

            'list' => [
                'label'  => 'Pag.bList',
                'icon'   => 'icon-list-check',
                'group'  => 'content',
                'fields' => [
                    ['name' => 'style', 'type' => 'select', 'label' => 'Pag.fStyle', 'options' => [
                        'check' => 'Pag.lCheck', 'bullet' => 'Pag.lBullet', 'number' => 'Pag.lNumber',
                        'arrow' => 'Pag.lArrow', 'star' => 'Pag.lStar', 'dash' => 'Pag.lDash',
                        'none' => 'Pag.lNone',
                    ]],
                    ['name' => 'items', 'type' => 'repeater', 'label' => 'Pag.fItems', 'addLabel' => 'Pag.addItem', 'fields' => [
                        ['name' => 'text', 'type' => 'text', 'label' => 'Pag.fText'],
                        // Un elemento de lista suele ser un enlace (un índice,
                        // un menú de servicios). Sin este campo había que
                        // escribirlo a mano en el bloque de texto.
                        ['name' => 'href', 'type' => 'link', 'label' => 'Pag.fLink', 'hint' => 'Pag.fListLinkHint'],
                    ]],
                ],
                'defaults' => ['style' => 'check', 'items' => []],
            ],

            'quote' => [
                'label'  => 'Pag.bQuote',
                'icon'   => 'icon-chat',
                'group'  => 'content',
                'fields' => [
                    ['name' => 'text', 'type' => 'textarea', 'label' => 'Pag.fQuoteText'],
                    ['name' => 'author', 'type' => 'text', 'label' => 'Pag.fAuthor'],
                    ['name' => 'role', 'type' => 'text', 'label' => 'Pag.fRole', 'placeholder' => 'Pag.phRole'],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                ],
                'defaults' => ['text' => '', 'author' => '', 'role' => '', 'align' => 'left'],
            ],

            'image' => [
                'label'  => 'Pag.bImage',
                'icon'   => 'icon-image',
                'group'  => 'content',
                'fields' => [
                    // De dónde sale la imagen. Con «logo» o «portada» se toma de
                    // `companies`: es lo que permite armar una cabecera con
                    // elementos sueltos sin volver a subir el logo en cada
                    // página (mismo principio que el resto de bloques de
                    // empresa: no se piden datos que la plataforma ya tiene).
                    ['name' => 'source', 'type' => 'select', 'label' => 'Pag.fImageSource', 'options' => [
                        'upload' => 'Pag.isUpload', 'logo' => 'Pag.isLogo', 'banner' => 'Pag.isBanner',
                    ]],
                    ['name' => 'src', 'type' => 'image', 'label' => 'Pag.fImage'],
                    ['name' => 'alt', 'type' => 'text', 'label' => 'Pag.fAlt', 'hint' => 'Pag.fAltHint'],
                    ['name' => 'caption', 'type' => 'text', 'label' => 'Pag.fCaption'],
                    ['name' => 'size', 'type' => 'select', 'label' => 'Pag.fSize', 'options' => [
                        'small' => 'Pag.iSmall', 'medium' => 'Pag.iMedium', 'large' => 'Pag.iLarge', 'full' => 'Pag.iFull',
                    ]],
                    // Medida exacta, en px o en %. Manda sobre el tamaño de
                    // arriba, que son cuatro escalones cerrados y no sirven
                    // cuando hace falta un logo de 160 px clavados.
                    ['name' => 'width', 'type' => 'text', 'label' => 'Pag.fWidth', 'hint' => 'Pag.fImageWidthHint', 'placeholder' => 'Pag.phImageWidth'],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                    ['name' => 'radius', 'type' => 'select', 'label' => 'Pag.fRadius', 'options' => [
                        'none' => 'Pag.rNone', 'sm' => 'Pag.rSmall', 'lg' => 'Pag.rLarge', 'full' => 'Pag.rCircle',
                    ]],
                    // `aspectRatio` de `core/image`: recorta la foto a una
                    // proporción fija. Sin esto, dos imágenes de alturas
                    // distintas descuadraban cualquier fila.
                    ['name' => 'ratio', 'type' => 'select', 'label' => 'Pag.fRatio', 'options' => self::ratioOptions()],
                    ['name' => 'href', 'type' => 'link', 'label' => 'Pag.fLink', 'hint' => 'Pag.fLinkHint'],
                ],
                'defaults' => [
                    'source' => 'upload', 'src' => '', 'alt' => '', 'caption' => '', 'size' => 'full',
                    'width' => '', 'align' => 'center', 'radius' => 'sm', 'ratio' => 'auto', 'href' => '',
                ],
            ],

            'gallery' => [
                'label'  => 'Pag.bGallery',
                'icon'   => 'icon-image',
                'group'  => 'content',
                'fields' => [
                    ['name' => 'columns', 'type' => 'select', 'label' => 'Pag.fColumns', 'options' => [
                        '2' => 'Pag.twoColumns', '3' => 'Pag.threeColumns', '4' => 'Pag.fourColumns',
                    ]],
                    ['name' => 'radius', 'type' => 'select', 'label' => 'Pag.fRadius', 'options' => [
                        'none' => 'Pag.rNone', 'sm' => 'Pag.rSmall', 'lg' => 'Pag.rLarge',
                    ]],
                    // `imageCrop` de `core/gallery`. Antes el recorte 4:3 estaba
                    // fijo en el CSS y no se podía cambiar.
                    ['name' => 'ratio', 'type' => 'select', 'label' => 'Pag.fRatio', 'options' => self::ratioOptions()],
                    ['name' => 'items', 'type' => 'repeater', 'label' => 'Pag.fImages', 'addLabel' => 'Pag.addImage', 'fields' => [
                        ['name' => 'src', 'type' => 'image', 'label' => 'Pag.fImage'],
                        ['name' => 'alt', 'type' => 'text', 'label' => 'Pag.fAlt'],
                        ['name' => 'caption', 'type' => 'text', 'label' => 'Pag.fCaption'],
                    ]],
                ],
                'defaults' => ['columns' => '3', 'radius' => 'sm', 'ratio' => '4-3', 'items' => []],
            ],

            'video' => [
                'label'  => 'Pag.bVideo',
                'hint'   => 'Pag.bVideoHint',
                'icon'   => 'icon-play-circle',
                'group'  => 'content',
                'fields' => [
                    ['name' => 'url', 'type' => 'text', 'label' => 'Pag.fVideoUrl', 'placeholder' => 'Pag.phVideoUrl'],
                    ['name' => 'ratio', 'type' => 'select', 'label' => 'Pag.fRatio', 'options' => [
                        '16-9' => '16:9', '4-3' => '4:3', '1-1' => '1:1',
                    ]],
                    ['name' => 'caption', 'type' => 'text', 'label' => 'Pag.fCaption'],
                ],
                'defaults' => ['url' => '', 'ratio' => '16-9', 'caption' => ''],
            ],

            // --------------------------------------------------------- acción
            /*
             * Botón suelto — **fuera de la paleta** (`hidden`).
             *
             * Lo sustituye `buttons`, que hace lo mismo con uno o con varios.
             * El tipo NO se elimina: hay páginas publicadas que lo usan y
             * guardan `label`/`href` sueltos, no una lista; borrarlo haría que
             * el saneador se las llevara por delante en el siguiente guardado.
             * Sigue pintándose y sigue teniendo inspector; solo no se puede
             * añadir uno nuevo.
             */
            'button' => [
                'label'  => 'Pag.bButton',
                'icon'   => 'icon-box-arrow-up-right',
                'group'  => 'action',
                'hidden' => true,
                'fields' => [
                    ['name' => 'label', 'type' => 'text', 'label' => 'Pag.fLabel', 'placeholder' => 'Pag.phButton'],
                    ['name' => 'href', 'type' => 'link', 'label' => 'Pag.fLink', 'hint' => 'Pag.fLinkHint'],
                    ['name' => 'style', 'type' => 'select', 'label' => 'Pag.fStyle', 'options' => [
                        'primary' => 'Pag.btnPrimary', 'outline' => 'Pag.btnOutline', 'ghost' => 'Pag.btnGhost',
                    ]],
                    ['name' => 'size', 'type' => 'select', 'label' => 'Pag.fSize', 'options' => [
                        'sm' => 'Pag.tSmall', 'md' => 'Pag.tNormal', 'lg' => 'Pag.tLarge',
                    ]],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                    ['name' => 'radius', 'type' => 'select', 'label' => 'Pag.fRadius', 'options' => [
                        'sm' => 'Pag.rSmall', 'none' => 'Pag.rNone', 'lg' => 'Pag.rLarge', 'full' => 'Pag.rPill',
                    ]],
                    // `backgroundColor` de `core/button`: hasta ahora el botón
                    // solo podía llevar el acento de la página.
                    ['name' => 'color', 'type' => 'color', 'label' => 'Pag.fButtonColor', 'hint' => 'Pag.fButtonColorHint'],
                    // El color con el puntero encima. Se emite como variable CSS
                    // porque una regla `:hover` no puede leer un `style` en
                    // línea; vacío = el mismo color un 20 % más oscuro.
                    ['name' => 'textColor', 'type' => 'color', 'label' => 'Pag.fButtonTextColor', 'hint' => 'Pag.fButtonTextColorHint'],
                    ['name' => 'hoverColor', 'type' => 'color', 'label' => 'Pag.fButtonHover', 'hint' => 'Pag.fButtonHoverHint'],
                    ['name' => 'fullWidth', 'type' => 'toggle', 'label' => 'Pag.fFullWidth', 'hint' => 'Pag.fFullWidthHint'],
                    ['name' => 'newTab', 'type' => 'toggle', 'label' => 'Pag.fNewTab'],
                ],
                'defaults' => [
                    'label' => '', 'href' => '', 'style' => 'primary',
                    'size' => 'md', 'align' => 'left', 'radius' => 'sm',
                    'color' => '', 'textColor' => '', 'hoverColor' => '', 'fullWidth' => '0', 'newTab' => '0',
                ],
            ],

            /*
             * Botones: uno o varios, en fila o en columna.
             *
             * Es EL elemento de acción de la paleta. Con un solo elemento es un
             * botón suelto; con varios en fila, la típica pareja «Contáctanos»
             * (sólido) + «Ver más» (con borde); en vertical y con el estilo
             * «enlace», una lista de navegación; y con «plegar en el móvil»,
             * el menú de una cabecera.
             *
             * El botón suelto (`button`) sigue existiendo —hay páginas
             * publicadas que lo usan y guardan `label`/`href` sueltos, no una
             * lista— pero ya no se ofrece en la paleta: dos elementos para lo
             * mismo obligaban a elegir antes de saber cuántos botones harían
             * falta.
             */
            'buttons' => [
                'label'  => 'Pag.bButtons',
                'hint'   => 'Pag.bButtonsHint',
                'icon'   => 'icon-box-arrow-up-right',
                'group'  => 'action',
                'fields' => [
                    ['name' => 'items', 'type' => 'repeater', 'label' => 'Pag.fButtons', 'addLabel' => 'Pag.addButton', 'fields' => [
                        ['name' => 'label', 'type' => 'text', 'label' => 'Pag.fLabel'],
                        ['name' => 'href', 'type' => 'link', 'label' => 'Pag.fLink'],
                        ['name' => 'style', 'type' => 'select', 'label' => 'Pag.fStyle', 'options' => [
                            'primary' => 'Pag.btnPrimary', 'outline' => 'Pag.btnOutline',
                            'ghost' => 'Pag.btnGhost', 'link' => 'Pag.btnLink',
                        ]],
                        ['name' => 'color', 'type' => 'color', 'label' => 'Pag.fButtonColor'],
                        // Sin esto la tinta la decidía sola la luminancia del
                        // fondo: blanca o negra y nada más.
                        ['name' => 'textColor', 'type' => 'color', 'label' => 'Pag.fButtonTextColor'],
                        // El color al pasar por encima lo tenía el botón
                        // suelto y no esta fila, que es la que se usa: sin él
                        // los botones de un menú se quedaban sin reacción.
                        ['name' => 'hoverColor', 'type' => 'color', 'label' => 'Pag.fButtonHover', 'hint' => 'Pag.fButtonHoverHint'],
                        ['name' => 'newTab', 'type' => 'toggle', 'label' => 'Pag.fNewTab'],
                    ]],
                    // Fila o columna. En columna y con el estilo «enlace», el
                    // bloque es un menú de navegación.
                    ['name' => 'direction', 'type' => 'select', 'label' => 'Pag.fDirection', 'options' => [
                        'row' => 'Pag.dirRow', 'column' => 'Pag.dirColumn',
                    ]],
                    ['name' => 'size', 'type' => 'select', 'label' => 'Pag.fSize', 'options' => [
                        'md' => 'Pag.tNormal', 'sm' => 'Pag.tSmall', 'lg' => 'Pag.tLarge',
                    ]],
                    ['name' => 'radius', 'type' => 'select', 'label' => 'Pag.fRadius', 'options' => [
                        'sm' => 'Pag.rSmall', 'none' => 'Pag.rNone', 'lg' => 'Pag.rLarge', 'full' => 'Pag.rPill',
                    ]],
                    ['name' => 'gap', 'type' => 'select', 'label' => 'Pag.fGap', 'options' => self::sizeOptions()],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                    ['name' => 'stack', 'type' => 'toggle', 'label' => 'Pag.fStackButtons', 'hint' => 'Pag.fStackButtonsHint'],
                    // Menú plegable en el teléfono. La casilla y el `:checked`
                    // hacen el trabajo: la página pública no carga ni una línea
                    // de JavaScript (§7.3), así que un menú con guion no es una
                    // opción.
                    ['name' => 'collapse', 'type' => 'toggle', 'label' => 'Pag.fCollapse', 'hint' => 'Pag.fCollapseHint'],
                ],
                'defaults' => [
                    'items' => [], 'direction' => 'row', 'size' => 'md', 'radius' => 'sm',
                    'gap' => 'sm', 'align' => 'left', 'stack' => '1', 'collapse' => '0',
                ],
            ],

            'hero' => [
                'label'  => 'Pag.bHero',
                'hint'   => 'Pag.bHeroHint',
                'icon'   => 'icon-star',
                'group'  => 'action',
                'fields' => [
                    ['name' => 'title', 'type' => 'text', 'label' => 'Pag.fTitle'],
                    ['name' => 'subtitle', 'type' => 'textarea', 'label' => 'Pag.fSubtitle'],
                    ['name' => 'image', 'type' => 'image', 'label' => 'Pag.fBackgroundImage'],
                    ['name' => 'overlay', 'type' => 'select', 'label' => 'Pag.fOverlay', 'options' => [
                        'none' => 'Pag.oNone', 'light' => 'Pag.oLight', 'dark' => 'Pag.oDark',
                    ]],
                    ['name' => 'height', 'type' => 'select', 'label' => 'Pag.fHeight', 'options' => [
                        'sm' => 'Pag.tSmall', 'md' => 'Pag.tNormal', 'lg' => 'Pag.tLarge',
                    ]],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                    ['name' => 'buttonLabel', 'type' => 'text', 'label' => 'Pag.fButtonLabel'],
                    ['name' => 'buttonHref', 'type' => 'link', 'label' => 'Pag.fButtonLink'],
                ],
                'defaults' => [
                    'title' => '', 'subtitle' => '', 'image' => '', 'overlay' => 'dark',
                    'height' => 'md', 'align' => 'center', 'buttonLabel' => '', 'buttonHref' => '',
                ],
            ],

            'cards' => [
                'label'  => 'Pag.bCards',
                'hint'   => 'Pag.bCardsHint',
                'icon'   => 'icon-box-seam',
                'group'  => 'action',
                'fields' => [
                    ['name' => 'columns', 'type' => 'select', 'label' => 'Pag.fColumns', 'options' => [
                        '2' => 'Pag.twoColumns', '3' => 'Pag.threeColumns', '4' => 'Pag.fourColumns',
                    ]],
                    ['name' => 'items', 'type' => 'repeater', 'label' => 'Pag.fCards', 'addLabel' => 'Pag.addCard', 'fields' => [
                        ['name' => 'image', 'type' => 'image', 'label' => 'Pag.fImage'],
                        ['name' => 'title', 'type' => 'text', 'label' => 'Pag.fTitle'],
                        ['name' => 'text', 'type' => 'textarea', 'label' => 'Pag.fText'],
                        ['name' => 'linkLabel', 'type' => 'text', 'label' => 'Pag.fLinkLabel'],
                        ['name' => 'href', 'type' => 'link', 'label' => 'Pag.fLink'],
                    ]],
                ],
                'defaults' => ['columns' => '3', 'items' => []],
            ],

            'form' => [
                'label'  => 'Pag.bForm',
                'hint'   => 'Pag.bFormHint',
                'icon'   => 'icon-envelope',
                'group'  => 'action',
                'fields' => [
                    ['name' => 'title', 'type' => 'text', 'label' => 'Pag.fTitle'],
                    ['name' => 'intro', 'type' => 'textarea', 'label' => 'Pag.fIntro'],
                    ['name' => 'fields', 'type' => 'repeater', 'label' => 'Pag.fFormFields', 'addLabel' => 'Pag.addField', 'fields' => [
                        ['name' => 'label', 'type' => 'text', 'label' => 'Pag.fLabel'],
                        ['name' => 'type', 'type' => 'select', 'label' => 'Pag.fFieldType', 'options' => [
                            'text' => 'Pag.ftText', 'email' => 'Pag.ftEmail', 'tel' => 'Pag.ftPhone',
                            'textarea' => 'Pag.ftTextarea', 'select' => 'Pag.ftSelect',
                        ]],
                        ['name' => 'options', 'type' => 'text', 'label' => 'Pag.fFieldOptions', 'hint' => 'Pag.fFieldOptionsHint'],
                        ['name' => 'required', 'type' => 'toggle', 'label' => 'Pag.fRequired'],
                    ]],
                    ['name' => 'submitLabel', 'type' => 'text', 'label' => 'Pag.fSubmitLabel'],
                    ['name' => 'successMessage', 'type' => 'textarea', 'label' => 'Pag.fSuccessMessage'],
                ],
                'defaults' => [
                    'title' => '', 'intro' => '', 'fields' => [],
                    'submitLabel' => '', 'successMessage' => '',
                ],
            ],

            // -------------------------------------------------------- empresa
            // Los cuatro leen `companies`: no piden datos que la plataforma ya
            // tiene (ver COMPANY_BLOCKS arriba).
            /*
             * La CABECERA y el PIE ya no son elementos.
             *
             * Eran dos bloques monolíticos con veinte campos cada uno: un
             * repetidor para el menú, otro para cada lista de enlaces, y
             * interruptores para encender o apagar trozos. Nada de eso se
             * podía mover, reordenar ni editar como lo que era —un logo, unos
             * enlaces, un párrafo—, y en la cascada aparecían como una sola
             * fila opaca.
             *
             * Ahora son SECCIONES PREARMADAS (`SectionLibrary::header()` y
             * `::footer()`), armadas con los elementos de siempre: imagen,
             * botones, título, texto y redes. Se sueltan igual de hechas desde
             * la pestaña «Bloques», pero cada pieza se edita por su cuenta.
             * Lo que hacía falta para que fueran de verdad equivalentes está
             * en los elementos: `image.source` (logo de la empresa),
             * `buttons.direction`/`collapse` (el menú, con hamburguesa sin
             * JavaScript) y `section.sticky` (fija al desplazar).
             */
            /*
             * Contacto de la empresa: dirección, teléfono y correo.
             *
             * Es el hermano que faltaba de `companySocial` y `companyQr`. Sin
             * él, al armar el pie con elementos sueltos había que **volver a
             * escribir a mano** el teléfono y el correo que ya están en
             * `companies` — exactamente lo que los bloques de empresa existen
             * para evitar. El teléfono y el correo salen pulsables (`tel:` y
             * `mailto:`), que en un móvil es la mitad del valor de un pie.
             */
            'companyContact' => [
                'label'  => 'Pag.bCompanyContact',
                'hint'   => 'Pag.bCompanyContactHint',
                'icon'   => 'icon-telephone',
                'group'  => 'company',
                'fields' => [
                    ['name' => 'showAddress', 'type' => 'toggle', 'label' => 'Pag.fShowAddress'],
                    ['name' => 'showPhone', 'type' => 'toggle', 'label' => 'Pag.fShowPhone'],
                    ['name' => 'showEmail', 'type' => 'toggle', 'label' => 'Pag.fShowEmail'],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                ],
                'defaults' => [
                    'showAddress' => '1', 'showPhone' => '1', 'showEmail' => '1', 'align' => 'left',
                ],
            ],

            'companyQr' => [
                'label'  => 'Pag.bCompanyQr',
                'hint'   => 'Pag.bCompanyQrHint',
                'icon'   => 'icon-qr-code',
                'group'  => 'company',
                'fields' => [
                    ['name' => 'target', 'type' => 'select', 'label' => 'Pag.fQrTarget', 'hint' => 'Pag.fQrTargetHint', 'options' => [
                        'page' => 'Pag.qrPage', 'website' => 'Pag.qrWebsite', 'whatsapp' => 'Pag.qrWhatsapp', 'custom' => 'Pag.qrCustom',
                    ]],
                    ['name' => 'url', 'type' => 'link', 'label' => 'Pag.fQrUrl', 'hint' => 'Pag.fQrUrlHint'],
                    ['name' => 'title', 'type' => 'text', 'label' => 'Pag.fTitle', 'placeholder' => 'Pag.phQrTitle'],
                    ['name' => 'caption', 'type' => 'text', 'label' => 'Pag.fCaption', 'placeholder' => 'Pag.phQrCaption'],
                    ['name' => 'size', 'type' => 'select', 'label' => 'Pag.fSize', 'options' => [
                        'sm' => 'Pag.tSmall', 'md' => 'Pag.tNormal', 'lg' => 'Pag.tLarge',
                    ]],
                    ['name' => 'frame', 'type' => 'select', 'label' => 'Pag.fFrame', 'options' => [
                        'card' => 'Pag.frameCard', 'plain' => 'Pag.framePlain',
                    ]],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                ],
                'defaults' => [
                    'target' => 'page', 'url' => '', 'title' => '', 'caption' => '',
                    'size' => 'md', 'frame' => 'card', 'align' => 'center',
                ],
            ],

            'companySocial' => [
                'label'  => 'Pag.bCompanySocial',
                'hint'   => 'Pag.bCompanySocialHint',
                'icon'   => 'icon-share',
                'group'  => 'company',
                'fields' => [
                    ['name' => 'title', 'type' => 'text', 'label' => 'Pag.fTitle', 'placeholder' => 'Pag.phSocialTitle'],
                    ['name' => 'style', 'type' => 'select', 'label' => 'Pag.fStyle', 'options' => [
                        'solid' => 'Pag.socialSolid', 'outline' => 'Pag.socialOutline', 'plain' => 'Pag.socialPlain',
                    ]],
                    ['name' => 'size', 'type' => 'select', 'label' => 'Pag.fSize', 'options' => [
                        'sm' => 'Pag.tSmall', 'md' => 'Pag.tNormal', 'lg' => 'Pag.tLarge',
                    ]],
                    ['name' => 'showWebsite', 'type' => 'toggle', 'label' => 'Pag.fShowWebsite'],
                    // `showLabels` de `core/social-links`: con el nombre al lado
                    // del icono. Ayuda cuando la marca no se reconoce sola.
                    ['name' => 'showLabels', 'type' => 'toggle', 'label' => 'Pag.fShowLabels', 'hint' => 'Pag.fShowLabelsHint'],
                    ['name' => 'align', 'type' => 'select', 'label' => 'Pag.fAlign', 'options' => self::alignOptions()],
                ],
                'defaults' => [
                    'title' => '', 'style' => 'solid', 'size' => 'md',
                    'showWebsite' => '1', 'showLabels' => '0', 'align' => 'center',
                ],
            ],

            'whatsappFloat' => [
                'label'  => 'Pag.bWhatsapp',
                'hint'   => 'Pag.bWhatsappHint',
                'icon'   => 'icon-whatsapp',
                'group'  => 'company',
                'fields' => [
                    ['name' => 'phone', 'type' => 'text', 'label' => 'Pag.fWaPhone', 'hint' => 'Pag.fWaPhoneHint', 'placeholder' => 'Pag.phWaPhone'],
                    ['name' => 'message', 'type' => 'textarea', 'label' => 'Pag.fWaMessage', 'hint' => 'Pag.fWaMessageHint'],
                    ['name' => 'label', 'type' => 'text', 'label' => 'Pag.fWaLabel', 'hint' => 'Pag.fWaLabelHint', 'placeholder' => 'Pag.phWaLabel'],
                    ['name' => 'position', 'type' => 'select', 'label' => 'Pag.fWaPosition', 'options' => [
                        'right' => 'Pag.waRight', 'left' => 'Pag.waLeft',
                    ]],
                    // Vacíos = el verde de WhatsApp con tinta blanca. Se puede
                    // cambiar, pero por defecto manda el color de la marca de
                    // WhatsApp y no el acento de la página: el botón se
                    // reconoce por el color tanto como por el icono.
                    ['name' => 'background', 'type' => 'color', 'label' => 'Pag.fBackground', 'hint' => 'Pag.fWaColorHint'],
                    ['name' => 'textColor', 'type' => 'color', 'label' => 'Pag.fButtonTextColor', 'hint' => 'Pag.fWaTextHint'],
                ],
                'defaults' => [
                    'phone' => '', 'message' => '', 'label' => '', 'position' => 'right',
                    'background' => '', 'textColor' => '',
                ],
            ],

            // ------------------------------------------------------- avanzado
            'html' => [
                'label'  => 'Pag.bHtml',
                'hint'   => 'Pag.bHtmlHint',
                'icon'   => 'icon-cpu',
                'group'  => 'advanced',
                'fields' => [
                    ['name' => 'code', 'type' => 'textarea', 'label' => 'Pag.fHtmlCode', 'rows' => 8],
                ],
                'defaults' => ['code' => ''],
            ],
        ];
    }

    /** Definición de un tipo, o null si no existe (bloque desconocido). */
    public static function get(string $type): ?array
    {
        return self::types()[$type] ?? null;
    }

    public static function exists(string $type): bool
    {
        return isset(self::types()[$type]);
    }

    public static function isContainer(string $type): bool
    {
        return in_array($type, self::CONTAINERS, true);
    }

    /** Valores con los que nace un bloque de este tipo. */
    public static function defaults(string $type): array
    {
        return self::types()[$type]['defaults'] ?? [];
    }

    /** Nombres de propiedad admitidos para un tipo (lo que sobrevive al saneado). */
    public static function propNames(string $type): array
    {
        $def = self::get($type);
        if (!$def) {
            return [];
        }

        return array_column($def['fields'] ?? [], 'name');
    }

    /** Definición de un campo concreto, para saber cómo sanearlo. */
    public static function field(string $type, string $name): ?array
    {
        foreach (self::get($type)['fields'] ?? [] as $f) {
            if ($f['name'] === $name) {
                return $f;
            }
        }

        return null;
    }

    /**
     * ¿El tipo declara ese campo POR SU CUENTA (no como campo común)?
     *
     * Hace falta porque un mismo nombre puede significar cosas distintas: el
     * `padding` de la **sección** es el aire vertical de la banda y ya lo pinta
     * ella (`pg-pad-*`), mientras que el común es el relleno del bloque
     * (`pg-p-*`). Aplicar los dos dejaba la sección con el doble de relleno y
     * además por los cuatro lados.
     */
    public static function hasOwnField(string $type, string $name): bool
    {
        foreach (self::definitions()[$type]['fields'] ?? [] as $f) {
            if (($f['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valores admitidos de un `select` (para validar sin confiar en el POST).
     *
     * **Siempre como cadena.** PHP convierte en enteros las claves de array que
     * son números («2», «3», «4» de las columnas; «1»..«4» de los niveles de
     * título), así que `array_keys()` devolvía enteros y la comparación estricta
     * contra el valor del bloque —que es una cadena— era falsa SIEMPRE. El
     * efecto era silencioso y feo: cualquier opción numérica caía al valor por
     * defecto, y una sección de 4 columnas se pintaba con 2.
     */
    public static function optionValues(string $type, string $name): array
    {
        $field = self::field($type, $name);
        if (!$field || ($field['type'] ?? '') !== 'select') {
            return [];
        }

        return array_map('strval', array_keys($field['options'] ?? []));
    }

    /**
     * El catálogo listo para el navegador: mismas claves, pero con los textos ya
     * traducidos. El editor no sabe de i18n; solo pinta lo que recibe.
     */
    public static function forJs(): array
    {
        $out = [];

        foreach (self::types() as $type => $def) {
            $fields = [];

            foreach ($def['fields'] ?? [] as $f) {
                $fields[] = self::translateField($f);
            }

            $out[$type] = [
                'label'     => lang($def['label']),
                'hint'      => isset($def['hint']) ? lang($def['hint']) : '',
                'icon'      => $def['icon'] ?? 'icon-puzzle',
                'group'     => $def['group'] ?? null,
                // Fuera de la paleta, pero CON sus campos: hay páginas que
                // todavía llevan bloques de este tipo y hay que poder editarlos.
                'hidden'    => !empty($def['hidden']),
                'container' => self::isContainer($type),
                'fields'    => $fields,
                'defaults'  => $def['defaults'] ?? [],
            ];
        }

        return $out;
    }

    /** Traduce etiquetas, ayudas, placeholders y opciones de un campo. */
    private static function translateField(array $f): array
    {
        $f['label'] = isset($f['label']) ? lang($f['label']) : '';
        if (isset($f['hint'])) {
            $f['hint'] = lang($f['hint']);
        }
        if (isset($f['placeholder'])) {
            $f['placeholder'] = lang($f['placeholder']);
        }
        if (isset($f['addLabel'])) {
            $f['addLabel'] = lang($f['addLabel']);
        }
        if (isset($f['options'])) {
            $opts = [];

            foreach ($f['options'] as $value => $label) {
                // Las etiquetas que no son clave de idioma (16:9, 4:3…) se dejan.
                $opts[(string) $value] = str_contains((string) $label, '.') ? lang($label) : $label;
            }
            $f['options'] = $opts;
        }
        foreach ($f['fields'] ?? [] as $i => $sub) {
            $f['fields'][$i] = self::translateField($sub);
        }

        return $f;
    }

    private static function sizeOptions(): array
    {
        return [
            'none' => 'Pag.sNone', 'sm' => 'Pag.sSmall', 'md' => 'Pag.sMedium',
            'lg' => 'Pag.sLarge', 'xl' => 'Pag.sXLarge',
        ];
    }

    private static function alignOptions(): array
    {
        return ['left' => 'Pag.aLeft', 'center' => 'Pag.aCenter', 'right' => 'Pag.aRight'];
    }

    /** Proporciones de recorte (el `aspectRatio` de Gutenberg). */
    private static function ratioOptions(): array
    {
        return [
            'auto' => 'Pag.arOriginal', '16-9' => '16:9', '4-3' => '4:3',
            '1-1' => '1:1', '3-4' => '3:4', '9-16' => '9:16',
        ];
    }
}
