/*
 * El editor del mapa: sube planos, coloca puntos y los mueve.
 *
 * Se apoya en `MapViewer` —el mismo que usa la página pública— y le añade lo
 * que solo tiene sentido dentro del panel: el formulario del punto, la gestión
 * de niveles y el guardado. Así el editor y la calle no pueden pintar el mapa
 * de forma distinta: comparten visor, estilos y datos.
 *
 * Sin dependencias ni CDN.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-map-editor]');
  if (!root || !window.MapViewer) { return; }

  function json(id, fallback) {
    var el = document.getElementById(id);
    try { return el ? JSON.parse(el.textContent) : fallback; } catch (e) { return fallback; }
  }

  var META = json('mapMeta', {});
  var I18N = json('mapI18n', {});
  var datos = json('mapData', { levels: [], categories: [] });

  /** Texto de interfaz. Sin diccionario devuelve la clave: se ve y se arregla. */
  function T(k, a) {
    var v = I18N[k];
    if (typeof v !== 'string') { return k; }
    return a === undefined ? v : v.replace('{0}', a);
  }

  /**
   * Busca en TODO el documento, no solo dentro del armazón.
   *
   * Los `<dialog>` viven fuera de `.map-shell` —es donde deben estar, para que
   * el modal se superponga a todo— y con `root.querySelector` devolvían `null`:
   * el editor moría al arrancar con «Cannot read properties of null», y como el
   * error ocurría antes de pintar, el lienzo se quedaba a medias sin decir por
   * qué. `root` sirve para decidir si arrancar; no para buscar.
   */
  var q = function (s) { return document.querySelector(s); };
  var crear = window.MapViewer.crear;

  /* ------------------------------------------------------------ el envío */

  /**
   * Un POST al servidor.
   *
   * El token de CSRF **rota en cada POST** (`Config\Security::$regenerate`),
   * así que basta con que otra pestaña de la aplicación haga uno para que el
   * que tiene cargado el editor deje de valer. Ante un 403 se pide uno fresco y
   * se reintenta una vez; si no, a partir del primer choque fallarían TODOS los
   * guardados hasta recargar.
   */
  function enviar(url, datosForm, reintento) {
    if (!(datosForm instanceof FormData)) {
      var fd = new FormData();
      Object.keys(datosForm || {}).forEach(function (k) {
        if (datosForm[k] !== null && datosForm[k] !== undefined) { fd.append(k, datosForm[k]); }
      });
      datosForm = fd;
    }
    // Se borra antes de poner: en un reintento el FormData ya trae el token
    // caducado, y apilar dos campos con el mismo nombre deja al servidor
    // eligiendo cuál vale.
    datosForm.delete(META.csrfName);
    datosForm.append(META.csrfName, META.csrfHash);

    return fetch(url, { method: 'POST', body: datosForm, credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (body) {
          var j = null;
          try { j = JSON.parse(body); } catch (e) { j = null; }
          if (j && j.csrf) { META.csrfHash = j.csrf; refrescarTokens(); }

          if (r.status === 403 && !reintento) {
            // Token caducado: se recoge uno nuevo y se repite. Una sola vez,
            // para no entrar en bucle si el 403 viene de otra cosa.
            return fetch(META.tokenUrl, { credentials: 'same-origin' })
              .then(function (t) { return t.json(); })
              .then(function (t) { META.csrfHash = t.csrf; refrescarTokens(); return enviar(url, datosForm, true); });
          }
          if (!j || !j.ok) { throw new Error((j && j.error) || T('errSave')); }

          return j;
        });
      });
  }

  /**
   * Repone el token de CSRF en los formularios que pintó el SERVIDOR.
   *
   * El token **rota en cada POST** (`Config\Security::$regenerate`). Los
   * formularios de la página —el de publicar— llevan el que había al renderizar,
   * así que en cuanto el editor guarda algo por JavaScript ese `input` queda
   * caduco: al pulsar Publicar sale un 403 «The action you requested is not
   * allowed», y a partir de ahí ninguno vuelve a funcionar hasta recargar.
   *
   * Se repone SIEMPRE después de cada guardado, no solo cuando hubo cambios: el
   * atajo de «si no cambió nada, no toques» es justo el camino que sigue
   * fallando, porque para entonces los guardados anteriores ya lo rotaron.
   */
  function refrescarTokens() {
    document.querySelectorAll('input[name="' + META.csrfName + '"]').forEach(function (i) {
      i.value = META.csrfHash;
    });
  }

  function aviso(msg, malo) {
    var caja = q('[data-map-flash]');
    if (!caja) { return; }
    caja.textContent = msg;
    caja.className = 'map-flash ' + (malo ? 'is-bad' : 'is-ok');
    caja.hidden = false;
    clearTimeout(caja._t);
    caja._t = setTimeout(function () { caja.hidden = true; }, 4000);
  }

  /* -------------------------------------------------------- recarga */

  function recargar() {
    return fetch(META.payloadUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { return; }
        datos = j.data;
        visor.setData(datos);
        pintarNiveles();
        pintarPuntos();
        // Tras recargar, el elegido es OTRO objeto con los mismos datos:
        // hay que volver a apuntarlo o el inspector seguiría enseñando
        // los valores de antes de guardar.
        if (elegido) {
          var lv = datos.levels[visor.levelIndex];
          elegido = ((lv && lv.points) || []).filter(function (x) { return x.id === elegido.id; })[0] || null;
        }
        pintarPropiedades();
      });
  }

  /* ------------------------------------------------------------ el visor */

  var visor = new window.MapViewer(q('[data-map-stage]'), {
    data: datos,
    editable: true,
    // Los textos de la ficha, que ahora arma el visor.
    i18n: I18N,
    // Pulsar un elemento en el plano lo elige tambien en el inspector: es
    // la via natural cuando se sabe donde esta pero no como se llama.
    onPick: function (p, btn, pos) { elegido = p; abrirFicha(p, btn, pos); marcar(p.id); pintarPropiedades(); },
    // Soltar un pin guarda su sitio. Solo llega aquí si de verdad se movió.
    onMove: function (p) {
      enviar(META.moveUrl.replace('__ID__', p.id), { x: p.x.toFixed(4), y: p.y.toFixed(4) })
        .then(function () { aviso(T('okMoved')); })
        .catch(function (e) { aviso(e.message, true); recargar(); });
    },
    // Clic limpio en el plano: lo recibe quien esté esperándolo.
    onCanvasClick: function (pos) {
      // Recolocar manda sobre todo lo demás: si hay un elemento esperando
      // sitio, el clic es para él.
      if (moviendoId !== null) {
        var p = (datos.levels[visor.levelIndex].points || []).filter(function (x) { return x.id === moviendoId; })[0];
        moviendoId = null;
        visor.stage.classList.remove('is-adding');
        if (p) { moverA(p, pos); }
        return;
      }
      if (!herramienta) { cerrarFicha(); return; }

      // Un pin y un rótulo se colocan de un clic; lo demás se traza.
      if (herramienta === 'pin' || herramienta === 'text') {
        var k = herramienta;
        salirDeHerramienta();
        abrirFormulario(null, pos, k, null);
        return;
      }
      anadirVertice(pos);
    },
    // Soltar un vertice: se guarda la geometria por su ruta propia, que no
    // pasa por el formulario y por tanto no puede tocar nada mas.
    onVertex: function (p) {
      enviar(META.geomUrl.replace('__ID__', p.id), { geometry: JSON.stringify(p.geom) })
        .then(function () { aviso(T('okGeom')); return recargar(); })
        .catch(function (e) { aviso(e.message, true); recargar(); });
    },
    // El puntero mientras se traza: el tramo hasta el cursor.
    onCanvasMove: function (pos) {
      if (!dibujo.kind || !dibujo.pts.length) { return; }
      visor.setDraft(dibujo.kind, dibujo.pts.concat([[pos.x, pos.y]]));
    }
  });

  /* ---------------------------------------------------------- niveles */

  function pintarNiveles() {
    var caja = q('[data-map-levels]');
    caja.innerHTML = '';

    datos.levels.forEach(function (lv, i) {
      var fila = crear('div', { class: 'map-level' + (i === visor.levelIndex ? ' is-on' : '') });

      fila.appendChild(crear('button', {
        type: 'button', class: 'map-level-name', text: lv.name,
        title: lv.points.length + ' · ' + (lv.image ? '' : T('levelNoPlan')),
        on: { click: function () { visor.setLevel(i); pintarNiveles(); pintarPuntos(); cerrarFicha(); } }
      }));

      fila.appendChild(crear('span', { class: 'map-level-n', text: String(lv.points.length) }));

      // Sin plano no se pueden colocar puntos, y conviene que se vea de un
      // vistazo cuál es el nivel al que le falta.
      if (!lv.image) { fila.appendChild(crear('span', { class: 'map-level-warn', text: '!', title: T('levelNoPlan') })); }

      fila.appendChild(crear('button', {
        type: 'button', class: 'map-mini', text: '✎', title: T('levelEdit'),
        on: { click: function () { editarNivel(lv); } }
      }));
      fila.appendChild(crear('button', {
        type: 'button', class: 'map-mini is-del', text: '✕', title: T('levelDelete'),
        on: {
          click: function () {
            if (!window.confirm(T('levelConfirm', lv.name))) { return; }
            enviar(META.levelDeleteUrl.replace('__ID__', lv.id), {})
              .then(function () { aviso(T('okDeleted')); return recargar(); })
              .catch(function (e) { aviso(e.message, true); });
          }
        }
      }));

      caja.appendChild(fila);
    });
  }

  /** El formulario del nivel: nombre y plano. Sirve para crear y para editar. */
  function editarNivel(lv) {
    var dlg = q('[data-map-level-dialog]');
    dlg.querySelector('[name=name]').value = lv ? lv.name : '';
    dlg.querySelector('[name=image]').value = '';
    dlg.dataset.id = lv ? lv.id : '';
    dlg.querySelector('[data-level-title]').textContent = lv ? T('levelEdit') : T('levelNew');
    dlg.showModal();
  }

  q('[data-map-level-dialog]').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var dlg = ev.target.closest('dialog');
    var fd  = new FormData(ev.target);
    var id  = dlg.dataset.id;
    var url = id ? META.levelUpdateUrl.replace('__ID__', id) : META.levelStoreUrl;

    enviar(url, fd)
      .then(function () { dlg.close(); aviso(T('okSaved')); return recargar(); })
      .catch(function (e) { aviso(e.message, true); });
  });

  /* --------------------------------------------- recolocar y ocultar */

  var moviendoId = null;

  /** La herramienta activa (null = navegar) y la forma que se está trazando. */
  var herramienta = null;
  var dibujo = { kind: null, pts: [] };

  /**
   * Recolocar sin arrastrar: se pulsa aquí y luego el sitio nuevo del plano.
   *
   * En un PIN es una comodidad —arrastrar es incómodo con el pin pegado a un
   * borde, muy ampliado o en una pantalla táctil—. En una FORMA es la **única**
   * manera de moverla entera: sus vértices se arrastran uno a uno, pero el
   * cuerpo no, y el servidor traslada toda la geometría de una vez.
   */
  function activarMover(p) {
    // Volver a pulsarlo cancela: es lo que espera cualquiera de un botón que ya
    // está encendido.
    moviendoId = moviendoId === p.id ? null : p.id;
    // Sale de la herramienta que estuviera activa: un clic no puede ser a la vez
    // «pon un vértice aquí» y «lleva esto aquí». `salirDeHerramienta()` deja el
    // lienzo limpio, así que el modo se vuelve a poner después.
    salirDeHerramienta();
    visor.stage.classList.toggle('is-adding', moviendoId !== null);
    aviso(moviendoId ? T('pointClickNew', p.title) : T('pointRelocateOff'));
    pintarPuntos();
  }

  /** Guarda la posición nueva tras pulsar en el plano. */
  function moverA(p, pos) {
    p.x = pos.x;
    p.y = pos.y;
    visor._placePins();
    enviar(META.moveUrl.replace('__ID__', p.id), { x: p.x.toFixed(4), y: p.y.toFixed(4) })
      // `recargar()` y no `pintarPuntos()`: en una FORMA el servidor traslada
      // todos los vértices, y `_placePins()` solo recoloca pines — el dibujo se
      // quedaba donde estaba y parecía que el botón no hacía nada. Es además la
      // única manera de mover una forma entera: sus vértices se arrastran uno a
      // uno, pero el cuerpo no.
      .then(function () { aviso(T('okMoved')); return recargar(); })
      .catch(function (e) { aviso(e.message, true); recargar(); });
  }

  /**
   * Mostrar u ocultar un punto en la página pública.
   *
   * Se manda el formulario ENTERO y no solo el interruptor porque `update()`
   * reescribe los campos que recibe: mandar únicamente `is_active` dejaría el
   * título, la descripción y el enlace en blanco.
   */
  function alternarVisible(p) {
    var fd = new FormData();
    fd.append('title', p.title);
    fd.append('description', p.desc || '');
    fd.append('url', p.url || '');
    if (p.category) { fd.append('category_id', p.category); }
    if (p.active !== false) {
      // Sin el campo, el controlador lo toma como activo: para apagarlo hay que
      // mandarlo explícitamente a 0.
      fd.append('is_active', '0');
    } else {
      fd.append('is_active', '1');
    }

    enviar(META.pointUpdateUrl.replace('__ID__', p.id), fd)
      .then(function () { aviso(T('okSaved')); cerrarFicha(); return recargar(); })
      .catch(function (e) { aviso(e.message, true); });
  }

  /* --------------------------------------------- propiedades del elemento */

  var elegido = null;   // el elemento cuyas propiedades se están tocando

  /**
   * El inspector de la columna derecha.
   *
   * Lo que ofrece depende del tipo: un rótulo no tiene relleno ni borde, y una
   * línea no tiene relleno. Enseñar controles que no hacen nada es peor que no
   * enseñarlos: el que los toca se queda esperando un cambio que no llega.
   *
   * Un campo **vacío significa «lo que diga su categoría»**, que no es lo mismo
   * que un valor por defecto escrito: si mañana cambia el color de la categoría,
   * los elementos que no lo hayan pisado lo siguen.
   */
  function pintarPropiedades() {
    var caja = q('[data-map-props]');
    if (!caja) { return; }

    caja.innerHTML = '';
    var vacio = q('[data-map-props-empty]');

    // Los manejadores siguen a la selección: se ven los vértices del elemento
    // que se está tocando y solo esos, o el plano se llenaría de puntos.
    visor.setHandles(elegido);

    if (!elegido) {
      caja.hidden = true;
      if (vacio) { vacio.hidden = false; }
      return;
    }
    caja.hidden = false;
    if (vacio) { vacio.hidden = true; }
    // Elegir algo salta a su pestaña: si no, se pulsa en el plano y en esta
    // columna no pasa nada visible porque estaba abierta la otra.
    irAPestana('props');

    var p = elegido;
    // `prop()` y no `s.loQueSea`: si `style` llegara como un ARRAY —lo que
    // hace un `[]` de PHP al serializarse— entonces `s.fill` sería el método
    // nativo `Array.prototype.fill`, truthy, y el color se pintaría como
    // «function fill() { [native code] }». El servidor ya lo emite como
    // objeto; esto es el cinturón.
    var s = p.style || {};
    var prop = function (k) { return Object.prototype.hasOwnProperty.call(s, k) ? s[k] : undefined; };
    var esTexto = p.kind === 'text';
    var esArea  = p.kind === 'polygon' || p.kind === 'rect' || p.kind === 'circle';
    var esPin   = !p.kind || p.kind === 'pin';

    var campos = [];

    var color = function (clave, etiqueta) {
      var input = crear('input', { type: 'color', class: 'map-prop-color', value: prop(clave) || '#2563eb', 'data-prop': clave });
      // El interruptor es lo que permite VOLVER a la categoría: sin él, en
      // cuanto se toca el color no hay forma de deshacerlo, porque un
      // `<input type=color>` no tiene estado «vacío».
      var here = crear('input', { type: 'checkbox', 'data-prop-on': clave });
      here.checked = prop(clave) !== undefined;
      return crear('div', { class: 'map-prop' }, [
        crear('label', { class: 'map-prop-lab', text: etiqueta }),
        crear('div', { class: 'map-prop-color-row' }, [
          here, input,
          crear('span', { class: 'map-prop-hint', text: T('propInherit') })
        ])
      ]);
    };

    var rango = function (clave, etiqueta, min, max, valor, sufijo) {
      var input = crear('input', {
        type: 'range', class: 'map-prop-range', min: min, max: max, value: valor, 'data-prop': clave
      });
      var eco = crear('span', { class: 'map-prop-val', text: valor + (sufijo || '') });
      input.addEventListener('input', function () { eco.textContent = this.value + (sufijo || ''); });
      return crear('div', { class: 'map-prop' }, [
        crear('label', { class: 'map-prop-lab' }, [crear('span', { text: etiqueta }), eco]),
        input
      ]);
    };

    if (esArea || esTexto) { campos.push(color('fill', T('propFill'))); }
    if (esArea) { campos.push(rango('fillOpacity', T('propOpacity'), 0, 100, prop('fillOpacity') == null ? 18 : prop('fillOpacity'), '%')); }

    if (!esTexto && !esPin) {
      campos.push(color('stroke', T('propStroke')));
      campos.push(rango('strokeWidth', T('propWidth'), 1, 12, prop('strokeWidth') || (p.kind === 'line' ? 4 : 2), ' px'));

      var sel = crear('select', { class: 'form-select form-select-sm', 'data-prop': 'border' });
      var bordes = [['solid', T('borderSolid')], ['dashed', T('borderDashed')], ['dotted', T('borderDotted')]];
      // «Sin borde» solo en un área: una línea SIN trazo desaparece del plano, y
      // ofrecerlo sería ofrecer una forma de hacer invisible lo que se acaba de
      // dibujar sin decir por qué.
      if (esArea) { bordes.push(['none', T('borderNone')]); }
      bordes.forEach(function (o) {
        var op = crear('option', { value: o[0], text: o[1] });
        if ((prop('border') || 'solid') === o[0]) { op.selected = true; }
        sel.appendChild(op);
      });
      campos.push(crear('div', { class: 'map-prop' }, [
        crear('label', { class: 'map-prop-lab', text: T('propBorder') }), sel
      ]));

      // El trazo en marcha. Solo se ve si el borde es discontinuo —animar el
      // desplazamiento de un trazo continuo no mueve nada—, así que al
      // encenderlo con un borde continuo el desplegable pasa a «discontinuo»
      // **a la vista**: se entiende por qué ha cambiado, en vez de aplicar una
      // regla escondida o dejar un interruptor que no hace nada.
      var flow = crear('input', { type: 'checkbox', 'data-prop': 'flow' });
      flow.checked = !!prop('flow');
      flow.addEventListener('change', function () {
        if (this.checked && (sel.value === 'solid' || sel.value === 'none')) { sel.value = 'dashed'; }
      });
      campos.push(crear('div', { class: 'map-prop' }, [
        crear('label', { class: 'map-prop-check' }, [
          flow, crear('span', { text: T('propFlow') })
        ]),
        crear('p', { class: 'map-prop-hint', text: T('propFlowHint') })
      ]));

      // Las puntas de flecha dicen lo mismo que el movimiento —el sentido— pero
      // quietas, así que es lo que sigue funcionando para quien pidió menos
      // movimiento. Solo en una línea: en un área cerrada el «sentido» no
      // significa nada.
      if (p.kind === 'line') {
        var arrows = crear('input', { type: 'checkbox', 'data-prop': 'arrows' });
        arrows.checked = !!prop('arrows');
        campos.push(crear('div', { class: 'map-prop' }, [
          crear('label', { class: 'map-prop-check' }, [
            arrows, crear('span', { text: T('propArrows') })
          ]),
          crear('p', { class: 'map-prop-hint', text: T('propArrowsHint') })
        ]));
      }
    }

    if (esTexto) { campos.push(rango('fontSize', T('propFont'), 8, 96, prop('fontSize') || 18, ' px')); }

    // Un pin no tiene nada que ajustar: su color es el de su categoría y su
    // forma es la del pin. Decirlo es mejor que enseñar una caja vacía.
    if (!campos.length) {
      campos.push(crear('p', { class: 'map-empty', text: T('propNone') }));
    }

    var guardar = crear('button', {
      type: 'button', class: 'btn btn-primary btn-sm w-full', text: T('save'),
      on: { click: function () { guardarPropiedades(p); } }
    });

    caja.appendChild(crear('div', { class: 'map-props-head' }, [
      crear('strong', { text: p.title }),
      crear('span', { class: 'map-props-kind', text: T('tool' + (p.kind || 'pin').charAt(0).toUpperCase() + (p.kind || 'pin').slice(1)) })
    ]));
    campos.forEach(function (c) { caja.appendChild(c); });
    if (campos.length && !(campos.length === 1 && esPin)) { caja.appendChild(guardar); }
  }

  /**
   * Guarda la presentación.
   *
   * Manda el formulario descriptivo COMPLETO además del estilo, porque
   * `update()` reescribe lo que recibe: mandar solo los colores dejaría el
   * elemento sin título. Es el mismo camino que sigue `alternarVisible`.
   */
  function guardarPropiedades(p) {
    var caja = q('[data-map-props]');
    var estilo = {};

    Array.prototype.forEach.call(caja.querySelectorAll('[data-prop]'), function (el) {
      var clave = el.getAttribute('data-prop');
      if (el.type === 'checkbox') {
        // Apagado no se manda: la ausencia ES el «no», igual que en el resto de
        // las propiedades. Guardar un 0 diría «se decidió que no», que se
        // comporta igual y obligaría a distinguir dos cosas idénticas.
        if (el.checked) { estilo[clave] = 1; }
        return;
      }
      if (el.type === 'color') {
        var on = caja.querySelector('[data-prop-on="' + clave + '"]');
        // Sin marcar, la propiedad NO se manda: ausente significa «la hereda de
        // su categoría», que es distinto de un color escrito a mano.
        if (on && on.checked) { estilo[clave] = el.value; }
        return;
      }
      estilo[clave] = el.value;
    });

    var fd = new FormData();
    fd.append('title', p.title);
    fd.append('description', p.desc || '');
    fd.append('url', p.url || '');
    if (p.category) { fd.append('category_id', p.category); }
    fd.append('is_active', p.active === false ? '0' : '1');
    fd.append('style', JSON.stringify(estilo));

    enviar(META.pointUpdateUrl.replace('__ID__', p.id), fd)
      .then(function () { aviso(T('okStyle')); return recargar(); })
      .catch(function (e) { aviso(e.message, true); });
  }

  /* ------------------------------------------------- la lista de puntos */

  /**
   * La columna derecha: los puntos del nivel con lo que los define.
   *
   * El plano dice DÓNDE está cada punto; esta lista dice QUÉ es, y es la única
   * forma de repasarlos —ver cuáles no tienen foto, cuáles están sin publicar,
   * cuáles no llevan enlace— sin ir pinchando pin por pin sobre el plano.
   */
  function pintarPuntos() {
    var caja = q('[data-map-points]');
    var tit  = q('[data-map-points-title]');
    if (!caja) { return; }

    var lv = datos.levels[visor.levelIndex];
    var pts = lv ? lv.points : [];
    // «Elementos» y no «Puntos»: desde que hay líneas, áreas y rótulos, la
    // lista ya no es solo de puntos.
    tit.textContent = T('elements') + ' (' + pts.length + ')';
    caja.innerHTML = '';

    if (!pts.length) {
      caja.appendChild(crear('p', { class: 'map-empty', text: T('noPoints') }));
      return;
    }

    /*
     * La lista va en CASCADA: los sub-elementos cuelgan del suyo.
     *
     * Un sub-elemento —la ruta de «cómo llegar» de un local— no se dibuja en el
     * plano con los demás, así que suelto en la lista aparecería como algo que
     * no se ve por ninguna parte. Colgado de su padre se entiende de qué es.
     *
     * Solo se anidan los del MISMO nivel: la lista es la de este piso, y un hijo
     * de otro piso hay que poder editarlo desde el suyo. Ese sale arriba con una
     * nota de a quién pertenece, en vez de quedarse sin fila en ninguna parte.
     */
    var enEsteNivel = {};
    pts.forEach(function (q) { enEsteNivel[q.id] = true; });
    var hijosDe = function (id) {
      return pts.filter(function (q) { return q.parent === id; });
    };
    var padreDe = function (id) {
      var out = null;
      datos.levels.forEach(function (lv) {
        (lv.points || []).forEach(function (q) { if (q.id === id) { out = q; } });
      });
      return out;
    };

    var raices = pts.filter(function (q) { return !q.parent || !enEsteNivel[q.parent]; });

    var pintarFila = function (p, esHijo) {
      var cat = (datos.categories || []).filter(function (c) { return c.id === p.category; })[0];

      var fila = crear('div', {
        class: 'map-point' + (p.active === false ? ' is-off' : '') + (esHijo ? ' is-sub' : ''),
        'data-id': p.id
      });

      // La cabecera: el color de su categoría, el título y el estado.
      fila.appendChild(crear('div', { class: 'map-point-head' }, [
        crear('i', { class: 'map-point-dot', style: 'background:' + (cat ? cat.color : '#2563eb') }),
        crear('button', {
          type: 'button', class: 'map-point-name', text: p.title,
          title: T('pointLocate'),
          // Pulsar el nombre lleva el plano hasta el punto: con cincuenta pines
          // en un plano de 4000 px, buscarlo a ojo es inviable.
          on: { click: function () { elegido = p; visor.focusPoint(p); abrirFicha(p, pinDe(p)); marcar(p.id); pintarPropiedades(); } }
        }),
        p.active === false ? crear('span', { class: 'map-point-off', text: T('hidden') }) : null
      ]));

      // Lo que lo define, en una línea: sin esto hay que abrir cada uno. Las
      // COORDENADAS no salen: son un número que no se lee ni se teclea —el
      // sitio se ve en el plano y se cambia arrastrando—, y repetido en cada
      // fila tapaba lo que sí distingue a un elemento de otro.
      var meta = [];
      meta.push(cat ? cat.name : T('noCategory'));
      if (p.image) { meta.push(T('hasImage')); }
      if (p.url) { meta.push(T('hasLink')); }
      fila.appendChild(crear('div', { class: 'map-point-meta', text: meta.join(' · ') }));

      // Las acciones.
      fila.appendChild(crear('div', { class: 'map-point-acts' }, [
        crear('button', {
          type: 'button', class: 'map-mini', text: '✎', title: T('edit'),
          on: { click: function () { abrirFormulario(p, null); } }
        }),
        crear('button', {
          type: 'button', class: 'map-mini' + (moviendoId === p.id ? ' is-on' : ''),
          text: '✥', title: T('pointRelocate'),
          // Recolocar sin arrastrar: en una pantalla pequeña, o con el pin
          // pegado al borde, arrastrar es incómodo. Se pulsa aquí y luego en
          // el sitio nuevo del plano.
          on: { click: function () { activarMover(p); } }
        }),
        crear('button', {
          type: 'button', class: 'map-mini', text: '⧉', title: T('pointDuplicate'),
          // Duplicar es lo que ahorra el trabajo de verdad: se monta un local
          // con su color, su borde y su tamaño y se repite. La copia nace al
          // lado —el servidor la aparta— y se queda elegida, que es lo
          // siguiente que se va a hacer con ella: cambiarle el nombre.
          on: {
            click: function () {
              enviar(META.pointCopyUrl.replace('__ID__', p.id), {})
                .then(function (r) {
                  aviso(T('okPointCopied'));
                  return recargar().then(function () { elegirPorId(r && r.id); });
                })
                .catch(function (e) { aviso(e.message, true); });
            }
          }
        }),
        crear('button', {
          type: 'button', class: 'map-mini', text: p.active === false ? '👁' : '⦸',
          title: p.active === false ? T('pointShow') : T('pointHide'),
          on: { click: function () { alternarVisible(p); } }
        }),
        crear('button', {
          type: 'button', class: 'map-mini is-del', text: '✕', title: T('delete'),
          on: {
            click: function () {
              if (!window.confirm(T('pointConfirm'))) { return; }
              enviar(META.pointDeleteUrl.replace('__ID__', p.id), {})
                .then(function () { cerrarFicha(); aviso(T('okDeleted')); return recargar(); })
                .catch(function (e) { aviso(e.message, true); });
            }
          }
        })
      ]));

      return fila;
    };

    raices.forEach(function (p) {
      var fila  = pintarFila(p, false);
      var hijos = hijosDe(p.id);

      // Un hijo cuyo padre está en OTRO piso: se dice de quién es, o su fila no
      // explicaría por qué no se dibuja en el plano.
      if (p.parent && !enEsteNivel[p.parent]) {
        var padre = padreDe(p.parent);
        if (padre) {
          fila.classList.add('is-sub');
          fila.appendChild(crear('div', { class: 'map-point-meta', text: T('subOf').replace('{0}', padre.title) }));
        }
      }

      if (!hijos.length) { caja.appendChild(fila); return; }

      // Con hijos, la fila gana su desplegable. Empieza ABIERTO: en el editor
      // se viene a trabajar sobre lo que hay, y esconder por defecto lo que se
      // acaba de crear obliga a buscarlo.
      var subs = crear('div', { class: 'map-subs' });
      hijos.forEach(function (h) { subs.appendChild(pintarFila(h, true)); });

      var det = crear('details', { class: 'map-point-tree', open: true }, [
        crear('summary', {}, [
          crear('span', { text: T('subElements') }),
          crear('b', { text: String(hijos.length) })
        ]),
        subs
      ]);

      caja.appendChild(fila);
      caja.appendChild(det);
    });
  }

  /**
   * Deja elegido un elemento por su id, después de recargar.
   *
   * `recargar()` reconstruye `datos`, así que el objeto de antes ya no sirve:
   * hay que volver a buscarlo por id o se estaría editando una copia muerta que
   * no vuelve a pintarse.
   */
  function elegirPorId(id) {
    if (!id) { return; }
    var lv = datos.levels[visor.levelIndex];
    var p  = ((lv && lv.points) || []).filter(function (x) { return x.id === id; })[0];
    if (!p) { return; }
    elegido = p;
    marcar(p.id);
    pintarPropiedades();
    visor.setHandles(p.geom && p.geom.length ? p : null);
  }

  /** El botón del pin de un punto, para poder abrirle la ficha desde la lista. */
  function pinDe(p) {
    var e = visor.pins.filter(function (x) { return x.point.id === p.id; })[0];

    return e ? e.el : null;
  }

  /** Resalta la fila del punto elegido. */
  function marcar(id) {
    var caja = q('[data-map-points]');
    if (!caja) { return; }
    caja.querySelectorAll('.map-point').forEach(function (f) {
      f.classList.toggle('is-on', String(f.dataset.id) === String(id));
    });
  }

  /* ------------------------------------------------------ ficha del punto */

  var fichaAbierta = null;

  function cerrarFicha() {
    if (visor) { visor.detachCard(); }
    fichaAbierta = null;
  }

  /**
   * La tarjeta emergente. Se coloca junto al pin y se **corrige si se sale**
   * del lienzo: pegada al borde derecho, una tarjeta de 19 rem se cortaría, y
   * es justo donde caen los locales del final del pasillo.
   */
  function abrirFicha(p, btn, pos) {
    cerrarFicha();

    // La arma el VISOR: la tenían copiada esta pantalla y la página pública, y
    // en cuanto una ganaba algo —los botones de los sub-elementos, sin ir más
    // lejos— la otra se quedaba atrás. Lo único que cambia aquí es el botón de
    // editar, que entra por `extra`.
    var card = visor.buildCard(p, {
      onClose: cerrarFicha,
      extra: editable ? [crear('button', {
        type: 'button', class: 'mv-card-more map-edit-point', text: T('edit'),
        on: { click: function () { abrirFormulario(p, null); } }
      })] : []
    });

    // La coloca el VISOR, anclada al punto: la ficha sigue al pin al mover
    // o ampliar el mapa, y la geometría vive en un solo sitio en vez de
    // duplicada aquí y en la página pública.
    // Anclada donde se pulsó si viene ese dato: el centroide de un polígono
    // grande puede caer fuera de la pantalla.
    fichaAbierta = visor.attachCard(card, pos || p);
  }

  /* ------------------------------------------------- formulario del punto */

  function abrirFormulario(p, pos, kind, geom) {
    var dlg = q('[data-map-point-dialog]');
    var f   = dlg.querySelector('form');

    f.reset();
    dlg.dataset.id = p ? p.id : '';
    dlg.dataset.x  = p ? p.x : (pos ? pos.x : 0);
    dlg.dataset.y  = p ? p.y : (pos ? pos.y : 0);
    // El tipo y los vértices viajan por el `dataset` y no por campos del
    // formulario: no son algo que se escriba, son el resultado del trazado.
    // Al EDITAR no se mandan: el tipo es inmutable y la geometría tiene su
    // propia ruta, así que guardar los textos no puede tocar el dibujo.
    dlg.dataset.kind = p ? '' : (kind || 'pin');
    dlg.dataset.geom = (!p && geom) ? JSON.stringify(geom) : '';
    dlg.querySelector('[data-point-title]').textContent = p ? T('pointEdit') : T('pointNew');

    if (p) {
      f.querySelector('[name=title]').value = p.title || '';
      f.querySelector('[name=description]').value = p.desc || '';
      f.querySelector('[name=url]').value = p.url || '';
      f.querySelector('[name=category_id]').value = p.category || '';
      f.querySelector('[name=is_active]').checked = p.active !== false;
    } else {
      f.querySelector('[name=is_active]').checked = true;
    }
    pintarPadres(dlg, p);
    dlg.querySelector('[data-point-delete]').hidden = !p;
    dlg.showModal();
  }

  /**
   * Los candidatos a padre: los elementos del mapa que no cuelgan ya de otro.
   *
   * Se ofrecen los de TODOS los niveles porque una ruta puede vivir en otro
   * piso —el «cómo llegar» a un local de la segunda planta empieza abajo—, y al
   * lado se dice de qué piso es, que si no dos locales con el mismo nombre en
   * pisos distintos son indistinguibles en la lista.
   *
   * Un elemento **que ya tiene hijos no puede pasar a ser hijo**: sería un
   * tercer nivel, y el pintado, la ficha y la cascada del directorio asumen dos.
   * El servidor lo rechaza igualmente; aquí se desactiva el campo y se dice por
   * qué, que es más útil que un guardado que no hace lo que se pidió.
   */
  function pintarPadres(dlg, p) {
    var sel  = dlg.querySelector('[data-point-parent]');
    var nota = dlg.querySelector('[data-point-parent-hint]');
    if (!sel) { return; }

    while (sel.options.length > 1) { sel.remove(1); }

    var tieneHijos = p && datos.levels.some(function (lv) {
      return (lv.points || []).some(function (q) { return q.parent === p.id; });
    });

    sel.disabled = !!tieneHijos;
    if (nota) { nota.textContent = tieneHijos ? T('pointParentBlocked') : T('pointParentHint'); }
    if (tieneHijos) { sel.value = ''; return; }

    datos.levels.forEach(function (lv, i) {
      (lv.points || []).forEach(function (q) {
        if (q.parent) { return; }                       // ya es hijo de otro
        if (p && q.id === p.id) { return; }             // ni de sí mismo
        var texto = q.title + (i === visor.levelIndex ? '' : ' · ' + lv.name);
        sel.appendChild(crear('option', { value: q.id, text: texto }));
      });
    });

    sel.value = (p && p.parent) ? String(p.parent) : '';
  }

  q('[data-map-point-dialog]').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var dlg = ev.target.closest('dialog');
    var fd  = new FormData(ev.target);
    var id  = dlg.dataset.id;

    // Las coordenadas no están en el formulario: vienen del sitio donde se hizo
    // clic o del elemento que ya existía. En una forma, el servidor las ignora
    // y calcula el ancla a partir de los vértices.
    fd.append('x', dlg.dataset.x);
    fd.append('y', dlg.dataset.y);
    if (dlg.dataset.kind) { fd.append('kind', dlg.dataset.kind); }
    if (dlg.dataset.geom) { fd.append('geometry', dlg.dataset.geom); }

    var url = id
      ? META.pointUpdateUrl.replace('__ID__', id)
      : META.pointStoreUrl.replace('__ID__', datos.levels[visor.levelIndex].id);

    enviar(url, fd)
      .then(function () { dlg.close(); cerrarFicha(); aviso(T('okSaved')); return recargar(); })
      .catch(function (e) { aviso(e.message, true); });
  });

  q('[data-point-delete]').addEventListener('click', function () {
    var dlg = q('[data-map-point-dialog]');
    if (!dlg.dataset.id || !window.confirm(T('pointConfirm'))) { return; }
    enviar(META.pointDeleteUrl.replace('__ID__', dlg.dataset.id), {})
      .then(function () { dlg.close(); cerrarFicha(); aviso(T('okDeleted')); return recargar(); })
      .catch(function (e) { aviso(e.message, true); });
  });

  /* --------------------------------------------- pestañas de las columnas */

  /**
   * Dos pestañas por columna. Un solo manejador para las dos, porque lo único
   * que cambia es qué panel se enseña.
   *
   * El panel se busca **dentro de la misma columna** (`closest('.map-side')`) y
   * no en todo el documento: las dos columnas usan los mismos nombres de
   * atributo, y buscar suelto haría que pulsar en una moviera la otra.
   */
  function abrirPestana(btn) {
    var col = btn.closest('.map-side');
    if (!col) { return; }
    var nombre = btn.getAttribute('data-map-tab');

    Array.prototype.forEach.call(col.querySelectorAll('[data-map-tab]'), function (b) {
      var suya = b === btn;
      b.classList.toggle('is-on', suya);
      b.setAttribute('aria-selected', suya ? 'true' : 'false');
    });
    Array.prototype.forEach.call(col.querySelectorAll('[data-map-pane]'), function (p) {
      p.hidden = p.getAttribute('data-map-pane') !== nombre;
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-map-tab]'), function (btn) {
    btn.addEventListener('click', function () { abrirPestana(btn); });
  });

  /** Lleva una columna a la pestaña indicada, sin saber cuál está abierta. */
  function irAPestana(nombre) {
    var btn = q('[data-map-tab="' + nombre + '"]');
    if (btn) { abrirPestana(btn); }
  }

  /* --------------------------------------------- los datos del mapa */

  var formAjustes = q('[data-map-settings]');
  if (formAjustes) {
    formAjustes.addEventListener('submit', function (ev) {
      ev.preventDefault();
      enviar(META.settingsUrl, new FormData(formAjustes))
        .then(function (j) {
          aviso(T('okSettings'));
          // El título de la barra también cambia: si no, el editor seguiría
          // diciendo el nombre viejo hasta recargar.
          var t = q('.map-bar-title strong');
          if (t && j && j.name) { t.textContent = j.name; }
        })
        .catch(function (e) { aviso(e.message, true); });
    });
  }

  /* ------------------------------------------------------- la barra */

  var editable = true;

  /**
   * Elegir herramienta y trazar.
   *
   * Una sola máquina de estados para los cuatro tipos con vértices: el clic
   * empuja uno, y el rectángulo y el círculo se cierran solos al llegar a dos.
   * Tener un camino por tipo sería cuatro veces el mismo código con una cifra
   * distinta.
   */
  var CON_VERTICES = { line: 0, polygon: 0, rect: 2, circle: 2 };

  function salirDeHerramienta() {
    herramienta = null;
    dibujo = { kind: null, pts: [] };
    visor.setDraft(null);
    visor.stage.classList.remove('is-adding');
    accionesTrazado.hidden = true;
    Array.prototype.forEach.call(document.querySelectorAll('[data-map-tool]'), function (b) {
      b.classList.remove('is-on');
      b.setAttribute('aria-pressed', 'false');
    });
  }

  function anadirVertice(pos) {
    dibujo.pts.push([+pos.x.toFixed(4), +pos.y.toFixed(4)]);
    visor.setDraft(dibujo.kind, dibujo.pts);
    accionesTrazado.hidden = false;

    var tope = CON_VERTICES[dibujo.kind];
    if (tope && dibujo.pts.length >= tope) { terminarTrazado(); }
  }

  /** Mínimos por tipo; el servidor los vuelve a comprobar, esto solo evita el viaje. */
  var MINIMOS = { line: 2, polygon: 3, rect: 2, circle: 2 };

  function terminarTrazado() {
    var kind = dibujo.kind;
    var pts  = dibujo.pts.slice();

    // Un doble clic deja además dos clics sueltos, así que el último vértice
    // llega repetido. Se descarta lo que esté prácticamente encima de lo
    // anterior en vez de intentar suprimir el segundo clic, que es más frágil.
    pts = pts.filter(function (v, i) {
      return i === 0 || Math.abs(v[0] - pts[i - 1][0]) > 0.3 || Math.abs(v[1] - pts[i - 1][1]) > 0.3;
    });

    if (pts.length < (MINIMOS[kind] || 2)) { aviso(T('shapeFew'), true); return; }

    salirDeHerramienta();
    abrirFormulario(null, { x: pts[0][0], y: pts[0][1] }, kind, pts);
  }

  var accionesTrazado = q('[data-map-draw-actions]');

  Array.prototype.forEach.call(document.querySelectorAll('[data-map-tool]'), function (btn) {
    btn.addEventListener('click', function () {
      var kind = btn.getAttribute('data-map-tool');
      var lv   = datos.levels[visor.levelIndex];
      if (!lv || !lv.image) { aviso(T('needPlan'), true); return; }

      var mismo = herramienta === kind;
      salirDeHerramienta();          // volver a pulsarla sale, y cambiar limpia
      if (mismo) { return; }

      herramienta = kind;
      dibujo = { kind: CON_VERTICES[kind] !== undefined ? kind : null, pts: [] };
      btn.classList.add('is-on');
      btn.setAttribute('aria-pressed', 'true');
      // La misma clase que ya usaba «añadir punto»: cursor de cruz y los pines
      // y las formas dejan de tragarse el clic, que es lo que hace falta para
      // poder trazar por encima de lo que ya está dibujado.
      visor.stage.classList.add('is-adding');
      moviendoId = null;
      cerrarFicha();
      aviso(T('shapeHint' + kind.charAt(0).toUpperCase() + kind.slice(1)));
    });
  });

  q('[data-map-finish]').addEventListener('click', terminarTrazado);
  q('[data-map-cancel]').addEventListener('click', salirDeHerramienta);

  // Doble clic sobre el plano: cerrar la forma. Es lo que espera cualquiera que
  // haya dibujado un polígono en cualquier otro programa.
  visor.stage.addEventListener('dblclick', function () {
    if (dibujo.kind && dibujo.pts.length) { terminarTrazado(); }
  });

  q('[data-map-newlevel]').addEventListener('click', function () { editarNivel(null); });
  q('[data-map-fit]').addEventListener('click', function () { visor.fit(); });
  q('[data-map-in]').addEventListener('click', function () { visor.zoomBy(1.3); });
  q('[data-map-out]').addEventListener('click', function () { visor.zoomBy(1 / 1.3); });

  // Filtro por categoría: se pinta desde los datos, no repetido en la vista.
  var filtro = q('[data-map-filter]');
  if (filtro) {
    filtro.addEventListener('change', function () {
      visor.setFilter(this.value === '' ? null : parseInt(this.value, 10));
      cerrarFicha();
    });
  }

  document.addEventListener('keydown', function (ev) {
    // Con un trazado a medias, Escape lo cancela y **devuelve ahí mismo**: si
    // siguiera, cerraría además la ficha y una sola tecla haría dos cosas.
    if (ev.key === 'Escape' && herramienta) { salirDeHerramienta(); return; }
    if (ev.key === 'Escape') { cerrarFicha(); return; }

    if (!dibujo.kind || !dibujo.pts.length) { return; }

    // Enter cierra la forma y Retroceso quita el último vértice: es lo que hace
    // cualquier programa de dibujo, y sin ellos un vértice mal puesto obliga a
    // empezar de cero.
    if (ev.key === 'Enter') { ev.preventDefault(); terminarTrazado(); }
    if (ev.key === 'Backspace') {
      ev.preventDefault();
      dibujo.pts.pop();
      visor.setDraft(dibujo.kind, dibujo.pts);
      if (!dibujo.pts.length) { accionesTrazado.hidden = true; }
    }
  });

  pintarNiveles();
  pintarPuntos();
})();
