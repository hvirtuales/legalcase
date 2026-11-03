/**
 * Editor de páginas por bloques — arrastrar, soltar, reordenar y editar.
 *
 * Sin dependencias ni CDN (política del núcleo): API de arrastre nativa de HTML5
 * y `document.execCommand` para el texto enriquecido, que es lo que ya usa el
 * resto del proyecto.
 *
 * ---------------------------------------------------------------------------
 * QUÉ ES ESTE ARCHIVO Y QUÉ NO
 *
 * El lienzo dibuja una **aproximación** de la página para poder trabajar sobre
 * ella. La salida de verdad la produce `Libraries/Renderer.php` en el servidor;
 * por eso «Vista previa» abre la página renderizada allí. Se comparte la hoja de
 * estilos (`Views/public/_styles.php`), así que lo que cambia entre las dos es
 * como mucho algún detalle de marcado, nunca el aspecto.
 *
 * Esa asimetría es deliberada: duplicar el renderizador entero en JS sería
 * duplicar también las reglas de escapado y validación, que es justo donde no
 * puede haber dos versiones. Aquí no se valida nada de cara a la seguridad —el
 * servidor **reconstruye** el árbol contra `BlockRegistry` al guardarlo
 * (`PageService::sanitizeTree`)—; lo que se manda es una propuesta, no un hecho.
 * ---------------------------------------------------------------------------
 */
