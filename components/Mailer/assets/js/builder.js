/*
 * Constructor visual de correos, para el paso 2 del asistente.
 *
 * Tres diferencias con el editor que había, y las tres importan:
 *
 *  1. **El HTML del correo lo hace el SERVIDOR.** Aquí solo se edita el árbol
 *     (`design_json`); `EmailRenderer` lo convierte al guardar. Antes el HTML lo
 *     generaba este archivo y el servidor guardaba lo que llegara: si el guion
 *     no cargaba —y no cargaba, porque sus assets daban 404— se guardaba un
 *     correo vacío.
 *  2. **La paleta y el inspector salen del registro** (`EmailRegistry::forJs()`),
 *     no de una lista repetida aquí. Añadir un bloque es tocar el registro.
 *  3. La vista del lienzo es una APROXIMACION de edición. Lo que se envía es lo
 *     que produce el servidor, y por eso la vista previa del paso 3 es un
 *     `iframe` a la vista previa real, no a este dibujo.
 *
 * Sin dependencias ni CDN.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-ml-builder]');
  if (!root) { return; }

  function data(id, fallback) {
    var el = document.getElementById(id);
    try { return el ? JSON.parse(el.textContent) : fallback; } catch (e) { return fallback; }
  }

  var REG    = data('mlRegistry', {});
  var GROUPS = data('mlGroups', {});
  var META   = data('mlMeta', {});
  var I18N   = data('mlI18n', {});
  var PRESETS = data('mlPresets', {});

  /**
   * Texto de interfaz. El componente NO incrusta español en el JavaScript:
   * los textos llegan traducidos del servidor (`lang('Mailer.*')`) en el
   * bloque `#mlI18n`. Sin diccionario devuelve la clave, que se ve y se
   * arregla, en vez de dejar la interfaz muda.
   */
  function T(key, a, b, c) {
    var v = I18N[key];
    if (typeof v !== 'string') { return key; }
    if (a === undefined) { return v; }

    return v.replace('{0}', a).replace('{1}', b).replace('{2}', c);
  }

  var input  = document.getElementById('design_json');

  var design = normalize(data('mlDesign', ''));
  var sel    = null;   // { s: índice de sección, c: columna, b: bloque }
  var tab    = 'content';

  /** Un diseño siempre tiene forma de diseño, venga como venga. */
  function normalize(raw) {
    var d = raw;
    if (typeof d === 'string') {
      try { d = JSON.parse(d); } catch (e) { d = null; }
    }
    if (!d || !Array.isArray(d.sections) || !d.sections.length) {
      return { sections: [blankSection(1)] };
    }
    d.sections.forEach(function (s) {
      s.cols = (s.cols >= 1 && s.cols <= 3) ? s.cols : 1;
      if (!Array.isArray(s.blocks)) { s.blocks = []; }
      while (s.blocks.length < s.cols) { s.blocks.push([]); }
      s.blocks.length = s.cols;
    });
    return d;
  }

  function blankSection(cols) {
    var blocks = [];
    for (var i = 0; i < cols; i++) { blocks.push([]); }
    return { cols: cols, bg: '', pad: 24, blocks: blocks };
  }

  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') { n.textContent = attrs[k]; }
      else if (k === 'html') { n.innerHTML = attrs[k]; }
      else if (k === 'on') { Object.keys(attrs.on).forEach(function (e) { n.addEventListener(e, attrs.on[e]); }); }
      else if (attrs[k] === true) { n.setAttribute(k, ''); }
      else if (attrs[k] !== false && attrs[k] != null) { n.setAttribute(k, attrs[k]); }
    });
    (kids || []).forEach(function (c) { if (c) { n.appendChild(c); } });
    return n;
  }

  function q(s) { return root.querySelector(s); }

  /** El árbol viaja al servidor en el campo oculto, en cada cambio. */
  function sync() {
    input.value = JSON.stringify(design);
  }

  /* ------------------------------------------------------------- la paleta */

  function paintPalette() {
    var box = q('[data-ml-palette]');
    box.innerHTML = '';
    var grid = el('div', { class: 'ml-palette' });

    // Las secciones ya armadas van primero: cabecera y pie son lo primero que
    // se pone en un correo, y buscarlas entre los elementos sueltos no ayuda.
    var codes = Object.keys(PRESETS);
    if (codes.length) {
      grid.appendChild(el('div', { class: 'ml-group', text: T('presets') }));
      codes.forEach(function (code) {
        var p = PRESETS[code];
        grid.appendChild(el('div', {
          class: 'ml-chip is-preset', title: p.hint,
          on: { click: function () { addPreset(code); } }
        }, [
          el('i', { class: 'icon ' + p.icon }),
          el('span', { text: p.label })
        ]));
      });
    }

    Object.keys(GROUPS).forEach(function (group) {
      var types = Object.keys(REG).filter(function (t) { return REG[t].group === group; });
      if (!types.length) { return; }

      grid.appendChild(el('div', { class: 'ml-group', text: GROUPS[group] }));

      types.forEach(function (type) {
        var def = REG[type];
        grid.appendChild(el('div', {
          class: 'ml-chip', draggable: 'true', title: def.hint,
          on: {
            click: function () { addBlock(type); },
            dragstart: function (ev) {
              ev.dataTransfer.setData('text/ml-type', type);
              ev.dataTransfer.effectAllowed = 'copy';
            }
          }
        }, [
          el('i', { class: 'icon ' + def.icon }),
          el('span', { text: def.label })
        ]));
      });
    });

    box.appendChild(grid);
  }

  /** Dónde cae un bloque nuevo: donde esté la selección, o al final. */
  function target() {
    if (sel) { return { s: sel.s, c: sel.c, at: sel.b + 1 }; }
    var s = design.sections.length - 1;
    return { s: s, c: 0, at: design.sections[s].blocks[0].length };
  }

  function addBlock(type, where) {
    var def = REG[type];
    if (!def) { return; }

    var pos   = where || target();
    var block = Object.assign({ type: type }, JSON.parse(JSON.stringify(def.defaults)));

    design.sections[pos.s].blocks[pos.c].splice(pos.at, 0, block);
    sel = { s: pos.s, c: pos.c, b: pos.at };
    showPanel('props');
    paintAll();
  }

  /**
   * Suelta una sección ya armada. `at` viene del servidor: 'start' la pone la
   * primera y 'end' la última, porque una cabecera en medio del correo no es
   * una cabecera. Se copia en profundidad para que dos usos no compartan
   * bloques.
   */
  function addPreset(code) {
    var p = PRESETS[code];
    if (!p) { return; }

    var section = JSON.parse(JSON.stringify(p.section));
    if (p.at === 'start') { design.sections.unshift(section); }
    else { design.sections.push(section); }

    sel = null;
    paintAll();
  }

  function currentBlock() {
    if (!sel) { return null; }
    var col = (design.sections[sel.s] || {}).blocks;
    return col && col[sel.c] ? col[sel.c][sel.b] || null : null;
  }

  /* -------------------------------------------------------------- el lienzo */

  function paintCanvas() {
    var list = q('[data-ml-list]');
    list.innerHTML = '';

    design.sections.forEach(function (section, si) {
      list.appendChild(sectionNode(section, si));
    });

    var n = design.sections.reduce(function (acc, s) {
      return acc + s.blocks.reduce(function (a, c) { return a + c.length; }, 0);
    }, 0);
    var s = design.sections.length;
    q('[data-ml-count]').textContent = n === 0 ? T('uiNoBlocks')
      : (n === 1 ? T('uiOneBlock') : T('uiNBlocks', n))
        + ' · ' + (s === 1 ? T('uiOneSection') : T('uiNSections', s));
  }

  function sectionNode(section, si) {
    var box = el('div', { class: 'ml-section' + (sel && sel.s === si ? ' is-on' : '') });

    // Barra de la sección: columnas, orden y borrado.
    var tools = el('div', { class: 'ml-sec-tools' });
    var cols  = el('div', { class: 'ml-sec-cols' });

    [1, 2, 3].forEach(function (c) {
      cols.appendChild(el('button', {
        type: 'button', text: c === 1 ? T('uiCol') : T('uiCols', c),
        class: section.cols === c ? 'is-on' : '',
        title: T('uiColsHint', c),
        on: { click: function () { setCols(si, c); } }
      }));
    });
    tools.appendChild(cols);

    tools.appendChild(tool('↑', T('uiSecUp'), function () { moveSection(si, -1); }));
    tools.appendChild(tool('↓', T('uiSecDown'), function () { moveSection(si, 1); }));
    tools.appendChild(tool('⧉', T('uiSecCopy'), function () {
      design.sections.splice(si + 1, 0, JSON.parse(JSON.stringify(section)));
      paintAll();
    }));
    if (design.sections.length > 1) {
      tools.appendChild(tool('✕', T('uiSecDel'), function () {
        if (!window.confirm(T('uiSecConfirm'))) { return; }
        design.sections.splice(si, 1);
        sel = null;
        paintAll();
      }, 'ml-del'));
    }
    box.appendChild(tools);

    var row = el('div', { class: 'ml-cols' });
    section.blocks.forEach(function (blocks, ci) {
      row.appendChild(columnNode(blocks, si, ci));
    });
    box.appendChild(row);

    return box;
  }

  function tool(sym, title, fn, extra) {
    return el('button', {
      type: 'button', title: title, text: sym, class: extra || '',
      on: { click: function (ev) { ev.stopPropagation(); fn(); } }
    });
  }

  function setCols(si, cols) {
    var s = design.sections[si];
    if (cols < s.cols) {
      // Al reducir, lo que había en las columnas que desaparecen se pasa a la
      // primera: perder bloques por cambiar el reparto sería inaceptable.
      for (var i = cols; i < s.cols; i++) {
        s.blocks[0] = s.blocks[0].concat(s.blocks[i]);
      }
    }
    s.blocks.length = cols;
    for (var j = 0; j < cols; j++) { if (!s.blocks[j]) { s.blocks[j] = []; } }
    s.cols = cols;
    sel = null;
    paintAll();
  }

  function moveSection(si, d) {
    var to = si + d;
    if (to < 0 || to >= design.sections.length) { return; }
    var moved = design.sections.splice(si, 1)[0];
    design.sections.splice(to, 0, moved);
    sel = null;
    paintAll();
  }

  function columnNode(blocks, si, ci) {
    var col = el('div', {
      class: 'ml-col',
      on: {
        dragover: function (ev) { ev.preventDefault(); col.classList.add('is-over'); },
        dragleave: function () { col.classList.remove('is-over'); },
        drop: function (ev) {
          ev.preventDefault();
          col.classList.remove('is-over');
          var type = ev.dataTransfer.getData('text/ml-type');
          if (type) { addBlock(type, { s: si, c: ci, at: blocks.length }); }
        }
      }
    });

    if (!blocks.length) {
      col.appendChild(el('div', { class: 'ml-empty', text: T('uiDropHere') }));
      return col;
    }

    blocks.forEach(function (b, bi) {
      col.appendChild(blockNode(b, si, ci, bi));
    });

    return col;
  }

  function blockNode(b, si, ci, bi) {
    var on  = sel && sel.s === si && sel.c === ci && sel.b === bi;
    var box = el('div', {
      class: 'ml-block' + (on ? ' is-on' : ''),
      draggable: 'true',
      on: {
        click: function (ev) {
          ev.stopPropagation();
          sel = { s: si, c: ci, b: bi };
          // Elegir un bloque enseña sus propiedades: si el panel se quedara en
          // los ajustes del correo, tocar un bloque no haría nada visible.
          showPanel('props');
          paintAll();
        },
        dragstart: function (ev) {
          ev.dataTransfer.setData('text/ml-move', JSON.stringify({ s: si, c: ci, b: bi }));
          ev.dataTransfer.effectAllowed = 'move';
        },
        dragover: function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          box.classList.toggle('is-after', afterHalf(ev, box));
          box.classList.toggle('is-before', !afterHalf(ev, box));
        },
        dragleave: function () { box.classList.remove('is-before', 'is-after'); },
        drop: function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          box.classList.remove('is-before', 'is-after');
          var at = bi + (afterHalf(ev, box) ? 1 : 0);

          var type = ev.dataTransfer.getData('text/ml-type');
          if (type) { addBlock(type, { s: si, c: ci, at: at }); return; }

          var from = ev.dataTransfer.getData('text/ml-move');
          if (from) { moveBlock(JSON.parse(from), { s: si, c: ci, at: at }); }
        }
      }
    });

    var tools = el('div', { class: 'ml-tools' });
    tools.appendChild(tool('↑', T('uiUp'), function () { moveBlock({ s: si, c: ci, b: bi }, { s: si, c: ci, at: bi - 1 }); }));
    tools.appendChild(tool('↓', T('uiDown'), function () { moveBlock({ s: si, c: ci, b: bi }, { s: si, c: ci, at: bi + 2 }); }));
    tools.appendChild(tool('⧉', T('uiDuplicate'), function () {
      design.sections[si].blocks[ci].splice(bi + 1, 0, JSON.parse(JSON.stringify(b)));
      paintAll();
    }));
    tools.appendChild(tool('✕', T('uiRemove'), function () {
      design.sections[si].blocks[ci].splice(bi, 1);
      sel = null;
      paintAll();
    }, 'ml-del'));
    box.appendChild(tools);

    box.appendChild(preview(b));
    return box;
  }

  /** ¿El puntero cayó en la mitad de abajo? Los bloques se apilan en columna. */
  function afterHalf(ev, box) {
    var r = box.getBoundingClientRect();
    return (ev.clientY - r.top) > r.height / 2;
  }

  function moveBlock(from, to) {
    var src = design.sections[from.s].blocks[from.c];
    var b   = src[from.b];
    if (!b) { return; }

    var at = to.at;
    if (from.s === to.s && from.c === to.c && from.b < at) { at--; }
    if (at < 0) { return; }

    src.splice(from.b, 1);
    design.sections[to.s].blocks[to.c].splice(at, 0, b);
    sel = { s: to.s, c: to.c, b: Math.min(at, design.sections[to.s].blocks[to.c].length - 1) };
    paintAll();
  }

  /* ----------------------------------------------- vista previa de un bloque */

  /*
   * APROXIMACION de edición, no la salida real: el correo lo pinta el servidor.
   * Se parece lo suficiente para colocar las cosas, que es para lo que sirve.
   */
  function preview(b) {
    var wrap = el('div', { style: 'pointer-events:none' });
    var t = b.type;

    if (t === 'title') {
      wrap.appendChild(el('div', {
        text: b.text || '',
        style: 'font-weight:700;font-size:' + Math.min(28, b.size || 24) + 'px;color:' + (b.color || '#111')
             + ';text-align:' + b.align
      }));
    } else if (t === 'text') {
      wrap.appendChild(el('div', {
        html: b.html || '<span style="opacity:.5">' + T('uiEmptyText') + '</span>',
        style: 'font-size:' + (b.size || 15) + 'px;color:' + (b.color || '#374151') + ';text-align:' + b.align
      }));
    } else if (t === 'button') {
      wrap.appendChild(el('div', { style: 'text-align:' + b.align }, [
        el('span', {
          text: b.text || T('dButton'),
          style: 'display:inline-block;padding:8px 18px;border-radius:' + (b.radius || 6) + 'px;'
               + 'background:' + (b.bg || '#4f46e5') + ';color:' + (b.color || '#fff') + ';font-size:13px;font-weight:700'
        })
      ]));
    } else if (t === 'image' || t === 'video') {
      wrap.appendChild(b.src
        ? el('div', { style: 'text-align:' + b.align }, [
            el('img', { src: b.src, alt: b.alt || '', style: 'max-width:100%;max-height:140px;border-radius:' + (b.radius || 0) + 'px' })
          ])
        : el('div', { class: 'ml-empty', text: t === 'video' ? T('phVideo') : T('phImage') }));
    } else if (t === 'logo') {
      var logo = (META.company || {}).logo || '';
      wrap.appendChild(logo
        ? el('div', { style: 'text-align:' + b.align }, [el('img', { src: logo, alt: '', style: 'max-height:60px' })])
        : el('div', { class: 'ml-empty', text: T('phNoLogo') }));
    } else if (t === 'social') {
      wrap.appendChild(el('div', {
        text: T('uiSocialSample'),
        style: 'text-align:' + b.align + ';font-size:12px;color:' + (b.color || '#6b7280')
      }));
    } else if (t === 'company') {
      wrap.appendChild(el('div', {
        text: T('uiCompanySample', (META.company || {}).name || '—'),
        style: 'text-align:' + b.align + ';font-size:11px;color:' + (b.color || '#6b7280')
      }));
    } else if (t === 'divider') {
      wrap.appendChild(el('div', { style: 'border-top:' + (b.thickness || 1) + 'px solid ' + (b.color || '#e5e7eb') + ';margin:8px 0' }));
    } else if (t === 'spacer') {
      wrap.appendChild(el('div', {
        text: (b.height || 24) + ' px',
        style: 'height:' + Math.min(60, b.height || 24) + 'px;display:flex;align-items:center;justify-content:center;'
             + 'color:#9ca3af;font-size:10px;background:repeating-linear-gradient(45deg,#f9fafb,#f9fafb 6px,#f3f4f6 6px,#f3f4f6 12px)'
      }));
    } else if (t === 'html') {
      wrap.appendChild(el('div', { class: 'ml-empty', text: T('bHtml') }));
    }

    return wrap;
  }

  /* ------------------------------------------------------------ el inspector */

  function paintInspector() {
    var box = q('[data-ml-inspector]');
    box.innerHTML = '';

    var b = currentBlock();
    if (!b) {
      box.appendChild(el('div', { class: 'ml-none', text: T('uiPickBlock') }));
      return;
    }

    var def = REG[b.type];
    if (!def) { return; }

    // Dos pestañas: lo que dice y cómo se ve.
    var tabs = el('div', { class: 'ml-tabs' });
    [['content', T('uiContent')], ['style', T('uiStyle')]].forEach(function (t) {
      tabs.appendChild(el('button', {
        type: 'button', text: t[1], class: 'ml-tab' + (tab === t[0] ? ' is-on' : ''),
        on: { click: function () { tab = t[0]; paintInspector(); } }
      }));
    });
    box.appendChild(tabs);

    var props = def.props.filter(function (p) { return p.tab === tab; });
    if (!props.length) {
      box.appendChild(el('div', { class: 'ml-none', text: T('uiNoProps') }));
      return;
    }

    props.forEach(function (p) { box.appendChild(control(b, p)); });
  }

  function control(b, p) {
    var wrap = el('div', { class: 'ml-field' });
    var set  = function (v) { b[p.name] = v; sync(); paintCanvas(); };

    if (p.type === 'toggle') {
      wrap.appendChild(el('label', { class: 'ml-check' }, [
        el('input', { type: 'checkbox', checked: !!b[p.name], on: { change: function (e) { set(e.target.checked); } } }),
        el('span', { text: p.label })
      ]));
      if (p.hint) { wrap.appendChild(el('p', { class: 'ml-hint', text: p.hint })); }
      return wrap;
    }

    wrap.appendChild(el('label', { text: p.label }));

    if (p.type === 'align') {
      var row = el('div', { class: 'ml-aligns' });
      [['left', T('uiLeft')], ['center', T('uiCenter')], ['right', T('uiRight')]].forEach(function (a) {
        row.appendChild(el('button', {
          type: 'button', text: a[1], class: b[p.name] === a[0] ? 'is-on' : '',
          on: { click: function () { set(a[0]); paintInspector(); } }
        }));
      });
      wrap.appendChild(row);
    } else if (p.type === 'select') {
      var s = el('select', { on: { change: function (e) { set(e.target.value); } } });
      (p.options || []).forEach(function (o) {
        s.appendChild(el('option', { value: o.value, text: o.label, selected: String(b[p.name]) === o.value }));
      });
      wrap.appendChild(s);
    } else if (p.type === 'color') {
      wrap.appendChild(el('input', {
        type: 'color', value: b[p.name] || '#000000',
        on: { input: function (e) { set(e.target.value); } }
      }));
    } else if (p.type === 'rich' || p.type === 'code') {
      wrap.appendChild(el('textarea', {
        text: b[p.name] || '',
        on: { input: function (e) { set(e.target.value); } }
      }));
    } else if (p.type === 'image') {
      wrap.appendChild(imageControl(b, p, set));
    } else {
      wrap.appendChild(el('input', {
        type: p.type === 'number' ? 'number' : (p.type === 'url' ? 'url' : 'text'),
        value: b[p.name] == null ? '' : String(b[p.name]),
        on: { input: function (e) { set(p.type === 'number' ? +e.target.value : e.target.value); } }
      }));
    }

    if (p.hint) { wrap.appendChild(el('p', { class: 'ml-hint', text: p.hint })); }
    return wrap;
  }

  /** Imagen: URL a mano o subida al servidor del componente. */
  function imageControl(b, p, set) {
    var box = el('div');

    box.appendChild(el('input', {
      type: 'url', value: b[p.name] || '', placeholder: 'https://…',
      on: { input: function (e) { set(e.target.value); } }
    }));

    var file = el('input', {
      type: 'file', accept: 'image/*', style: 'display:none',
      on: {
        change: function (e) {
          var f = e.target.files && e.target.files[0];
          if (!f) { return; }
          aviso.textContent = T('uiUploading');

          var fd = new FormData();
          // `image`, que es como lo lee el servidor. Mandarlo como `file` era el
          // motivo de que NINGUNA subida funcionara.
          fd.append('image', f);
          fd.append(META.csrfName, META.csrfHash);
          // El ancho al que se va a ver: el servidor lo acota al del correo y
          // no sube nada más grande de lo que se va a pintar.
          if (b.width > 0) { fd.append('width', b.width); }

          fetch(META.uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (body) {
              var j = null;
              try { j = JSON.parse(body); } catch (err) { j = null; }

              // El token de CSRF rota en cada POST: se recoge el nuevo si viene,
              // o la siguiente subida fallaría.
              if (j && j.csrf) { META.csrfHash = j.csrf; }

              if (j && (j.url || j.location)) {
                set(j.url || j.location);
                var medida = j.width
                  ? T('upDone', j.width, j.height, Math.max(1, Math.round((j.bytes || 0) / 1024)))
                  : '';
                paintInspector();
                var nuevo = root.querySelector('[data-ml-pane="props"] .ml-hint');
                if (nuevo) { nuevo.textContent = medida; }
              } else {
                aviso.textContent = (j && j.error) ? j.error : T('uiUploadFail');
              }
            })
            .catch(function () { aviso.textContent = T('uiUploadFail'); });
        }
      }
    });

    var aviso = el('p', { class: 'ml-hint' });

    box.appendChild(el('button', {
      type: 'button', class: 'btn btn-secondary btn-sm', text: T('uiUpload'),
      style: 'margin-top:.35rem',
      on: { click: function () { file.click(); } }
    }));
    box.appendChild(file);
    box.appendChild(aviso);

    return box;
  }

  /* ----------------------------------------------- pestañas del panel derecho */

  /**
   * El panel derecho tiene dos caras: las propiedades del bloque seleccionado y
   * los ajustes del correo —asunto y remitente—, que antes ocupaban una tarjeta
   * encima del lienzo. Moverlos aquí es lo que le devuelve el alto al correo.
   *
   * Los ajustes NO los pinta este archivo: son campos del servidor dentro del
   * mismo formulario. Aquí solo se enseña una cara u otra.
   */
  function showPanel(which) {
    root.querySelectorAll('[data-ml-pane]').forEach(function (p) {
      p.hidden = p.getAttribute('data-ml-pane') !== which;
    });
    root.querySelectorAll('[data-ml-panel]').forEach(function (b) {
      b.classList.toggle('is-on', b.getAttribute('data-ml-panel') === which);
    });
  }

  root.querySelectorAll('[data-ml-panel]').forEach(function (b) {
    b.addEventListener('click', function () { showPanel(b.getAttribute('data-ml-panel')); });
  });

  /**
   * Un campo obligatorio escondido en la otra cara NO se puede enfocar, y el
   * navegador entonces se niega a enviar el formulario **sin decir nada**: se
   * pulsa Guardar y no pasa absolutamente nada. Al primer campo inválido
   * saltamos a su cara. En captura, porque `invalid` no burbujea.
   */
  root.addEventListener('invalid', function (ev) {
    var pane = ev.target.closest ? ev.target.closest('[data-ml-pane]') : null;
    if (pane && pane.hidden) { showPanel(pane.getAttribute('data-ml-pane')); }
  }, true);

  /**
   * Con qué cara se abre. Si queda algo obligatorio por rellenar —el asunto de
   * una campaña recién creada, el nombre de una plantilla nueva— se abre por
   * ahí; si no, por las propiedades, que es donde se trabaja.
   */
  function initialPanel() {
    var pane = root.querySelector('[data-ml-pane="mail"]');
    if (!pane) { return 'props'; }
    var pend = pane.querySelector('input[required]:not([value]), input[required][value=""]');

    return pend ? 'mail' : 'props';
  }

  /* -------------------------------------------------------------- arranque */

  function paintAll() {
    sync();
    paintCanvas();
    paintInspector();
  }

  var addSection = q('[data-ml-addsection]');
  if (addSection) {
    addSection.addEventListener('click', function () {
      design.sections.push(blankSection(1));
      sel = null;
      paintAll();
    });
  }

  // Clic fuera de un bloque: deseleccionar.
  q('[data-ml-list]').addEventListener('click', function () { sel = null; paintAll(); });

  paintPalette();
  showPanel(initialPanel());
  paintAll();
})();
