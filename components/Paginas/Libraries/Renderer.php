<?php

namespace Components\Paginas\Libraries;

/**
 * JSON de bloques → HTML. **Única autoridad sobre la salida.**
 *
 * La página no guarda HTML: guarda el árbol. Eso significa que este archivo es
 * el sitio donde se decide cómo se ve todo lo publicado —cambiarlo aquí cambia
 * todas las páginas, sin migrar nada— y también el único punto por el que puede
 * escaparse una inyección. De ahí las tres reglas que sigue sin excepción:
 *
 *   1. **Todo texto sale por `esc()`.** Lo único que se emite crudo es el HTML
 *      del bloque de texto y el del bloque avanzado, y ambos pasaron por
 *      `HtmlSanitizer` **al guardarse** (no aquí: lo que está en BD ya es seguro).
 *   2. **Nada que venga de los datos entra en un atributo sin validarse antes.**
 *      Los colores contra una expresión regular de hex, las URL contra una lista
 *      de esquemas, y todo lo demás (alineación, tamaño, columnas) contra los
 *      valores declarados en `BlockRegistry` — el navegador nunca decide una
 *      clase CSS.
 *   3. **Un tipo que no esté en `BlockRegistry` no se pinta.** No hay rama
 *      «por defecto» que intente adivinar.
 *
 * El editor dibuja una aproximación en el navegador para poder trabajar, pero la
 * salida buena es esta. Por eso «Vista previa» abre la página renderizada aquí.
 */
class Renderer
{
    private HtmlSanitizer $san;

    /**
     * @param array $settings Ajustes de la página (color de acento, ancho…).
     * @param array $context  Datos que solo conoce quien pinta: formAction,
     *                        csrf, sentBlock (formulario recién enviado),
     *                        formErrors, editor (bool).
     */
    public function __construct(private array $settings = [], private array $context = [])
    {
        $this->san = new HtmlSanitizer();
        helper('media');
    }

    /** Pinta una lista de bloques (el árbol de la página). */
    public function render(array $blocks): string
    {
        $out = '';

        foreach ($blocks as $block) {
            if (is_array($block)) {
                $out .= $this->block($block);
            }
        }

        return $out;
    }

    /** Pinta un bloque. Un tipo desconocido no produce nada. */
    private function block(array $b): string
    {
        $type = (string) ($b['type'] ?? '');
        if (!BlockRegistry::exists($type)) {
            return '';
        }

        $p        = is_array($b['props'] ?? null) ? $b['props'] : [];
        $children = is_array($b['children'] ?? null) ? $b['children'] : [];
        $uid      = $this->uid($b);

        return $this->withCommon($this->paint($type, $p, $children, $uid), $p, $type);
    }

    /**
     * Aplica a la etiqueta raíz del bloque lo que es común a todos: el ancla
     * (`id=`) y las clases de espaciado.
     *
     * Se hace aquí y no en cada método porque **todos devuelven un único
     * elemento raíz**; repetirlo en los veinte sería garantizar que un día
     * alguno se quede sin ello. Los valores ya vienen saneados por
     * `PageService`, así que lo único que queda es colocarlos.
     */
    private function withCommon(string $html, array $p, string $type = ''): string
    {
        if ($html === '') {
            return $html;
        }

        // --- ancla ---------------------------------------------------------
        $id = trim((string) ($p['anchor'] ?? ''));
        if ($id !== '' && preg_match('/^[a-z][a-z0-9-]{0,60}$/', $id)) {
            // Solo la PRIMERA etiqueta, y solo si aún no tiene id.
            $html = preg_replace('/^(<[a-z]+[a-z0-9]*)(?![^>]*\sid=)/i', '$1 id="' . $id . '"', $html, 1) ?? $html;
        }

        // --- color del texto -----------------------------------------------
        //
        // En la sección y en la columna **cascada a sus hijos**: es lo que
        // resuelve una banda oscura entera sin ir bloque a bloque. Un hijo que
        // fije el suyo gana, porque su `color` va en su propia etiqueta.
        $tinta = $this->color($p['textColor'] ?? '');
        if ($tinta !== '') {
            $html = $this->addStyle($html, 'color:' . $tinta);
        }

        // --- espaciado -----------------------------------------------------
        $clases = [];

        foreach ([['marginTop', 'pg-mt-'], ['marginBottom', 'pg-mb-'], ['padding', 'pg-p-']] as [$prop, $prefijo]) {
            // Si el TIPO declara ese campo por su cuenta, es suyo y ya lo ha
            // pintado él: la sección tiene su propio `padding` (el aire de la
            // banda, `pg-pad-*`), y añadirle también el común la dejaba con el
            // doble de relleno y por los cuatro lados.
            if ($type !== '' && BlockRegistry::hasOwnField($type, $prop)) {
                continue;
            }
            $v = (string) ($p[$prop] ?? '');
            // Vacío = «lo que traiga el bloque»: no se emite nada.
            if ($v !== '' && in_array($v, self::SIZES, true)) {
                $clases[] = $prefijo . $v;
            }
        }

        if (!$clases) {
            return $html;
        }
        $extra = implode(' ', $clases);

        // Se añaden al `class` que ya tiene la raíz; si no tuviera, se crea.
        $conClase = preg_replace(
            '/^(<[a-z]+[a-z0-9]*\b[^>]*?)\sclass="([^"]*)"/i',
            '$1 class="$2 ' . $extra . '"',
            $html,
            1,
            $hechas
        );
        if ($hechas) {
            return $conClase ?? $html;
        }

        return preg_replace('/^(<[a-z]+[a-z0-9]*)/i', '$1 class="' . $extra . '"', $html, 1) ?? $html;
    }

    /**
     * Añade una declaración al `style` de la etiqueta raíz (lo crea si no lo
     * tiene).
     *
     * Va **delante** de lo que ya hubiera: así una regla propia del bloque
     * —que se emitió después— sigue mandando. El color común es el valor por
     * defecto de la tinta, no una imposición sobre lo que el bloque decida.
     * La declaración llega ya validada (`color()`), nunca del navegador.
     */
    private function addStyle(string $html, string $decl): string
    {
        $conStyle = preg_replace(
            '/^(<[a-z]+[a-z0-9]*\b[^>]*?)\sstyle="([^"]*)"/i',
            '$1 style="' . $decl . ';$2"',
            $html,
            1,
            $hechas
        );
        if ($hechas) {
            return $conStyle ?? $html;
        }

        return preg_replace('/^(<[a-z]+[a-z0-9]*)/i', '$1 style="' . $decl . '"', $html, 1) ?? $html;
    }