(function () {
    'use strict';

    var root = document.getElementById('pagEditor');
    if (!root) { return; }

    // ---------------------------------------------------------------- estado
    var REG    = readJson('pagRegistry', {});
    var I18N   = readJson('pagI18n', {});
    var SAVED  = readJson('pagSaved', []);
    var SECS   = readJson('pagSections', []);  // secciones prearmadas (código)
    var CO     = readJson('pagCompany', {});   // ficha de la empresa, recortada
    // Iconos SVG en línea: los MISMOS que publica el servidor.
    var ICONS  = readJson('pagIcons', {});
    var tree   = readJson('pagTree', []);
    var selectedId = null;
    var dirty  = false;
    var saving = false;

    var canvas    = document.getElementById('pagCanvas');
    var inspector = document.getElementById('pagInspector');
    var statusEl  = document.getElementById('pagStatus');
    var saveBtn   = document.getElementById('pagSave');

    /**
     * Guarda los ajustes de la página (título, dirección, SEO, acento y los
     * dos anchos). La define `pageSettings()` más abajo; aquí queda el hueco
     * para que `save()` —el botón de la barra— pueda llamarla ANTES de
     * mandar los bloques. Sin esto había dos botones de guardar y el título
     * no se guardaba con el de arriba.
     */
    var guardaAjustes = function () { return Promise.resolve(); };
    var treeView  = document.getElementById('pagTreeView');
    var outline   = document.getElementById('pagOutline');

    // Bloques contenedores plegados en la vista en cascada. Es estado de la
    // interfaz, no del documento: no se guarda.
    var collapsed = {};

    // Paneles del inspector («Espaciado», «Avanzado») que quedan desplegados.
    // El inspector se repinta entero con cada cambio, así que sin esto se
    // cerraban solos en cuanto se tocaba uno de sus campos.
    var panelAbierto = {};

    var cfg = {
        saveUrl:      root.dataset.saveUrl,
        uploadUrl:    root.dataset.uploadUrl,
        blockUrl:     root.dataset.blockUrl,
        // De aquí se pide un token nuevo cuando el que hay deja de valer.
        tokenUrl:     root.dataset.tokenUrl,
        libraryUrl:   root.dataset.libraryUrl,
        csrfName:     root.dataset.csrfName,
        csrfHash:     root.dataset.csrfHash,
        canSaveBlock: root.dataset.canSaveBlock === '1'
    };

    // Lo que se está arrastrando ahora mismo. El `dataTransfer` no se puede leer
    // durante `dragover` en todos los navegadores, así que se guarda aquí.
    var drag = null;

    function readJson(id, fallback) {
        var el = document.getElementById(id);
        if (!el) { return fallback; }
        try { return JSON.parse(el.textContent) || fallback; } catch (e) { return fallback; }
    }

    function t(key) { return I18N[key] || key; }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function uid() {
        return 'b' + Math.random().toString(36).slice(2, 12);
    }

    // ------------------------------------------------------- árbol: utilidades

    /** Localiza un bloque: devuelve {block, list, index} o null. */
    function locate(id, list) {
        list = list || tree;
        for (var i = 0; i < list.length; i++) {
            if (list[i].id === id) { return { block: list[i], list: list, index: i }; }
            if (list[i].children) {
                var found = locate(id, list[i].children);
                if (found) { return found; }
            }
        }
        return null;
    }

    /** ¿`ancestorId` contiene a `id`? Evita soltar una sección dentro de sí misma. */
    function contains(ancestorId, id) {
        var node = locate(ancestorId);
        if (!node || !node.block.children) { return false; }
        return !!locate(id, node.block.children);
    }

    function defaults(type) {
        var def = REG[type];
        return def ? JSON.parse(JSON.stringify(def.defaults || {})) : {};
    }

    /** Bloque nuevo del tipo pedido, con sus columnas si es una sección. */
    function makeBlock(type) {
        var def = REG[type];
        if (!def) { return null; }
        var block = { id: uid(), type: type, props: defaults(type) };
        if (def.container) {
            block.children = [];
            if (type === 'section') {
                var cols = Math.max(1, Math.min(4, parseInt(block.props.columns, 10) || 1));
                for (var i = 0; i < cols; i++) { block.children.push(makeBlock('column')); }
            }
        }
        return block;
    }

    /** Copia profunda con identificadores nuevos (duplicar, insertar guardado). */
    function cloneBlock(block) {
        var copy = JSON.parse(JSON.stringify(block));
        (function reindex(node) {
            node.id = uid();
            (node.children || []).forEach(reindex);
        })(copy);
        return copy;
    }

    /**
     * Ajusta las columnas de una sección cuando cambia su número.
     * Al reducir, el contenido de las columnas sobrantes **no se tira**: se pasa
     * a la última que queda. Perder bloques por cambiar un desplegable sería el
     * peor accidente posible del editor.
     */
    function syncColumns(block) {
        var want = Math.max(1, Math.min(4, parseInt(block.props.columns, 10) || 1));
        block.children = block.children || [];
        while (block.children.length < want) { block.children.push(makeBlock('column')); }
        if (block.children.length > want) {
            var extra = block.children.splice(want);
            var last  = block.children[want - 1];
            extra.forEach(function (col) {
                last.children = (last.children || []).concat(col.children || []);
            });
        }
    }

    // --------------------------------------------------------- pintado: lienzo

    function render() {
        canvas.innerHTML = '';
        if (!tree.length) {
            canvas.appendChild(el('div', 'pag-empty',
                '<strong>' + esc(t('empty')) + '</strong><span>' + esc(t('emptyHint')) + '</span>'));
            // Aun vacío tiene que aceptar que se suelte algo.
            canvas.appendChild(dropZone(tree, 0));
            return;
        }
        renderList(tree, canvas);
    }

    /** Pinta una lista de bloques intercalando las zonas de soltado. */
    function renderList(list, host) {
        host.appendChild(dropZone(list, 0));
        list.forEach(function (block, i) {
            host.appendChild(renderNode(block));
            host.appendChild(dropZone(list, i + 1));
        });
    }

    function dropZone(list, index) {
        var zone = el('div', 'pag-drop');
        zone.addEventListener('dragover', function (e) {
            if (!drag) { return; }
            e.preventDefault();
            zone.classList.add('is-over');
        });
        zone.addEventListener('dragleave', function () { zone.classList.remove('is-over'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('is-over');
            handleDrop(list, index);
        });
        return zone;
    }

    /** Envuelve un bloque con su etiqueta, sus herramientas y su contenido. */
    /** @param {?number} span reparto de la columna sobre la rejilla de 12 */
    function renderNode(block, span) {
        var def      = REG[block.type] || {};
        var isColumn = block.type === 'column';
        var node     = el('div', 'pag-node');
        node.dataset.id   = block.id;
        node.dataset.type = block.type;
        if (block.id === selectedId) { node.classList.add('is-selected'); }

        // Etiqueta: además de nombrar el bloque, es el asa para arrastrarlo.
        var tag = el('span', 'pag-tag', esc(isColumn ? t('column') : (def.label || block.type)));

        // Una columna NO se arrastra ni se borra suelta: existe porque su
        // sección dice cuántas hay, y sacarla dejaría un `column` en la raíz del
        // árbol —válido para el saneador, pero un sinsentido al pintarlo—. Se
        // gobierna desde el desplegable «Columnas» de la sección.
        if (!isColumn) {
            tag.draggable = true;
            tag.addEventListener('dragstart', function (e) {
                drag = { move: block.id };
                node.classList.add('is-dragging');
                try { e.dataTransfer.setData('text/plain', block.id); } catch (err) { /* Safari */ }
                e.dataTransfer.effectAllowed = 'move';
                e.stopPropagation();
            });
            tag.addEventListener('dragend', function () {
                drag = null;
                node.classList.remove('is-dragging');
                clearOver();
            });
        }
        node.appendChild(tag);

        if (!isColumn) {
            node.appendChild(toolbar(block));
        }

        node.appendChild(body(block, span));

        node.addEventListener('click', function (e) {
            e.stopPropagation();
            select(block.id);
        });

        return node;
    }

    function toolbar(block) {
        var bar = el('div', 'pag-tools');
        bar.appendChild(tool('icon-arrow-up-circle', t('moveUp'), function () { move(block.id, -1); }));
        bar.appendChild(tool('icon-arrow-down-circle', t('moveDown'), function () { move(block.id, 1); }));
        bar.appendChild(tool('icon-clipboard', t('duplicate'), function () { duplicate(block.id); }));
        if (cfg.canSaveBlock) {
            bar.appendChild(tool('icon-save', t('saveAsBlock'), function () { saveAsBlock(block); }));
        }
        var del = tool('icon-trash', t('remove'), function () {
            if (window.confirm(t('confirmRemove'))) { remove(block.id); }
        });
        del.classList.add('is-danger');
        bar.appendChild(del);
        return bar;
    }

    function tool(icon, title, onClick) {
        var b = el('button', 'pag-tool', '<i class="icon ' + icon + '"></i>');
        b.type  = 'button';
        b.title = title;
        b.addEventListener('click', function (e) { e.stopPropagation(); onClick(); });
        return b;
    }

    /** El contenido visible del bloque: contenedores se recorren, hojas se pintan. */
    function body(block, span) {
        if (block.type === 'section') { return sectionBody(block); }
        if (block.type === 'column')  { return columnBody(block, span); }

        var wrap = el('div', 'pag-body');
        var html = withSpacing(preview(block), block.props || {});
        if (html) {
            wrap.innerHTML = html;
        } else {
            var label = (REG[block.type] || {}).label || block.type;
            wrap.appendChild(el('div', 'pag-ghost', esc(label) + ' — ' + esc(t('emptyBlock'))));
        }
        return wrap;
    }

    /** Reparto de una columna sobre la rejilla de 12 (igual que el servidor). */
    /**
     * Ancho de una columna en por ciento, o ''. La MISMA regla que el
     * servidor (`Renderer::columnWidth()`), incluidas las seis palabras de la
     * antigua rejilla de 12, que siguen guardadas en páginas publicadas.
     */
    var COLW = { quarter: '25%', third: '33.33%', half: '50%', twothirds: '66.66%', threequarters: '75%' };
    function colWidth(v) {
        var s = String(v || '').toLowerCase().replace(/ /g, '');
        if (!s || s === 'auto') { return ''; }
        if (COLW[s]) { return COLW[s]; }
        var m = /^(\d{1,3}(?:\.\d{1,2})?)%?$/.exec(s);
        if (!m) { return ''; }
        var n = parseFloat(m[1]);
        return n > 0 && n <= 100 ? m[1] + '%' : '';
    }

    function sectionBody(block) {
        var p    = block.props || {};
        var hijos = block.children || [];

        // ¿Alguna columna pide ancho propio? Entonces rejilla de 12, como en
        // `Renderer::section()`. Sin esto, un reparto 2/3 + 1/3 se veía a
        // partes iguales mientras se maquetaba y cambiaba al publicar.
        var aMedida = hijos.some(function (c) {
            return c.type === 'column' && colWidth((c.props || {}).width);
        });

        var conImagen = !!p.backgroundImage;
        // La sección y la columna NO pasan por `withSpacing()` —se dibujan como
        // contenedores de verdad, con sus hijos dentro—, así que su espaciado
        // hay que ponerlo aquí: sin esto, cambiarlo en el inspector no se veía
        // hasta publicar. `padding` se omite porque la sección ya lo pinta como
        // el aire de su banda (`pg-pad-*`).
        var espacio = spacingClasses(p, 'padding');
        var section = el('section', 'pg-section pg-pad-' + (p.padding || 'lg') + ' pg-w-' + (p.width || 'boxed')
            + (conImagen ? ' pg-sec-img pg-ov-' + (p.overlay || 'none') : '')
            + (espacio ? ' ' + espacio : ''));

        if (hex(p.background)) { section.style.backgroundColor = p.background; }
        // La tinta cascada a los hijos, igual que en el servidor. La sección y
        // la columna NO pasan por `withSpacing()` —se dibujan como contenedores
        // de verdad, con sus hijos dentro—, así que hay que ponerla aquí: es
        // justo donde más se usa (aclarar una banda oscura entera).
        if (hex(p.textColor)) { section.style.color = p.textColor; }
        if (conImagen) { section.style.backgroundImage = 'url("' + p.backgroundImage + '")'; }
        if (conImagen && (p.overlay || 'none') !== 'none') {
            section.appendChild(el('span', 'pg-sec-scrim'));
        }

        var wrap = el('div', 'pg-wrap');
        var cols = el('div', 'pg-cols ' + (aMedida ? 'pg-cols-free' : 'pg-cols-' + (p.columns || '1'))
            + ' pg-gap-' + (p.gap || 'md') + ' pg-v-' + (p.vAlign || 'start')
            + (p.stackOnMobile === '0' ? ' pg-nostack' : ''));

        hijos.forEach(function (col) {
            cols.appendChild(renderNode(col, aMedida ? colWidth((col.props || {}).width) : ''));
        });
        wrap.appendChild(cols);
        section.appendChild(wrap);
        return section;
    }

    function columnBody(block, ancho) {
        // El fondo y el redondeo van juntos, igual que en el servidor: sin
        // color no hay caja que redondear ni relleno que dar.
        var cp = block.props || {};
        var fondo = hex(cp.background) ? cp.background : '';
        var espacio = spacingClasses(cp);
        // `top` es el reparto de siempre y no emite clase.
        var vert = ['center', 'bottom', 'between'].indexOf(cp.vAlign) >= 0 ? ' pg-cv-' + cp.vAlign : '';
        var col = el('div', 'pg-col pg-a-' + (cp.align || 'left')
            + (ancho ? ' pg-cw' : '') + vert
            + (fondo ? ' pg-col-bg pg-r-' + (cp.radius || 'none') : '')
            + (espacio ? ' ' + espacio : ''));
        // El porcentaje va como variable, no como `width`: es la hoja la que
        // decide cuándo se aplica (en el teléfono la columna ocupa la fila).
        if (ancho) { col.style.setProperty('--pg-cw', ancho); }
        if (fondo) { col.style.backgroundColor = fondo; }
        if (hex(cp.textColor)) { col.style.color = cp.textColor; }
        block.children = block.children || [];
        if (!block.children.length) {
            var empty = el('div', 'pag-colempty', esc(t('dropHere')));
            empty.addEventListener('dragover', function (e) {
                if (!drag) { return; }
                e.preventDefault();
                empty.classList.add('is-over');
            });
            empty.addEventListener('dragleave', function () { empty.classList.remove('is-over'); });
            empty.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                empty.classList.remove('is-over');
                handleDrop(block.children, 0);
            });
            col.appendChild(empty);
        } else {
            renderList(block.children, col);
        }
        return col;
    }

    // -------------------------------------------------- vista previa por tipo

    /**
     * Aproximación del bloque para el lienzo. Devuelve '' si el bloque está
     * vacío, y entonces se pinta un fantasma para poder seleccionarlo igual.
     */
    function preview(block) {
        var p = block.props || {};
        var a = ' pg-a-' + (p.align || 'left');

        switch (block.type) {
            case 'heading':
                if (!p.text) { return ''; }
                var lv = p.level || '2';
                var style = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(p.color || '')
                    ? ' style="color:' + p.color + '"' : '';
                return '<h' + lv + ' class="pg-h pg-h' + lv + a + '"' + style + '>' + esc(p.text) + '</h' + lv + '>';

            case 'text':
                // Contenido del propio usuario en su propio navegador; el
                // servidor lo sanea al guardar (`HtmlSanitizer`).
                if (!p.html) { return ''; }
                return '<div class="pg-text' + a + ' pg-t-' + (p.size || 'md')
                    + (p.dropCap === '1' ? ' pg-dropcap' : '') + '"'
                    + (hex(p.color) ? ' style="color:' + p.color + '"' : '') + '>' + p.html + '</div>';

            case 'list':
                var items = (p.items || []).filter(function (i) { return i && i.text; });
                if (!items.length) { return ''; }
                var tag = p.style === 'number' ? 'ol' : 'ul';
                return '<' + tag + ' class="pg-list pg-list-' + (p.style || 'check') + '">'
                    + items.map(function (i) {
                        // Un elemento puede ser enlace. En el lienzo se pinta
                        // como tal pero SIN `href`: un clic dentro del editor
                        // debe seleccionar el bloque, no navegar fuera.
                        var txt = esc(i.text);
                        return '<li>' + (i.href ? '<a>' + txt + '</a>' : txt) + '</li>';
                    }).join('')
                    + '</' + tag + '>';

            case 'quote':
                if (!p.text) { return ''; }
                return '<blockquote class="pg-quote' + a + '"><p>' + esc(p.text) + '</p>'
                    + (p.author || p.role
                        ? '<cite class="pg-cite">' + esc(p.author || '')
                          + (p.role ? '<span class="pg-cite-role">' + esc(p.role) + '</span>' : '') + '</cite>'
                        : '')
                    + '</blockquote>';

            case 'image':
                // La imagen puede salir de la ficha de la empresa (el logo o la
                // portada): es lo que permite armar una cabecera con elementos.
                var isrc = p.source === 'logo' ? (CO.logo || '')
                    : p.source === 'banner' ? (CO.banner || '')
                    : (p.src || '');
                if (!isrc) { return p.source && p.source !== 'upload' ? noCompany() : ''; }
                var iw = cssLength(p.width);
                return '<figure class="pg-fig' + a + ' pg-img-' + (p.size || 'full') + '"'
                    + (iw ? ' style="width:' + iw + ';max-width:100%"' : '') + '>'
                    + '<img src="' + esc(isrc) + '" alt="' + esc(p.alt || '') + '" class="pg-r-' + (p.radius || 'sm')
                    + ratioCls(p.ratio) + '">'
                    + (p.caption ? '<figcaption>' + esc(p.caption) + '</figcaption>' : '')
                    + '</figure>';

            case 'gallery':
                var imgs = (p.items || []).filter(function (i) { return i && i.src; });
                if (!imgs.length) { return ''; }
                return '<div class="pg-gallery pg-cols-' + (p.columns || '3') + '">'
                    + imgs.map(function (i) {
                        return '<figure class="pg-gitem"><img src="' + esc(i.src) + '" alt="' + esc(i.alt || '')
                            + '" class="pg-r-' + (p.radius || 'sm') + ratioCls(p.ratio || '4-3') + '">'
                            + (i.caption ? '<figcaption>' + esc(i.caption) + '</figcaption>' : '') + '</figure>';
                    }).join('')
                    + '</div>';

            case 'video':
                // Marcador en vez del iframe: cargar reproductores dentro del
                // editor lo vuelve lento y no aporta nada para maquetar.
                if (!p.url) { return ''; }
                return '<div class="pg-video pg-ratio-' + (p.ratio || '16-9') + '">'
                    + '<div class="pag-ghost"><i class="icon icon-play-circle"></i> ' + esc(p.url) + '</div>'
                    + (p.caption ? '<figcaption>' + esc(p.caption) + '</figcaption>' : '')
                    + '</div>';

            case 'button':
                if (!p.label) { return ''; }
                // Mismas variables que emite el servidor: sin esto, cambiar el
                // color o el ancho en el inspector no se veía hasta publicar.
                var bvars = [];
                if (hex(p.color)) { bvars.push('--pg-btn-bg:' + p.color, '--pg-btn-fg:' + inkFor(p.color)); }
                if (hex(p.textColor)) { bvars.push('--pg-btn-fg:' + p.textColor); }
                if (hex(p.hoverColor)) { bvars.push('--pg-btn-hover:' + p.hoverColor); }
                return '<div class="pg-btnwrap' + a + (p.fullWidth === '1' ? ' pg-btn-block' : '') + '">'
                    + '<span class="pg-btn pg-btn-' + (p.style || 'primary')
                    + ' pg-btn-' + (p.size || 'md') + ' pg-btn-r-' + (p.radius || 'sm') + '"'
                    + (bvars.length ? ' style="' + bvars.join(';') + '"' : '') + '>'
                    + esc(p.label) + '</span></div>';

            case 'hero':
                if (!p.title && !p.subtitle && !p.image) { return ''; }
                var bg = p.image ? ' style="background-image:url(&quot;' + esc(p.image) + '&quot;)"' : '';
                return '<section class="pg-hero pg-hero-' + (p.height || 'md') + ' pg-ov-'
                    + (p.overlay || 'dark') + a + '"' + bg + '><div class="pg-hero-in">'
                    + (p.title ? '<h1 class="pg-hero-title">' + esc(p.title) + '</h1>' : '')
                    + (p.subtitle ? '<p class="pg-hero-sub">' + esc(p.subtitle) + '</p>' : '')
                    + (p.buttonLabel ? '<span class="pg-btn pg-btn-primary pg-btn-lg">' + esc(p.buttonLabel) + '</span>' : '')
                    + '</div></section>';

            case 'cards':
                var cards = (p.items || []).filter(function (i) { return i && (i.title || i.text || i.image); });
                if (!cards.length) { return ''; }
                return '<div class="pg-cards pg-cols-' + (p.columns || '3') + '">'
                    + cards.map(function (c) {
                        return '<article class="pg-card">'
                            + (c.image ? '<img class="pg-card-img" src="' + esc(c.image) + '" alt="">' : '')
                            + '<div class="pg-card-body">'
                            + (c.title ? '<h3 class="pg-card-title">' + esc(c.title) + '</h3>' : '')
                            + (c.text ? '<p class="pg-card-text">' + esc(c.text) + '</p>' : '')
                            + (c.linkLabel ? '<span class="pg-card-link">' + esc(c.linkLabel) + '</span>' : '')
                            + '</div></article>';
                    }).join('')
                    + '</div>';

            case 'form':
                var fields = (p.fields || []).filter(function (f) { return f && f.label; });
                return '<div class="pg-form">'
                    + (p.title ? '<h3 class="pg-form-title">' + esc(p.title) + '</h3>' : '')
                    + (p.intro ? '<p class="pg-form-intro">' + esc(p.intro) + '</p>' : '')
                    + '<div class="pg-form-body">'
                    + fields.map(function (f) {
                        var input = f.type === 'textarea'
                            ? '<textarea rows="3" disabled></textarea>'
                            : (f.type === 'select' ? '<select disabled></select>' : '<input type="text" disabled>');
                        return '<div class="pg-field"><label>' + esc(f.label)
                            + (f.required === '1' ? ' <span class="pg-req">*</span>' : '') + '</label>' + input + '</div>';
                    }).join('')
                    + '<span class="pg-btn pg-btn-primary pg-btn-md">' + esc(p.submitLabel || '···') + '</span>'
                    + '</div></div>';

            case 'spacer':
                return '<div class="pg-sp pg-sp-' + (p.size || 'md') + '"></div>';

            case 'divider':
                var hr = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(p.color || '')
                    ? ' style="border-top-color:' + p.color + '"' : '';
                return '<hr class="pg-hr pg-hr-' + (p.style || 'solid') + '"' + hr + '>';

            case 'html':
                // A propósito NO se inyecta: un `<script>` pegado aquí se
                // ejecutaría dentro del editor. Se ve al previsualizar.
                if (!p.code) { return ''; }
                return '<div class="pag-ghost"><i class="icon icon-cpu"></i> HTML</div>';

            // --------------------------------------------- bloques de empresa
            // Se dibujan con los datos reales de `companies`, no con relleno:
            // media plantilla depende de ellos y hay que poder juzgarla aquí.

            case 'buttons':
                var fila = (p.items || []).filter(function (b) { return b && b.label && b.href; });
                if (!fila.length) { return ''; }
                var dir = p.direction === 'column' ? 'column' : 'row';
                // La hamburguesa NO se dibuja en el lienzo: solo aparece por
                // debajo de 860 px y aquí engañaría, porque el lienzo es
                // estrecho por estar entre dos paneles, no porque la pantalla
                // lo sea. Para verla está la vista por dispositivo.
                return '<div class="pg-btnrow pg-btnrow-' + dir + a + ' pg-gap-' + (p.gap || 'sm')
                    + (p.stack !== '0' && dir === 'row' ? ' pg-btnrow-stack' : '') + '">'
                    + fila.map(function (b) {
                        var estilo = b.style || 'primary';
                        var css = [];
                        // En el estilo «enlace» el color es el del TEXTO.
                        if (hex(b.color)) {
                            if (estilo === 'link') {
                                css.push('--pg-btn-fg:' + b.color);
                            } else {
                                css.push('--pg-btn-bg:' + b.color, '--pg-btn-fg:' + inkFor(b.color));
                            }
                        }
                        // La tinta pedida manda sobre la calculada.
                        if (hex(b.textColor)) { css.push('--pg-btn-fg:' + b.textColor); }
                        if (hex(b.hoverColor)) { css.push('--pg-btn-hover:' + b.hoverColor); }
                        var v = css.length ? ' style="' + css.join(';') + '"' : '';
                        return '<span class="pg-btn pg-btn-' + estilo
                            + ' pg-btn-' + (p.size || 'md') + ' pg-btn-r-' + (p.radius || 'sm') + '"'
                            + v + (b.newTab === '1' ? ' data-newtab="1"' : '') + '>' + esc(b.label) + '</span>';
                    }).join('')
                    + '</div>';

            case 'companyContact':
                var datos = [
                    [p.showAddress, [CO.address, CO.city].filter(Boolean).join(', ')],
                    [p.showPhone, CO.contact_phone],
                    [p.showEmail, CO.contact_email]
                ].filter(function (d) { return d[0] !== '0' && d[1]; });
                if (!datos.length) { return noCompany(); }
                return '<div class="pg-contact' + a + '">'
                    + datos.map(function (d) {
                        return '<span class="pg-contact-item"><span>' + esc(d[1]) + '</span></span>';
                    }).join('') + '</div>';

            case 'companyQr':
                // El QR lo genera el servidor (GD). En el lienzo va un hueco con
                // su medida, que es lo que importa para maquetar.
                return '<div class="pg-qrwrap' + a + '"><figure class="pg-qr pg-qr-'
                    + (p.size || 'md') + ' pg-qr-' + (p.frame || 'card') + '">'
                    + (p.title ? '<figcaption class="pg-qr-title">' + esc(p.title) + '</figcaption>' : '')
                    + '<div class="pag-ghost"><i class="icon icon-qr-code"></i> ' + esc(t('qrHere')) + '</div>'
                    + (p.caption ? '<figcaption class="pg-qr-cap">' + esc(p.caption) + '</figcaption>' : '')
                    + '</figure></div>';

            case 'companySocial':
                return companySocial(p);

            case 'whatsappFloat':
                var waPhone = digits(p.phone) || digits(CO.whatsapp) || digits(CO.contact_phone);
                if (!waPhone) { return noCompany(); }
                // `pg-wa-static` para que no se ancle a la ventana y tape el
                // panel: el mismo interruptor que usa el renderizador del
                // servidor cuando pinta en modo editor.
                // Sin etiqueta es un círculo, igual que al publicar.
                // Colores propios, como variables (igual que el servidor).
                // Mapa y no lista: la tinta pedida sustituye a la calculada.
                var waCss = {};
                if (hex(p.background)) {
                    waCss['--pg-wa-bg'] = p.background;
                    waCss['--pg-wa-fg'] = inkFor(p.background);
                }
                if (hex(p.textColor)) { waCss['--pg-wa-fg'] = p.textColor; }
                var waStyle = Object.keys(waCss).map(function (k) { return k + ':' + waCss[k]; });
                return '<a class="pg-wa pg-wa-' + (p.position || 'right')
                    + (p.label ? '' : ' pg-wa-round') + ' pg-wa-static"'
                    + (waStyle.length ? ' style="' + waStyle.join(';') + '"' : '') + '>'
                    + '<span class="pg-wa-icon">' + waIcon() + '</span>'
                    + (p.label ? '<span class="pg-wa-label">' + esc(p.label) + '</span>' : '')
                    + '</a>';

            default:
                return '';
        }
    }

    /**
     * Añade a la etiqueta raíz las clases de espaciado del bloque, igual que
     * hace `Renderer::withCommon()` en el servidor. Sin esto, cambiar el
     * relleno en el inspector no se vería hasta publicar.
     */
    /**
     * Las clases de espaciado de un bloque, como cadena.
     *
     * `omite` es el campo que el TIPO declara por su cuenta y que por tanto ya
     * pinta él: la sección tiene su propio `padding` (el aire de la banda), y
     * añadirle además el común la dejaba con el doble y por los cuatro lados.
     * Misma regla que `Renderer::withCommon()`.
     */
    function spacingClasses(p, omite) {
        var escala = ['none', 'sm', 'md', 'lg', 'xl'];

        return [['marginTop', 'pg-mt-'], ['marginBottom', 'pg-mb-'], ['padding', 'pg-p-']]
            .map(function (par) {
                if (omite && par[0] === omite) { return ''; }
                var v = p[par[0]] || '';

                return escala.indexOf(v) >= 0 ? par[1] + v : '';
            })
            .filter(Boolean)
            .join(' ');
    }

    function withSpacing(html, p) {
        if (!html) { return html; }

        // Color del texto: campo común de todos los bloques. En la sección y en
        // la columna cascada a sus hijos, igual que en el servidor.
        // `hexA()` VALIDA (devuelve booleano); el valor es el del bloque. Usar
        // su retorno como color escribía `style="color:true"`.
        var tinta = hexA(p.textColor || '') ? p.textColor : '';
        if (tinta) {
            html = /^<[a-z][a-z0-9]*\b[^>]*\sstyle="/i.test(html)
                ? html.replace(/^(<[a-z][a-z0-9]*\b[^>]*?)\sstyle="([^"]*)"/i, '$1 style="color:' + tinta + ';$2"')
                : html.replace(/^(<[a-z][a-z0-9]*)/i, '$1 style="color:' + tinta + '"');
        }

        var cls = spacingClasses(p);

        if (!cls) { return html; }

        // Se añade al `class` que ya tenga la raíz; si no tuviera, se crea.
        if (/^<[a-z][a-z0-9]*\b[^>]*\sclass="/i.test(html)) {
            return html.replace(/^(<[a-z][a-z0-9]*\b[^>]*?)\sclass="([^"]*)"/i, '$1 class="$2 ' + cls + '"');
        }
        return html.replace(/^(<[a-z][a-z0-9]*)/i, '$1 class="' + cls + '"');
    }

    function digits(v) { return String(v || '').replace(/\D+/g, ''); }

    /** ¿Es un color hexadecimal válido? Misma comprobación que el servidor. */
    // La MISMA regla que el servidor (`Renderer::color()`): 3, 4, 6 u 8 dígitos.
    //
    // Aceptaba solo 3 y 6, así que un color con transparencia —que es de 8— se
    // caía aquí en silencio: el inspector lo guardaba bien y la página
    // publicada lo pintaba, pero el lienzo no lo dibujaba, y desde el editor
    // parecía que la opacidad no hacía nada.
    function hex(v) { return hexA(v); }

    /** Clase de recorte, o nada si es «original». */
    function ratioCls(v) {
        return v && v !== 'auto' ? ' pg-ar pg-ar-' + v : '';
    }

    /** Tinta legible sobre ese fondo (misma regla que `Renderer::inkFor`). */
    function inkFor(v) {
        var h = String(v || '').replace('#', '');
        // 4 dígitos es la forma corta CON alfa: los tres primeros son el color.
        if (h.length === 3 || h.length === 4) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
        var r = parseInt(h.slice(0, 2), 16), g = parseInt(h.slice(2, 4), 16), b = parseInt(h.slice(4, 6), 16);
        return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#14161a' : '#ffffff';
    }

    function noCompany() {
        return '<div class="pg-cempty">' + esc(t('noCompanyData')) + '</div>';
    }

    /**
     * Una medida CSS (`320px`, `60%`), o ''. La MISMA regla que el servidor
     * (`Renderer::cssLength()`): se valida y se recompone, no se escapa.
     */
    function cssLength(v) {
        var s = String(v || '').toLowerCase().replace(/ /g, '');
        var m = /^(\d{1,4}(?:\.\d{1,2})?)(px|%|rem|em)$/.exec(s);
        if (m) {
            var n = parseFloat(m[1]);
            var max = m[2] === '%' ? 100 : (m[2] === 'px' ? 4000 : 200);
            // Los ceros solo sobran DETRÁS DE LA COMA: recortarlos siempre
            // convertía «150px» en «15px».
            var num = m[1].indexOf('.') >= 0 ? m[1].replace(/\.?0+$/, '') : m[1];
            return n > 0 && n <= max ? num + m[2] : '';
        }
        return /^\d{1,4}$/.test(s) && parseInt(s, 10) > 0 ? s + 'px' : '';
    }

    /** Redes de la empresa. Sin iconos de marca en el lienzo: van por inicial. */
    function companySocial(p) {
        var nets = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'].filter(function (n) { return CO[n]; });
        if (p.showWebsite !== '0' && CO.website) { nets.push('website'); }
        if (!nets.length) { return noCompany(); }

        var conNombre = p.showLabels === '1';

        return '<div class="pg-social pg-soc-' + (p.style || 'solid') + ' pg-soc-' + (p.size || 'md')
            + ' pg-a-' + (p.align || 'center') + '">'
            + (p.title ? '<p class="pg-soc-title">' + esc(p.title) + '</p>' : '')
            + '<div class="pg-soc-row">'
            + nets.map(function (n) {
                var nombre = n.charAt(0).toUpperCase() + n.slice(1);
                // El sitio web usa el icono del globo, igual que el servidor.
                var svg = ICONS[n === 'website' ? 'globe' : n] || '';
                return '<span class="pg-soc-link' + (conNombre ? ' pg-soc-labeled' : '') + '" title="' + esc(nombre) + '">'
                    + svg
                    + (conNombre ? '<span class="pg-soc-name">' + esc(nombre) + '</span>' : '')
                    + '</span>';
            }).join('')
            + '</div></div>';
    }

    /** El icono de WhatsApp, el mismo que sale publicado. */
    function waIcon() {
        return ICONS.whatsapp || '';
    }

    // ------------------------------------------------------------- selección

    function select(id) {
        selectedId = id;
        render();
        renderTree();
        renderInspector();
        var node = canvas.querySelector('.pag-node.is-selected');
        if (node && node.scrollIntoView) { node.scrollIntoView({ block: 'nearest' }); }
    }

    // ----------------------------------------------------------- operaciones

    function insert(list, index, block) {
        list.splice(index, 0, block);
        selectedId = block.id;
        touch();
    }

    /**
     * Inserta varios bloques conservando su orden. Se recorre al revés porque
     * cada uno entra en el MISMO índice: al derecho saldrían invertidos.
     * Cada bloque se clona con identificadores nuevos — una sección prearmada se
     * suelta muchas veces y dos bloques con el mismo id romperían la bandeja de
     * mensajes (no se sabría de qué formulario vino cada uno).
     */
    function insertMany(list, index, blocks) {
        if (!blocks || !blocks.length) { return; }
        blocks.slice().reverse().forEach(function (b) {
            insert(list, index, cloneBlock(b));
        });
    }

    function handleDrop(list, index) {
        if (!drag) { return; }

        if (drag.type) {
            var fresh = makeBlock(drag.type);
            if (fresh) { insert(list, index, fresh); }
        } else if (drag.saved != null) {
            insertMany(list, index, (SAVED.filter(function (s) { return s.id === drag.saved; })[0] || {}).blocks);
        } else if (drag.section) {
            insertMany(list, index, (SECS.filter(function (s) { return s.code === drag.section; })[0] || {}).blocks);
        } else if (drag.move) {
            moveTo(drag.move, list, index);
        }
        drag = null;
    }

    /** Mueve un bloque a otra posición del árbol. */
    function moveTo(id, list, index) {
        var found = locate(id);
        if (!found) { return; }
        // Una sección no puede acabar dentro de sí misma.
        if (found.block.children && listBelongsTo(list, id)) { return; }

        var from = found.list;
        var at   = found.index;
        // Si es la misma lista y se suelta después, el índice se corre al sacar.
        if (from === list && index > at) { index--; }
        from.splice(at, 1);
        list.splice(index, 0, found.block);
        selectedId = id;
        touch();
    }

    /** ¿La lista de destino está dentro del bloque que se mueve? */
    function listBelongsTo(list, ancestorId) {
        var node = locate(ancestorId);
        if (!node) { return false; }
        var inside = false;
        (function walk(children) {
            (children || []).forEach(function (child) {
                if (child.children === list) { inside = true; }
                walk(child.children);
            });
        })(node.block.children);
        return inside || node.block.children === list;
    }

    function move(id, delta) {
        var found = locate(id);
        if (!found) { return; }
        var to = found.index + delta;
        if (to < 0 || to >= found.list.length) { return; }
        found.list.splice(found.index, 1);
        found.list.splice(to, 0, found.block);
        touch();
    }

    function duplicate(id) {
        var found = locate(id);
        if (!found) { return; }
        var copy = cloneBlock(found.block);
        found.list.splice(found.index + 1, 0, copy);
        selectedId = copy.id;
        touch();
    }

    function remove(id) {
        var found = locate(id);
        if (!found) { return; }
        found.list.splice(found.index, 1);
        if (selectedId === id) { selectedId = null; }
        touch();
    }

    function touch() {
        dirty = true;
        setStatus('unsaved', 'badge-warning');
        render();
        renderTree();
        renderInspector();
    }

    function clearOver() {
        Array.prototype.forEach.call(canvas.querySelectorAll('.is-over'), function (n) {
            n.classList.remove('is-over');
        });
    }

    // ------------------------------------------------- estructura (cascada)
    //
    // El equivalente a la «vista de lista» de Gutenberg: el árbol entero de la
    // página en una columna, sangrado por profundidad. Sirve para tres cosas
    // que el lienzo hace mal cuando la página crece: ver de un vistazo de quién
    // cuelga cada bloque, llegar a uno que está enterrado en una columna, y
    // reordenar arrastrando sin tener que desplazarse por media página.

    /** Un resumen corto del contenido del bloque, para reconocerlo en la lista. */
    function summary(block) {
        var p = block.props || {};
        var raw = p.text || p.title || p.label || p.name
            || (p.html ? String(p.html).replace(/<[^>]*>/g, ' ') : '')
            || p.url || p.src || '';
        raw = String(raw).replace(/\s+/g, ' ').trim();

        return raw.length > 34 ? raw.slice(0, 34) + '…' : raw;
    }

    function renderTree() {
        if (!treeView) { return; }
        treeView.innerHTML = '';

        if (!tree.length) {
            treeView.appendChild(el('div', 'pag-tempty', esc(t('treeEmpty'))));
            renderOutline();
            return;
        }

        treeList(tree, treeView, 0);
        renderOutline();
    }

    /** Pinta una lista de bloques y, recursivamente, sus hijos. */
    function treeList(list, host, depth) {
        // Cuántas columnas hay en este nivel, para numerarlas: «Columna 2 de 3»
        // dice mucho más que tres filas iguales llamadas «Columna».
        var cols = list.filter(function (b) { return b.type === 'column'; }).length;
        var nCol = 0;

        host.appendChild(treeDrop(list, 0));
        list.forEach(function (block, i) {
            if (block.type === 'column') { nCol++; }
            host.appendChild(treeNode(block, depth, block.type === 'column' ? { n: nCol, of: cols } : null));

            var abierto = !collapsed[block.id];
            if (block.children && block.children.length && abierto) {
                treeList(block.children, host, depth + 1);
            }
            host.appendChild(treeDrop(list, i + 1));
        });
    }

    function treeNode(block, depth, colInfo) {
        var def      = REG[block.type] || {};
        var isColumn = block.type === 'column';
        var hijos    = (block.children || []).length;

        var node = el('div', 'pag-tnode' + (block.id === selectedId ? ' is-selected' : '') + (isColumn ? ' is-fixed' : ''));
        node.style.setProperty('--depth', String(depth));
        node.dataset.id = block.id;

        // Plegar/desplegar los contenedores con hijos.
        if (hijos) {
            var abierto = !collapsed[block.id];
            var tog = el('button', 'pag-ttoggle' + (abierto ? ' is-open' : ''), '▶');
            tog.type  = 'button';
            tog.title = abierto ? t('collapse') : t('expand');
            tog.addEventListener('click', function (e) {
                e.stopPropagation();
                if (collapsed[block.id]) { delete collapsed[block.id]; } else { collapsed[block.id] = true; }
                renderTree();
            });
            node.appendChild(tog);
        } else {
            node.appendChild(el('span', 'pag-tspacer'));
        }

        node.appendChild(el('i', 'icon ' + (isColumn ? 'icon-list-columns-reverse' : (def.icon || 'icon-puzzle'))));

        var texto = isColumn
            ? (colInfo && colInfo.of > 1
                ? t('columnOf').replace('{0}', colInfo.n).replace('{1}', colInfo.of)
                : t('column'))
            : (def.label || block.type);

        var resumen = summary(block);
        node.appendChild(el('span', 'pag-tlabel',
            esc(texto) + (resumen ? '<span class="pag-tsummary">' + esc(resumen) + '</span>' : '')));

        if (hijos) {
            var c = el('span', 'pag-tcount', String(hijos));
            c.title = t('blocksInside');
            node.appendChild(c);
        }

        // Toda fila —sección, columna o elemento— se selecciona, y el inspector
        // de la derecha pasa a mostrar SUS propiedades. Es el recorrido que pide
        // la cascada: bajar hasta el bloque enterrado y editarlo ahí mismo.
        node.addEventListener('click', function () { select(block.id); });

        // Arrastrar desde el árbol reordena igual que en el lienzo. Las columnas
        // no: las gobierna el desplegable «Columnas» de su sección.
        if (!isColumn) {
            node.draggable = true;
            node.addEventListener('dragstart', function (e) {
                drag = { move: block.id };
                node.classList.add('is-dragging');
                try { e.dataTransfer.setData('text/plain', block.id); } catch (err) { /* Safari */ }
                e.dataTransfer.effectAllowed = 'move';
            });
            node.addEventListener('dragend', function () {
                drag = null;
                node.classList.remove('is-dragging');
                clearTreeOver();
            });
        }

        return node;
    }

    function treeDrop(list, index) {
        var zone = el('div', 'pag-tdrop');
        zone.addEventListener('dragover', function (e) {
            if (!drag) { return; }
            e.preventDefault();
            zone.classList.add('is-over');
        });
        zone.addEventListener('dragleave', function () { zone.classList.remove('is-over'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('is-over');
            handleDrop(list, index);
        });
        return zone;
    }

    function clearTreeOver() {
        if (!treeView) { return; }
        Array.prototype.forEach.call(treeView.querySelectorAll('.is-over'), function (n) {
            n.classList.remove('is-over');
        });
    }

    /**
     * Esquema de títulos, como el «Outline» de Gutenberg: la jerarquía h1..h4 de
     * la página y un aviso si se salta un nivel (un h4 detrás de un h2). Ese
     * salto no se ve en el lienzo y rompe la estructura del documento para los
     * lectores de pantalla y para los buscadores.
     */
    function renderOutline() {
        if (!outline) { return; }
        outline.innerHTML = '';

        var titulos = [];
        (function walk(list) {
            list.forEach(function (b) {
                if (b.type === 'heading' && (b.props || {}).text) {
                    titulos.push({ level: parseInt(b.props.level, 10) || 2, text: b.props.text, id: b.id });
                }
                // La portada de empresa aporta el h1 de la página.
                // La cabecera del sitio no aporta un H1: el título de la página
                // lo pone el hero o el primer bloque de título.
                if (b.type === 'hero' && (b.props || {}).title) {
                    titulos.push({ level: 1, text: b.props.title, id: b.id });
                }
                if (b.children) { walk(b.children); }
            });
        }(tree));

        outline.appendChild(el('p', 'pag-otitle', esc(t('outlineTitle'))));

        if (!titulos.length) {
            outline.appendChild(el('p', 'pag-onote', esc(t('outlineEmpty'))));
            return;
        }

        var salto = false;
        var previo = 0;

        titulos.forEach(function (h) {
            if (previo && h.level > previo + 1) { salto = true; }
            previo = h.level;

            var it = el('a', 'pag-oitem', '<b>H' + h.level + '</b> ' + esc(h.text));
            it.style.setProperty('--level', String(h.level));
            it.href = '#';
            it.addEventListener('click', function (e) { e.preventDefault(); select(h.id); });
            outline.appendChild(it);
        });

        outline.appendChild(el('p', 'pag-onote ' + (salto ? 'is-warn' : 'is-ok'),
            esc(salto ? t('outlineSkip') : t('outlineOk'))));
    }

    // -------------------------------------------------------------- inspector

    function renderInspector() {
        inspector.innerHTML = '';
        var found = selectedId ? locate(selectedId) : null;

        if (!found) {
            inspector.appendChild(el('div', 'pag-inone',
                '<strong>' + esc(t('noSelection')) + '</strong><span>' + esc(t('noSelectionHint')) + '</span>'));
            return;
        }

        var block = found.block;
        var def   = REG[block.type] || {};

        inspector.appendChild(el('div', 'pag-ihead',
            '<i class="icon ' + esc(def.icon || 'icon-puzzle') + '"></i><span>' + esc(def.label || block.type) + '</span>'));

        if (def.hint) {
            inspector.appendChild(el('p', 'pag-ihint', esc(def.hint)));
        }

        // Los campos propios del bloque van arriba y sueltos; los comunes
        // —espaciado y avanzado— en paneles plegados. Con veinte tipos y cuatro
        // campos comunes cada uno, ponerlos todos a la vista escondería lo que
        // de verdad se viene a cambiar. Es lo que hace Gutenberg con sus
        // secciones «Dimensiones» y «Avanzado».
        var paneles = {};

        (def.fields || []).forEach(function (field) {
            if (!field.panel) {
                inspector.appendChild(fieldControl(block, field));
                return;
            }
            (paneles[field.panel] = paneles[field.panel] || []).push(field);
        });

        Object.keys(paneles).forEach(function (nombre) {
            var caja = el('details', 'pag-ipanel');
            // El inspector se repinta entero al cambiar cualquier cosa, así que
            // sin recordar el estado el panel se CERRABA solo en cuanto se
            // tocaba uno de sus campos: había que volver a abrirlo para el
            // siguiente. Se guarda por nombre de panel, no por bloque: quien
            // abre «Espaciado» quiere tenerlo abierto para todos.
            if (panelAbierto[nombre]) { caja.open = true; }
            var cab = el('summary', '', esc(t('panel_' + nombre) || nombre));
            cab.addEventListener('click', function () {
                // El clic aún no ha cambiado `open`: se anota el valor futuro.
                panelAbierto[nombre] = !caja.open;
            });
            caja.appendChild(cab);
            paneles[nombre].forEach(function (field) {
                caja.appendChild(fieldControl(block, field));
            });
            inspector.appendChild(caja);
        });
    }

    /** Un control del inspector según el tipo de campo declarado en el registro. */
    function fieldControl(block, field) {
        var wrap  = el('div', 'pag-ifield');
        var label = el('label', '', esc(field.label || field.name));
        wrap.appendChild(label);

        var value = (block.props || {})[field.name];
        var input;

        switch (field.type) {
            case 'select':
                // Las escalas ordenadas (espaciado, radio, tamaño) se manejan
                // mucho mejor con un deslizador que con un desplegable: se ve de
                // un vistazo dónde está el valor dentro del recorrido y se sube
                // o baja un punto sin abrir nada.
                if (isScale(field)) {
                    wrap.appendChild(scaleControl(block, field, value));
                    if (field.hint) { wrap.appendChild(el('p', 'pag-ihint', esc(field.hint))); }
                    return wrap;
                }
                input = document.createElement('select');
                input.className = 'form-select';
                Object.keys(field.options || {}).forEach(function (key) {
                    var opt = document.createElement('option');
                    opt.value = key;
                    opt.textContent = field.options[key];
                    if (String(value) === key) { opt.selected = true; }
                    input.appendChild(opt);
                });
                input.addEventListener('change', function () {
                    setProp(block, field.name, input.value);
                    // Cambiar las columnas reordena los hijos de la sección.
                    if (block.type === 'section' && field.name === 'columns') { syncColumns(block); }
                    touch();
                });
                break;

            case 'toggle':
                wrap.innerHTML = '';
                var check = el('div', 'form-check');
                input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.checked = String(value) === '1';
                input.id = 'pag-f-' + block.id + '-' + field.name;
                var lbl = document.createElement('label');
                lbl.className = 'form-check-label';
                lbl.htmlFor = input.id;
                lbl.textContent = field.label || field.name;
                input.addEventListener('change', function () {
                    setProp(block, field.name, input.checked ? '1' : '0');
                    touch();
                });
                check.appendChild(input);
                check.appendChild(lbl);
                wrap.appendChild(check);
                if (field.hint) { wrap.appendChild(el('p', 'pag-ihint', esc(field.hint))); }
                return wrap;

            case 'textarea':
                input = document.createElement('textarea');
                input.className = 'form-textarea';
                input.rows = field.rows || 3;
                input.value = value || '';
                if (field.placeholder) { input.placeholder = field.placeholder; }
                bindLive(input, block, field.name);
                break;

            case 'color':
                wrap.appendChild(colorControl(block, field, value));
                if (field.hint) { wrap.appendChild(el('p', 'pag-ihint', esc(field.hint))); }
                return wrap;

            case 'image':
                wrap.appendChild(imageControl(block, field, value));
                if (field.hint) { wrap.appendChild(el('p', 'pag-ihint', esc(field.hint))); }
                return wrap;

            case 'richtext':
                wrap.appendChild(richText(block, field, value));
                if (field.hint) { wrap.appendChild(el('p', 'pag-ihint', esc(field.hint))); }
                return wrap;

            case 'repeater':
                wrap.appendChild(repeater(block, field, value));
                if (field.hint) { wrap.appendChild(el('p', 'pag-ihint', esc(field.hint))); }
                return wrap;

            case 'number':
                input = document.createElement('input');
                input.type = 'number';
                input.className = 'form-input';
                input.value = value || '';
                bindLive(input, block, field.name);
                break;

            default: // text, link
                input = document.createElement('input');
                input.type = field.type === 'link' ? 'text' : 'text';
                input.className = 'form-input';
                input.value = value || '';
                if (field.placeholder) { input.placeholder = field.placeholder; }
                bindLive(input, block, field.name);
        }

        wrap.appendChild(input);
        if (field.hint) { wrap.appendChild(el('p', 'pag-ihint', esc(field.hint))); }
        return wrap;
    }

    /**
     * ¿Este desplegable es una ESCALA ordenada? Lo son las que van de menos a
     * más —espaciado, radio, tamaño—, donde un deslizador dice más que una
     * lista. No lo son las de alineación, estilo o proporción, que son
     * alternativas sin orden entre ellas.
     */
    var CAMPOS_ESCALA = [
        'padding', 'gap', 'marginTop', 'marginBottom', 'size',
        'radius', 'height', 'level', 'columns',
    ];

    function isScale(field) {
        return field.type === 'select'
            && CAMPOS_ESCALA.indexOf(field.name) >= 0
            && Object.keys(field.options || {}).length >= 3;
    }

    /**
     * Deslizador sobre los valores de la escala. El `range` recorre POSICIONES,
     * no valores: la escala es de palabras («sm», «md»…), no de números, así que
     * el índice es lo único que se puede deslizar. Debajo va el nombre del valor
     * actual, que es lo que el usuario entiende.
     */
    function scaleControl(block, field, value) {
        var claves = Object.keys(field.options || {});
        var pos = Math.max(0, claves.indexOf(String(value === undefined ? '' : value)));

        var caja = el('div', 'pag-scale');

        var cab = el('div', 'pag-scale-head');
        var val = el('span', 'pag-scale-val', esc(field.options[claves[pos]] || ''));
        cab.appendChild(val);
        caja.appendChild(cab);

        var input = document.createElement('input');
        input.type = 'range';
        input.className = 'pag-range';
        input.min = '0';
        input.max = String(claves.length - 1);
        input.step = '1';
        input.value = String(pos);
        input.setAttribute('aria-label', field.label || field.name);

        input.addEventListener('input', function () {
            var i = parseInt(input.value, 10) || 0;
            val.textContent = field.options[claves[i]] || '';
            setProp(block, field.name, claves[i]);
            // Las columnas de una sección cambian su número de hijos.
            if (block.type === 'section' && field.name === 'columns') {
                syncColumns(block);
                scheduleRelayout();
            } else {
                scheduleRepaint();
            }
        });

        caja.appendChild(input);

        // Marcas: los extremos, para que se entienda el recorrido.
        var pies = el('div', 'pag-scale-ends');
        pies.appendChild(el('span', '', esc(field.options[claves[0]] || '')));
        pies.appendChild(el('span', '', esc(field.options[claves[claves.length - 1]] || '')));
        caja.appendChild(pies);

        return caja;
    }

    /**
     * Repinta lienzo Y árbol (no el inspector, para no perder el foco del
     * deslizador). Hace falta cuando el cambio altera la ESTRUCTURA, como
     * cambiar el número de columnas de una sección.
     */
    function scheduleRelayout() {
        if (repaintTimer) { clearTimeout(repaintTimer); }
        repaintTimer = setTimeout(function () {
            var keep = selectedId;
            render();
            renderTree();
            selectedId = keep;
        }, 250);
    }

    /**
     * Escribir en un campo repinta el lienzo, pero **no** el inspector: eso
     * arrancaría el foco del campo en cada tecla. Por eso `scheduleRepaint()`
     * y no `touch()`.
     */
    function bindLive(input, block, name) {
        input.addEventListener('input', function () {
            setProp(block, name, input.value);
            scheduleRepaint();
        });
    }

    function setProp(block, name, value) {
        block.props = block.props || {};
        block.props[name] = value;
        dirty = true;
        setStatus('unsaved', 'badge-warning');
    }

    var repaintTimer = null;
    function scheduleRepaint() {
        if (repaintTimer) { clearTimeout(repaintTimer); }
        repaintTimer = setTimeout(function () {
            var keep = selectedId;
            render();
            selectedId = keep;
        }, 250);
    }

    /**
     * Control de color con transparencia.
     *
     * El selector nativo (`input type="color"`) tiene dos carencias que había
     * que tapar: **no tiene estado vacío** —con el valor sin poner mostraba
     * blanco, indistinguible de un blanco elegido a propósito— y **no admite
     * canal alfa**. Aquí se resuelven con una muestra que enseña el color real
     * sobre un damero (para que se vea la transparencia), el valor escrito al
     * lado, y un deslizador de opacidad.
     *
     * El valor se guarda como hexadecimal de 8 dígitos cuando hay
     * transparencia (`#1a2b3c80`), que es CSS estándar; con opacidad total se
     * queda en los 6 de siempre.
     */
    function colorControl(block, field, value) {
        var actual = String(value || '');
        var base   = /^#[0-9a-fA-F]{6}/.test(actual) ? actual.slice(0, 7) : '#2563eb';
        var alfa   = /^#[0-9a-fA-F]{8}$/.test(actual) ? parseInt(actual.slice(7, 9), 16) / 255 : 1;
        var puesto = hexA(actual);

        var caja = el('div', 'pag-color');

        // --- muestra + valor -----------------------------------------------
        var fila = el('div', 'pag-color-row');

        var picker = document.createElement('input');
        picker.type = 'color';
        picker.className = 'pag-color-pick';
        picker.value = base;

        var muestra = el('span', 'pag-color-swatch');
        var texto   = el('span', 'pag-color-val');

        function pinta() {
            var v = valor();
            // El damero de debajo hace visible la transparencia; sin él, un
            // color al 20 % sobre fondo blanco parece simplemente claro.
            muestra.style.setProperty('--pag-swatch', v || 'transparent');
            muestra.className = 'pag-color-swatch' + (v ? '' : ' is-empty');
            texto.textContent = v || t('noColor');
            texto.className = 'pag-color-val' + (v ? '' : ' is-empty');
        }

        function valor() {
            if (!puesto) { return ''; }
            var a = Math.round(alfa * 255);
            return a >= 255 ? picker.value : picker.value + ('0' + a.toString(16)).slice(-2);
        }

        function aplica() {
            pinta();
            setProp(block, field.name, valor());
            scheduleRepaint();
        }

        picker.addEventListener('input', function () { puesto = true; aplica(); });

        fila.appendChild(muestra);
        fila.appendChild(picker);
        fila.appendChild(texto);

        // Quitar el color: el selector nativo no puede volver a «sin color».
        var quitar = el('button', 'pag-color-clear', '<i class="icon icon-x-lg"></i>');
        quitar.type = 'button';
        quitar.title = t('noColor');
        quitar.addEventListener('click', function () {
            puesto = false;
            alfa = 1;
            rango.value = '100';
            pinta();
            setProp(block, field.name, '');
            scheduleRepaint();
        });
        fila.appendChild(quitar);
        caja.appendChild(fila);

        // --- transparencia --------------------------------------------------
        var pieAlfa = el('div', 'pag-color-alpha');
        var etiqueta = el('span', '', esc(t('opacity')));
        var pct = el('span', 'pag-color-pct', Math.round(alfa * 100) + ' %');

        var rango = document.createElement('input');
        rango.type = 'range';
        rango.className = 'pag-range';
        rango.min = '0';
        rango.max = '100';
        rango.step = '5';
        rango.value = String(Math.round(alfa * 100));
        rango.setAttribute('aria-label', t('opacity'));
        rango.addEventListener('input', function () {
            alfa = (parseInt(rango.value, 10) || 0) / 100;
            pct.textContent = rango.value + ' %';
            // Mover la opacidad implica que SÍ se quiere color.
            puesto = true;
            aplica();
        });

        var cab = el('div', 'pag-color-alpha-head');
        cab.appendChild(etiqueta);
        cab.appendChild(pct);
        pieAlfa.appendChild(cab);
        pieAlfa.appendChild(rango);
        caja.appendChild(pieAlfa);

        pinta();
        return caja;
    }

    /** ¿Hay un color puesto? (hex de 3, 4, 6 u 8 dígitos) */
    function hexA(v) {
        return /^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(String(v || ''));
    }

    // Control de imagen: miniatura + subir + quitar.
    function imageControl(block, field, value) {
        var box = el('div', 'pag-img');
        var img = document.createElement('img');
        img.alt = '';
        if (value) { img.src = value; } else { img.style.display = 'none'; }
        box.appendChild(img);

        var file = document.createElement('input');
        file.type = 'file';
        file.accept = 'image/*';
        file.style.display = 'none';

        var pick = el('button', 'btn btn-secondary btn-sm', '<i class="icon icon-upload"></i> ' + esc(t('chooseImage')));
        pick.type = 'button';
        pick.addEventListener('click', function () { file.click(); });

        var drop = el('button', 'btn btn-ghost btn-sm', '<i class="icon icon-x-lg"></i>');
        drop.type = 'button';
        drop.title = t('removeImage');
        drop.addEventListener('click', function () {
            setProp(block, field.name, '');
            touch();
        });

        file.addEventListener('change', function () {
            if (!file.files || !file.files[0]) { return; }
            pick.disabled = true;
            pick.innerHTML = esc(t('uploading'));
            // Si el bloque declara su ancho en px, ese es el ancho del hueco:
            // guardar más píxeles solo engorda la página.
            var m = /^(\d{2,4})px$/.exec(String((block.props || {}).width || '').replace(/ /g, ''));
            upload(file.files[0], m ? parseInt(m[1], 10) : 0).then(function (res) {
                if (res && res.ok) {
                    setProp(block, field.name, res.url);
                    touch();
                } else {
                    window.alert((res && res.error) || t('uploadError'));
                    pick.disabled = false;
                    pick.innerHTML = '<i class="icon icon-upload"></i> ' + esc(t('chooseImage'));
                }
            });
        });

        // Elegir una ya subida en vez de volver a subirla: es lo que evita
        // tener la misma foto cinco veces en el servidor con cinco nombres.
        var lib = el('button', 'btn btn-ghost btn-sm', '<i class="icon icon-image"></i> ' + esc(t('fromLibrary')));
        lib.type = 'button';
        lib.addEventListener('click', function () {
            abreBiblioteca(function (item) {
                setProp(block, field.name, item.url);
                touch();
            });
        });

        var btns = el('div', 'pag-imgbtns');
        btns.appendChild(pick);
        btns.appendChild(lib);
        btns.appendChild(drop);
        box.appendChild(btns);
        box.appendChild(file);

        // Además del botón, se puede escribir/pegar una URL directamente.
        var url = document.createElement('input');
        url.type = 'text';
        url.className = 'form-input';
        url.value = value || '';
        url.placeholder = 'https://…';
        bindLive(url, block, field.name);
        box.appendChild(url);

        return box;
    }


    /* =====================================================================
       BIBLIOTECA DE MEDIOS

       Elegir una imagen ya subida en vez de volver a subirla. Sin esto, la
       misma foto acababa cinco veces en el servidor con cinco nombres, y no
       había forma de reusar en una página lo que se había subido en otra.

       Se pide al abrir, no al cargar el editor: una empresa puede acumular
       cientos de imágenes y no tiene sentido traerlas todas cada vez que se
       abre una página para tocar un título.
       ===================================================================== */

    var bibCaja = null;

    /** Abre el selector y llama a `alElegir(item)` con la imagen escogida. */
    function abreBiblioteca(alElegir) {
        if (!bibCaja) { bibCaja = construyeBiblioteca(); }
        bibCaja._alElegir = alElegir;
        bibCaja.classList.add('is-open');
        var q = bibCaja.querySelector('[data-bib-q]');
        if (q) { q.value = ''; }
        cargaBiblioteca('');
        if (q) { q.focus(); }
    }

    function construyeBiblioteca() {
        var caja = el('div', 'pag-biblio');
        caja.innerHTML =
            '<div class="pag-biblio-box">'
            + '<div class="pag-biblio-head">'
            + '<strong>' + esc(t('mediaPick')) + '</strong>'
            + '<button type="button" class="pag-close" data-bib-close>'
            + '<i class="icon icon-x-lg"></i></button>'
            + '</div>'
            + '<div class="pag-biblio-tools">'
            + '<input type="search" class="form-input" data-bib-q placeholder="' + esc(t('searchMedia')) + '">'
            + '<button type="button" class="btn btn-secondary btn-sm" data-bib-up>'
            + '<i class="icon icon-upload"></i> ' + esc(t('chooseImage')) + '</button>'
            + '</div>'
            + '<div class="pag-biblio-grid" data-bib-grid></div>'
            + '</div>';
        document.body.appendChild(caja);

        var file = document.createElement('input');
        file.type = 'file';
        file.accept = 'image/*';
        file.style.display = 'none';
        caja.appendChild(file);

        caja.addEventListener('click', function (e) {
            if (e.target === caja || e.target.closest('[data-bib-close]')) {
                caja.classList.remove('is-open');
                return;
            }
            var celda = e.target.closest('[data-bib-url]');
            if (celda && caja._alElegir) {
                caja._alElegir({ url: celda.dataset.bibUrl, display: celda.dataset.bibDisplay });
                caja.classList.remove('is-open');
            }
        });

        var buscador = caja.querySelector('[data-bib-q]');
        var reloj = null;
        buscador.addEventListener('input', function () {
            clearTimeout(reloj);
            reloj = setTimeout(function () { cargaBiblioteca(buscador.value); }, 250);
        });

        // Subir desde el propio selector: es donde el usuario ya está cuando
        // descubre que la imagen que busca no está.
        caja.querySelector('[data-bib-up]').addEventListener('click', function () { file.click(); });
        file.addEventListener('change', function () {
            if (!file.files || !file.files[0]) { return; }
            var grid = caja.querySelector('[data-bib-grid]');
            grid.innerHTML = '<p class="pag-ihint">' + esc(t('uploading')) + '</p>';
            upload(file.files[0]).then(function (res) {
                if (!res || !res.ok) { window.alert((res && res.error) || t('uploadError')); }
                cargaBiblioteca('');
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { caja.classList.remove('is-open'); }
        });

        return caja;
    }

    function cargaBiblioteca(q) {
        var grid = bibCaja.querySelector('[data-bib-grid]');
        grid.innerHTML = '<p class="pag-ihint">' + esc(t('saving')) + '</p>';

        fetch(cfg.libraryUrl + '?q=' + encodeURIComponent(q || ''), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var items = (d && d.items) || [];
                if (!items.length) {
                    grid.innerHTML = '<p class="pag-ihint">' + esc(t('mediaEmptyPick')) + '</p>';
                    return;
                }
                grid.innerHTML = items.map(function (m) {
                    return '<button type="button" class="pag-biblio-item"'
                        + ' data-bib-url="' + esc(m.url) + '" data-bib-display="' + esc(m.display) + '"'
                        + ' title="' + esc(m.name || '') + '">'
                        + '<img src="' + esc(m.display) + '" alt="" loading="lazy">'
                        + '<span>' + esc(m.name || '') + '</span></button>';
                }).join('');
            })
            .catch(function () {
                grid.innerHTML = '<p class="pag-ihint is-err">' + esc(t('saveError')) + '</p>';
            });
    }

    /** Texto enriquecido sin dependencias: `contenteditable` + execCommand. */
    function richText(block, field, value) {
        var box = el('div', 'pag-rt');
        var bar = el('div', 'pag-rtbar');

        [
            ['bold', '<strong>B</strong>', t('bold')],
            ['italic', '<em>I</em>', t('italic')],
            ['underline', '<u>U</u>', t('underline')],
            ['insertUnorderedList', '<i class="icon icon-list"></i>', t('bullets')],
            ['insertOrderedList', '<i class="icon icon-list-check"></i>', t('numbers')],
            ['createLink', '<i class="icon icon-link"></i>', t('link')],
            ['removeFormat', '<i class="icon icon-x-lg"></i>', t('clearFormat')]
        ].forEach(function (item) {
            var b = el('button', 'pag-rtbtn', item[1]);
            b.type  = 'button';
            b.title = item[2];
            // `mousedown` y no `click`: el clic quitaría el foco del área y con
            // él la selección sobre la que hay que aplicar el formato.
            b.addEventListener('mousedown', function (e) {
                e.preventDefault();
                area.focus();
                if (item[0] === 'createLink') {
                    var href = window.prompt(t('linkPrompt'), 'https://');
                    if (href) { document.execCommand('createLink', false, href); }
                } else {
                    document.execCommand(item[0], false, null);
                }
                setProp(block, field.name, area.innerHTML);
                scheduleRepaint();
            });
            bar.appendChild(b);
        });

        var area = el('div', 'pag-rtarea');
        area.contentEditable = 'true';
        area.innerHTML = value || '';
        area.addEventListener('input', function () {
            setProp(block, field.name, area.innerHTML);
            scheduleRepaint();
        });
        // Pegar siempre como texto plano: si no, entra el marcado de Word o de
        // otra web y el saneador del servidor se lo come igual, pero el usuario
        // ve una cosa mientras escribe y otra al publicar.
        area.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });

        box.appendChild(bar);
        box.appendChild(area);
        return box;
    }

    /** Repetidor: lista de filas con los sub-campos declarados. */
    function repeater(block, field, value) {
        var rows = Array.isArray(value) ? value : [];
        block.props[field.name] = rows;

        var box = el('div', 'pag-rep');

        rows.forEach(function (row, i) {
            var item = el('div', 'pag-repitem');
            item.appendChild(el('span', 'pag-repnum', String(i + 1)));

            var del = el('button', 'pag-repdel', '<i class="icon icon-trash"></i>');
            del.type  = 'button';
            del.title = t('removeItem');
            del.addEventListener('click', function () {
                rows.splice(i, 1);
                touch();
            });
            item.appendChild(del);

            (field.fields || []).forEach(function (sub) {
                // Cada fila del repetidor se edita con los mismos controles que
                // un bloque: se le pasa un `props` prestado que apunta a la fila.
                item.appendChild(fieldControl({ id: block.id + '-' + i, type: block.type, props: row }, sub));
            });

            box.appendChild(item);
        });

        var add = el('button', 'btn btn-secondary btn-sm',
            '<i class="icon icon-plus-circle"></i> ' + esc(field.addLabel || t('addItem')));
        add.type = 'button';
        add.addEventListener('click', function () {
            var fresh = {};
            (field.fields || []).forEach(function (sub) { fresh[sub.name] = ''; });
            rows.push(fresh);
            touch();
        });
        box.appendChild(add);

        return box;
    }

    // ------------------------------------------------------------- guardado

    function setStatus(key, badge) {
        if (!statusEl) { return; }
        statusEl.textContent = t(key);
        statusEl.className = 'badge ' + (badge || 'badge-light');
    }

    /**
     * Avisa de que la página sigue sin título propio y lleva a donde se pone.
     *
     * Se pinta como aviso en el sitio, no como `alert`: al guardar cada dos por
     * tres, un diálogo modal acabaría cerrándose sin leer. Solo una vez por
     * sesión de edición, para no repetirse en cada guardado.
     */
    var avisadoTitulo = false;
    function recuerdaTitulo() {
        if (avisadoTitulo) { return; }
        avisadoTitulo = true;

        root.dispatchEvent(new CustomEvent('pag:opensettings'));

        var msg = document.getElementById('pfMsg');
        var ajustesSucios = false;
        if (msg) {
            msg.textContent = t('needsTitle');
            msg.className = 'pag-ihint is-err';
        }
        setStatus('needsTitle', 'badge-warning');
    }

    /**
     * Envía al servidor y, si contesta **403**, pide un token nuevo y reintenta
     * UNA vez.
     *
     * El token de CSRF **rota en cada POST** (`Config\Security::$regenerate`),
     * y el editor se queda con el suyo cargado en memoria. Basta con que algo
     * más rote la cookie —otra pestaña abierta en la misma aplicación, el
     * formulario de una página publicada, una sesión larga— para que el que
     * tiene el editor deje de valer: el guardado responde 403 con una página
     * HTML de error, `r.json()` revienta, y a partir de ahí **todos** los
     * guardados fallan hasta recargar. Eso es lo que se veía como «no se pudo
     * guardar el contenido, inténtalo de nuevo» una y otra vez.
     *
     * `haz(hash)` reconstruye las opciones del `fetch` porque el token va
     * dentro del cuerpo, no solo en la cabecera: hay que rehacerlo con el nuevo.
     */
    function enviar(url, haz) {
        return fetch(url, haz(cfg.csrfHash)).then(function (r) {
            if (r.status !== 403) { return r; }

            return fetch(cfg.tokenUrl, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (t) { return t.json(); })
                .then(function (d) {
                    if (!d || !d.csrf) { return r; }
                    cfg.csrfHash = d.csrf;

                    return fetch(url, haz(cfg.csrfHash));
                })
                .catch(function () { return r; });
        });
    }

    /** Lee JSON aunque el cuerpo no lo sea (una página de error, un 500). */
    function leeJson(r) {
        return r.text().then(function (txt) {
            try {
                return JSON.parse(txt);
            } catch (e) {
                return { ok: false, error: t('saveError') };
            }
        });
    }

    function save() {
        // Devuelve la promesa: la vista por dispositivo encadena para abrir la
        // ventana DESPUÉS de guardar.
        if (saving) { return Promise.resolve(); }
        saving = true;
        setStatus('saving', 'badge-info');

        // Primero los ajustes y luego los bloques, en ese orden: el título
        // que se acaba de escribir tiene que estar guardado cuando el
        // servidor decida si hace falta recordarlo (`needsTitle`).
        return guardaAjustes().then(function () {
        return enviar(cfg.saveUrl, function (hash) {
            var payload = { blocks: tree };
            payload[cfg.csrfName] = hash;

            return {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': hash,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            };
        }).then(leeJson).then(function (res) {
            saving = false;
            if (res.csrf) { cfg.csrfHash = res.csrf; }
            if (!res.ok) {
                setStatus('saveError', 'badge-danger');
                window.alert(res.error || t('saveError'));
                return;
            }
            // El servidor devuelve el árbol **ya saneado**: el editor se
            // sincroniza con lo que de verdad quedó guardado, no con lo que
            // creía haber mandado.
            tree  = res.blocks || [];
            dirty = false;
            setStatus('saved', 'badge-success');
            render();
            renderTree();
            renderInspector();

            // Recordatorio: la página sigue con el título provisional. No se
            // interrumpe el guardado —lo que se estaba montando es el
            // contenido—, se avisa y se abre la pestaña donde se pone.
            if (res.needsTitle) { recuerdaTitulo(); }
        }).catch(function () {
            saving = false;
            setStatus('saveError', 'badge-danger');
        });
        });
    }

    /**
     * Sube un archivo. `maxWidth` es el ancho del ESPACIO donde va a caber:
     * una imagen que se pinta a 150 px no tiene por qué guardarse a 1300. El
     * servidor lo acota a su máximo, así que no se le cree a ciegas.
     */
    function upload(file, maxWidth) {
        return enviar(cfg.uploadUrl, function (hash) {
            // El FormData se rehace en cada intento: lleva el token dentro.
            var data = new FormData();
            data.append('file', file);
            data.append(cfg.csrfName, hash);
            if (maxWidth) { data.append('maxWidth', maxWidth); }

            return {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': hash, 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            };
        }).then(leeJson).then(function (res) {
            if (res && res.csrf) { cfg.csrfHash = res.csrf; }
            return res;
        }).catch(function () { return { ok: false, error: t('uploadError') }; });
    }

    function saveAsBlock(block) {
        var name = window.prompt(t('saveBlockName'), (REG[block.type] || {}).label || block.type);
        if (!name) { return; }

        enviar(cfg.blockUrl, function (hash) {
            var payload = { name: name, blocks: [block] };
            payload[cfg.csrfName] = hash;

            return {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': hash,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            };
        }).then(leeJson).then(function (res) {
            if (res.csrf) { cfg.csrfHash = res.csrf; }
            if (!res.ok) { window.alert(res.error || t('saveError')); return; }
            SAVED.push(res.block);
            addSavedToPalette(res.block);
            window.alert(t('blockSaved'));
        }).catch(function () { window.alert(t('saveError')); });
    }

    /** Añade el bloque recién guardado a la paleta sin recargar la página. */
    function addSavedToPalette(saved) {
        var group = root.querySelectorAll('.pag-pgroup');
        var list  = group.length ? group[group.length - 1].querySelector('.pag-plist') : null;
        if (!list) { return; }
        var empty = list.querySelector('.pag-pempty');
        if (empty) { empty.remove(); }

        var btn = el('button', 'pag-pitem', '<i class="icon icon-puzzle"></i><span>' + esc(saved.name) + '</span>');
        btn.type = 'button';
        btn.draggable = true;
        btn.dataset.addSaved = String(saved.id);
        bindPaletteItem(btn);
        list.appendChild(btn);
    }

    // --------------------------------------------------------------- paleta

    function bindPaletteItem(btn) {
        // Tres orígenes: un tipo de bloque suelto, una sección prearmada o un
        // bloque guardado por el usuario.
        function payload() {
            if (btn.dataset.addType) { return { type: btn.dataset.addType }; }
            if (btn.dataset.addSection) { return { section: btn.dataset.addSection }; }
            return { saved: parseInt(btn.dataset.addSaved, 10) };
        }

        btn.addEventListener('dragstart', function (e) {
            drag = payload();
            e.dataTransfer.effectAllowed = 'copy';
            try {
                e.dataTransfer.setData('text/plain',
                    btn.dataset.addType || btn.dataset.addSection || btn.dataset.addSaved || '');
            } catch (err) { /* Safari */ }
        });
        btn.addEventListener('dragend', function () { drag = null; clearOver(); });

        // Clic = añadir al final. Arrastrar no siempre es cómodo (ni posible en
        // pantallas táctiles), y sin esto la paleta sería inservible ahí.
        btn.addEventListener('click', function () {
            drag = payload();
            handleDrop(tree, tree.length);
        });
    }

    // ------------------------------------------------------------- arranque

    function el(tag, className, html) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (html != null) { node.innerHTML = html; }
        return node;
    }

    Array.prototype.forEach.call(root.querySelectorAll('.pag-pitem'), bindPaletteItem);

    // Pestañas de la columna izquierda: bloques / estructura.
    var tabs   = root.querySelectorAll('.pag-tab');
    var panels = root.querySelectorAll('.pag-panel');
    Array.prototype.forEach.call(tabs, function (tab) {
        tab.addEventListener('click', function () {
            Array.prototype.forEach.call(tabs, function (o) {
                var act = o === tab;
                o.classList[act ? 'add' : 'remove']('is-active');
                o.setAttribute('aria-selected', act ? 'true' : 'false');
            });
            Array.prototype.forEach.call(panels, function (p) {
                p.classList[p.dataset.panel === tab.dataset.tab ? 'add' : 'remove']('is-active');
            });
            // El árbol se repinta al mostrarlo: mientras está oculto no se
            // actualiza, así que podría venir de otra versión del documento.
            if (tab.dataset.tab === 'tree') { renderTree(); }
        });
    });

    // --------------------------------- ocultar y mostrar las columnas
    //
    // Se recuerda en `localStorage`: quien trabaja sin inspector no quiere
    // volver a cerrarlo en cada página. Es preferencia de interfaz, no del
    // documento, así que no viaja al servidor.
    function bindPanelToggle(btnId, cls, key) {
        var btn = document.getElementById(btnId);
        var editor = document.getElementById('pagEditor');
        if (!editor) { return; }

        function apply(oculto) {
            editor.classList[oculto ? 'add' : 'remove'](cls);
            if (btn) {
                btn.classList[oculto ? 'remove' : 'add']('is-on');
                btn.setAttribute('aria-pressed', oculto ? 'false' : 'true');
            }
            try { localStorage.setItem(key, oculto ? '1' : '0'); } catch (e) { /* modo privado */ }
        }

        var guardado = null;
        try { guardado = localStorage.getItem(key); } catch (e) { /* modo privado */ }
        if (guardado === '1') { apply(true); }

        if (btn) {
            btn.addEventListener('click', function () { apply(!editor.classList.contains(cls)); });
        }
        // La X de dentro del panel hace lo mismo que su botón de la barra.
        Array.prototype.forEach.call(root.querySelectorAll('.pag-close'), function (x) {
            if (x.dataset.close === (cls === 'is-noleft' ? 'left' : 'right')) {
                x.addEventListener('click', function () { apply(true); });
            }
        });
    }

    bindPanelToggle('pagToggleLeft', 'is-noleft', 'pagPanelLeft');
    bindPanelToggle('pagToggleRight', 'is-noright', 'pagPanelRight');

    // ------------------------------ ajustes de la página, sin salir de aquí
    //
    // Los mismos que la pantalla `ajustes/{id}`, en la segunda pestaña del
    // inspector. Se guardan por su propia ruta JSON: cambiar el ancho o el
    // color no debería obligar a abandonar la página que se está montando y
    // volver a entrar.
    (function pageSettings() {
        var itabs = root.querySelectorAll('.pag-itab');
        var ipanes = root.querySelectorAll('.pag-ipane');

        /** Deja activa una de las dos pestañas del inspector. */
        function abreTab(cual) {
            Array.prototype.forEach.call(itabs, function (o) {
                var act = o.dataset.itab === cual;
                o.classList[act ? 'add' : 'remove']('is-active');
                o.setAttribute('aria-selected', act ? 'true' : 'false');
            });
            Array.prototype.forEach.call(ipanes, function (p) {
                p.classList[p.dataset.ipane === cual ? 'add' : 'remove']('is-active');
            });
        }

        Array.prototype.forEach.call(itabs, function (tab) {
            tab.addEventListener('click', function () { abreTab(tab.dataset.itab); });
        });

        /**
         * El botón «Ajustes» de la barra superior. Ya no sale del editor —esa
         * pantalla se retiró—: abre la pestaña «Página» y, si el panel derecho
         * estaba plegado, lo despliega. Sin lo segundo el botón parecería roto.
         */
        var abrir = document.getElementById('pagOpenSettings');
        if (abrir) {
            abrir.addEventListener('click', function () {
                var editor = document.getElementById('pagEditor');
                if (editor && editor.classList.contains('is-noright')) {
                    var t = document.getElementById('pagToggleRight');
                    if (t) { t.click(); }
                }
                abreTab('page');
                var titulo = document.getElementById('pfTitle');
                if (titulo) { titulo.focus(); }
            });
        }

        // Lo mismo desde fuera: el recordatorio del título abre esta pestaña.
        root.addEventListener('pag:opensettings', function () {
            if (abrir) { abrir.click(); }
        });

        var form = document.getElementById('pagPageForm');
        if (!form) { return; }

        var msg = document.getElementById('pfMsg');
        var ajustesSucios = false;

        /**
         * Refleja en el lienzo lo que se acaba de cambiar. El CSS de la página
         * se genera en el servidor, pero las tres medidas que importan son
         * variables: basta reescribirlas para ver el efecto sin recargar.
         */
        function applyToCanvas() {
            var acento = form.elements.accent ? form.elements.accent.value : '';
            var cont   = parseInt(form.elements.contentWidth.value, 10) || 1100;
            var ancho  = parseInt(form.elements.wideWidth.value, 10) || Math.round(cont * 1.25);

            cont  = Math.max(360, Math.min(1600, cont));
            ancho = Math.max(cont, Math.min(1920, ancho));

            canvas.style.setProperty('--pg-content', cont + 'px');
            canvas.style.setProperty('--pg-wide', ancho + 'px');
            if (hex(acento)) { canvas.style.setProperty('--pg-accent', acento); }
        }

        ['input', 'change'].forEach(function (ev) {
            form.addEventListener(ev, function (e) {
                // Cambiar el título dejaba el estado en «Guardado»: parecía
                // que ya estaba, y al salir se perdía.
                ajustesSucios = true;
                dirty = true;
                setStatus('unsaved', 'badge-warning');
                if (['accent', 'contentWidth', 'wideWidth'].indexOf(e.target.name) >= 0) {
                    applyToCanvas();
                }
            });
        });
        applyToCanvas();

        // Imagen para compartir: se sube por la misma ruta que las del editor y
        // solo se guarda su referencia.
        var pick = document.getElementById('pfOgPick');
        if (pick) {
            var file = document.createElement('input');
            file.type = 'file';
            file.accept = 'image/*';
            pick.addEventListener('click', function () { file.click(); });
            file.addEventListener('change', function () {
                if (!file.files || !file.files[0]) { return; }
                pick.disabled = true;
                pick.textContent = t('uploading');
                upload(file.files[0]).then(function (res) {
                    pick.disabled = false;
                    pick.innerHTML = '<i class="icon icon-upload"></i> ' + esc(t('chooseImage'));
                    if (!res || !res.ok) { window.alert((res && res.error) || t('uploadError')); return; }
                    document.getElementById('pfOgImage').value = res.url;
                    var img = document.getElementById('pfOgPreview');
                    img.src = res.display || res.url;
                    img.style.display = '';
                });
            });
        }

        /**
         * Manda los ajustes y devuelve la promesa.
         *
         * La usa el botón de la barra —que guarda TODO— y también el envío
         * del propio formulario (la tecla Intro dentro de un campo).
         */
        function enviaAjustes() {
            if (msg) { msg.textContent = t('saving'); msg.className = 'pag-ihint'; }

            return enviar(form.dataset.url, function (hash) {
                var datos = new FormData(form);
                datos.append(cfg.csrfName, hash);

                return {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': hash, 'X-Requested-With': 'XMLHttpRequest' },
                    body: datos
                };
            }).then(leeJson).then(function (res) {
                if (res.csrf) { cfg.csrfHash = res.csrf; }
                if (!res.ok) {
                    if (msg) { msg.textContent = res.error || t('saveError'); msg.className = 'pag-ihint is-err'; }
                    return;
                }
                // La dirección puede haber cambiado (se hace única dentro de la
                // empresa), así que se recoge la que devolvió el servidor.
                if (form.elements.slug) { form.elements.slug.value = res.slug; }
                // La dirección pública lleva el slug dentro: si el servidor lo
                // cambió (se hace único), el enlace se queda apuntando a donde
                // ya no está.
                var publica = document.getElementById('pfPublicUrl');
                if (publica && res.slug) {
                    publica.href = publica.dataset.base + encodeURIComponent(res.slug);
                    publica.firstChild.nodeValue = publica.href + ' ';
                }
                var titulo = document.querySelector('.pag-toptitle');
                if (titulo) { titulo.textContent = res.title; }
                if (msg) { msg.textContent = t('pageSaved'); msg.className = 'pag-ihint is-ok'; }
                ajustesSucios = false;
                setTimeout(function () { if (msg) { msg.textContent = ''; } }, 3000);
            }).catch(function () {
                if (msg) { msg.textContent = t('saveError'); msg.className = 'pag-ihint is-err'; }
            });
        }

        // Se expone para el botón de la barra: es el que guarda todo.
        guardaAjustes = enviaAjustes;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            enviaAjustes();
        });
    }());

    // ------------------------------------- vista previa por dispositivo
    //
    // Abre la página renderizada por el SERVIDOR en una ventana del ancho
    // elegido. Se hace así y no encogiendo el lienzo porque las `@media` miran
    // el ancho de la ventana, no el del elemento: estrechar el lienzo cambiaría
    // su tamaño sin disparar ni una regla responsive, y enseñaría una mentira.
    //
    // Antes de abrir se guarda, para que lo que se vea sea lo que hay en
    // pantalla y no lo último guardado.
    (function devicePreview() {
        var enlace = document.getElementById('pagPreview');
        if (!enlace) { return; }

        Array.prototype.forEach.call(root.querySelectorAll('.pag-device'), function (btn) {
            btn.addEventListener('click', function () {
                var w = parseInt(btn.dataset.w, 10) || 390;
                var h = parseInt(btn.dataset.h, 10) || 844;
                var abrir = function () {
                    window.open(enlace.href, 'pagDevice' + w,
                        'width=' + w + ',height=' + h + ',menubar=no,toolbar=no,location=no');
                };
                // Con cambios sin guardar, primero se guarda: si no, la ventana
                // mostraría la versión anterior y parecería que no funcionó.
                if (dirty) { save().then(abrir); } else { abrir(); }
            });
        });
    }());

    /* =====================================================================
       CÓDIGO DE LA PÁGINA — copiarla entera y pegarla en otra
       =====================================================================
       Una página de este componente NO es HTML guardado: es un árbol de
       bloques. Eso la hace portable de verdad, y este cuadro es lo único que
       hacía falta para aprovecharlo — enseñarla tal cual, copiarla y pegarla
       en otra página, que queda igual.

       Lo que se pega **no se cree**. Al guardar pasa por el mismo
       `PageService::sanitizeTree()` que todo lo demás, que reconstruye el árbol
       campo por campo contra el registro: un tipo inventado, una propiedad de
       más o un `href="javascript:"` pegados aquí se quedan fuera igual que si
       llegaran por cualquier otra vía. Aquí solo se comprueba la FORMA, para
       poder avisar con un mensaje claro en vez de dejar guardar algo que el
       servidor tirará en silencio.
       ===================================================================== */
    (function () {
        var abrir  = document.getElementById('pagCode');
        var caja   = document.getElementById('pagCodeBox');
        var texto  = document.getElementById('pagCodeText');
        var msg    = document.getElementById('pagCodeMsg');
        var copiar = document.getElementById('pagCodeCopy');
        var aplicar = document.getElementById('pagCodeApply');
        if (!abrir || !caja || !texto) { return; }

        var form = document.getElementById('pagPageForm');

        /** Los ajustes que se llevan de una página a otra. */
        function ajustes() {
            if (!form) { return {}; }
            var e = form.elements;
            return {
                accent: e.accent ? e.accent.value : '',
                contentWidth: e.contentWidth ? e.contentWidth.value : '',
                wideWidth: e.wideWidth ? e.wideWidth.value : '',
                header: e.header && e.header.checked ? '1' : '0',
                footer: e.footer && e.footer.checked ? '1' : '0'
            };
        }

        function aviso(t, clase) {
            if (!msg) { return; }
            msg.textContent = t;
            msg.className = 'pag-ihint' + (clase ? ' ' + clase : '');
        }

        function abrirCaja() {
            // El título y la dirección NO viajan: identifican a la página, y
            // dos páginas de la misma empresa no pueden compartir dirección.
            texto.value = JSON.stringify({
                pag: 1,
                blocks: tree,
                settings: ajustes()
            }, null, 2);
            aviso('');
            caja.hidden = false;
            texto.focus();
            texto.select();
        }

        function cerrar() { caja.hidden = true; }

        abrir.addEventListener('click', abrirCaja);
        Array.prototype.forEach.call(caja.querySelectorAll('[data-close-code]'), function (b) {
            b.addEventListener('click', cerrar);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !caja.hidden) { cerrar(); }
        });

        if (copiar) {
            copiar.addEventListener('click', function () {
                texto.select();
                // `navigator.clipboard` no existe fuera de un origen seguro, y
                // el editor se usa en `localhost` **y** por IP en la red local,
                // donde no lo hay: sin el respaldo, copiar fallaría justo en la
                // mitad de los casos.
                var hecho = function () { aviso(t('codeCopied'), 'is-ok'); };
                var falla = function () { aviso(t('codeCopyManual'), 'is-err'); };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(texto.value).then(hecho, function () {
                        try { document.execCommand('copy') ? hecho() : falla(); } catch (err) { falla(); }
                    });
                    return;
                }
                try { document.execCommand('copy') ? hecho() : falla(); } catch (err) { falla(); }
            });
        }

        if (aplicar) {
            aplicar.addEventListener('click', function () {
                var datos;
                try {
                    datos = JSON.parse(texto.value);
                } catch (err) {
                    aviso(t('codeBadJson'), 'is-err');
                    return;
                }

                if (!datos || datos.pag !== 1 || !Array.isArray(datos.blocks)) {
                    aviso(t('codeBadShape'), 'is-err');
                    return;
                }

                var limpios = datos.blocks.filter(function (b) {
                    return b && typeof b === 'object' && typeof b.type === 'string';
                });
                if (!limpios.length) {
                    aviso(t('codeEmpty'), 'is-err');
                    return;
                }
                if (tree.length && !window.confirm(t('codeConfirm'))) { return; }

                // Ids nuevos: pegar la misma página dos veces no puede dejar
                // dos bloques con el mismo identificador (el formulario los usa
                // para saber cuál se envió).
                tree.length = 0;
                limpios.forEach(function (b) { tree.push(cloneBlock(b)); });
                selectedId = null;

                // Los ajustes de aspecto se dejan puestos en su pestaña, no se
                // guardan solos: tienen su propio botón y su propia ruta.
                var s = datos.settings;
                if (form && s && typeof s === 'object') {
                    if (form.elements.accent && hex(s.accent)) { form.elements.accent.value = s.accent; }
                    ['contentWidth', 'wideWidth'].forEach(function (k) {
                        var n = parseInt(s[k], 10);
                        if (form.elements[k] && n > 0) { form.elements[k].value = n; }
                    });
                    ['header', 'footer'].forEach(function (k) {
                        if (form.elements[k] && (s[k] === '0' || s[k] === '1')) {
                            form.elements[k].checked = s[k] === '1';
                        }
                    });
                    form.dispatchEvent(new Event('change', { bubbles: true }));
                }

                cerrar();
                touch();
                setStatus('unsaved', 'badge-warning');
            });
        }
    }());

    if (saveBtn) { saveBtn.addEventListener('click', save); }

    // Ctrl/Cmd + S guarda, como en cualquier editor.
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            save();
        }
    });

    // Clic fuera de un bloque = deseleccionar.
    canvas.addEventListener('click', function () {
        if (selectedId) { select(null); }
    });

    // Salir con cambios sin guardar avisa.
    window.addEventListener('beforeunload', function (e) {
        if (!dirty) { return; }
        e.preventDefault();
        e.returnValue = '';
    });

    render();
    renderTree();
    renderInspector();
    setStatus('saved', 'badge-light');
}());
