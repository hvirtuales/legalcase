/*
 * Editor de formularios: arrastrar y soltar, inspector y guardado sin recargar.
 *
 * Sin dependencias, sin CDN y sin plantillas: todo se pinta con `document`.
 *
 * Tres cosas que conviene tener claras al tocar este archivo:
 *
 *  1. El REGISTRO manda. La paleta y el inspector se generan desde
 *     `FieldRegistry::forJs()`; aquí no hay ninguna lista de tipos ni de
 *     propiedades. Añadir un campo al registro lo hace aparecer solo.
 *  2. El SERVIDOR devuelve el árbol ya saneado en cada guardado y el editor se
 *     sincroniza con eso: lo que se ve es lo que de verdad quedó guardado.
 *  3. El token de CSRF ROTA en cada POST. Si otra pestaña hace uno, el que
 *     tenemos en memoria deja de valer y el guardado responde 403 con una
 *     página HTML. Por eso todo sale por `enviar()`, que pide uno fresco y
 *     reintenta una vez, y las respuestas se leen con `leeJson()`.
 */
(function () {
  'use strict';

  function data(id) {
    var el = document.getElementById(id);
    try { return el ? JSON.parse(el.textContent) : {}; } catch (e) { return {}; }
  }

  var REG  = data('frmRegistry');
  var META = data('frmMeta');
  var L    = data('frmLang');
  var schema   = data('frmSchema');
  var settings = data('frmSettings');

  if (!schema.pages || !schema.pages.length) {
    schema = { pages: [{ id: 'p_' + rnd(), title: 'Paso 1', description: '' }], fields: [] };
  }
  if (!schema.fields) { schema.fields = []; }

  var sel = null;          // id del campo seleccionado
  var page = 0;            // índice de la página activa
  var dirty = false;
  var csrf = META.csrfHash;

  /* ------------------------------------------------------------- utilidades */

  function rnd() { return Math.random().toString(36).slice(2, 8); }

  function el(tag, attrs, kids) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') { node.textContent = attrs[k]; }
      else if (k === 'html') { node.innerHTML = attrs[k]; }
      else if (k === 'on') { Object.keys(attrs.on).forEach(function (ev) { node.addEventListener(ev, attrs.on[ev]); }); }
      else if (attrs[k] === true) { node.setAttribute(k, ''); }
      else if (attrs[k] !== false && attrs[k] != null) { node.setAttribute(k, attrs[k]); }
    });
    (kids || []).forEach(function (c) { if (c) { node.appendChild(c); } });
    return node;
  }

  function q(sel2) { return document.querySelector(sel2); }

  function markDirty() {
    dirty = true;
    var s = q('[data-frm-state]');
    if (s) { s.setAttribute('data-frm-state', 'dirty'); s.textContent = L.unsaved; }
    // Toda mutación pasa por aquí, así que aquí es donde se apunta al historial.
    scheduleHistory();
  }

  function markSaved(ok) {
    dirty = false;
    var s = q('[data-frm-state]');
    if (!s) { return; }
    s.setAttribute('data-frm-state', ok ? 'saved' : 'error');
    s.textContent = ok ? L.saved : L.saveFailed;
  }

  /*
   * `page` es el índice de la página activa, o la cadena 'all' cuando se están
   * viendo todas de una vez. Ese modo existe porque en un formulario de tres
   * pasos hace falta poder mirar el conjunto para mover un campo de sitio.
   */
  function allMode() { return page === 'all'; }

  function pageId() {
    if (allMode()) { return schema.pages[0].id; }
    return (schema.pages[page] || schema.pages[0]).id;
  }

  function fieldsOfPage() {
    if (allMode()) { return schema.fields.slice(); }
    var id = pageId();
    var first = schema.pages[0].id;
    return schema.fields.filter(function (f) { return (f.pageId || first) === id; });
  }

  function pageIndexOf(id) {
    for (var i = 0; i < schema.pages.length; i++) {
      if (schema.pages[i].id === id) { return i; }
    }
    return 0;
  }

  function countFields(pageIdent) {
    var first = schema.pages[0].id;
    return schema.fields.filter(function (f) { return (f.pageId || first) === pageIdent; }).length;
  }

  /** «3 campos», «1 campo» o «sin campos»: el plural no se apaña con una `s`. */
  function fieldsLabel(n) {
    if (n === 0) { return L.noFields; }
    if (n === 1) { return L.oneField; }
    return L.nFields.replace('{0}', n);
  }

  /* ------------------------------------------------- deshacer y rehacer */

  /*
   * Historial de hasta 30 pasos, como el de la app de la que nace esto.
   *
   * Se guarda una instantánea del árbol y de los ajustes, no un registro de
   * operaciones: es más tosco, pero no hay forma de que deshacer deje el estado
   * a medias. El apunte va RETRASADO (400 ms) para que escribir una etiqueta no
   * produzca una entrada por tecla.
   */
  var history = [];
  var histAt = -1;
  var histTimer = null;

  function snapshot() {
    return JSON.stringify({ schema: schema, settings: settings, meta: meta });
  }

  function pushHistory() {
    var snap = snapshot();
    if (histAt >= 0 && history[histAt] === snap) { return; }

    history = history.slice(0, histAt + 1);
    history.push(snap);
    if (history.length > 30) { history.shift(); }
    histAt = history.length - 1;
    paintHistoryButtons();
  }

  function scheduleHistory() {
    if (histTimer) { clearTimeout(histTimer); }
    histTimer = setTimeout(pushHistory, 400);
  }

  function restore(step) {
    if (step < 0 || step >= history.length) { return; }
    var snap = JSON.parse(history[step]);

    schema = snap.schema;
    settings = snap.settings;
    // `meta` se rellena, no se sustituye: el resto del guion tiene su referencia.
    Object.keys(snap.meta).forEach(function (k) { meta[k] = snap.meta[k]; });

    histAt = step;
    if (page !== 'all' && page >= schema.pages.length) { page = 0; }
    if (sel && !findField(sel)) { sel = null; }

    dirty = true;
    var s = q('[data-frm-state]');
    if (s) { s.setAttribute('data-frm-state', 'dirty'); s.textContent = L.unsaved; }

    paintAll();
    paintHistoryButtons();
  }

  function paintHistoryButtons() {
    var u = q('[data-frm-undo]');
    var r = q('[data-frm-redo]');
    if (u) { u.disabled = histAt <= 0; }
    if (r) { r.disabled = histAt >= history.length - 1; }
  }

  function findField(id) {
    for (var i = 0; i < schema.fields.length; i++) {
      if (schema.fields[i].id === id) { return schema.fields[i]; }
    }
    return null;
  }

  /* ------------------------------------------------------------- la paleta */

  function paintPalette() {
    var box = q('[data-frm-palette]');
    box.innerHTML = '';

    // El orden es el de los grupos, aunque los rótulos se pinten discretos:
    // afinidad primero, taxonomía después.
    Object.keys(META.groups).forEach(function (group) {
      var types = Object.keys(REG).filter(function (t) { return REG[t].group === group; });
      if (!types.length) { return; }

      box.appendChild(el('div', { class: 'frm-group-title', text: META.groups[group] }));

      types.forEach(function (type) {
        var def = REG[type];
        var chip = el('div', {
          class: 'frm-chip', draggable: 'true', title: def.hint,
          on: {
            click: function () { addField(type); },
            dragstart: function (ev) {
              ev.dataTransfer.setData('text/frm-type', type);
              ev.dataTransfer.effectAllowed = 'copy';
            }
          }
        }, [
          el('i', { class: 'icon ' + def.icon }),
          el('span', { text: def.label })
        ]);
        box.appendChild(chip);
      });
    });
  }

  function addField(type, index) {
    var def = REG[type];
    if (!def) { return; }

    var f = { id: 'f_' + rnd() + rnd().slice(0, 4), pageId: pageId(), type: type, width: '100', label: def.label };

    // Un campo de opciones nace con tres: uno vacío no se puede ni probar.
    if (type === 'select' || type === 'radio' || type === 'checkbox') {
      f.options = [1, 2, 3].map(function (n) {
        return { id: 'o_' + rnd(), label: 'Opción ' + n, value: 'opcion_' + n };
      });
    }
    if (type === 'rating') { f.maxStars = 5; f.ratingIcon = 'star'; }
    if (type === 'slider') { f.min = 0; f.max = 100; f.step = 5; f.defaultValue = 50; }
    if (type === 'nps') { f.lowLabel = '0'; f.highLabel = '10'; }
    if (type === 'heading') { f.headingLevel = 'h2'; }
    if (type === 'paragraph') { f.alertType = 'info'; f.content = ''; }

    if (index == null || index < 0) {
      schema.fields.push(f);
    } else {
      // El índice es el de la PAGINA; hay que traducirlo al del árbol.
      var list = fieldsOfPage();
      var anchor = list[index];
      var at = anchor ? schema.fields.indexOf(anchor) : schema.fields.length;
      schema.fields.splice(at, 0, f);
    }

    sel = f.id;
    markDirty();
    paintAll();
  }

  /* ------------------------------------------------ páginas y barra de pasos */

  function paintPageBar() {
    var bar = q('[data-frm-pagebar]');
    bar.innerHTML = '';

    // Cada pestaña lleva SU NUMERO DE CAMPOS: es el dato que dice de un vistazo
    // si un paso se quedó vacío o cargado de más, y no estaba en ninguna parte.
    schema.pages.forEach(function (p, i) {
      bar.appendChild(el('div', {
        class: 'frm-page-tab' + (i === page ? ' is-on' : ''),
        on: { click: function () { page = i; sel = null; paintAll(); } }
      }, [
        el('span', { text: (i + 1) + '. ' + p.title }),
        el('span', { class: 'frm-page-n', text: String(countFields(p.id)) })
      ]));
    });

    bar.appendChild(el('div', {
      class: 'frm-page-tab frm-page-add', text: '+ ' + L.addPage,
      on: { click: addPage }
    }));

    // «Ver todas»: en un formulario de tres pasos hace falta mirar el conjunto
    // para mover un campo de una página a otra.
    if (schema.pages.length > 1) {
      bar.appendChild(el('div', {
        class: 'frm-page-tab frm-page-all' + (allMode() ? ' is-on' : ''),
        text: L.allPages,
        on: { click: function () { page = allMode() ? 0 : 'all'; sel = null; paintAll(); } }
      }));
    }
  }

  function addPage() {
    schema.pages.push({
      id: 'p_' + rnd(),
      title: L.newPageName.replace('{0}', schema.pages.length + 1),
      description: ''
    });
    page = schema.pages.length - 1;
    sel = null;
    markDirty();
    paintAll();
  }

  /**
   * Ajustes de la página activa, junto al lienzo: nombre, instrucciones, orden,
   * duplicar y eliminar. Estaban solo en la pestaña «Formulario», que es donde
   * nadie los busca mientras coloca campos.
   */
  function paintPageTools() {
    var box = q('[data-frm-pagetools]');
    box.innerHTML = '';

    if (allMode()) {
      box.appendChild(el('p', { class: 'frm-hint', text: L.allPages }));
      return;
    }

    var p = schema.pages[page];
    if (!p) { return; }

    box.appendChild(el('input', {
      class: 'frm-page-title', type: 'text', value: p.title, placeholder: L.pageTitle,
      on: { input: function (e) { p.title = e.target.value; markDirty(); paintPageBar(); paintStructure(); } }
    }));

    box.appendChild(el('input', {
      class: 'frm-page-desc', type: 'text', value: p.description || '', placeholder: L.pageDesc,
      on: { input: function (e) { p.description = e.target.value; markDirty(); } }
    }));

    var acts = el('div', { class: 'frm-page-acts' });

    acts.appendChild(tool('icon-chevron-left', L.pageBefore, function () { movePage(page - 1); }));
    acts.appendChild(tool('icon-chevron-right', L.pageAfter, function () { movePage(page + 1); }));
    acts.appendChild(tool('icon-arrow-repeat', L.pageDuplicate, duplicatePage));

    if (schema.pages.length > 1) {
      acts.appendChild(tool('icon-trash', L.deletePage, deletePage, 'frm-del'));
    }

    box.appendChild(acts);
  }

  function movePage(to) {
    if (allMode() || to < 0 || to >= schema.pages.length) { return; }
    var moved = schema.pages.splice(page, 1)[0];
    schema.pages.splice(to, 0, moved);
    page = to;
    markDirty();
    paintAll();
  }

  /** Duplica la página CON SUS CAMPOS, que es lo que se espera al duplicar. */
  function duplicatePage() {
    if (allMode()) { return; }
    var src = schema.pages[page];
    var copy = {
      id: 'p_' + rnd(),
      title: L.pageCopy.replace('{0}', src.title),
      description: src.description || ''
    };

    var clones = schema.fields
      .filter(function (f) { return (f.pageId || schema.pages[0].id) === src.id; })
      .map(function (f) {
        var c = JSON.parse(JSON.stringify(f));
        c.id = 'f_' + rnd() + rnd().slice(0, 4);
        c.pageId = copy.id;
        // Una regla que apunte a un campo de la página original se queda como
        // estaba: sigue existiendo y sigue siendo el que decide.
        return c;
      });

    schema.pages.splice(page + 1, 0, copy);
    schema.fields = schema.fields.concat(clones);
    page = page + 1;
    sel = null;
    markDirty();
    paintAll();
  }

  function deletePage() {
    if (allMode() || schema.pages.length <= 1) { return; }
    if (!window.confirm(L.confirmDeletePage)) { return; }

    var gone = schema.pages.splice(page, 1)[0];
    var first = schema.pages[0].id;
    schema.fields.forEach(function (f) { if (f.pageId === gone.id) { f.pageId = first; } });
    page = Math.max(0, page - 1);
    sel = null;
    markDirty();
    paintAll();
  }

  /** Título y descripción del formulario, editables donde se ven. */
  function paintPaperHead() {
    var box = q('[data-frm-paperhead]');
    box.innerHTML = '';

    box.appendChild(el('input', {
      class: 'frm-form-title', type: 'text', value: meta.title, placeholder: L.canvasTitle,
      on: {
        input: function (e) {
          meta.title = e.target.value;
          markDirty();
          q('#frmTitleText').textContent = e.target.value;
        }
      }
    }));

    box.appendChild(el('textarea', {
      class: 'frm-form-desc', rows: 2, placeholder: L.canvasDesc,
      text: settings.description || '',
      on: { input: function (e) { settings.description = e.target.value; markDirty(); } }
    }));
  }

  /** «12 campos · 3 páginas» bajo el lienzo. */
  function paintCount() {
    var box = q('[data-frm-count]');
    var n = schema.pages.length;
    box.textContent = fieldsLabel(schema.fields.length) + ' · '
      + (n === 1 ? L.onePage : L.nPages.replace('{0}', n));
  }

  /* ------------------------------------------------------------- el lienzo */

  function paintCanvas() {
    var list = q('[data-frm-list]');
    list.innerHTML = '';

    var fields = fieldsOfPage();

    if (!fields.length) {
      var empty = el('div', { class: 'frm-empty', text: L.emptyCanvas });
      dropTarget(empty, 0, 'is-over');
      list.appendChild(empty);
      return;
    }

    fields.forEach(function (f, i) {
      list.appendChild(node(f, i));
    });

    // Una sola zona al final, para soltar al cierre. Entre campo y campo NO hay
    // separadores: eran elementos a todo el ancho y rompían la fila, así que dos
    // campos al 1/2 nunca podían compartirla. Ahora el sitio donde cae lo decide
    // el propio campo, según por qué lado se suelte (ver `node()`).
    list.appendChild(gap(fields.length));
  }

  /** Zona de soltado al final de la página. */
  function gap(index) {
    var g = el('div', { class: 'frm-gap' });
    dropTarget(g, index, 'is-over');
    return g;
  }

  function dropTarget(node2, index, cls) {
    node2.addEventListener('dragover', function (ev) {
      ev.preventDefault();
      node2.classList.add(cls);
    });
    node2.addEventListener('dragleave', function () { node2.classList.remove(cls); });
    node2.addEventListener('drop', function (ev) {
      ev.preventDefault();
      node2.classList.remove(cls);

      var type = ev.dataTransfer.getData('text/frm-type');
      if (type) { addField(type, index); return; }

      var moving = ev.dataTransfer.getData('text/frm-move');
      if (moving) { moveField(moving, index); }
    });
  }

  function moveField(id, index) {
    var f = findField(id);
    if (!f) { return; }

    var list = fieldsOfPage();
    var anchor = list[index];

    schema.fields.splice(schema.fields.indexOf(f), 1);
    f.pageId = pageId();

    var at = anchor ? schema.fields.indexOf(anchor) : schema.fields.length;
    schema.fields.splice(at < 0 ? schema.fields.length : at, 0, f);

    markDirty();
    paintAll();
  }

  /** Un campo en el lienzo: barra de herramientas + su vista. */
  function node(f, i) {
    var def = REG[f.type] || {};
    var box = el('div', {
      class: 'frm-node w' + (f.width || '100') + (sel === f.id ? ' is-on' : '')
        + (f.conditionalRule && f.conditionalRule.enabled ? ' is-hidden-by-logic' : ''),
      draggable: 'true',
      on: {
        click: function (ev) { ev.stopPropagation(); sel = f.id; paintAll(); },
        dragstart: function (ev) {
          ev.dataTransfer.setData('text/frm-move', f.id);
          ev.dataTransfer.effectAllowed = 'move';
          box.classList.add('is-dragging');
        },
        dragend: function () { box.classList.remove('is-dragging'); },

        /*
         * El propio campo es la zona de soltado: por la mitad izquierda cae
         * ANTES y por la derecha DESPUES. Antes había un separador entre cada
         * dos campos, pero ocupaba la fila entera y por eso dos campos al 1/2
         * jamás compartían fila. Sin separadores, la fila se reparte de verdad.
         */
        dragover: function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          box.classList.toggle('is-after', despuesDe(ev, box));
          box.classList.toggle('is-before', !despuesDe(ev, box));
        },
        dragleave: function () { box.classList.remove('is-before', 'is-after'); },
        drop: function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          var at = i + (despuesDe(ev, box) ? 1 : 0);
          box.classList.remove('is-before', 'is-after');

          var type = ev.dataTransfer.getData('text/frm-type');
          if (type) { addField(type, at); return; }

          var moving = ev.dataTransfer.getData('text/frm-move');
          if (moving) { moveField(moving, at); }
        }
      }
    });

    // En «ver todas», cada campo dice a qué paso pertenece: sin eso la vista de
    // conjunto es un revoltijo.
    box.appendChild(el('span', {
      class: 'frm-badge',
      text: allMode()
        ? (pageIndexOf(f.pageId || schema.pages[0].id) + 1) + ' · ' + (def.label || f.type)
        : (def.label || f.type)
    }));

    var tools = el('div', { class: 'frm-tools' });

    /*
     * ANCHO DEL CAMPO, en la propia barra del campo. Es lo que permite poner
     * VARIOS CAMPOS EN UNA FILA (dos al 1/2, tres al 1/3) y estaba escondido en
     * una pestaña del inspector, así que en la práctica no existía: la app de
     * origen lo tenía aquí, y aquí es donde se usa.
     */
    var widths = el('div', { class: 'frm-widths' });
    [
      { v: '100', t: '1/1', title: L.widthFull },
      { v: '50', t: '1/2', title: L.widthHalf },
      { v: '33', t: '1/3', title: L.widthThird }
    ].forEach(function (w) {
      widths.appendChild(el('button', {
        type: 'button', title: w.title, text: w.t,
        class: 'frm-w' + ((f.width || '100') === w.v ? ' is-on' : ''),
        on: {
          click: function (ev) {
            ev.stopPropagation();
            f.width = w.v;
            markDirty();
            paintCanvas();
            if (sel === f.id) { paintInspector(); }
          }
        }
      }));
    });
    tools.appendChild(widths);

    tools.appendChild(tool('icon-arrow-up-circle', L.moveUp, function () { swap(i, i - 1); }));
    tools.appendChild(tool('icon-arrow-down-circle', L.moveDown, function () { swap(i, i + 1); }));
    tools.appendChild(tool('icon-arrow-repeat', L.duplicateField, function () {
      var copy = JSON.parse(JSON.stringify(f));
      copy.id = 'f_' + rnd() + rnd().slice(0, 4);
      schema.fields.splice(schema.fields.indexOf(f) + 1, 0, copy);
      sel = copy.id;
      markDirty();
      paintAll();
    }));
    tools.appendChild(tool('icon-trash', L.deleteField, function () {
      schema.fields.splice(schema.fields.indexOf(f), 1);
      if (sel === f.id) { sel = null; }
      markDirty();
      paintAll();
    }, 'frm-del'));
    box.appendChild(tools);

    box.appendChild(preview(f));
    return box;
  }

  /**
   * ¿El puntero está en la mitad derecha del campo?
   *
   * Se mira el eje horizontal porque los campos comparten fila: en una fila de
   * tres, «antes» y «después» son izquierda y derecha, no arriba y abajo. Con un
   * campo a fila completa el resultado es el mismo de siempre.
   */
  function despuesDe(ev, box) {
    var r = box.getBoundingClientRect();
    return (ev.clientX - r.left) > r.width / 2;
  }

  function tool(icon, title, fn, extra) {
    return el('button', {
      type: 'button', title: title, class: extra || '',
      html: '<i class="icon ' + icon + '"></i>',
      on: { click: function (ev) { ev.stopPropagation(); fn(); } }
    });
  }

  function swap(from, to) {
    var list = fieldsOfPage();
    if (to < 0 || to >= list.length) { return; }
    var a = schema.fields.indexOf(list[from]);
    var b = schema.fields.indexOf(list[to]);
    var moved = schema.fields.splice(a, 1)[0];
    schema.fields.splice(b, 0, moved);
    markDirty();
    paintAll();
  }

  /* --------------------------------------------------- vista previa del campo */

  /*
   * Es una APROXIMACION de edición: usa las clases `ff-*` de la hoja del
   * formulario publicado, que el layout incrusta, así que se parece mucho. La
   * salida buena la produce el `Renderer` en el servidor, y por eso el botón
   * «Ver» abre la página real en vez de fiarse de esto.
   */
  function preview(f) {
    var wrap = el('div', { style: 'pointer-events:none' });
    var t = f.type;

    if (t === 'divider') { wrap.appendChild(el('hr', { class: 'ff-divider' })); return wrap; }

    if (t === 'heading') {
      wrap.appendChild(el(f.headingLevel || 'h2', {
        class: 'ff-heading ff-' + (f.headingLevel || 'h2'), text: f.label || ''
      }));
      return wrap;
    }

    if (t === 'paragraph') {
      wrap.appendChild(el('div', { class: 'ff-note ff-note-' + (f.alertType || 'info') }, [
        el('div', {}, [
          f.label ? el('strong', { text: f.label }) : null,
          el('span', { text: f.content || '' })
        ])
      ]));
      return wrap;
    }

    if (f.label) {
      wrap.appendChild(el('label', { class: 'ff-label', text: f.label + (f.required ? ' *' : '') }));
    }

    if (t === 'textarea') {
      wrap.appendChild(el('textarea', { class: 'ff-input ff-textarea', placeholder: f.placeholder || '', rows: 3 }));
    } else if (t === 'select') {
      var s = el('select', { class: 'ff-input ff-select' });
      s.appendChild(el('option', { text: f.placeholder || L.selectOne }));
      (f.options || []).forEach(function (o) { s.appendChild(el('option', { text: o.label })); });
      wrap.appendChild(s);
    } else if (t === 'radio' || t === 'checkbox') {
      var box = el('div', { class: 'ff-choices' });
      (f.options || []).forEach(function (o) {
        box.appendChild(el('label', { class: 'ff-choice' }, [
          el('input', { type: t === 'radio' ? 'radio' : 'checkbox', disabled: true }),
          el('span', { text: o.label })
        ]));
      });
      wrap.appendChild(box);
    } else if (t === 'switch') {
      wrap.appendChild(el('label', { class: 'ff-switch' }, [
        el('span', { class: 'ff-switch-track' }),
        el('span', { class: 'ff-switch-text', text: f.label || '' })
      ]));
    } else if (t === 'rating') {
      var r = el('div', { class: 'ff-rating' });
      for (var i = 0; i < (f.maxStars || 5); i++) {
        r.appendChild(el('span', { class: 'ff-star', html: starSvg() }));
      }
      r.appendChild(el('span', { class: 'ff-rating-text', text: L.notRated }));
      wrap.appendChild(r);
    } else if (t === 'nps') {
      var row = el('div', { class: 'ff-nps-row' });
      for (var n = 0; n <= 10; n++) {
        row.appendChild(el('span', { class: 'ff-nps-btn', text: String(n) }));
      }
      wrap.appendChild(el('div', { class: 'ff-nps' }, [row]));
    } else if (t === 'slider') {
      wrap.appendChild(el('input', { type: 'range', class: 'ff-range', min: f.min || 0, max: f.max || 100, value: f.defaultValue || 0 }));
    } else if (t === 'file') {
      wrap.appendChild(el('div', { class: 'ff-file' }, [
        el('span', { class: 'ff-file-face' }, [el('span', { class: 'ff-file-text', text: L.fileDrop })])
      ]));
    } else if (t === 'signature') {
      wrap.appendChild(el('div', { class: 'ff-sign' }, [
        el('div', { class: 'ff-sign-bar' }, [el('span', { text: L.signHere })]),
        el('div', { style: 'height:110px;background:#fff' })
      ]));
    } else {
      var type = t === 'date' ? 'date' : (t === 'time' ? 'time' : (t === 'number' ? 'number' : 'text'));
      wrap.appendChild(el('input', { class: 'ff-input', type: type, placeholder: f.placeholder || '' }));
    }

    if (f.helperText) { wrap.appendChild(el('p', { class: 'ff-help', text: f.helperText })); }
    return wrap;
  }

  function starSvg() {
    return '<svg class="ff-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">'
      + '<path d="m12 3 2.6 5.6 6 .8-4.4 4.2 1.1 6.1L12 16.8 6.7 19.7l1.1-6.1L3.4 9.4l6-.8Z"/></svg>';
  }

  /* --------------------------------------------------------- la estructura */

  function paintStructure() {
    var box = q('[data-frm-structure]');
    box.innerHTML = '';
    var tree = el('div', { class: 'frm-tree' });

    schema.pages.forEach(function (p, pi) {
      tree.appendChild(el('div', { class: 'frm-tree-page', text: (pi + 1) + '. ' + p.title }));

      schema.fields.filter(function (f) {
        return (f.pageId || schema.pages[0].id) === p.id;
      }).forEach(function (f) {
        var def = REG[f.type] || {};
        tree.appendChild(el('div', {
          class: 'frm-tree-item' + (sel === f.id ? ' is-on' : ''),
          draggable: 'true',
          on: {
            // Clic en la fila: es la vía para llegar a un campo que quedó
            // fuera de la vista en un formulario largo.
            click: function () { page = pi; sel = f.id; paintAll(); },
            dragstart: function (ev) { ev.dataTransfer.setData('text/frm-move', f.id); }
          }
        }, [
          el('i', { class: 'icon ' + (def.icon || 'icon-dash-circle') }),
          el('span', { text: f.label || def.label || f.type })
        ]));
      });
    });

    box.appendChild(tree);
  }

  /* --------------------------------------------------------- el inspector */

  function paintInspector() {
    var box = q('[data-frm-inspector]');
    box.innerHTML = '';

    var f = sel ? findField(sel) : null;
    if (!f) {
      box.appendChild(el('div', { class: 'frm-inspector-empty', text: L.selectField }));
      return;
    }

    var def = REG[f.type];
    if (!def) { return; }

    // A qué página pertenece el campo. Mover un campo entre pasos arrastrándolo
    // obliga a tener las dos páginas a la vista; con el selector es un clic.
    box.appendChild(el('div', { class: 'frm-group-title', text: L.fieldPage }));
    box.appendChild(pageSelect(f));

    var tabs = ['general', 'validation', 'layout'];
    var names = { general: L.tabGeneral, validation: L.tabValidation, layout: L.tabLayout };

    tabs.forEach(function (tab) {
      var props = def.props.filter(function (p) { return p.tab === tab; });
      if (!props.length) { return; }

      box.appendChild(el('div', { class: 'frm-group-title', text: names[tab] }));
      props.forEach(function (p) { box.appendChild(control(f, p)); });
    });

    box.appendChild(el('div', { class: 'frm-group-title', text: L.tabLogic }));
    box.appendChild(logicBox(f));
  }

  /** Selector de página del campo, con atajo para crear una nueva y llevárselo. */
  function pageSelect(f) {
    var wrap = el('div', { class: 'frm-field' });
    var s = el('select', {
      on: {
        change: function (e) {
          if (e.target.value === '__new') {
            addPage();
            f.pageId = schema.pages[schema.pages.length - 1].id;
          } else {
            f.pageId = e.target.value;
          }
          markDirty();
          paintAll();
        }
      }
    });

    schema.pages.forEach(function (p, i) {
      s.appendChild(el('option', {
        value: p.id, text: (i + 1) + '. ' + p.title,
        selected: (f.pageId || schema.pages[0].id) === p.id
      }));
    });
    s.appendChild(el('option', { value: '__new', text: L.fieldPageNew }));

    wrap.appendChild(s);
    return wrap;
  }

  /** Un control del inspector, según el tipo que declaró el registro. */
  function control(f, p) {
    var wrap = el('div', { class: 'frm-field' });
    var set = function (v) { f[p.name] = v; markDirty(); paintCanvas(); paintStructure(); };

    if (p.type === 'toggle') {
      wrap.appendChild(el('label', { class: 'frm-check' }, [
        el('input', {
          type: 'checkbox', checked: !!f[p.name],
          on: { change: function (e) { set(e.target.checked); } }
        }),
        el('span', { text: p.label })
      ]));
      return wrap;
    }

    wrap.appendChild(el('label', { text: p.label }));

    if (p.type === 'options') {
      wrap.appendChild(optionsBox(f));
    } else if (p.type === 'select') {
      var s = el('select', { on: { change: function (e) { set(e.target.value); } } });
      (p.options || []).forEach(function (o) {
        s.appendChild(el('option', { value: o.value, text: o.label, selected: String(f[p.name]) === o.value }));
      });
      wrap.appendChild(s);
    } else if (p.type === 'textarea') {
      wrap.appendChild(el('textarea', {
        text: f[p.name] == null ? '' : String(f[p.name]),
        on: { input: function (e) { set(e.target.value); } }
      }));
    } else {
      wrap.appendChild(el('input', {
        type: p.type === 'number' ? 'number' : 'text',
        value: f[p.name] == null ? '' : String(f[p.name]),
        on: {
          input: function (e) {
            set(p.type === 'number' ? (e.target.value === '' ? null : +e.target.value) : e.target.value);
          }
        }
      }));
    }

    if (p.hint) { wrap.appendChild(el('p', { class: 'frm-hint', text: p.hint })); }
    return wrap;
  }

  /** Editor de opciones: etiqueta y valor guardado, uno debajo de otro. */
  function optionsBox(f) {
    var box = el('div');
    if (!f.options) { f.options = []; }

    f.options.forEach(function (o, i) {
      box.appendChild(el('div', { class: 'frm-opt' }, [
        el('input', {
          type: 'text', value: o.label, placeholder: L.optionLabel,
          on: { input: function (e) { o.label = e.target.value; markDirty(); paintCanvas(); } }
        }),
        el('input', {
          type: 'text', value: o.value, placeholder: L.optionValue, style: 'max-width:8rem',
          on: { input: function (e) { o.value = e.target.value; markDirty(); } }
        }),
        el('button', {
          type: 'button', title: L.removeOption, html: '<i class="icon icon-trash"></i>',
          on: {
            click: function () {
              f.options.splice(i, 1);
              markDirty();
              paintInspector();
              paintCanvas();
            }
          }
        })
      ]));
    });

    box.appendChild(el('button', {
      type: 'button', class: 'btn btn-secondary btn-sm', text: '+ ' + L.addOption,
      on: {
        click: function () {
          var n = f.options.length + 1;
          f.options.push({ id: 'o_' + rnd(), label: 'Opción ' + n, value: 'opcion_' + n });
          markDirty();
          paintInspector();
          paintCanvas();
        }
      }
    }));

    return box;
  }

  /** Visibilidad condicional. Solo se ofrecen campos ANTERIORES a este. */
  function logicBox(f) {
    var box = el('div', { class: 'frm-sub' });
    var rule = f.conditionalRule;

    var before = [];
    for (var i = 0; i < schema.fields.length; i++) {
      var c = schema.fields[i];
      if (c.id === f.id) { break; }
      if (!(REG[c.type] || {}).structural) { before.push(c); }
    }

    box.appendChild(el('label', { class: 'frm-check' }, [
      el('input', {
        type: 'checkbox', checked: !!(rule && rule.enabled), disabled: !before.length,
        on: {
          change: function (e) {
            if (e.target.checked && before.length) {
              f.conditionalRule = { enabled: true, fieldId: before[0].id, operator: 'equals', value: '' };
            } else {
              delete f.conditionalRule;
            }
            markDirty();
            paintInspector();
            paintCanvas();
          }
        }
      }),
      el('span', { text: L.logicEnable })
    ]));

    if (!before.length) {
      box.appendChild(el('p', { class: 'frm-hint', text: L.logicNoFields }));
      return box;
    }
    box.appendChild(el('p', { class: 'frm-hint', text: L.logicHint }));

    if (!rule || !rule.enabled) { return box; }

    box.appendChild(el('div', { class: 'frm-field' }, [
      el('label', { text: L.logicField }),
      (function () {
        var s = el('select', { on: { change: function (e) { rule.fieldId = e.target.value; markDirty(); } } });
        before.forEach(function (c) {
          s.appendChild(el('option', { value: c.id, text: c.label || c.type, selected: rule.fieldId === c.id }));
        });
        return s;
      })()
    ]));

    box.appendChild(el('div', { class: 'frm-field' }, [
      el('label', { text: L.logicOperator }),
      (function () {
        var s = el('select', { on: { change: function (e) { rule.operator = e.target.value; markDirty(); paintInspector(); } } });
        Object.keys(META.operators).forEach(function (op) {
          s.appendChild(el('option', { value: op, text: META.operators[op], selected: rule.operator === op }));
        });
        return s;
      })()
    ]));

    if (['is_checked', 'is_not_checked', 'is_empty', 'is_not_empty'].indexOf(rule.operator) === -1) {
      box.appendChild(el('div', { class: 'frm-field' }, [
        el('label', { text: L.logicValue }),
        el('input', {
          type: 'text', value: rule.value == null ? '' : rule.value,
          on: { input: function (e) { rule.value = e.target.value; markDirty(); } }
        })
      ]));
    }

    return box;
  }

  /* -------------------------------------- pestaña «Formulario» (los ajustes) */

  var meta = {
    title: META.title, slug: META.slug,
    maxResponses: META.maxResponses, allowMultiple: META.allowMultiple,
    opensAt: META.opensAt, closesAt: META.closesAt, password: ''
  };

  function paintSettings() {
    var box = q('[data-frm-settings]');
    box.innerHTML = '';

    // La página activa se edita en el lienzo (`paintPageTools`), que es donde se
    // está mirando; aquí solo se repite cuando hay una seleccionada, para no
    // dejar el hueco a quien la busque en los ajustes.
    if (!allMode()) {
      var p = schema.pages[page];
      box.appendChild(el('div', { class: 'frm-group-title', text: (page + 1) + '. ' + L.pageTitle }));
      box.appendChild(text(L.pageTitle, p.title, function (v) {
        p.title = v; markDirty(); paintPageBar(); paintPageTools();
      }));
      box.appendChild(text(L.pageDesc, p.description || '', function (v) {
        p.description = v; markDirty(); paintPageTools();
      }));
    }

    box.appendChild(el('div', { class: 'frm-group-title', text: L.fTitle }));
    box.appendChild(text(L.fTitle, meta.title, function (v) {
      meta.title = v;
      markDirty();
      q('#frmTitleText').textContent = v;
      paintPaperHead();
    }));
    box.appendChild(text(L.fSlug, meta.slug, function (v) { meta.slug = v; markDirty(); }));
    box.appendChild(el('p', { class: 'frm-hint', id: 'frmPublicUrl', text: META.publicUrl }));

    /*
     * LA PRESENTACION. Es la misma decisión que se toma al crear el formulario,
     * repetida aquí porque cambiarla no puede obligar a empezar de cero: se ve
     * el resultado y se corrige sin salir de lo que se está montando.
     */
    box.appendChild(el('div', { class: 'frm-group-title', text: L.display }));
    box.appendChild(radios('display', [
      { value: 'card', label: L.displayCard, hint: L.displayCardHint },
      { value: 'fullscreen', label: L.displayFull, hint: L.displayFullHint }
    ], settings.display, function (v) { settings.display = v; markDirty(); }));

    box.appendChild(radios('flow', [
      { value: 'wizard', label: L.flowWizard, hint: '' },
      { value: 'single', label: L.flowSingle, hint: '' }
    ], settings.flow, function (v) { settings.flow = v; markDirty(); }));

    box.appendChild(el('div', { class: 'frm-group-title', text: L.fDescription }));
    box.appendChild(area(L.fDescription, settings.description, function (v) { settings.description = v; markDirty(); }));
    box.appendChild(text(L.fSubmitLabel, settings.submitLabel, function (v) { settings.submitLabel = v; markDirty(); }));
    box.appendChild(text(L.fSuccessTitle, settings.successTitle, function (v) { settings.successTitle = v; markDirty(); }));
    box.appendChild(area(L.fSuccessMessage, settings.successMessage, function (v) { settings.successMessage = v; markDirty(); }));
    box.appendChild(text(L.fRedirect, settings.redirectUrl, function (v) { settings.redirectUrl = v; markDirty(); }));

    box.appendChild(el('div', { class: 'frm-group-title', text: L.fPrimaryColor }));
    box.appendChild(text(L.fPrimaryColor, settings.primaryColor, function (v) { settings.primaryColor = v; markDirty(); }));
    box.appendChild(check(L.fProgressBar, settings.showProgressBar, function (v) { settings.showProgressBar = v; markDirty(); }));
    box.appendChild(check(L.fShowBrand, settings.showBrand, function (v) { settings.showBrand = v; markDirty(); }));

    box.appendChild(el('div', { class: 'frm-group-title', text: L.fNotifyEmail }));
    box.appendChild(text(L.fNotifyEmail, settings.notifyEmail, function (v) { settings.notifyEmail = v; markDirty(); }));
    box.appendChild(ackSelect());

    box.appendChild(el('div', { class: 'frm-group-title', text: L.fOpensAt }));
    box.appendChild(text(L.fOpensAt, meta.opensAt, function (v) { meta.opensAt = v; markDirty(); }, 'datetime-local'));
    box.appendChild(text(L.fClosesAt, meta.closesAt, function (v) { meta.closesAt = v; markDirty(); }, 'datetime-local'));
    box.appendChild(text(L.fMaxResponses, meta.maxResponses, function (v) { meta.maxResponses = v; markDirty(); }, 'number'));
    box.appendChild(check(L.fAllowMultiple, meta.allowMultiple, function (v) { meta.allowMultiple = v; markDirty(); }));

    var pw = text(L.fPassword, '', function (v) { meta.password = v; markDirty(); }, 'password');
    pw.appendChild(el('p', { class: 'frm-hint', text: L.fPasswordHint }));
    box.appendChild(pw);
  }

  /** El acuse solo se ofrece sobre campos de correo: no se adivina ninguno. */
  function ackSelect() {
    var wrap = el('div', { class: 'frm-field' }, [el('label', { text: L.fAckField })]);
    var s = el('select', { on: { change: function (e) { settings.ackFieldId = e.target.value; markDirty(); } } });

    s.appendChild(el('option', { value: '', text: L.fAckNone }));
    schema.fields.filter(function (f) { return f.type === 'email'; }).forEach(function (f) {
      s.appendChild(el('option', { value: f.id, text: f.label || f.id, selected: settings.ackFieldId === f.id }));
    });

    wrap.appendChild(s);
    return wrap;
  }

  function text(label, value, onInput, type) {
    return el('div', { class: 'frm-field' }, [
      el('label', { text: label }),
      el('input', {
        type: type || 'text', value: value == null ? '' : String(value),
        on: { input: function (e) { onInput(e.target.value); } }
      })
    ]);
  }

  function area(label, value, onInput) {
    return el('div', { class: 'frm-field' }, [
      el('label', { text: label }),
      el('textarea', { text: value == null ? '' : String(value), on: { input: function (e) { onInput(e.target.value); } } })
    ]);
  }

  function check(label, value, onChange) {
    return el('div', { class: 'frm-field' }, [
      el('label', { class: 'frm-check' }, [
        el('input', { type: 'checkbox', checked: !!value, on: { change: function (e) { onChange(e.target.checked); } } }),
        el('span', { text: label })
      ])
    ]);
  }

  function radios(name, opts, current, onChange) {
    var box = el('div', { class: 'frm-field' });
    opts.forEach(function (o) {
      var row = el('label', { class: 'frm-check', style: 'align-items:flex-start;margin-bottom:.35rem' }, [
        el('input', {
          type: 'radio', name: 'frm-' + name, value: o.value, checked: current === o.value,
          on: { change: function () { onChange(o.value); } }
        }),
        el('span', {}, [
          el('span', { text: o.label, style: 'font-weight:600;display:block' }),
          o.hint ? el('span', { class: 'frm-hint', text: o.hint }) : null
        ])
      ]);
      box.appendChild(row);
    });
    return box;
  }

  /* --------------------------------------------------------------- guardado */

  /**
   * Envío con reintento por CSRF.
   *
   * `Config\Security::$regenerate` está en true: basta con que otra pestaña
   * haga un POST para que el token que tenemos deje de valer. Ante un 403 se
   * pide uno nuevo y se reintenta UNA vez; si vuelve a fallar, se avisa.
   */
  function enviar(url, payload, reintento) {
    var head = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    head[META.csrfName] = csrf;
    head['X-CSRF-TOKEN'] = csrf;

    return fetch(url, {
      method: 'POST', headers: head, credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (r.status === 403 && !reintento) {
        return fetch(META.tokenUrl, { credentials: 'same-origin' })
          .then(leeJson)
          .then(function (t) {
            if (t && t.csrf) { csrf = t.csrf; }
            return enviar(url, payload, true);
          });
      }
      return leeJson(r);
    });
  }

  /** Lee la respuesta sin romperse si el cuerpo no es JSON (una página de error). */
  function leeJson(r) {
    return r.text().then(function (body) {
      try { return JSON.parse(body); } catch (e) { return null; }
    });
  }

  /**
   * UN SOLO botón de guardar.
   *
   * Guarda primero los ajustes y después el árbol, en ese orden porque el
   * servidor decide con el título ya guardado si toca recordar ponerle uno. La
   * lección la dejó el editor de Páginas: con dos botones se escribía el título,
   * se pulsaba «Guardar» y no se guardaba.
   */
  function save() {
    var state = q('[data-frm-state]');
    if (state) { state.setAttribute('data-frm-state', 'dirty'); state.textContent = L.saving; }

    return enviar(META.settingsUrl, {
      title: meta.title,
      slug: meta.slug,
      settings: settings,
      maxResponses: meta.maxResponses,
      allowMultiple: meta.allowMultiple,
      opensAt: meta.opensAt,
      closesAt: meta.closesAt,
      password: meta.password
    }).then(function (res) {
      if (res && res.ok) {
        if (res.csrf) { csrf = res.csrf; }
        meta.slug = res.slug;
        meta.password = '';
        var url = q('#frmPublicUrl');
        if (url && res.publicUrl) { url.textContent = res.publicUrl; }
      }
      return enviar(META.saveUrl, { schema: schema });
    }).then(function (res) {
      if (!res || !res.ok) { markSaved(false); return; }

      if (res.csrf) { csrf = res.csrf; }

      // El servidor devuelve el árbol YA SANEADO: sincronizarse con él es lo
      // que evita seguir editando algo que en la base de datos quedó distinto.
      if (res.schema) {
        schema = res.schema;
        if (page >= schema.pages.length) { page = 0; }
        if (sel && !findField(sel)) { sel = null; }
      }

      markSaved(true);
      paintAll();

      // Recordatorio de ponerle nombre: una vez por sesión de edición y sin
      // bloquear nada. Lo que se está montando es el contenido.
      var rem = q('#frmTitleReminder');
      if (rem && res.needsTitle && !sessionStorage.getItem('frm-title-' + META.id)) {
        rem.hidden = false;
        sessionStorage.setItem('frm-title-' + META.id, '1');
        showPane('right', 'form');
      }
    }).catch(function () { markSaved(false); });
  }

  /* ------------------------------------------------------------------ pintar */

  function paintAll() {
    paintPageBar();
    paintPageTools();
    paintPaperHead();
    paintCanvas();
    paintCount();
    paintStructure();
    paintInspector();
    paintSettings();
  }

  /* ------------------------------------------------------------- pestañas */

  function showPane(side, which) {
    var attr = side === 'left' ? 'lpane' : 'rpane';
    var tab  = side === 'left' ? 'ltab' : 'rtab';

    document.querySelectorAll('[data-frm-' + attr + ']').forEach(function (p) {
      p.hidden = p.getAttribute('data-frm-' + attr) !== which;
    });
    document.querySelectorAll('[data-frm-' + tab + ']').forEach(function (t) {
      t.classList.toggle('is-on', t.getAttribute('data-frm-' + tab) === which);
    });

    var panel = q('[data-frm-panel="' + side + '"]');
    if (panel) { panel.hidden = false; }
  }

  document.querySelectorAll('[data-frm-ltab]').forEach(function (t) {
    t.addEventListener('click', function () { showPane('left', t.getAttribute('data-frm-ltab')); });
  });
  document.querySelectorAll('[data-frm-rtab]').forEach(function (t) {
    t.addEventListener('click', function () { showPane('right', t.getAttribute('data-frm-rtab')); });
  });

  // Plegar una columna. La preferencia se recuerda: quien trabaja con el lienzo
  // ancho no quiere volver a plegarla en cada formulario.
  document.querySelectorAll('[data-frm-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var side = b.getAttribute('data-frm-toggle');
      var panel = q('[data-frm-panel="' + side + '"]');
      panel.hidden = !panel.hidden;
      try { localStorage.setItem('frm-panel-' + side, panel.hidden ? '0' : '1'); } catch (e) {}
    });
  });

  ['left', 'right'].forEach(function (side) {
    try {
      if (localStorage.getItem('frm-panel-' + side) === '0') {
        q('[data-frm-panel="' + side + '"]').hidden = true;
      }
    } catch (e) {}
  });

  /* -------------------------------------------------------------- arranque */

  var saveBtn = q('[data-frm-save]');
  if (saveBtn) { saveBtn.addEventListener('click', save); }

  // Publicar guarda primero: publicar lo de antes sería publicar otra cosa.
  /*
   * Publicar guarda primero: publicar lo de antes sería publicar otra cosa.
   *
   * Y AL GUARDAR, EL TOKEN DE CSRF ROTA (`Config\Security::$regenerate`), así
   * que el que pintó el servidor en este formulario ya no vale: enviarlo tal
   * cual daba «The action you requested is not allowed» (Security.php:262) y se
   * perdía la publicación. Hay que reponerlo con el que devolvió el guardado
   * antes de enviar.
   */
  var pubForm = q('[data-frm-publish]');
  if (pubForm) {
    pubForm.addEventListener('submit', function (ev) {
      // SIEMPRE se repone el token, haya o no cambios que guardar. Dejar pasar
      // el envío cuando no había nada pendiente era el resto del fallo: para
      // entonces el token de la página ya lo habían rotado los guardados
      // anteriores, y publicar volvía a dar 403.
      ev.preventDefault();

      var enviar2 = function () {
        refreshFormToken(pubForm);
        pubForm.submit();
      };

      dirty ? save().then(enviar2) : enviar2();
    });
  }

  /** Repone en un formulario el token vigente que tenemos en memoria. */
  function refreshFormToken(el2) {
    var input = el2.querySelector('input[name="' + META.csrfName + '"]');
    if (input && csrf) { input.value = csrf; }
  }

  /* ------------------------------------------- vista previa por dispositivo */

  /*
   * Abre la página REAL en una ventana del ancho pedido, y guarda antes si hay
   * cambios: previsualizar lo de antes sería previsualizar otra cosa. Lo que se
   * ve es lo que produce el `Renderer`, no la aproximación del lienzo.
   */
  document.querySelectorAll('[data-frm-see-w]').forEach(function (b) {
    b.addEventListener('click', function () {
      var w = parseInt(b.getAttribute('data-frm-see-w'), 10);
      var abrir = function () {
        window.open(META.publicUrl, 'frmPreview',
          'width=' + w + ',height=' + Math.min(900, w * 2) + ',menubar=no,toolbar=no');
      };
      dirty ? save().then(abrir) : abrir();
    });
  });

  var seeBtn = q('[data-frm-see]');
  if (seeBtn) {
    seeBtn.addEventListener('click', function (ev) {
      if (!dirty) { return; }
      ev.preventDefault();
      save().then(function () { window.open(META.publicUrl, '_blank'); });
    });
  }

  /* ----------------------------------------- deshacer, rehacer y vaciar */

  var undoBtn = q('[data-frm-undo]');
  var redoBtn = q('[data-frm-redo]');
  if (undoBtn) { undoBtn.addEventListener('click', function () { restore(histAt - 1); }); }
  if (redoBtn) { redoBtn.addEventListener('click', function () { restore(histAt + 1); }); }

  var clearBtn = q('[data-frm-clear]');
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (!schema.fields.length || !window.confirm(L.confirmClear)) { return; }
      schema.fields = [];
      sel = null;
      markDirty();
      paintAll();
    });
  }

  /* ------------------------------------------ copiar y pegar el formulario */

  var codeDlg = q('#frmCode');
  if (codeDlg) {
    var codeBox = codeDlg.querySelector('[data-frm-code-box]');
    var codeMsg = codeDlg.querySelector('[data-frm-code-msg]');

    var openCode = function () {
      codeBox.value = JSON.stringify(schema, null, 2);
      codeMsg.hidden = true;
      codeDlg.showModal();
    };

    q('[data-frm-code]').addEventListener('click', openCode);
    codeDlg.querySelector('[data-frm-code-close]').addEventListener('click', function () { codeDlg.close(); });

    // `navigator.clipboard` no existe fuera de un origen seguro, y este panel se
    // usa por IP en la red local: de ahí el respaldo con `execCommand`.
    codeDlg.querySelector('[data-frm-code-copy]').addEventListener('click', function () {
      codeBox.select();
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(codeBox.value);
      } else {
        try { document.execCommand('copy'); } catch (e) { /* nada que hacer */ }
      }
      codeMsg.hidden = false;
      codeMsg.textContent = L.copied;
    });

    codeDlg.querySelector('[data-frm-code-apply]').addEventListener('click', function () {
      var tree;
      try { tree = JSON.parse(codeBox.value); } catch (e) { tree = null; }

      // Lo pegado NO se cree: aquí solo se comprueba que tenga la forma mínima,
      // y al guardar pasa por el mismo saneador del servidor que todo lo demás.
      if (!tree || !Array.isArray(tree.fields) || !Array.isArray(tree.pages) || !tree.pages.length) {
        codeMsg.hidden = false;
        codeMsg.textContent = L.codeBad;
        return;
      }

      schema = tree;
      page = 0;
      sel = null;
      markDirty();
      paintAll();
      codeDlg.close();
    });
  }

  document.addEventListener('keydown', function (ev) {
    if ((ev.ctrlKey || ev.metaKey) && ev.key === 's') { ev.preventDefault(); save(); }
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'z') {
      ev.preventDefault();
      ev.shiftKey ? restore(histAt + 1) : restore(histAt - 1);
    }
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'y') { ev.preventDefault(); restore(histAt + 1); }
    if (ev.key === 'Escape' && !q('#frmCode[open]')) { sel = null; paintAll(); }
  });

  window.addEventListener('beforeunload', function (ev) {
    if (!dirty) { return; }
    ev.preventDefault();
    ev.returnValue = '';
  });

  // Clic en el papel, fuera de un campo: deseleccionar.
  var paper = q('.frm-paper');
  if (paper) { paper.addEventListener('click', function () { sel = null; paintAll(); }); }

  paintPalette();
  paintAll();

  // Punto de partida del historial: sin él, el primer «deshacer» no tendría a
  // dónde volver.
  pushHistory();
})();