    private function paint(string $type, array $p, array $children, string $uid): string
    {
        return match ($type) {
            'section' => $this->section($p, $children),
            'column'  => $this->column($p, $children),
            'spacer'  => $this->spacer($p),
            'divider' => $this->divider($p),
            'heading' => $this->heading($p),
            'text'    => $this->text($p),
            'list'    => $this->list($p),
            'quote'   => $this->quote($p),
            'image'   => $this->image($p),
            'gallery' => $this->gallery($p),
            'video'   => $this->video($p),
            'button'  => $this->button($p),
            'hero'    => $this->hero($p),
            'cards'   => $this->cards($p),
            'form'    => $this->form($p, $uid),
            'html'    => $this->rawHtml($p),
            'buttons'       => $this->buttons($p, $uid),
            'companyContact' => $this->companyContact($p),
            'companyQr'     => $this->companyQr($p),
            'companySocial' => $this->companySocial($p),
            'whatsappFloat' => $this->whatsappFloat($p),
            default   => '',
        };
    }

    // -------------------------------------------------- bloques de empresa
    //
    // Los cuatro leen `companies` en vez de pedir los datos otra vez. Si no hay
    // empresa en el contexto (una plantilla previsualizada sin empresa activa)
    // devuelven un aviso en el editor y **nada** en la página publicada: mejor
    // un hueco que un bloque a medias con el nombre de nadie.

    /** La empresa del contexto, o null. */
    private function company(): ?array
    {
        $c = $this->context['company'] ?? null;

        return is_array($c) && $c ? $c : null;
    }

    /** Aviso en el editor / nada en la página publicada. */
    private function noCompany(string $label): string
    {
        return !empty($this->context['editor'])
            ? '<div class="pg-cempty">' . esc(lang('Pag.noCompanyBlock', [$label])) . '</div>'
            : '';
    }

    /**
     * Iconos SVG en línea. **Única fuente**: la usa el renderizador y también
     * el editor, que los recibe en un bloque JSON para dibujar el lienzo con
     * los mismos que se publican. Antes el editor llevaba su propia copia
     * dibujada a mano —el de WhatsApp era solo el bocadillo, sin el auricular
     * dentro, y las redes eran la inicial en negrita— y lo que se veía al
     * maquetar no era lo que salía publicado.
     */
    public static function iconSet(): array
    {
        static $icons = null;

        if ($icons === null) {
            $icons = require __DIR__ . '/../Views/public/_icons.php';
        }

        return $icons;
    }

    private function icons(): array
    {
        return self::iconSet();
    }

    /**
     * Destino de un enlace de menú. Además de los esquemas seguros admite las
     * **anclas** (`#servicios`), que es a donde apunta la mitad de un menú de
     * una sola página.
     */
    private function menuHref(string $raw): string
    {
        $v = trim($raw);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^#[a-z][a-z0-9-]{0,60}$/', $v)) {
            return $v;
        }

        return $this->url($v);
    }

    /**
     * Botones: uno o varios, en fila o en columna — y, plegado, el menú.
     *
     * Es el elemento de acción de la paleta. Con `direction = column` y el
     * estilo «enlace» es una lista de navegación; con `collapse` se convierte
     * en el menú de una cabecera, que en el teléfono se pliega tras una
     * hamburguesa.
     *
     * **El desplegable no lleva JavaScript.** La página pública no carga ni una
     * línea de guion (§7.3): lo resuelven una casilla oculta y el selector
     * `:checked`, que es todo lo que necesita un panel que se abre y se cierra.
     * Funciona con el teclado y sin red.
     */
    private function buttons(array $p, string $uid = ''): string
    {
        $items = $this->items($p['items'] ?? []);
        $size   = $this->opt('buttons', 'size', $p['size'] ?? null, 'md');
        $radius = $this->opt('buttons', 'radius', $p['radius'] ?? null, 'sm');
        $gap    = $this->opt('buttons', 'gap', $p['gap'] ?? null, 'sm');
        $align  = $this->opt('buttons', 'align', $p['align'] ?? null, 'left');
        $dir    = $this->opt('buttons', 'direction', $p['direction'] ?? null, 'row');
        $apila  = ($p['stack'] ?? '1') !== '0';
        $plega  = ($p['collapse'] ?? '0') === '1';

        $html = '';

        foreach ($items as $it) {
            $label = trim((string) ($it['label'] ?? ''));
            $href  = $this->menuHref((string) ($it['href'] ?? ''));
            if ($label === '' || $href === '') {
                continue;
            }
            $estilo = in_array($it['style'] ?? '', ['primary', 'outline', 'ghost', 'link'], true) ? $it['style'] : 'primary';
            $color  = $this->color($it['color'] ?? '');
            $tinta  = $this->color($it['textColor'] ?? '');
            $css    = [];

            // El estilo «enlace» no tiene fondo: ahí el color es el del texto.
            if ($color !== '') {
                if ($estilo === 'link') {
                    $css['--pg-btn-fg'] = $color;
                } else {
                    $css['--pg-btn-bg'] = $color;
                    // Tinta legible sobre ese fondo… salvo que se pida otra.
                    $css['--pg-btn-fg'] = $this->inkFor($color);
                }
            }
            if ($tinta !== '') {
                $css['--pg-btn-fg'] = $tinta;
            }

            $sobre = $this->color($it['hoverColor'] ?? '');
            if ($sobre !== '') {
                $css['--pg-btn-hover'] = $sobre;
            }

            $vars = $css ? ' style="' . $this->cssVars($css) . '"' : '';
            $target = !empty($it['newTab']) && $it['newTab'] !== '0'
                ? ' target="_blank" rel="noopener noreferrer"' : '';

            $html .= '<a class="pg-btn pg-btn-' . $estilo . ' pg-btn-' . $size . ' pg-btn-r-' . $radius . '"'
                . ' href="' . esc($href) . '"' . $target . $vars . '>' . esc($label) . '</a>';
        }

        if ($html === '') {
            return '';
        }

        $cls = 'pg-btnrow pg-btnrow-' . $dir . ' pg-a-' . $align . ' pg-gap-' . $gap
            . ($apila && $dir === 'row' ? ' pg-btnrow-stack' : '');

        if (!$plega) {
            return '<div class="' . $cls . '">' . $html . '</div>';
        }

        // El id de la casilla tiene que ser único: dos menús plegables en la
        // misma página compartirían el mismo desplegable y se abrirían juntos.
        $toggle = 'pg-nav-' . ($uid !== '' ? $uid : bin2hex(random_bytes(4)));

        return '<div class="pg-btnrow-wrap">'
            . '<input type="checkbox" id="' . $toggle . '" class="pg-nav-check" hidden>'
            . '<label for="' . $toggle . '" class="pg-nav-burger" aria-label="' . esc(lang('Pag.menu'), 'attr') . '">'
            . '<span></span><span></span><span></span></label>'
            . '<nav class="' . $cls . ' pg-btnrow-collapse">' . $html . '</nav>'
            . '</div>';
    }

    /**
     * Dirección, teléfono y correo de la empresa, con el teléfono y el correo
     * pulsables. Los datos salen de `companies`: no se vuelven a escribir.
     */
    private function companyContact(array $p): string
    {
        $c = $this->company();
        if (!$c) {
            return $this->noCompany(lang('Pag.bCompanyContact'));
        }

        $icons = $this->icons();
        $align = $this->opt('companyContact', 'align', $p['align'] ?? null, 'left');
        $filas = '';

        foreach ([
            ['showAddress', 'pin', trim(implode(', ', array_filter([$c['address'] ?? '', $c['city'] ?? '']))), ''],
            ['showPhone', 'phone', (string) ($c['contact_phone'] ?? ''), 'tel:'],
            ['showEmail', 'mail', (string) ($c['contact_email'] ?? ''), 'mailto:'],
        ] as [$flag, $icono, $valor, $esquema]) {
            $valor = trim($valor);
            if (($p[$flag] ?? '1') === '0' || $valor === '') {
                continue;
            }
            $cuerpo = ($icons[$icono] ?? '') . '<span>' . esc($valor) . '</span>';
            $filas .= $esquema !== ''
                ? '<a class="pg-contact-item" href="' . esc($esquema . preg_replace('/\s+/', '', $valor)) . '">' . $cuerpo . '</a>'
                : '<span class="pg-contact-item">' . $cuerpo . '</span>';
        }

        return $filas === '' ? $this->noCompany(lang('Pag.bCompanyContact'))
            : '<div class="pg-contact pg-a-' . $align . '">' . $filas . '</div>';
    }

    /** QR de la propia página, del sitio web, del WhatsApp o de una URL a medida. */
    private function companyQr(array $p): string
    {
        $c      = $this->company();
        $target = $this->opt('companyQr', 'target', $p['target'] ?? null, 'page');

        $data = match ($target) {
            'page'     => (string) ($this->context['pageUrl'] ?? ''),
            'website'  => $c ? $this->url((string) ($c['website'] ?? '')) : '',
            'whatsapp' => $c ? $this->waLink($this->waPhone($c, ''), '') : '',
            'custom'   => $this->url((string) ($p['url'] ?? '')),
            default    => '',
        };

        if ($data === '') {
            return $this->noCompany(lang('Pag.bCompanyQr'));
        }

        $png = (new QrService())->dataUri($data, 5);
        if ($png === null) {
            return $this->noCompany(lang('Pag.bCompanyQr'));
        }

        $size  = $this->opt('companyQr', 'size', $p['size'] ?? null, 'md');
        $frame = $this->opt('companyQr', 'frame', $p['frame'] ?? null, 'card');
        $align = $this->opt('companyQr', 'align', $p['align'] ?? null, 'center');
        $title = trim((string) ($p['title'] ?? ''));
        $cap   = trim((string) ($p['caption'] ?? ''));

        return '<div class="pg-qrwrap pg-a-' . $align . '">'
            . '<figure class="pg-qr pg-qr-' . $size . ' pg-qr-' . $frame . '">'
            . ($title !== '' ? '<figcaption class="pg-qr-title">' . esc($title) . '</figcaption>' : '')
            // El QR ya es un data URI generado aquí: no hay URL de terceros.
            . '<img src="' . $png . '" alt="' . esc(lang('Pag.qrAlt'), 'attr') . '" width="220" height="220">'
            . ($cap !== '' ? '<figcaption class="pg-qr-cap">' . esc($cap) . '</figcaption>' : '')
            . '</figure></div>';
    }

    /** Redes sociales de la empresa, con sus iconos en línea. */
    private function companySocial(array $p): string
    {
        $c = $this->company();
        if (!$c) {
            return $this->noCompany(lang('Pag.bCompanySocial'));
        }

        $icons  = $this->icons();
        $labels = ($p['showLabels'] ?? '0') === '1';
        $links  = '';

        // Con etiqueta el enlace deja de ser un círculo y pasa a ser una píldora:
        // un círculo con texto dentro no cabe.
        $link = static function (string $url, string $icon, string $name) use ($labels): string {
            return '<a class="pg-soc-link' . ($labels ? ' pg-soc-labeled' : '') . '" href="' . esc($url) . '"'
                . ' target="_blank" rel="noopener noreferrer"'
                . ' aria-label="' . esc($name, 'attr') . '" title="' . esc($name, 'attr') . '">'
                . $icon
                . ($labels ? '<span class="pg-soc-name">' . esc($name) . '</span>' : '')
                . '</a>';
        };

        foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'] as $net) {
            $url = $this->url((string) ($c[$net] ?? ''));
            if ($url === '' || empty($icons[$net])) {
                continue;
            }
            $links .= $link($url, $icons[$net], ucfirst($net));
        }

        if (($p['showWebsite'] ?? '1') !== '0') {
            $web = $this->url((string) ($c['website'] ?? ''));
            if ($web !== '') {
                $links .= $link($web, $icons['globe'] ?? '', lang('Pag.website'));
            }
        }

        if ($links === '') {
            return $this->noCompany(lang('Pag.bCompanySocial'));
        }

        $style = $this->opt('companySocial', 'style', $p['style'] ?? null, 'solid');
        $size  = $this->opt('companySocial', 'size', $p['size'] ?? null, 'md');
        $align = $this->opt('companySocial', 'align', $p['align'] ?? null, 'center');
        $title = trim((string) ($p['title'] ?? ''));

        return '<div class="pg-social pg-soc-' . $style . ' pg-soc-' . $size . ' pg-a-' . $align . '">'
            . ($title !== '' ? '<p class="pg-soc-title">' . esc($title) . '</p>' : '')
            . '<div class="pg-soc-row">' . $links . '</div></div>';
    }

    /**
     * Botón flotante de WhatsApp.
     *
     * En el editor se pinta **estático** dentro del lienzo: `position: fixed` se
     * anclaría a la ventana y taparía la paleta o el inspector.
     */
    private function whatsappFloat(array $p): string
    {
        $c     = $this->company();
        $phone = $this->waPhone($c ?? [], (string) ($p['phone'] ?? ''));
        if ($phone === '') {
            return $this->noCompany(lang('Pag.bWhatsapp'));
        }

        $href  = $this->waLink($phone, (string) ($p['message'] ?? ''));
        $pos   = $this->opt('whatsappFloat', 'position', $p['position'] ?? null, 'right');
        $label = trim((string) ($p['label'] ?? ''));
        $icons = $this->icons();
        $inEditor = !empty($this->context['editor']);

        // Sin etiqueta es un **círculo** (el flotante de toda la vida); con
        // etiqueta, una píldora. La forma la decide el contenido y no un campo
        // aparte: una píldora sin texto sería un óvalo raro, y un círculo con
        // texto no cabe.
        // Colores propios. Van como VARIABLES para que la sombra —que se
        // calcula a partir del fondo— y el `:hover` puedan usarlas: una regla
        // CSS no lee un `style` en línea, pero sí una variable declarada en él.
        $fondo = $this->color($p['background'] ?? '');
        $tinta = $this->color($p['textColor'] ?? '');
        // Mapa y no lista: la tinta pedida SUSTITUYE a la calculada en vez de
        // añadirse detrás. Emitir la misma variable dos veces funciona —gana
        // la última— pero deja un atributo contradictorio a la vista.
        $vars = [];
        if ($fondo !== '') {
            $vars['--pg-wa-bg'] = $fondo;
            // Con fondo propio la tinta se decide por luminancia, como en el
            // botón: blanca sobre un verde oscuro no es lo mismo que sobre
            // un amarillo.
            $vars['--pg-wa-fg'] = $this->inkFor($fondo);
        }
        if ($tinta !== '') {
            $vars['--pg-wa-fg'] = $tinta;
        }
        $estilo = $vars ? ' style="' . $this->cssVars($vars) . '"' : '';

        return '<a class="pg-wa pg-wa-' . $pos . ($label === '' ? ' pg-wa-round' : '')
            . ($inEditor ? ' pg-wa-static' : '') . '"' . $estilo
            . ' href="' . esc($href) . '" target="_blank" rel="noopener noreferrer"'
            . ' aria-label="' . esc($label !== '' ? $label : lang('Pag.waAria'), 'attr') . '">'
            . '<span class="pg-wa-icon">' . ($icons['whatsapp'] ?? '') . '</span>'
            . ($label !== '' ? '<span class="pg-wa-label">' . esc($label) . '</span>' : '')
            . '</a>';
    }

    /**
     * Teléfono para wa.me: solo dígitos con indicativo y sin `+`, que es el
     * formato que exige el enlace (y el que ya guarda `public/js/phone-input.js`
     * del núcleo). Prioridad: el del bloque, luego `companies.whatsapp`, luego
     * el teléfono de contacto.
     */
    private function waPhone(array $c, string $override): string
    {
        foreach ([$override, (string) ($c['whatsapp'] ?? ''), (string) ($c['contact_phone'] ?? '')] as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';
            // Menos de 8 dígitos no es un número marcable; más de 15 no es E.164.
            if (strlen($digits) >= 8 && strlen($digits) <= 15) {
                return $digits;
            }
        }

        return '';
    }

    /** Enlace wa.me con el mensaje ya codificado. */
    private function waLink(string $phone, string $message): string
    {
        if ($phone === '') {
            return '';
        }
        $text = trim($message);

        return 'https://wa.me/' . $phone . ($text !== '' ? '?text=' . rawurlencode(mb_substr($text, 0, 500)) : '');
    }

    /**
     * Tinta legible sobre un fondo dado: blanca si el fondo es oscuro, casi
     * negra si es claro. Misma regla que usa la tarjeta digital de Servicios.
     */
    private function inkFor(string $hex): string
    {
        $h = ltrim($hex, '#');
        // 4 dígitos es la forma corta CON alfa: los tres primeros son el color.
        if (strlen($h) === 3 || strlen($h) === 4) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        [$r, $g, $b] = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];

        // Luminancia percibida (ITU-R BT.601): el ojo pesa más el verde.
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#14161a' : '#ffffff';
    }

    /** URL sin esquema ni barra final, para mostrarla (no para enlazarla). */
    private function prettyUrl(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return $host !== '' ? preg_replace('/^www\./', '', $host) . $path : $url;
    }

    // ------------------------------------------------------------ estructura

    /** Escala de espaciado admitida en las clases `pg-mt-*`, `pg-mb-*`, `pg-p-*`. */
    private const SIZES = ['none', 'sm', 'md', 'lg', 'xl'];

    /**
     * Ancho de una columna, en por ciento, o '' si no lo declara.
     *
     * Admite el número a secas («25»), con signo («25 %») y las **seis palabras
     * de antes**, que siguen guardadas en las páginas publicadas: la rejilla de
     * 12 columnas se retiró, pero no los datos que la usaban.
     */
    private function columnWidth($value): string
    {
        $v = strtolower(str_replace(' ', '', trim((string) $value)));

        if ($v === '' || $v === 'auto') {
            return '';
        }

        // Herencia: las fracciones fijas de la rejilla de 12.
        $viejas = [
            'quarter' => '25%', 'third' => '33.33%', 'half' => '50%',
            'twothirds' => '66.66%', 'threequarters' => '75%',
        ];
        if (isset($viejas[$v])) {
            return $viejas[$v];
        }

        if (preg_match('/^(\d{1,3}(?:\.\d{1,2})?)%?$/', $v, $m)) {
            $n = (float) $m[1];

            return $n > 0 && $n <= 100 ? $m[1] . '%' : '';
        }

        return '';
    }

    private function section(array $p, array $children): string
    {
        $cols  = $this->opt('section', 'columns', $p['columns'] ?? null, '2');
        $width = $this->opt('section', 'width', $p['width'] ?? null, 'boxed');
        $pad   = $this->opt('section', 'padding', $p['padding'] ?? null, 'lg');
        $gap   = $this->opt('section', 'gap', $p['gap'] ?? null, 'md');
        $vAl   = $this->opt('section', 'vAlign', $p['vAlign'] ?? null, 'start');
        $bg    = $this->color($p['background'] ?? '');
        $img   = $this->cssUrl((string) ($p['backgroundImage'] ?? ''));
        $over  = $this->opt('section', 'overlay', $p['overlay'] ?? null, 'none');
        $stack = ($p['stackOnMobile'] ?? '1') !== '0';
        // Fija al desplazar. En el editor NO se aplica: anclada a la ventana
        // se montaría sobre la paleta y no habría forma de trabajar debajo.
        $fija  = ($p['sticky'] ?? '0') === '1' && empty($this->context['editor']);

        $styles = [];
        if ($bg !== '') {
            $styles[] = 'background-color:' . $bg;
        }
        if ($img !== '') {
            $styles[] = 'background-image:url(&quot;' . esc($img) . '&quot;)';
        }
        $style = $styles ? ' style="' . implode(';', $styles) . '"' : '';

        /*
         * ¿Alguna columna pide un ancho concreto? Entonces la sección deja el
         * reparto en partes iguales y pasa a una **fila flexible** donde cada
         * columna ocupa su porcentaje.
         *
         * Antes esto era una rejilla de 12 columnas, y ahí estaba el fallo: la
         * rejilla llevaba 11 huecos de separación **además** de las doce
         * pistas, así que en un ancho apretado —el lienzo del editor con los
         * dos paneles abiertos— los huecos se comían el sitio, las pistas se
         * encogían a casi nada y el contenido se salía de su celda
         * amontonándose a la izquierda. Con una fila flexible la columna pide
         * su parte y **cede lo que haga falta** para que quepa la separación.
         */
        $aMedida = false;

        foreach ($children as $child) {
            if (is_array($child) && ($child['type'] ?? '') === 'column'
                && $this->columnWidth($child['props']['width'] ?? '') !== '') {
                $aMedida = true;
                break;
            }
        }

        // Las columnas son los hijos directos; si el árbol viene sin ellas (una
        // sección recién creada), se pinta el contenido suelto igual.
        $inner = '';

        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $inner .= $aMedida && ($child['type'] ?? '') === 'column'
                ? $this->withCommon(
                    $this->column(
                        $child['props'] ?? [],
                        $child['children'] ?? [],
                        $this->columnWidth($child['props']['width'] ?? '')
                    ),
                    $child['props'] ?? [],
                    'column'
                )
                : $this->block($child);
        }

        $grid = $aMedida ? 'pg-cols-free' : 'pg-cols-' . $cols;

        return '<section class="pg-section pg-pad-' . $pad . ' pg-w-' . $width
            . ($fija ? ' pg-sec-sticky' : '')
            . ($img !== '' ? ' pg-sec-img pg-ov-' . $over : '') . '"' . $style . '>'
            . ($img !== '' && $over !== 'none' ? '<span class="pg-sec-scrim" aria-hidden="true"></span>' : '')
            . '<div class="pg-wrap"><div class="pg-cols ' . $grid . ' pg-gap-' . $gap . ' pg-v-' . $vAl
            . ($stack ? '' : ' pg-nostack') . '">'
            . $inner
            . '</div></div></section>';
    }

    /**
     * @param string $ancho porcentaje ya validado, o '' (reparto en partes
     *                      iguales). Va como variable CSS y no como `width` a
     *                      secas para que la hoja decida **cuándo** aplicarlo:
     *                      en el teléfono la columna ocupa la fila entera.
     */
    private function column(array $p, array $children, string $ancho = ''): string
    {
        $align  = $this->opt('column', 'align', $p['align'] ?? null, 'left');
        $radius = $this->opt('column', 'radius', $p['radius'] ?? null, 'none');
        $fondo  = $this->color($p['background'] ?? '');
        // Alineación VERTICAL de lo que hay dentro. `top` es el reparto de
        // siempre y no emite clase: así una página ya hecha no se recompone.
        $vert   = $this->opt('column', 'vAlign', $p['vAlign'] ?? null, 'top');

        $cls = 'pg-col pg-a-' . $align . ($ancho !== '' ? ' pg-cw' : '')
            . ($vert !== 'top' ? ' pg-cv-' . $vert : '')
            // Con fondo propio la columna necesita aire por dentro, o el texto
            // quedaría pegado al borde del color.
            . ($fondo !== '' ? ' pg-col-bg pg-r-' . $radius : '');

        $estilos = [];
        if ($ancho !== '') {
            $estilos[] = '--pg-cw:' . $ancho;
        }
        if ($fondo !== '') {
            $estilos[] = 'background-color:' . $fondo;
        }
        $style = $estilos ? ' style="' . implode(';', $estilos) . '"' : '';

        return '<div class="' . $cls . '"' . $style . '>'
            . $this->render(is_array($children) ? $children : []) . '</div>';
    }

    private function spacer(array $p): string
    {
        $size = $this->opt('spacer', 'size', $p['size'] ?? null, 'md');

        return '<div class="pg-sp pg-sp-' . $size . '" aria-hidden="true"></div>';
    }

    private function divider(array $p): string
    {
        $style = $this->opt('divider', 'style', $p['style'] ?? null, 'solid');
        $color = $this->color($p['color'] ?? '');
        $inline = $color !== '' ? ' style="border-top-color:' . $color . '"' : '';

        return '<hr class="pg-hr pg-hr-' . $style . '"' . $inline . '>';
    }

    // ------------------------------------------------------------- contenido

    private function heading(array $p): string
    {
        $text = trim((string) ($p['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $level = $this->opt('heading', 'level', $p['level'] ?? null, '2');
        $align = $this->opt('heading', 'align', $p['align'] ?? null, 'left');
        $color = $this->color($p['color'] ?? '');
        $style = $color !== '' ? ' style="color:' . $color . '"' : '';
        $tag   = 'h' . $level;

        return '<' . $tag . ' class="pg-h pg-h' . $level . ' pg-a-' . $align . '"' . $style . '>'
            . esc($text) . '</' . $tag . '>';
    }

    private function text(array $p): string
    {
        // Ya saneado al guardar; aquí sale tal cual (ver regla 1 de la cabecera).
        $html = (string) ($p['html'] ?? '');
        if (trim($html) === '') {
            return '';
        }
        $align = $this->opt('text', 'align', $p['align'] ?? null, 'left');
        $size  = $this->opt('text', 'size', $p['size'] ?? null, 'md');
        $color = $this->color($p['color'] ?? '');
        $drop  = ($p['dropCap'] ?? '0') === '1';
        $style = $color !== '' ? ' style="color:' . $color . '"' : '';

        return '<div class="pg-text pg-a-' . $align . ' pg-t-' . $size
            . ($drop ? ' pg-dropcap' : '') . '"' . $style . '>' . $html . '</div>';
    }

    private function list(array $p): string
    {
        $items = $this->items($p['items'] ?? []);
        if (!$items) {
            return '';
        }
        $style = $this->opt('list', 'style', $p['style'] ?? null, 'check');
        $tag   = $style === 'number' ? 'ol' : 'ul';

        $li = '';

        foreach ($items as $it) {
            $text = trim((string) ($it['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            // Un elemento puede ser un enlace; se admiten anclas de la propia
            // página, que es lo que se usa en un índice.
            $href = $this->menuHref((string) ($it['href'] ?? ''));
            $li .= '<li>' . ($href !== ''
                ? '<a href="' . esc($href) . '">' . esc($text) . '</a>'
                : esc($text)) . '</li>';
        }

        return $li === '' ? '' : '<' . $tag . ' class="pg-list pg-list-' . $style . '">' . $li . '</' . $tag . '>';
    }

    private function quote(array $p): string
    {
        $text = trim((string) ($p['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $align  = $this->opt('quote', 'align', $p['align'] ?? null, 'left');
        $author = trim((string) ($p['author'] ?? ''));
        $role   = trim((string) ($p['role'] ?? ''));

        $cite = '';
        if ($author !== '' || $role !== '') {
            $cite = '<cite class="pg-cite">' . esc($author)
                . ($role !== '' ? '<span class="pg-cite-role">' . esc($role) . '</span>' : '')
                . '</cite>';
        }

        return '<blockquote class="pg-quote pg-a-' . $align . '"><p>' . esc($text) . '</p>' . $cite . '</blockquote>';
    }

    private function image(array $p): string
    {
        // La imagen puede salir de la ficha de la empresa: es lo que permite
        // montar una cabecera con elementos sueltos sin volver a subir el logo
        // en cada página. Sin empresa en el contexto se cae a la subida, que en
        // una plantilla previsualizada es lo único que hay.
        $fuente = $this->opt('image', 'source', $p['source'] ?? null, 'upload');
        $c      = $this->company();
        $src    = match ($fuente) {
            'logo'   => $c ? $this->url((string) ($c['logo'] ?? '')) : '',
            'banner' => $c ? $this->url((string) ($c['banner'] ?? '')) : '',
            default  => $this->url((string) ($p['src'] ?? '')),
        };

        if ($src === '' && $fuente !== 'upload') {
            return $this->noCompany(lang('Pag.bImage'));
        }
        if ($src === '') {
            return '';
        }
        $size   = $this->opt('image', 'size', $p['size'] ?? null, 'full');
        $align  = $this->opt('image', 'align', $p['align'] ?? null, 'center');
        $radius = $this->opt('image', 'radius', $p['radius'] ?? null, 'sm');
        $ratio  = $this->opt('image', 'ratio', $p['ratio'] ?? null, 'auto');
        $alt    = (string) ($p['alt'] ?? '');
        $cap    = trim((string) ($p['caption'] ?? ''));

        $img = '<img src="' . esc($src) . '" alt="' . esc($alt, 'attr') . '" loading="lazy" class="pg-r-' . $radius
            . ($ratio !== 'auto' ? ' pg-ar pg-ar-' . $ratio : '') . '">';

        // `menuHref` y no `url`: una imagen puede enlazar a un ancla de la
        // propia página —el logo de la cabecera a «#inicio» es el caso— y
        // `url()` la convertía en una dirección absoluta. Y una ancla NO se
        // abre en otra pestaña: llevaría a una copia de la misma página.
        $href = $this->menuHref((string) ($p['href'] ?? ''));
        if ($href !== '') {
            $fuera = !str_starts_with($href, '#') ? ' target="_blank" rel="noopener noreferrer"' : '';
            $img = '<a href="' . esc($href) . '"' . $fuera . '>' . $img . '</a>';
        }

        // Medida exacta: manda sobre el tamaño en escalones. Solo se emite si
        // pasa la validación (número + px o %), nunca lo que llegue escrito.
        $ancho = $this->cssLength((string) ($p['width'] ?? ''));
        $style = $ancho !== '' ? ' style="width:' . $ancho . ';max-width:100%"' : '';

        return '<figure class="pg-fig pg-a-' . $align . ' pg-img-' . $size . '"' . $style . '>' . $img
            . ($cap !== '' ? '<figcaption>' . esc($cap) . '</figcaption>' : '')
            . '</figure>';
    }

    private function gallery(array $p): string
    {
        $items = $this->items($p['items'] ?? []);
        if (!$items) {
            return '';
        }
        $cols   = $this->opt('gallery', 'columns', $p['columns'] ?? null, '3');
        $radius = $this->opt('gallery', 'radius', $p['radius'] ?? null, 'sm');
        $ratio  = $this->opt('gallery', 'ratio', $p['ratio'] ?? null, '4-3');

        $cells = '';

        foreach ($items as $it) {
            $src = $this->url((string) ($it['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $cap = trim((string) ($it['caption'] ?? ''));
            $cells .= '<figure class="pg-gitem">'
                . '<img src="' . esc($src) . '" alt="' . esc((string) ($it['alt'] ?? ''), 'attr') . '" loading="lazy" class="pg-r-' . $radius
                . ($ratio !== 'auto' ? ' pg-ar pg-ar-' . $ratio : '') . '">'
                . ($cap !== '' ? '<figcaption>' . esc($cap) . '</figcaption>' : '')
                . '</figure>';
        }

        return $cells === '' ? '' : '<div class="pg-gallery pg-cols-' . $cols . '">' . $cells . '</div>';
    }

    private function video(array $p): string
    {
        $embed = $this->embedUrl((string) ($p['url'] ?? ''));
        if ($embed === '') {
            return '';
        }
        $ratio = $this->opt('video', 'ratio', $p['ratio'] ?? null, '16-9');
        $cap   = trim((string) ($p['caption'] ?? ''));

        return '<figure class="pg-video pg-ratio-' . $ratio . '">'
            . '<iframe src="' . esc($embed) . '" title="' . esc(lang('Pag.videoFrame'), 'attr') . '"'
            . ' loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"'
            . ' allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>'
            . ($cap !== '' ? '<figcaption>' . esc($cap) . '</figcaption>' : '')
            . '</figure>';
    }

    // ---------------------------------------------------------------- acción

    private function button(array $p): string
    {
        $label = trim((string) ($p['label'] ?? ''));
        $href  = $this->url((string) ($p['href'] ?? ''));
        if ($label === '' || $href === '') {
            return '';
        }
        $style  = $this->opt('button', 'style', $p['style'] ?? null, 'primary');
        $size   = $this->opt('button', 'size', $p['size'] ?? null, 'md');
        $align  = $this->opt('button', 'align', $p['align'] ?? null, 'left');
        $newTab = !empty($p['newTab']) && $p['newTab'] !== '0';
        $target = $newTab ? ' target="_blank" rel="noopener noreferrer"' : '';
        $radius = $this->opt('button', 'radius', $p['radius'] ?? null, 'sm');
        $full   = ($p['fullWidth'] ?? '0') === '1';
        $color  = $this->color($p['color'] ?? '');
        $hover  = $this->color($p['hoverColor'] ?? '');

        // Los colores se emiten como VARIABLES, no como propiedades: la hoja
        // decide qué hace cada estilo con ellas (fondo en el sólido, borde y
        // tinta en los otros) y —lo que antes era imposible— el `:hover` puede
        // usarlas, porque una regla no lee un `style` en línea pero sí una
        // variable declarada en él.
        $vars = [];
        if ($color !== '') {
            $vars[] = '--pg-btn-bg:' . $color;
            // Con fondo propio, la tinta se decide por luminancia: un botón
            // amarillo con letra blanca no se lee.
            $vars[] = '--pg-btn-fg:' . $this->inkFor($color);
        }
        // …salvo que se pida una tinta concreta, que manda sobre la
        // calculada: el automatismo solo sabe elegir entre blanco y casi negro.
        $tinta = $this->color($p['textColor'] ?? '');
        if ($tinta !== '') {
            $vars[] = '--pg-btn-fg:' . $tinta;
        }
        if ($hover !== '') {
            $vars[] = '--pg-btn-hover:' . $hover;
        }
        $inline = $vars ? ' style="' . implode(';', $vars) . '"' : '';

        return '<div class="pg-btnwrap pg-a-' . $align . ($full ? ' pg-btn-block' : '') . '">'
            . '<a class="pg-btn pg-btn-' . $style . ' pg-btn-' . $size . ' pg-btn-r-' . $radius . '"'
            . ' href="' . esc($href) . '"' . $target . $inline . '>'
            . esc($label) . '</a></div>';
    }

    private function hero(array $p): string
    {
        $title = trim((string) ($p['title'] ?? ''));
        $sub   = trim((string) ($p['subtitle'] ?? ''));
        $img   = $this->url((string) ($p['image'] ?? ''));
        if ($title === '' && $sub === '' && $img === '') {
            return '';
        }
        $overlay = $this->opt('hero', 'overlay', $p['overlay'] ?? null, 'dark');
        $height  = $this->opt('hero', 'height', $p['height'] ?? null, 'md');
        $align   = $this->opt('hero', 'align', $p['align'] ?? null, 'center');

        // Ojo: aquí la URL acaba dentro de un `url()` de CSS, que a su vez vive
        // en un atributo HTML. El escapado HTML NO basta: `&quot;` se decodifica
        // a `"` antes de que el motor de CSS lea la regla, así que una URL con
        // comillas cerraría el `url()` e inyectaría CSS. Por eso `cssUrl()`
        // rechaza (no escapa) todo lo que pueda romper la regla.
        $css   = $this->cssUrl($img);
        $style = $css !== '' ? ' style="background-image:url(&quot;' . esc($css) . '&quot;)"' : '';

        $btn = '';
        $bl  = trim((string) ($p['buttonLabel'] ?? ''));
        $bh  = $this->url((string) ($p['buttonHref'] ?? ''));
        if ($bl !== '' && $bh !== '') {
            $btn = '<a class="pg-btn pg-btn-primary pg-btn-lg" href="' . esc($bh) . '">' . esc($bl) . '</a>';
        }

        return '<section class="pg-hero pg-hero-' . $height . ' pg-ov-' . $overlay . ' pg-a-' . $align . '"' . $style . '>'
            . '<div class="pg-hero-in">'
            . ($title !== '' ? '<h1 class="pg-hero-title">' . esc($title) . '</h1>' : '')
            . ($sub !== '' ? '<p class="pg-hero-sub">' . nl2br(esc($sub)) . '</p>' : '')
            . $btn
            . '</div></section>';
    }

    private function cards(array $p): string
    {
        $items = $this->items($p['items'] ?? []);
        if (!$items) {
            return '';
        }
        $cols  = $this->opt('cards', 'columns', $p['columns'] ?? null, '3');
        $cells = '';

        foreach ($items as $it) {
            $title = trim((string) ($it['title'] ?? ''));
            $text  = trim((string) ($it['text'] ?? ''));
            $img   = $this->url((string) ($it['image'] ?? ''));
            if ($title === '' && $text === '' && $img === '') {
                continue;
            }
            $href  = $this->url((string) ($it['href'] ?? ''));
            $lbl   = trim((string) ($it['linkLabel'] ?? ''));

            $cells .= '<article class="pg-card">'
                . ($img !== '' ? '<img class="pg-card-img" src="' . esc($img) . '" alt="' . esc($title, 'attr') . '" loading="lazy">' : '')
                . '<div class="pg-card-body">'
                . ($title !== '' ? '<h3 class="pg-card-title">' . esc($title) . '</h3>' : '')
                . ($text !== '' ? '<p class="pg-card-text">' . nl2br(esc($text)) . '</p>' : '')
                . ($href !== '' && $lbl !== '' ? '<a class="pg-card-link" href="' . esc($href) . '">' . esc($lbl) . '</a>' : '')
                . '</div></article>';
        }

        return $cells === '' ? '' : '<div class="pg-cards pg-cols-' . $cols . '">' . $cells . '</div>';
    }

    /**
     * Formulario de contacto. Envía a la propia página (`formAction`), lleva el
     * token CSRF del núcleo y el id del bloque, para saber de qué formulario
     * vino el mensaje cuando la página tiene más de uno.
     */
    private function form(array $p, string $uid): string
    {
        $fields = $this->items($p['fields'] ?? []);
        $title  = trim((string) ($p['title'] ?? ''));
        $intro  = trim((string) ($p['intro'] ?? ''));

        // Sin campos no hay formulario que enviar; se muestra el encabezado para
        // que en el editor se vea que el bloque está ahí.
        $head = ($title !== '' ? '<h3 class="pg-form-title">' . esc($title) . '</h3>' : '')
            . ($intro !== '' ? '<p class="pg-form-intro">' . nl2br(esc($intro)) . '</p>' : '');

        if (!$fields) {
            return '<div class="pg-form">' . $head
                . '<p class="pg-form-empty">' . esc(lang('Pag.formNoFields')) . '</p></div>';
        }

        // Ya se envió este formulario: se muestra el acuse en vez del formulario.
        if (($this->context['sentBlock'] ?? '') === $uid && $uid !== '') {
            $msg = trim((string) ($p['successMessage'] ?? '')) ?: lang('Pag.formSent');

            return '<div class="pg-form pg-form-ok">' . $head
                . '<p class="pg-form-success">' . nl2br(esc($msg)) . '</p></div>';
        }

        $action = (string) ($this->context['formAction'] ?? '');
        $rows   = '';

        foreach ($fields as $i => $f) {
            $label = trim((string) ($f['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = in_array($f['type'] ?? '', ['text', 'email', 'tel', 'textarea', 'select'], true)
                ? $f['type'] : 'text';
            $req  = !empty($f['required']) && $f['required'] !== '0';
            // El nombre del campo es su posición, no la etiqueta: así el POST no
            // depende de textos que el usuario puede cambiar (ni de sus acentos).
            $name = 'f' . $i;
            $id   = 'pgf-' . esc($uid, 'attr') . '-' . $i;

            $input = match ($type) {
                'textarea' => '<textarea id="' . $id . '" name="' . $name . '" rows="4"' . ($req ? ' required' : '') . '></textarea>',
                'select'   => '<select id="' . $id . '" name="' . $name . '"' . ($req ? ' required' : '') . '>'
                    . '<option value="">' . esc(lang('Pag.formChoose')) . '</option>'
                    . $this->selectOptions((string) ($f['options'] ?? ''))
                    . '</select>',
                default    => '<input id="' . $id . '" type="' . $type . '" name="' . $name . '"' . ($req ? ' required' : '') . '>',
            };

            $rows .= '<div class="pg-field">'
                . '<label for="' . $id . '">' . esc($label) . ($req ? ' <span class="pg-req">*</span>' : '') . '</label>'
                . $input . '</div>';
        }

        $submit = trim((string) ($p['submitLabel'] ?? '')) ?: lang('Pag.formSubmit');
        $error  = ($this->context['formErrorBlock'] ?? '') === $uid && $uid !== ''
            ? '<p class="pg-form-error">' . esc((string) ($this->context['formError'] ?? lang('Pag.formError'))) . '</p>'
            : '';

        // En el editor el formulario se pinta pero no se puede enviar: no hay a
        // dónde, y un submit accidental sacaría al usuario del editor.
        if (!empty($this->context['editor']) || $action === '') {
            return '<div class="pg-form">' . $head . '<div class="pg-form-body">' . $rows
                . '<button type="button" class="pg-btn pg-btn-primary pg-btn-md" disabled>' . esc($submit) . '</button>'
                . '</div></div>';
        }

        return '<form class="pg-form" method="post" action="' . esc($action) . '">'
            . ($this->context['csrf'] ?? '')
            . '<input type="hidden" name="block" value="' . esc($uid, 'attr') . '">'
            . $head . $error
            . '<div class="pg-form-body">' . $rows
            . '<button type="submit" class="pg-btn pg-btn-primary pg-btn-md">' . esc($submit) . '</button>'
            . '</div></form>';
    }

    private function rawHtml(array $p): string
    {
        // Saneado al guardar (ver regla 1). Si quedó vacío, no se pinta nada.
        $code = trim((string) ($p['code'] ?? ''));

        return $code === '' ? '' : '<div class="pg-raw">' . $code . '</div>';
    }

    // -------------------------------------------------------------- utilidad

    /** Opciones de un `<select>` del formulario: «Uno, Dos, Tres». */
    private function selectOptions(string $raw): string
    {
        $out = '';

        foreach (array_filter(array_map('trim', explode(',', $raw)), 'strlen') as $opt) {
            $out .= '<option value="' . esc($opt, 'attr') . '">' . esc($opt) . '</option>';
        }

        return $out;
    }

    /**
     * Valor de un `select` validado contra `BlockRegistry`. Lo que no esté
     * declarado cae al valor por defecto: el dato nunca elige la clase CSS.
     */
    private function opt(string $type, string $name, $value, string $fallback): string
    {
        $allowed = BlockRegistry::optionValues($type, $name);
        $value   = (string) ($value ?? '');

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * Color hexadecimal validado, **con transparencia opcional**.
     *
     * Se admiten 3, 4, 6 y 8 dígitos: los de 4 y 8 llevan el canal alfa al final
     * (`#1a2b3c80` es ese color al 50 %). Es CSS estándar desde hace años y
     * evita tener que guardar la opacidad en un campo aparte y recomponer un
     * `rgba()` a mano.
     *
     * Se devuelve **crudo** a propósito: `esc($hex,'css')` produce `\23 …` y
     * `esc($hex,'attr')` `&#x23;…`, y ninguno de los dos es un color válido
     * (contrato §7.3).
     */
    private function color($value): string
    {
        $v = trim((string) $value);

        return preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v) ? $v : '';
    }

    /** Pares ya validados → texto de un atributo `style`. */
    private function cssVars(array $pares): string
    {
        $out = [];

        foreach ($pares as $k => $v) {
            $out[] = $k . ':' . $v;
        }

        return implode(';', $out);
    }

    /**
     * Una medida CSS (`320px`, `60 %`), o '' si no lo es.
     *
     * Va a parar dentro de un `style="…"`, así que se **valida y se recompone**
     * en vez de escapar lo que llegue: solo salen dígitos y la unidad, nunca el
     * texto original. Se topa en 4000, que ya es más ancho que cualquier
     * pantalla, y en 100 % por evidente.
     */
    private function cssLength(string $value): string
    {
        $v = strtolower(str_replace(' ', '', trim($value)));

        if (preg_match('/^(\d{1,4}(?:\.\d{1,2})?)(px|%|rem|em)$/', $v, $m)) {
            $n = (float) $m[1];
            $max = $m[2] === '%' ? 100 : ($m[2] === 'px' ? 4000 : 200);

            // Los ceros solo sobran DETRÁS DE LA COMA: recortarlos siempre
            // convertía «150px» en «15px».
            $num = str_contains($m[1], '.') ? rtrim(rtrim($m[1], '0'), '.') : $m[1];

            return $n > 0 && $n <= $max ? $num . $m[2] : '';
        }

        // Un número pelado se entiende en px: es lo que se escribe sin pensar.
        return preg_match('/^\d{1,4}$/', $v) && (int) $v > 0 ? $v . 'px' : '';
    }

    /**
     * URL apta para meterla en un `url()` de CSS, o '' si no lo es.
     *
     * Se **rechaza**, no se escapa: dentro de `style="…url(…)…"` hay dos capas
     * de decodificación (HTML y luego CSS) y no existe un escapado que sirva
     * para las dos a la vez. Lo único seguro es exigir que la URL no contenga
     * nada capaz de cerrar el paréntesis o la comilla.
     */
    private function cssUrl(string $value): string
    {
        $v = $this->url($value);

        return $v !== '' && !preg_match('/["\'()\\\\\s<>]/u', $v) ? $v : '';
    }

    /** URL segura y ya resuelta (las relativas de `/uploads/` pasan por media_url). */
    private function url(string $value): string
    {
        $v = trim($value);
        if ($v === '' || !$this->san->safeUrl($v)) {
            return '';
        }

        return function_exists('media_url') ? media_url($v) : $v;
    }

    /** Normaliza un repetidor: siempre una lista de arrays. */
    private function items($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, 'is_array'));
    }

    /** Id del bloque dentro del árbol (lo usa el formulario para identificarse). */
    private function uid(array $b): string
    {
        $id = (string) ($b['id'] ?? '');

        return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) ? $id : '';
    }

    /**
     * URL de reproducción a partir de la que pegó el usuario.
     *
     * Solo YouTube y Vimeo, y **solo el identificador**: nunca se reenvía la URL
     * que llegó. Así una dirección manipulada no puede acabar en el `src` de un
     * iframe.
     */
    private function embedUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~i', $raw, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d{6,12})~i', $raw, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return '';
    }
}
