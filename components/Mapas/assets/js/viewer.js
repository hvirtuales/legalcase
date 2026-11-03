/*
 * Visor del mapa: el plano en CANVAS, los pines en DOM.
 *
 * Por qué esa mezcla y no todo en uno:
 *
 *  - El **plano** va en canvas porque es un bitmap de varios megapíxeles y hay
 *    que moverlo y escalarlo a 60 fps. En DOM, un `<img>` con `transform`
 *    también funcionaría, pero canvas permite decidir la calidad del remuestreo
 *    y no depende de cómo cada navegador decida escalar la imagen.
 *  - Los **pines** van en DOM porque un pin dibujado en canvas no se puede
 *    enfocar con el teclado, no lo ve un lector de pantalla y obliga a resolver
 *    a mano en qué pin se hizo clic. Como `<button>` sobre el lienzo son
 *    accesibles, se estilan con CSS y el tooltip no necesita geometría.
 *
 * Los dos comparten la misma transformación (escala + desplazamiento), así que
 * no se pueden desincronizar: se calcula una vez por fotograma y se aplica a
 * los dos.
 *
 * Las coordenadas de un punto son PORCENTAJE del plano (0–100), nunca píxeles.
 * Ver la migración de puntos para el porqué.
 *
 * Sin dependencias ni CDN.
 */
(function (global) {
  'use strict';

  var MAX_ZOOM = 8;
  // Píxeles de pantalla que hay que recorrer para que un gesto deje de ser un
  // clic y pase a ser un arrastre. Lo comparten el plano, los pines y las
  // formas: si cada uno tuviera el suyo, tocar un elemento se sentiría distinto
  // según qué elemento fuera.
  var UMBRAL_ARRASTRE = 3;
  // Alto del pin y hueco hasta la ficha. Tienen que ir a la par con
  // `.mv-pin{height}` de `_map_styles.php`: el anclaje de la ficha cuenta con
  // ellos para quedar justo encima de la punta.
  var PIN_H = 26;
  var GAP = 10;

  function crear(tag, attrs, hijos) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') { n.textContent = attrs[k]; }
      else if (k === 'html') { n.innerHTML = attrs[k]; }
      else if (k === 'on') { Object.keys(attrs.on).forEach(function (e) { n.addEventListener(e, attrs.on[e]); }); }
      else if (attrs[k] === true) { n.setAttribute(k, ''); }
      else if (attrs[k] !== false && attrs[k] != null) { n.setAttribute(k, attrs[k]); }
    });
    (hijos || []).forEach(function (c) { if (c) { n.appendChild(c); } });
    return n;
  }

  /**
   * Lo mismo, pero para SVG.
   *
   * Hace falta un hermano y no un `crear()` más listo: `createElement` no sirve
   * para SVG —los nodos van en su propio namespace y el navegador los pinta
   * como desconocidos, sin decir nada—, y hacer que `crear()` decida por el
   * nombre de la etiqueta se equivocaría en silencio con `<a>` y `<title>`, que
   * existen en los dos mundos.
   */
  function crearNS(tag, attrs, hijos) {
    var n = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') { n.textContent = attrs[k]; }
      else if (k === 'on') { Object.keys(attrs.on).forEach(function (e) { n.addEventListener(e, attrs.on[e]); }); }
      else if (attrs[k] === true) { n.setAttribute(k, ''); }
      else if (attrs[k] !== false && attrs[k] != null) { n.setAttribute(k, attrs[k]); }
    });
    (hijos || []).forEach(function (c) { if (c) { n.appendChild(c); } });
    return n;
  }

  /**
   * @param {HTMLElement} root   contenedor
   * @param {object} opts        { data, editable, onMove, onPick, onCanvasClick, i18n }
   */
  function MapViewer(root, opts) {
    this.root = root;
    this.opts = opts || {};
    this.data = this.opts.data || { levels: [], categories: [] };
    this.editable = !!this.opts.editable;

    this.levelIndex = 0;
    this.img = null;          // la imagen del nivel actual, ya cargada
    this.scale = 1;
    this.tx = 0;
    this.ty = 0;
    this.filtro = null;       // id de categoría, o null = todas
    // El sub-elemento que se está enseñando. Los hijos NO se dibujan con los
    // demás: uno solo, y solo cuando se pide desde la ficha de su padre.
    this.activeChild = null;
    this.pins = [];           // los botones del DOM, en el mismo orden que los puntos
    this.formas = [];         // lo dibujado en la capa SVG: líneas, áreas y rótulos

    this._build();
    this.setLevel(0);
  }

  MapViewer.prototype._build = function () {
    var self = this;

    this.canvas = crear('canvas', { class: 'mv-canvas' });
    this.ctx = this.canvas.getContext('2d');

    // La capa de las formas, ENTRE el plano y los pines: lo dibujado se lee
    // sobre el plano y por debajo de los pines, y el orden lo da el DOM (en SVG
    // no hay `z-index`). Va en SVG y no en el canvas porque una forma tiene que
    // poder recibir el clic para abrir su ficha —canvas no da eventos—, porque
    // `vector-effect` mantiene el grosor del trazo al ampliar sin dividir nada
    // por la escala, y porque `_draw()` limpia el lienzo entero en cada
    // fotograma y sale antes si el nivel aún no tiene plano cargado.
    this.shapes = crearNS('svg', { class: 'mv-shapes', 'aria-hidden': 'false' });

    // La capa de pines: NO recibe eventos salvo en los propios pines, para que
    // arrastrar el plano funcione aunque el cursor pase por encima de uno.
    this.layer = crear('div', { class: 'mv-pins' });

    // `is-editable` en el armazón y no una clase por elemento: lo que cambia
    // es el modo, no cada pin. De ahí cuelga el cursor que anuncia que se puede
    // arrastrar, que en la página pública no debe aparecer.
    this.stage = crear('div', { class: 'mv-stage' + (this.editable ? ' is-editable' : '') }, [this.canvas, this.shapes, this.layer]);
    this.root.appendChild(this.stage);

    this._bindPan();
    this._bindZoom();

    // El lienzo se redimensiona con la ventana; `ResizeObserver` y no `resize`
    // porque el contenedor también cambia al plegar un panel lateral.
    if (global.ResizeObserver) {
      this._ro = new ResizeObserver(function () { self.resize(); });
      this._ro.observe(this.stage);
    } else {
      global.addEventListener('resize', function () { self.resize(); });
    }
  };

  /* ------------------------------------------------------------ niveles */

  MapViewer.prototype.levels = function () { return this.data.levels || []; };

  MapViewer.prototype.setLevel = function (i) {
    var lv = this.levels()[i];
    // Cambiar de piso o de filtro cierra la ficha: lo que enseñaba puede que ya
    // ni se pinte. Se hace AQUÍ y no dentro de `_paintPins()`, que es donde
    // estaba: allí era un efecto secundario del pintado, y encender un
    // sub-elemento —que repinta— se llevaba por delante la ficha desde la que
    // se acababa de pulsar el botón.
    this.detachCard();
    this.levelIndex = i;
    this.img = null;
    this._paint();

    if (!lv || !lv.image) { this.resize(); return; }

    var self = this;
    var img = new Image();
    // El plano puede venir de S3/R2; sin esto, `drawImage` marcaría el canvas
    // como contaminado y cualquier lectura de píxeles fallaría.
    img.crossOrigin = 'anonymous';
    img.onload = function () {
      self.img = img;
      // Si el servidor no guardó las medidas, se toman de la imagen ya cargada.
      if (!lv.width || !lv.height) { lv.width = img.naturalWidth; lv.height = img.naturalHeight; }
      self.fit();
    };
    img.onerror = function () { self.img = null; self.resize(); };
    img.src = lv.image;
  };

  MapViewer.prototype.level = function () { return this.levels()[this.levelIndex] || null; };

  /* -------------------------------------------------------- encuadre */

  /** Encuadre de partida: el plano cubriendo el lienzo, y centrado. */
  MapViewer.prototype.fit = function () {
    var lv = this.level();
    var w = this.stage.clientWidth;
    var h = this.stage.clientHeight;
    if (!lv || !lv.width || !lv.height || !w || !h) { this.resize(); return; }

    this.scale = this.fitScale();
    this.tx = (w - lv.width * this.scale) / 2;
    this.ty = (h - lv.height * this.scale) / 2;
    this.resize();
  };

  /**
   * La escala mínima: la que hace que el plano **cubra** el lienzo.
   *
   * Antes era la que lo dejaba entero a la vista (`min`, «contener»), y con
   * un plano que no tuviera la proporción exacta del hueco quedaban franjas
   * de fondo arriba y abajo o a los lados. Eso no es un mapa: es una foto
   * suelta en una página. Con `max` («cubrir») el plano llena siempre la
   * ventana y lo que se recorta se alcanza desplazándose —hasta el borde de
   * la imagen y ni un píxel más, de eso se encarga `_clampView()`—.
   *
   * El precio es que el plano ya no se ve entero de un vistazo cuando su
   * proporción no es la del hueco. Se acepta a cambio de que no haya fondo:
   * en un tótem o en un móvil, el marco gris alrededor se lee como que la
   * página está rota.
   */
  MapViewer.prototype.fitScale = function () {
    var lv = this.level();
    var w = this.stage.clientWidth, h = this.stage.clientHeight;
    if (!lv || !lv.width || !lv.height || !w || !h) { return 1; }

    return Math.max(w / lv.width, h / lv.height);
  };

  /**
   * Mantiene el plano cubriendo el lienzo: acota la escala por abajo y el
   * desplazamiento a los bordes de la imagen.
   *
   * Va en `resize()` y no en cada sitio que mueve la vista porque `resize()`
   * es el embudo por el que pasan todos —arrastrar, la rueda, el pellizco, la
   * animación, `fit()` y el propio cambio de tamaño de la ventana—. Repartido,
   * el día que se añada otra forma de mover el mapa se olvidaría en esa.
   */
  MapViewer.prototype._clampView = function () {
    var lv = this.level();
    var w = this.stage.clientWidth, h = this.stage.clientHeight;
    if (!lv || !lv.width || !lv.height || !w || !h) { return; }

    var min = this.fitScale();
    if (this.scale < min) { this.scale = min; }

    var iw = lv.width * this.scale, ih = lv.height * this.scale;
    // `<= w + .5` y no `<= w`: con la escala justa de cubrir, el redondeo deja
    // el ancho una milésima corto y aparecería una línea de fondo.
    this.tx = iw <= w + 0.5 ? (w - iw) / 2 : Math.min(0, Math.max(w - iw, this.tx));
    this.ty = ih <= h + 0.5 ? (h - ih) / 2 : Math.min(0, Math.max(h - ih, this.ty));
  };

  /**
   * Lleva la vista hasta un punto y lo deja **centrado**, ampliado lo bastante
   * para ver dónde está dentro del piso.
   *
   * Con cincuenta pines sobre un plano de 4000 px, encontrar uno a ojo es
   * inviable: esto es lo que convierte una lista de locales en una forma de
   * llegar a ellos. Se amplía a 2,5 veces el encuadre de partida —el que cubre
   * el lienzo—, que enseña el punto y su entorno; ampliar más lo dejaría sin
   * referencias alrededor.
   *
   * Puede quedar **descentrado** si el punto está pegado a un borde: la vista
   * se acota para no enseñar fondo, y ahí eso manda sobre centrarlo.
   *
   * El movimiento va **animado** a propósito: si la vista saltara, no se
   * entendería adónde ha ido el mapa. Con `prefers-reduced-motion` se hace de
   * golpe, que es lo que ha pedido quien lo configuró.
   */
  MapViewer.prototype.focusPoint = function (p, escala, alLlegar) {
    var lv = this.level();
    var w = this.stage.clientWidth, h = this.stage.clientHeight;
    if (!lv || !lv.width || !lv.height || !w || !h) { return; }

    var cx = p.x;
    var cy = p.y;
    var s = escala;

    // Una FORMA se encuadra por su rectángulo contenedor: centrar el centroide
    // de una ruta larga a 2,5× no la enseña entera, y desde el directorio es
    // justo lo que se espera al pulsarla. Va aquí dentro y no en un método
    // hermano para que todo lo que ya llama a `focusPoint` —el directorio
    // público, el nombre de la fila en el editor— funcione con formas sin
    // tocar una línea, y para que no quede forma de llamar a la equivocada.
    var g = p.geom;
    if (!s && g && g.length) {
      var xs = g.map(function (v) { return v[0]; });
      var ys = g.map(function (v) { return v[1]; });
      var x0 = Math.min.apply(null, xs), x1 = Math.max.apply(null, xs);
      var y0 = Math.min.apply(null, ys), y1 = Math.max.apply(null, ys);
      cx = (x0 + x1) / 2;
      cy = (y0 + y1) / 2;

      // El 0,85 es el aire alrededor: encajarla al milímetro la deja tocando
      // los bordes y no se entiende dónde está dentro del piso.
      var bw = Math.max(((x1 - x0) / 100) * lv.width, 1);
      var bh = Math.max(((y1 - y0) / 100) * lv.height, 1);
      s = Math.min(MAX_ZOOM, Math.max(this.fitScale(), Math.min(w / bw, h / bh) * 0.85));
    }

    s = s || Math.min(MAX_ZOOM, Math.max(this.fitScale() * 2.5, this.fitScale()));

    this.flyTo(cx, cy, s, alLlegar);
  };

  /**
   * El VIAJE hasta un sitio: se aleja un poco, se desplaza y se acerca.
   *
   * Interpolar la posición y la escala en línea recta llega, pero se lee plano
   * —el mapa se desliza y ya—. Aquí la escala describe una curva que **baja en
   * el camino y sube al llegar**, que es como se mira un plano de verdad:
   * primero se echa la vista atrás para situarse y luego se acerca. Y con eso
   * se entiende *cuánto* se ha viajado, que un deslizamiento plano no cuenta.
   *
   * Se interpola el **centro en porcentaje**, no `tx`/`ty`: con la escala
   * cambiando a la vez, mover el desplazamiento en línea recta haría que el
   * destino se saliera y volviera. Cada fotograma se recalculan `tx`/`ty` a
   * partir del centro y la escala de ese instante.
   *
   * @param {number} cx centro de destino, en porcentaje del plano
   * @param {number} cy
   */
  MapViewer.prototype.flyTo = function (cx, cy, escala, alLlegar) {
    var self = this;
    var lv = this.level();
    var w = this.stage.clientWidth, h = this.stage.clientHeight;
    if (!lv || !lv.width || !lv.height || !w || !h) { return; }

    if (this._anim) { cancelAnimationFrame(this._anim); this._anim = null; }

    var desde = this.toPercent(w / 2, h / 2);
    var s0 = this.scale;
    var piso = this.fitScale();

    var colocar = function (x, y, s) {
      self.scale = s;
      self.tx = w / 2 - (x / 100) * lv.width * s;
      self.ty = h / 2 - (y / 100) * lv.height * s;
      self.resize();
    };

    var quieto = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (quieto) { colocar(cx, cy, escala); if (alLlegar) { alLlegar(); } return; }

    // Cuánto hay que viajar, en porcentaje del plano. De ahí salen las dos
    // cosas que hacen que el movimiento se lea: cuánto dura y cuánto se aleja.
    var dist = Math.hypot(cx - desde.x, cy - desde.y);
    var dur = Math.min(900, 340 + dist * 6);
    // El alejamiento es proporcional al viaje —un salto corto casi no se
    // aleja— y **nunca por debajo de la escala que cubre el lienzo**, o
    // aparecería fondo justo en mitad del vuelo.
    var dip = Math.min(0.4, dist / 160);

    var t0 = performance.now();
    var paso = function (t) {
      var k = Math.min(1, (t - t0) / dur);
      // easeInOutCubic: arranca y termina despacio. Un `easeOut` a secas sale
      // disparado, que es justo la sensación que se quería quitar.
      var e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;

      var x = desde.x + (cx - desde.x) * e;
      var y = desde.y + (cy - desde.y) * e;
      // La curva del alejamiento: 0 al empezar y al terminar, máximo en medio.
      var s = (s0 + (escala - s0) * e) * (1 - dip * Math.sin(Math.PI * k));

      colocar(x, y, Math.max(piso, s));
      if (k < 1) { self._anim = requestAnimationFrame(paso); } else { self._anim = null; if (alLlegar) { alLlegar(); } }
    };
    this._anim = requestAnimationFrame(paso);
  };

  /**
   * Un destello sobre el elemento al que se acaba de llegar.
   *
   * Sin él, el viaje termina y no queda claro cuál de los diez pines de esa
   * esquina era el que se buscaba. Se quita solo, y con «menos movimiento» no
   * se enciende.
   */
  MapViewer.prototype.pulse = function (id) {
    var quieto = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (quieto) { return; }

    var el = this.shapes.querySelector('.mv-shape[data-id="' + id + '"]');
    if (!el) {
      var pin = this.pins.filter(function (x) { return x.point.id === id; })[0];
      el = pin && pin.el;
    }
    if (!el) { return; }

    el.classList.remove('is-arrived');
    // Forzar el reinicio: sin leer una propiedad de maquetación entre quitar y
    // poner, el navegador junta las dos cosas y la animación no vuelve a
    // arrancar en dos llegadas seguidas al mismo sitio.
    void el.getBoundingClientRect();
    el.classList.add('is-arrived');
    setTimeout(function () { el.classList.remove('is-arrived'); }, 900);
  };

  MapViewer.prototype.zoomBy = function (factor, cx, cy) {
    var w = this.stage.clientWidth, h = this.stage.clientHeight;
    if (cx == null) { cx = w / 2; cy = h / 2; }

    // Por abajo, la escala que cubre el lienzo: alejarse más enseñaría fondo.
    var nueva = Math.max(this.fitScale(), Math.min(MAX_ZOOM, this.scale * factor));
    if (nueva === this.scale) { return; }

    // Se amplía HACIA EL PUNTERO: el punto del plano que está bajo el cursor
    // tiene que seguir estando bajo el cursor después de ampliar. Sin esto, el
    // zoom «se va» y hay que recolocar el plano a mano en cada rueda.
    var k = nueva / this.scale;
    this.tx = cx - (cx - this.tx) * k;
    this.ty = cy - (cy - this.ty) * k;
    this.scale = nueva;
    this.resize();
  };

  /* ---------------------------------------------------------- pintado */

  MapViewer.prototype.resize = function () {
    var w = this.stage.clientWidth;
    var h = this.stage.clientHeight;
    if (!w || !h) { return; }

    // El canvas se dimensiona en píxeles REALES del dispositivo: en una
    // pantalla retina, hacerlo en píxeles CSS deja el plano borroso.
    var dpr = Math.min(global.devicePixelRatio || 1, 2);
    if (this.canvas.width !== Math.round(w * dpr) || this.canvas.height !== Math.round(h * dpr)) {
      this.canvas.width = Math.round(w * dpr);
      this.canvas.height = Math.round(h * dpr);
      this.canvas.style.width = w + 'px';
      this.canvas.style.height = h + 'px';
    }
    this._dpr = dpr;
    // Antes de dibujar: la vista nunca puede enseñar fondo.
    this._clampView();
    this._draw();
    this._placePins();
  };

  MapViewer.prototype._draw = function () {
    var ctx = this.ctx;
    if (!ctx) { return; }
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    if (!this.img) { return; }

    var d = this._dpr || 1;
    ctx.setTransform(this.scale * d, 0, 0, this.scale * d, this.tx * d, this.ty * d);
    // Suavizado bueno solo al reducir; al ampliar mucho, el navegador ya
    // interpola y forzarlo solo cuesta tiempo.
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = this.scale < 1 ? 'high' : 'low';
    ctx.drawImage(this.img, 0, 0);
  };

  /* ------------------------------------------------------------- pines */

  /** De porcentaje del plano a píxeles de la pantalla. */
  MapViewer.prototype.toScreen = function (xPct, yPct) {
    var lv = this.level();
    if (!lv) { return { x: 0, y: 0 }; }
    return {
      x: this.tx + (xPct / 100) * lv.width * this.scale,
      y: this.ty + (yPct / 100) * lv.height * this.scale
    };
  };

  /**
   * De píxeles de la pantalla a porcentaje del plano. Es la que usa el editor
   * al soltar un pin, y la que decide dónde queda guardado.
   */
  MapViewer.prototype.toPercent = function (px, py) {
    var lv = this.level();
    if (!lv || !lv.width || !lv.height) { return { x: 0, y: 0 }; }
    return {
      x: Math.max(0, Math.min(100, ((px - this.tx) / this.scale / lv.width) * 100)),
      y: Math.max(0, Math.min(100, ((py - this.ty) / this.scale / lv.height) * 100))
    };
  };

  MapViewer.prototype.categoryColor = function (id) {
    var c = (this.data.categories || []).filter(function (x) { return x.id === id; })[0];
    return (c && c.color) || '#2563eb';
  };

  /* ------------------------------------------------------------- formas */

  /** ¿Este elemento se dibuja como pin? Lo demás va a la capa de formas. */
  function esPin(p) {
    return !p.kind || p.kind === 'pin';
  }

  /**
   * Una propiedad de presentación, **solo si es suya**.
   *
   * `Object.prototype.hasOwnProperty` y no `s[k]` a secas: si `style` llegara
   * como un array —lo que hace un `[]` de PHP al serializarse— entonces
   * `s.fill` sería el **método nativo `Array.prototype.fill`**, que es truthy y
   * se colaría como si fuera un color. El servidor ya lo emite como objeto;
   * esto es el cinturón.
   */
  function prop(s, k) {
    return (s && Object.prototype.hasOwnProperty.call(s, k)) ? s[k] : undefined;
  }

  /** El color de un elemento: el suyo si lo pisó, si no el de su categoría. */
  MapViewer.prototype.shapeColor = function (p, cual) {
    return prop(p.style, cual) || this.categoryColor(p.category);
  };

  /**
   * Repinta las formas del nivel.
   *
   * Los vértices se escriben **una sola vez, en píxeles de la imagen**, y no se
   * vuelven a tocar al mover ni al ampliar: de eso se encarga la matriz que
   * `_placePins()` le pone a la capa entera. Es menos trabajo por fotograma del
   * que hacen los pines.
   */
  MapViewer.prototype._paintShapes = function () {
    var self = this;
    this.shapes.innerHTML = '';
    this.formas = [];

    var lv = this.level();
    if (!lv || !lv.width || !lv.height) { return; }

    this.shapes.setAttribute('viewBox', '0 0 ' + lv.width + ' ' + lv.height);
    this.shapes.style.width = lv.width + 'px';
    this.shapes.style.height = lv.height + 'px';

    var visibles = (lv.points || []).filter(function (p) {
      if (esPin(p)) { return false; }
      if (self.oculto(p)) { return false; }
      // El mismo filtro de categoría que los pines: si no, filtrar dejaría las
      // formas puestas y el plano diría una cosa y la leyenda otra.
      return self.filtro === null || p.category === self.filtro;
    });

    // Las áreas primero y **de mayor a menor**: una tienda pequeña dentro de una
    // plaza grande tiene que seguir siendo pulsable. Después las líneas, para
    // que una ruta se lea por encima de la zona que cruza. En SVG el orden del
    // documento ES el orden de apilado.
    var area = function (p) {
      var g = p.geom || [];
      if (!g.length) { return 0; }
      var xs = g.map(function (v) { return v[0]; });
      var ys = g.map(function (v) { return v[1]; });
      return (Math.max.apply(null, xs) - Math.min.apply(null, xs))
           * (Math.max.apply(null, ys) - Math.min.apply(null, ys));
    };
    var rango = { polygon: 0, rect: 0, circle: 0, line: 1, text: 2 };
    visibles.sort(function (a, b) {
      var d = (rango[a.kind] || 0) - (rango[b.kind] || 0);
      return d !== 0 ? d : area(b) - area(a);
    });

    visibles.forEach(function (p) {
      var g = self._shapeGroup(p, lv);
      if (!g) { return; }
      self.shapes.appendChild(g);
      self.formas.push({ el: g, point: p });
    });
  };

  /**
   * El `<g>` de UN elemento: su dibujo, su decoración y sus eventos.
   *
   * Sale de `_paintShapes()` porque aquello decidía a la vez qué se pinta
   * y cómo se monta cada cosa, y la regla del apilado —áreas primero y de
   * mayor a menor, para que la tienda dentro de la plaza siga siendo
   * pulsable— quedaba enterrada bajo ochenta líneas de construcción de SVG.
   *
   * @return {SVGGElement|null} null si el elemento no tiene nada que dibujar.
   */
  MapViewer.prototype._shapeGroup = function (p, lv) {
    var self = this;
    var nodo = self._shapeNode(p, lv);
    if (!nodo) { return null; }

    // El patrón del trazo y el movimiento van como CLASE y no solo como
    // atributo: el desplazamiento que hace que los guiones fluyan sin salto
    // tiene que valer exactamente el largo del patrón, y eso se resuelve en
    // la hoja de estilos, no aquí.
    var s = p.style || {};
    var borde = prop(s, 'border');
    var mueve = !!prop(s, 'flow') && p.kind !== 'text';
    var mueveFlechas = !!prop(s, 'arrows');
    // Proporcional al plano: a la escala de partida se lee, y al ampliar
    // crece con todo lo demás en vez de quedarse en un punto.
    var flechaTam = Math.max(8, Math.max(lv.width, lv.height) / 70);

    var g = crearNS('g', {
      class: 'mv-shape mv-shape-' + p.kind
        + (borde === 'dashed' || borde === 'dotted' ? ' is-' + borde : '')
        + (mueve ? ' is-flow' : '')
        + (p.active === false ? ' is-off' : '')
        // Es un sub-elemento, y si además es EL que se ha pedido. De estas
        // dos clases cuelga que el botón de la ficha se note: en el editor
        // los sub-elementos se dibujan siempre —hay que poder moverlos—, así
        // que sin ellas encenderlo no cambiaba nada en pantalla.
        + (p.parent ? ' is-sub' : '')
        + (p.parent && self.activeChild === p.id ? ' is-sub-on' : ''),
      'data-id': p.id,
      role: 'button',
      tabindex: '0',
      'aria-label': p.title,
      style: '--pin:' + self.categoryColor(p.category)
    }, [
      // El tooltip nativo del navegador, igual que el `title` del pin: lo lee
      // un lector de pantalla y sale al pasar el ratón, sin depender de la
      // ficha emergente.
      crearNS('title', { text: p.title })
    ]);

    // Las puntas de flecha: el sentido del recorrido, **sin movimiento**.
    // Van como `<marker>` de SVG 1.1 —lo más viejo y sólido que hay— y no
    // dibujadas a mano: el navegador las coloca y las orienta solo en cada
    // vértice y en el final, que es exactamente donde hacen falta.
    if (mueveFlechas && p.kind === 'line' && (p.geom || []).length > 1) {
      var idFlecha = 'mv-ar-' + p.id;
      // Un marcador POR FORMA porque lleva su color dentro: `context-stroke`
      // heredaría el del trazo con un solo marcador para todas, pero su
      // soporte en Safari es justo el que no se puede dar por hecho aquí.
      var m = crearNS('marker', {
        id: idFlecha,
        viewBox: '0 0 10 10', refX: 7, refY: 5,
        // En unidades del plano: la flecha crece con el mapa igual que la
        // forma que la lleva. El grosor del trazo es la excepción a esa
        // regla, no la norma.
        markerUnits: 'userSpaceOnUse',
        markerWidth: flechaTam, markerHeight: flechaTam,
        orient: 'auto'
      }, [
        crearNS('path', { d: 'M0,0 L10,5 L0,10 z', fill: self.shapeColor(p, 'stroke') })
      ]);
      g.appendChild(crearNS('defs', {}, [m]));
      nodo.setAttribute('marker-mid', 'url(#' + idFlecha + ')');
      nodo.setAttribute('marker-end', 'url(#' + idFlecha + ')');
    }

    // Una línea solo recibe el puntero en su trazo, y 4 px es un objetivo
    // cruel con el dedo: debajo va una copia transparente y gorda que es la
    // que se pulsa. Así el grosor con el que se ve y el grosor con el que se
    // acierta dejan de ser el mismo número.
    if (p.kind === 'line') {
      g.appendChild(self._shapeNode(p, lv, true));
    }
    g.appendChild(nodo);

    /**
     * Al pulsar una forma se avisa **con el sitio donde cayó el dedo**.
     *
     * Un pin es pequeño y su ficha se cuelga de él sin más; una forma puede
     * ocupar medio plano, y anclar la ficha a su centroide la mandaba fuera
     * de la pantalla justo cuando la forma es grande —que es cuando más falta
     * hace—. El punto va en PORCENTAJE del plano y no en píxeles: así la
     * ficha sigue al mapa al moverlo, igual que todo lo demás.
     */
    var abrir = function (ev) {
      ev.stopPropagation();
      // Un arrastre termina también en `click`. Sin esta salida, mover un
      // elemento le abriría la ficha justo al soltarlo —encima del sitio
      // donde se acaba de dejar—, que es lo contrario de lo que se pedía.
      if (g.__mvArrastrado) { g.__mvArrastrado = false; return; }
      if (!self.opts.onPick) { return; }
      var pos = null;
      if (ev.clientX !== undefined) {
        var r = self.stage.getBoundingClientRect();
        pos = self.toPercent(ev.clientX - r.left, ev.clientY - r.top);
      }
      self.opts.onPick(p, g, pos);
    };
    g.addEventListener('click', abrir);
    // El cuerpo entero se arrastra en el editor: los manejadores mueven un
    // vértice, y sin esto una forma solo se podía desplazar entera con el
    // botón de recolocar.
    if (self.editable) { self._bindShapeDrag(g, p); }
    g.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); abrir(ev); }
    });

    return g;
  };

  /**
   * El nodo SVG de una forma, en píxeles de la imagen.
   *
   * @param {boolean} hit true para la copia transparente que recibe el clic
   */
  MapViewer.prototype._shapeNode = function (p, lv, hit) {
    var g = p.geom || [];
    var s = p.style || {};
    var X = function (v) { return (v[0] / 100) * lv.width; };
    var Y = function (v) { return (v[1] / 100) * lv.height; };

    var comun = {
      // `non-scaling-stroke`: el trazo mide lo mismo en pantalla a cualquier
      // aumento. Sin esto habría que dividir el grosor por la escala en cada
      // fotograma, que es justo lo que obliga a hacer el canvas.
      'vector-effect': 'non-scaling-stroke',
      'stroke-width': hit ? 16 : (prop(s, 'strokeWidth') || (p.kind === 'line' ? 4 : 2)),
      class: hit ? 'mv-shape-hit' : 'mv-shape-draw'
    };
    if (!hit) {
      // «Sin borde» solo se honra en un área: una línea sin trazo desaparece
      // del plano y no habría forma de saber por qué.
      var sinBorde = prop(s, 'border') === 'none' && p.kind !== 'line';
      comun.stroke = sinBorde ? 'none' : this.shapeColor(p, 'stroke');
      comun.fill = p.kind === 'line' ? 'none' : this.shapeColor(p, 'fill');
      if (p.kind !== 'line') {
        // Un área SIEMPRE lleva relleno, aunque sea tenue: con `fill:none` el
        // interior no recibe el puntero y solo se podría pulsar el borde.
        comun['fill-opacity'] = (prop(s, 'fillOpacity') == null ? 18 : prop(s, 'fillOpacity')) / 100;
      }
      if (prop(s, 'border') === 'dashed') { comun['stroke-dasharray'] = '10 7'; }
      if (prop(s, 'border') === 'dotted') { comun['stroke-dasharray'] = '1 6'; comun['stroke-linecap'] = 'round'; }
    } else {
      comun.stroke = 'transparent';
      comun.fill = 'none';
    }

    if (p.kind === 'text') {
      // Un rótulo no es una forma con texto encima: es el texto, colocado en su
      // punto. No lleva geometría, así que se sitúa por el ancla.
      return crearNS('text', {
        x: (p.x / 100) * lv.width,
        y: (p.y / 100) * lv.height,
        class: 'mv-shape-text',
        fill: this.shapeColor(p, 'fill'),
        'font-size': prop(s, 'fontSize') || 18,
        'text-anchor': 'middle',
        'dominant-baseline': 'middle',
        text: p.title
      });
    }

    if (!g.length) { return null; }

    if (p.kind === 'circle') {
      var cx = X(g[0]);
      var cy = Y(g[0]);
      // El radio se mide en PÍXELES DE LA IMAGEN a partir del punto del borde:
      // en porcentaje saldría una elipse en cuanto el plano no fuera cuadrado.
      var r = Math.hypot(X(g[1]) - cx, Y(g[1]) - cy);
      return crearNS('circle', Object.assign({ cx: cx, cy: cy, r: r }, comun));
    }

    if (p.kind === 'rect') {
      var x0 = Math.min(X(g[0]), X(g[1]));
      var y0 = Math.min(Y(g[0]), Y(g[1]));
      return crearNS('rect', Object.assign({
        x: x0, y: y0,
        width: Math.abs(X(g[1]) - X(g[0])),
        height: Math.abs(Y(g[1]) - Y(g[0]))
      }, comun));
    }

    var puntos = g.map(function (v) { return X(v) + ',' + Y(v); }).join(' ');

    return crearNS(p.kind === 'polygon' ? 'polygon' : 'polyline', Object.assign({ points: puntos }, comun));
  };

  /**
   * El BORRADOR: la forma que se está trazando, antes de guardarla.
   *
   * Lo pinta **el mismo código** que la forma definitiva (`_shapeNode`), que es
   * lo que garantiza que lo que se ve al trazar es lo que se va a guardar. Vive
   * en la misma capa, en su propio `<g>`, y se borra con `setDraft(null)`.
   *
   * @param {string|null} kind
   * @param {Array} pts vértices en porcentaje
   */
  MapViewer.prototype.setDraft = function (kind, pts) {
    if (this._draft) { this._draft.remove(); this._draft = null; }

    var lv = this.level();
    if (!kind || !pts || !pts.length || !lv) { return; }

    var falso = { kind: kind, geom: pts, style: {}, category: null, x: pts[0][0], y: pts[0][1] };
    var g = crearNS('g', { class: 'mv-draft mv-shape-' + kind });

    // Con un solo vértice todavía no hay forma que pintar; se enseñan los
    // vértices puestos para que se vea que el clic entró.
    var nodo = pts.length > 1 ? this._shapeNode(falso, lv) : null;
    if (nodo) { g.appendChild(nodo); }

    pts.forEach(function (v) {
      g.appendChild(crearNS('circle', {
        class: 'mv-draft-dot',
        cx: (v[0] / 100) * lv.width,
        cy: (v[1] / 100) * lv.height,
        r: 4
      }));
    });

    this.shapes.appendChild(g);
    this._draft = g;
  };

  /* ------------------------------------------- el trazo en marcha (respaldo) */

  /**
   * Comprueba que la animación del trazo **avanza de verdad**, y si no, la mueve
   * a mano.
   *
   * El movimiento lo hace CSS animando `stroke-dashoffset`, que es lo barato y
   * lo correcto. Pero Safari tiene un problema viejo con esa animación cuando el
   * trazo lleva `vector-effect: non-scaling-stroke` —que es justo lo que
   * mantiene el grosor al ampliar—: la animación se declara, el navegador dice
   * que existe, y el trazo **no se repinta**. Desde un PC no hay forma de
   * reproducirlo, así que en vez de adivinar se mide: se lee el desplazamiento
   * dos veces y, si no ha cambiado, se conduce desde JavaScript.
   *
   * Lo que **no** hace es saltarse la preferencia del sistema: si quien mira
   * pidió menos movimiento, aquí no se enciende nada. Esa decisión es de la
   * persona, no del navegador.
   */
  MapViewer.prototype._checkFlow = function () {
    var self = this;
    if (this._flowRaf || this._flowChecked) { return; }

    var quieto = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (quieto) { return; }

    var el = this.shapes.querySelector('.mv-shape.is-flow .mv-shape-draw');
    if (!el) { return; }

    var antes = global.getComputedStyle(el).strokeDashoffset;
    setTimeout(function () {
      if (!el.isConnected) { return; }
      var ahora = global.getComputedStyle(el).strokeDashoffset;
      if (antes !== ahora) { self._flowChecked = true; return; }   // CSS ya lo mueve

      // No se movió: se conduce a mano. La clase apaga la animación de CSS —una
      // animación gana a un estilo en línea, así que sin apagarla el valor que
      // se escriba aquí no tendría efecto.
      self.stage.classList.add('mv-flow-js');
      self._flowChecked = true;
      self._flowLoop();
    }, 260);
  };

  /** El motor de respaldo: solo se enciende si el de CSS no funcionó. */
  MapViewer.prototype._flowLoop = function () {
    var self = this;
    var t0 = performance.now();

    var paso = function (t) {
      var nodos = self.shapes.querySelectorAll('.mv-shape.is-flow .mv-shape-draw');
      if (!nodos.length) { self._flowRaf = null; return; }

      // El mismo ciclo que la hoja de estilos: 17 en 1,1 s el discontinuo y 7 en
      // 0,7 s el punteado. Si cambian allí, cambian aquí.
      nodos.forEach(function (n) {
        var punteado = n.parentNode && n.parentNode.classList.contains('is-dotted');
        var largo = punteado ? 7 : 17;
        var dur = punteado ? 700 : 1100;
        n.style.strokeDashoffset = -(((t - t0) % dur) / dur) * largo;
      });
      self._flowRaf = requestAnimationFrame(paso);
    };
    this._flowRaf = requestAnimationFrame(paso);
  };

  /** Repinta las dos capas. Lo que antes hacía `_paintPins()` por su cuenta. */
  MapViewer.prototype._paint = function () {
    this._paintShapes();
    this._paintPins();
    // Tras repintar hay formas nuevas: se vuelve a comprobar si el trazo se
    // mueve, porque puede que antes no hubiera ninguna en marcha.
    this._flowChecked = false;
    this._checkFlow();
  };

  /** Crea los botones de los pines del nivel actual. */
  MapViewer.prototype._paintPins = function () {
    var self = this;
    this.layer.innerHTML = '';
    this.pins = [];

    var lv = this.level();
    if (!lv) { return; }

    (lv.points || []).forEach(function (p, i) {
      // Solo los pines: lo demás lo dibuja la capa de formas, y sin esto un
      // polígono pintaría además un pin en su centroide.
      if (!esPin(p)) { return; }
      if (self.filtro !== null && p.category !== self.filtro) { return; }
      if (self.oculto(p)) { return; }

      var btn = crear('button', {
        type: 'button',
        class: 'mv-pin' + (p.active === false ? ' is-off' : ''),
        // El título va en el botón: es lo que lee un lector de pantalla y lo
        // que sale al pasar el ratón, sin depender del tooltip.
        title: p.title,
        'aria-label': p.title,
        'data-i': i,
        style: '--pin:' + self.categoryColor(p.category)
      });
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        // Igual que en una forma: arrastrar termina en `click`, y abrir la ficha
        // al soltar la pondría justo encima del sitio recién elegido.
        if (btn.__mvArrastrado) { btn.__mvArrastrado = false; return; }
        if (self.opts.onPick) { self.opts.onPick(p, btn); }
      });
      if (self.editable) { self._bindDrag(btn, p); }

      self.layer.appendChild(btn);
      self.pins.push({ el: btn, point: p });
    });

    // Los manejadores se recrean AQUÍ, al final, porque este método acaba de
    // hacer `layer.innerHTML = ''`: cualquier cosa que viva en la capa de pines
    // desaparece en cada `setLevel()` y en cada `setFilter()`.
    this._paintHandles();
    this._placePins();
  };

  /* --------------------------------------------- manejadores de vértice */

  /**
   * Enseña los vértices de una forma para poder arrastrarlos.
   *
   * Van como `<button>` en la capa de pines y **no como `<circle>` en el SVG**:
   * ahí tendrían que ser de tamaño constante en pantalla, y habría que
   * recalcular su radio dividiendo por la escala en cada fotograma. Como botones
   * del DOM el tamaño lo fija el CSS, se pueden enfocar con el teclado y los
   * coloca el mismo `_placePins()` que ya coloca los pines.
   *
   * @param {object|null} point la forma elegida, o null para esconderlos
   */
  MapViewer.prototype.setHandles = function (point) {
    this.handlePoint = (point && point.geom && point.geom.length) ? point : null;
    this._paintHandles();
    this._placePins();
  };

  MapViewer.prototype._paintHandles = function () {
    var self = this;

    this.handles = [];
    Array.prototype.forEach.call(this.layer.querySelectorAll('.mv-handle'), function (h) { h.remove(); });

    var p = this.handlePoint;
    if (!p || !p.geom || !this.editable) { return; }

    p.geom.forEach(function (v, i) {
      var h = crear('button', {
        type: 'button',
        class: 'mv-handle',
        'data-i': i,
        'aria-label': String(i + 1)
      });
      self._bindHandle(h, p, i);
      self.layer.appendChild(h);
      self.handles.push({ el: h, index: i });
    });
  };

  /**
   * Arrastrar un vértice.
   *
   * Escribe en la geometría **en vivo** para que el trazo siga al dedo, y solo
   * al soltar —y solo si de verdad se movió— avisa para guardar: un clic para
   * elegir un vértice no tiene por qué escribir en la base.
   */
  MapViewer.prototype._bindHandle = function (btn, point, i) {
    var self = this;
    var moviendo = false, movido = false;

    btn.addEventListener('pointerdown', function (ev) {
      // Sin esto, el gesto lo recogería el arrastre del plano.
      ev.stopPropagation();
      moviendo = true; movido = false;
      btn.setPointerCapture(ev.pointerId);
      btn.classList.add('is-dragging');
    });

    btn.addEventListener('pointermove', function (ev) {
      if (!moviendo) { return; }
      movido = true;
      var r = self.stage.getBoundingClientRect();
      var pos = self.toPercent(ev.clientX - r.left, ev.clientY - r.top);
      point.geom[i] = [+pos.x.toFixed(4), +pos.y.toFixed(4)];
      // Repintar la forma entera y no solo el manejador: es lo que hace que el
      // trazo siga al dedo en vez de moverse solo al soltar.
      self._paintShapes();
      self._placePins();
    });

    var soltar = function (ev) {
      if (!moviendo) { return; }
      moviendo = false;
      btn.classList.remove('is-dragging');
      try { btn.releasePointerCapture(ev.pointerId); } catch (e) {}
      if (movido && self.opts.onVertex) { self.opts.onVertex(point); }
    };
    btn.addEventListener('pointerup', soltar);
    btn.addEventListener('pointercancel', soltar);

    // Doble clic: quitar el vértice. Sin esto, uno mal puesto obliga a borrar la
    // forma y trazarla de nuevo. El mínimo lo comprueban las dos puntas — aquí
    // para no hacer el viaje, y el servidor porque es quien decide.
    btn.addEventListener('dblclick', function (ev) {
      ev.stopPropagation();
      var minimo = point.kind === 'polygon' ? 3 : 2;
      if (point.geom.length <= minimo) { return; }
      point.geom.splice(i, 1);
      self._paintShapes();
      self._paintHandles();
      self._placePins();
      if (self.opts.onVertex) { self.opts.onVertex(point); }
    });
  };

  /* ------------------------------------------- arrastrar el cuerpo entero */

  /**
   * Mover una forma —o un rótulo— arrastrándola por su cuerpo.
   *
   * Los manejadores mueven **un** vértice; esto mueve el elemento entero, que
   * es lo que hace falta cuando lo que está mal es el sitio y no la figura. Sin
   * ello, la única forma de trasladar un polígono era el botón de recolocar (un
   * clic aquí, otro allá) o arrastrar sus vértices uno a uno, que además la
   * deforma.
   *
   * **Durante el arrastre NO se repinta la capa de formas**: `_paintShapes()`
   * la vacía entera, así que se llevaría por delante el propio nodo que tiene
   * capturado el puntero y el gesto moriría en el primer fotograma. En su lugar
   * se le pone al nodo un `transform` con el desplazamiento —en píxeles de la
   * imagen, que es el sistema del SVG— y se escribe a la vez la geometría en
   * memoria: así los manejadores y la ficha, que sí se recolocan cada
   * fotograma, van con el cuerpo en lugar de quedarse atrás. Al soltar se
   * repinta una sola vez y el `transform` desaparece.
   */
  MapViewer.prototype._bindShapeDrag = function (g, point) {
    var self = this;
    var moviendo = false, movido = false;
    var ini = null, iniPx = null, base = null, baseXY = null;

    g.addEventListener('pointerdown', function (ev) {
      // Sin esto el gesto lo recogería el arrastre del plano.
      ev.stopPropagation();
      var lv = self.level();
      if (!lv) { return; }

      moviendo = true; movido = false;
      // La marca se limpia al EMPEZAR y no solo al consumirla: con el dedo, el
      // `click` se sintetiza en el sitio donde se soltó y puede no llegar a
      // este nodo, así que una marca puesta y nunca gastada se comería el
      // siguiente clic de verdad.
      g.__mvArrastrado = false;
      var r = self.stage.getBoundingClientRect();
      ini    = self.toPercent(ev.clientX - r.left, ev.clientY - r.top);
      iniPx  = { x: ev.clientX, y: ev.clientY };
      base   = (point.geom || []).map(function (v) { return [v[0], v[1]]; });
      baseXY = { x: point.x, y: point.y };
      try { g.setPointerCapture(ev.pointerId); } catch (e) {}
    });

    g.addEventListener('pointermove', function (ev) {
      if (!moviendo) { return; }

      // Umbral en píxeles de PANTALLA y no en porcentaje: el mismo temblor del
      // dedo vale muchísimo más porcentaje con el plano muy ampliado, así que
      // un umbral en porcentaje sería tacaño de lejos y sordo de cerca.
      if (!movido && Math.hypot(ev.clientX - iniPx.x, ev.clientY - iniPx.y) < UMBRAL_ARRASTRE) { return; }
      if (!movido) { movido = true; g.classList.add('is-dragging'); }

      var lv = self.level();
      if (!lv) { return; }
      var r   = self.stage.getBoundingClientRect();
      var pos = self.toPercent(ev.clientX - r.left, ev.clientY - r.top);
      var d   = self._clampDelta(base, baseXY, pos.x - ini.x, pos.y - ini.y);

      if (base.length) {
        point.geom = base.map(function (v) {
          return [+(v[0] + d.dx).toFixed(4), +(v[1] + d.dy).toFixed(4)];
        });
      }
      // El ancla se mueve con el cuerpo para que la ficha no se quede atrás; al
      // guardar, el servidor la recalcula desde la geometría —él decide.
      point.x = +(baseXY.x + d.dx).toFixed(4);
      point.y = +(baseXY.y + d.dy).toFixed(4);

      g.setAttribute('transform', 'translate('
        + ((d.dx / 100) * lv.width) + ',' + ((d.dy / 100) * lv.height) + ')');
      self._placePins();
    });

    var soltar = function (ev) {
      if (!moviendo) { return; }
      moviendo = false;
      g.classList.remove('is-dragging');
      try { g.releasePointerCapture(ev.pointerId); } catch (e) {}
      if (!movido) { return; }

      // Que el `click` que viene detrás no abra la ficha.
      g.__mvArrastrado = true;
      g.removeAttribute('transform');
      self._paintShapes();
      self._paintHandles();
      self._placePins();

      // Una forma guarda su geometría; un rótulo no tiene, así que guarda su
      // sitio por el mismo camino que un pin.
      var cb = base.length ? self.opts.onVertex : self.opts.onMove;
      if (cb) { cb(point); }
    };
    g.addEventListener('pointerup', soltar);
    g.addEventListener('pointercancel', soltar);
  };

  /**
   * El desplazamiento que **cabe** dentro del plano.
   *
   * Se acota el DELTA y no cada vértice por separado: acotando vértice a
   * vértice, al llegar a un borde los de fuera se quedarían pegados mientras
   * los de dentro siguen —y la forma se deformaría en vez de pararse—. Es la
   * misma regla que aplica `PointModel::translate()` en el servidor, que es
   * quien decide de verdad.
   */
  MapViewer.prototype._clampDelta = function (base, ancla, dx, dy) {
    var xs = base.length ? base.map(function (v) { return v[0]; }) : [ancla.x];
    var ys = base.length ? base.map(function (v) { return v[1]; }) : [ancla.y];
    var minX = Math.min.apply(null, xs), maxX = Math.max.apply(null, xs);
    var minY = Math.min.apply(null, ys), maxY = Math.max.apply(null, ys);

    return {
      dx: Math.max(-minX, Math.min(dx, 100 - maxX)),
      dy: Math.max(-minY, Math.min(dy, 100 - maxY))
    };
  };
  /**
   * El punto que hay detrás de un nodo del plano: un pin o una forma.
   *
   * Sale de `pins`/`formas`, que ya se llevan esa correspondencia al pintar, en
   * vez de colgar el objeto del DOM: una segunda copia se quedaría vieja en
   * cuanto `recargar()` reemplazara los datos.
   */
  MapViewer.prototype._pointOfNode = function (nodo) {
    var todos = (this.pins || []).concat(this.formas || []);
    for (var i = 0; i < todos.length; i++) {
      if (todos[i].el === nodo) { return todos[i].point; }
    }

    return null;
  };

  /** Recoloca los pines tras mover o ampliar. Es lo único que corre por fotograma. */
  /* ------------------------------------------------------- sub-elementos */

  /**
   * ¿Este elemento está escondido por colgar de otro?
   *
   * Un sub-elemento —la ruta de «cómo llegar» de un local— **no se dibuja con
   * los demás**: con cuarenta locales ofreciendo la suya, el plano deja de ser
   * un plano. Aparece cuando alguien la pide desde la ficha de su padre, y solo
   * una a la vez.
   *
   * En el EDITOR se dibujan todas: ahí hay que verlas para poder moverlas y
   * corregirles los vértices. Para ver el comportamiento de la calle está el
   * botón de vista previa, que abre la página publicada.
   */
  MapViewer.prototype.oculto = function (p) {
    if (!p.parent) { return false; }

    return !this.editable && this.activeChild !== p.id;
  };

  /** Los sub-elementos de un elemento, en TODOS los niveles: una ruta puede cruzar pisos. */
  MapViewer.prototype.childrenOf = function (p) {
    if (!p || !p.id) { return []; }
    var out = [];
    this.levels().forEach(function (lv, i) {
      (lv.points || []).forEach(function (q) {
        if (q.parent === p.id) { out.push({ point: q, levelIndex: i, levelName: lv.name }); }
      });
    });

    return out;
  };

  /**
   * Enseña —o esconde— un sub-elemento.
   *
   * Pulsar el mismo dos veces lo apaga: el botón es un interruptor, no un
   * disparador, porque quien enciende una ruta quiere poder apagarla sin tener
   * que buscar otra cosa que pulsar.
   *
   * El encuadre incluye **al padre y al hijo a la vez**. Enfocar solo la ruta
   * mandaría el local fuera de la pantalla —y con él la ficha, que va anclada a
   * él—, así que se vería el recorrido sin saber ya adónde lleva.
   */
  MapViewer.prototype.setActiveChild = function (id, padre) {
    var apagar = this.activeChild === id;
    this.activeChild = apagar ? null : id;

    if (apagar) {
      this._paint();
      this._syncSubButtons();

      return null;
    }

    var hijo = null, nivel = -1;
    this.levels().forEach(function (lv, i) {
      (lv.points || []).forEach(function (q) { if (q.id === id) { hijo = q; nivel = i; } });
    });
    if (!hijo) { return null; }

    // Puede vivir en otro piso: una ruta desde la entrada hasta un local de la
    // segunda planta son dos tramos, uno por nivel. Cambiar de piso cierra la
    // ficha —el padre ya no está a la vista—, así que tampoco se encuadra con
    // él: se encuadra la ruta sola.
    var otroPiso = nivel !== this.levelIndex;
    if (otroPiso) { this.setLevel(nivel); }
    this._paint();

    // Un rectángulo que abarca las dos cosas. Se arma como si fuera la
    // geometría de un elemento para reutilizar el encuadre que ya existe, en
    // vez de escribir un segundo que se desincronice con el primero.
    var puntos = (hijo.geom && hijo.geom.length ? hijo.geom.slice() : [[hijo.x, hijo.y]]);
    if (padre && !otroPiso) { puntos.push([padre.x, padre.y]); }
    /*
     * El desvío de la ficha se calcula UNA VEZ, al abrirla, y a partir de ahí
     * sigue al punto rígidamente —eso es a propósito: recalcularlo cada
     * fotograma la dejaría clavada al borde mientras el pin se marcha—. Pero
     * este encuadre ocurre DESPUÉS de abrirla, y mueve el punto: la ficha se
     * iba con él debajo del panel lateral y de la barra, donde sus botones
     * dejaban de recibir el puntero y ni siquiera se podía cerrar. Así que se
     * vuelve a medir **una sola vez, al terminar el viaje**.
     */
    var self = this;
    this.focusPoint({ x: hijo.x, y: hijo.y, geom: puntos }, undefined, function () { self._measureCard(); });
    this.pulse(hijo.id);
    this._syncSubButtons();

    return hijo;
  };

  /**
   * Pone al día los botones de la ficha abierta.
   *
   * Hace falta porque el sub-elemento **también se enciende desde fuera** —desde
   * el desplegable del directorio—, y entonces la ficha se arma antes de que el
   * estado cambie: sin esto, la ruta se dibujaba pero su botón salía apagado, y
   * quien mirara la ficha no sabría cuál está puesta ni qué apagar. Se
   * sincroniza en `setActiveChild()`, que es el único sitio donde cambia el
   * estado, en vez de en cada uno de los que lo llaman.
   */
  MapViewer.prototype._syncSubButtons = function () {
    if (!this.card) { return; }
    var activo = this.activeChild;
    Array.prototype.forEach.call(this.card.el.querySelectorAll('.mv-card-sub'), function (b) {
      var on = String(activo) === b.getAttribute('data-id');
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  };

  /** Un texto de la interfaz, con respaldo: un diccionario a medias no deja el botón mudo. */
  MapViewer.prototype.t = function (clave, defecto) {
    var d = this.opts.i18n || {};

    return d[clave] || defecto || clave;
  };

  /**
   * La ficha de un elemento.
   *
   * Vive **en el visor** y no en cada pantalla porque la armaban dos veces —el
   * editor y la página pública— con el mismo código copiado, y en cuanto una
   * ganaba algo, la otra se quedaba atrás. Lo que de verdad cambia entre las dos
   * son los botones de más (el editor añade «Editar»), y eso entra por `extra`.
   *
   * @param {object} p
   * @param {object} opts { onClose, extra: [nodos], onChild: fn }
   */
  MapViewer.prototype.buildCard = function (p, opts) {
    var self = this;
    opts = opts || {};

    var cat = (this.data.categories || []).filter(function (c) { return c.id === p.category; })[0];
    var cuerpo = [];

    if (cat) {
      cuerpo.push(crear('div', { class: 'mv-card-cat' }, [crear('i', {}), crear('span', { text: cat.name })]));
    }
    cuerpo.push(crear('h3', { text: p.title }));
    if (p.desc) { cuerpo.push(crear('p', { text: p.desc })); }

    // Los sub-elementos, como botones: es lo que convierte la ficha de un local
    // en algo con lo que se puede hacer algo —«cómo llegar»— en vez de un
    // cartel. El texto del botón es el título del hijo, así que lo escribe
    // quien monta el mapa y no hay que traducirlo.
    var hijos = this.childrenOf(p);
    if (hijos.length) {
      var caja = crear('div', { class: 'mv-card-subs' });
      hijos.forEach(function (h) {
        var b = crear('button', {
          type: 'button',
          'data-id': h.point.id,
          class: 'mv-card-sub' + (self.activeChild === h.point.id ? ' is-on' : ''),
          'aria-pressed': self.activeChild === h.point.id ? 'true' : 'false',
          text: h.point.title
        });
        // El piso solo si es OTRO: repetirlo en todos los botones sería la misma
        // palabra doce veces y dejaría de leerse.
        if (h.levelIndex !== self.levelIndex) {
          b.appendChild(crear('small', { text: h.levelName }));
        }
        b.addEventListener('click', function (ev) {
          ev.stopPropagation();
          var encendido = self.activeChild !== h.point.id;
          self.setActiveChild(h.point.id, p);
          if (opts.onChild) { opts.onChild(h.point, encendido); }
        });
        caja.appendChild(b);
      });
      cuerpo.push(caja);
    }

    if (p.url) {
      // `rel=noopener`: la pestaña que se abre no debe poder tocar la nuestra.
      cuerpo.push(crear('a', {
        class: 'mv-card-more', href: p.url, target: '_blank', rel: 'noopener noreferrer',
        text: this.t('more', 'Ver más')
      }));
    }
    (opts.extra || []).forEach(function (n) { if (n) { cuerpo.push(n); } });

    return crear('div', { class: 'mv-card', style: '--pin:' + (cat ? cat.color : '#2563eb') }, [
      p.image ? crear('img', { src: p.image, alt: p.title, loading: 'lazy' }) : null,
      crear('div', { class: 'mv-card-body' }, cuerpo),
      crear('button', {
        type: 'button', class: 'mv-card-close', text: '\u2715',
        'aria-label': this.t('close', 'Cerrar'),
        on: { click: function () { if (opts.onClose) { opts.onClose(); } } }
      })
    ]);
  };

  /* --------------------------------------------------- la ficha del punto */

  /**
   * Ancla la ficha a un PUNTO, no a la posición que tenía el pin al abrirla.
   *
   * Antes la colocaban las dos pantallas por su cuenta, leyendo el rectángulo
   * del pin y escribiendo un `left`/`top` fijos: en cuanto se movía o se
   * ampliaba el mapa, la ficha se quedaba donde estaba y acababa señalando a
   * otro local. Ahora se recoloca en `_placePins()`, que ya corre una vez por
   * fotograma, así que ficha y pin no se pueden desincronizar — es la misma
   * razón por la que el plano y los pines comparten transformación.
   *
   * El **desvío se calcula una sola vez**, al abrirla, y luego se aplica tal
   * cual: así la ficha nace entera dentro del lienzo (si el punto está pegado
   * al borde derecho, se corre a la izquierda lo justo) y a partir de ahí
   * sigue al punto **rígidamente**. Recalcular el recorte en cada fotograma la
   * dejaría clavada al borde mientras el pin se marcha, que es justo lo que se
   * quería arreglar.
   */
  MapViewer.prototype.attachCard = function (el, point) {
    var self = this;
    this.detachCard();

    this.stage.appendChild(el);
    this.card = { el: el, point: point, dx: 0, dy: 0 };
    this._measureCard();

    // La imagen llega después y cambia el alto: sin volver a medir, la ficha
    // se queda montada sobre el pin.
    var img = el.querySelector('img');
    if (img && !img.complete) {
      img.addEventListener('load', function () { if (self.card && self.card.el === el) { self._measureCard(); } });
    }

    return el;
  };

  MapViewer.prototype.detachCard = function () {
    if (this.card) {
      if (this.card.el.parentNode) { this.card.el.parentNode.removeChild(this.card.el); }
      this.card = null;
    }
  };

  /** Decide el desvío de la ficha respecto a la punta del pin. Solo al abrir. */
  MapViewer.prototype._measureCard = function () {
    var c = this.card;
    if (!c) { return; }

    var w = c.el.offsetWidth, h = c.el.offsetHeight;
    var sw = this.stage.clientWidth, sh = this.stage.clientHeight;
    var s = this.toScreen(c.point.x, c.point.y);

    // Encima del pin: la punta marca el sitio, así que hay que subir su alto
    // (26 px) más el hueco.
    var dy = -(PIN_H + GAP + h);
    // Si arriba no cabe, debajo; y si tampoco, se queda arriba y que la corte
    // el lienzo antes que taparle al usuario el punto que acaba de tocar.
    if (s.y + dy < GAP && s.y + GAP + h < sh) { dy = GAP; }

    // Por la izquierda puede haber algo encima del lienzo —el directorio de
    // la página pública va superpuesto—, así que el borde útil no es 0. Lo
    // dice quien monta el visor con `opts.inset()`; sin él, el lienzo entero.
    var izq = GAP;
    if (typeof this.opts.inset === 'function') { izq = Math.max(GAP, this.opts.inset() + GAP); }

    var dx = -w / 2;
    // Recorte de apertura: la ficha entra entera aunque el punto esté al borde.
    if (s.x + dx < izq) { dx = izq - s.x; }
    else if (s.x + dx + w > sw - GAP) { dx = sw - GAP - w - s.x; }

    c.dx = dx; c.dy = dy;
    this._placeCard();
  };

  MapViewer.prototype._placeCard = function () {
    var c = this.card;
    if (!c) { return; }
    var s = this.toScreen(c.point.x, c.point.y);
    c.el.style.transform = 'translate(' + (s.x + c.dx) + 'px,' + (s.y + c.dy) + 'px)';
  };

  MapViewer.prototype._placePins = function () {
    var self = this;
    this.pins.forEach(function (pin) {
      var s = self.toScreen(pin.point.x, pin.point.y);
      // `translate(-50%,-100%)`: la punta del pin es la que marca el sitio, no
      // su centro. Si se centrara, el punto quedaría medio pin más abajo de
      // donde se hizo clic, y al ampliar el error crecería.
      pin.el.style.transform = 'translate(' + s.x + 'px,' + s.y + 'px) translate(-50%,-100%)';
    });

    // Los manejadores marcan el vértice con su CENTRO, no con una punta: no son
    // un pin, son el punto exacto.
    var lv = this.level();
    var geom = (this.handlePoint && this.handlePoint.geom) || [];
    (this.handles || []).forEach(function (h) {
      var v = geom[h.index];
      if (!v || !lv) { h.el.hidden = true; return; }
      h.el.hidden = false;
      var s = self.toScreen(v[0], v[1]);
      h.el.style.transform = 'translate(' + s.x + 'px,' + s.y + 'px) translate(-50%,-50%)';
    });

    // La capa de formas se mueve ENTERA con una sola transformación: sus
    // vértices están escritos en píxeles de la imagen y no se recalculan nunca.
    // Es la misma idea que sostiene el canvas y los pines —una sola matriz para
    // todos— y por eso no se pueden desincronizar.
    this.shapes.style.transform = 'translate(' + this.tx + 'px,' + this.ty + 'px) scale(' + this.scale + ')';

    this._placeCard();
  };

  /** Vuelve a leer los datos y repinta. */
  MapViewer.prototype.setData = function (data) {
    this.data = data || { levels: [], categories: [] };
    if (this.levelIndex >= this.levels().length) { this.levelIndex = 0; }
    this.setLevel(this.levelIndex);
  };

  MapViewer.prototype.setFilter = function (categoryId) {
    this.filtro = categoryId;
    this.detachCard();
    this._paint();
  };

  /* ------------------------------------------------- mover y ampliar */

  /**
   * Arrastrar el plano. Con Pointer Events, que unifican ratón, dedo y lápiz:
   * con `mousedown`/`touchstart` por separado habría dos caminos que mantener
   * y el táctil siempre se queda atrás.
   */
  MapViewer.prototype._bindPan = function () {
    var self = this;
    var arrastrando = false;
    var x0 = 0, y0 = 0, tx0 = 0, ty0 = 0, movido = false;
    // Sobre qué elemento empezó el gesto, cuando empezó sobre uno.
    var origen = null;

    this.stage.addEventListener('pointerdown', function (ev) {
      /*
       * Solo se arrastra el PLANO: el lienzo o el fondo del armazón.
       *
       * Antes era una lista de lo que NO arrastra —pines, formas, manejadores—,
       * y esa lista se quedó corta en cuanto la ficha creció: **la ficha vive
       * dentro del armazón**, así que pulsar un botón suyo empezaba un arrastre
       * del plano, el armazón capturaba el puntero y el clic no llegaba nunca al
       * botón; al soltar, el gesto se tomaba por un clic limpio en el plano y la
       * ficha se cerraba. Se veía como «el botón no hace nada». Afectaba a TODOS
       * los controles de la ficha —el sub-elemento, «Ver más», «Editar»—; el
       * aspa se libraba de milagro, porque cerrar era justo lo que pasaba.
       *
       * Invertida, la regla no se puede quedar corta: lo que no sea el plano es
       * interfaz puesta encima, y manda ella. (Las capas de formas y de pines
       * tienen `pointer-events:none`, así que nunca son el destino: lo son sus
       * hijos.)
       */
      var sobre = ev.target.closest('.mv-pin, .mv-shape, .mv-handle');

      // En el EDITOR mandan los elementos: empezar encima de uno lo arrastra a
      // ÉL, no al plano. Eso lo resuelven `_bindDrag`, `_bindShapeDrag` y
      // `_bindHandle`, cada uno con su `stopPropagation`.
      if (self.editable && sobre) { return; }

      // Fuera del editor no hay nada que arrastrar salvo el plano, así que un
      // pin o un área **no pueden bloquear el desplazamiento**: con un polígono
      // que ocupa media planta, el visitante empezaba el gesto encima y el mapa
      // no se movía. El clic limpio lo sigue recibiendo el elemento —lo reparte
      // `soltar()` según el umbral—, así que no se pierde nada a cambio.
      if (!sobre && ev.target !== self.stage && ev.target !== self.canvas) { return; }

      origen = sobre || null;
      arrastrando = true; movido = false;
      x0 = ev.clientX; y0 = ev.clientY; tx0 = self.tx; ty0 = self.ty;
      self.stage.setPointerCapture(ev.pointerId);
      self.stage.classList.add('is-panning');
    });

    this.stage.addEventListener('pointermove', function (ev) {
      // El puntero sobre el plano, solo mientras se está trazando algo: es lo
      // que dibuja el tramo hasta el cursor. Se comprueba la clase para no
      // pagar una conversión de coordenadas por cada movimiento del ratón en
      // la página pública, donde nadie traza nada.
      if (self.opts.onCanvasMove && self.stage.classList.contains('is-adding')) {
        var rr = self.stage.getBoundingClientRect();
        self.opts.onCanvasMove(self.toPercent(ev.clientX - rr.left, ev.clientY - rr.top));
      }
      if (!arrastrando) { return; }
      var dx = ev.clientX - x0, dy = ev.clientY - y0;
      // Un umbral de 3 px separa «arrastrar» de «hacer clic»: sin él, el
      // temblor del dedo convierte cada toque en un arrastre y el clic en el
      // fondo no llega nunca.
      if (Math.abs(dx) > UMBRAL_ARRASTRE || Math.abs(dy) > UMBRAL_ARRASTRE) { movido = true; }
      self.tx = tx0 + dx; self.ty = ty0 + dy;
      self.resize();
    });

    var soltar = function (ev) {
      if (!arrastrando) { return; }
      arrastrando = false;
      self.stage.classList.remove('is-panning');
      try { self.stage.releasePointerCapture(ev.pointerId); } catch (e) {}
      /*
       * Un gesto que no llegó al umbral es un CLIC, y hay que repartirlo:
       *
       *  - empezó sobre un elemento  → se le abre su ficha;
       *  - empezó en el plano pelado → clic en el fondo (en el editor, colocar).
       *
       * Lo reparte aquí y no cada elemento por su cuenta porque, al capturar el
       * puntero el armazón, el `click` del navegador se dispara sobre ÉL y no
       * sobre el pin ni la forma: sus propios manejadores no llegan a correr.
       */
      var r = self.stage.getBoundingClientRect();
      var pos = self.toPercent(ev.clientX - r.left, ev.clientY - r.top);

      if (!movido) {
        var punto = origen ? self._pointOfNode(origen) : null;
        if (punto && self.opts.onPick) {
          // El sitio del clic solo para las formas: una puede ocupar medio
          // plano y su ficha se ancla donde cayó el dedo. En un pin manda su
          // propio punto, que es lo que ya hacía.
          self.opts.onPick(punto, origen, punto.geom && punto.geom.length ? pos : null);
        } else if (!origen && self.opts.onCanvasClick) {
          self.opts.onCanvasClick(pos);
        }
      }
      origen = null;
    };
    this.stage.addEventListener('pointerup', soltar);
    this.stage.addEventListener('pointercancel', soltar);
  };

  MapViewer.prototype._bindZoom = function () {
    var self = this;

    this.stage.addEventListener('wheel', function (ev) {
      // `passive:false` + `preventDefault` para que la rueda amplíe el plano y
      // no desplace la página entera por debajo.
      ev.preventDefault();
      var r = self.stage.getBoundingClientRect();
      // `deltaMode` 1 son líneas y 0 son píxeles: sin normalizar, un ratón de
      // rueda dentada da saltos enormes frente a un trackpad.
      var delta = ev.deltaMode === 1 ? ev.deltaY * 16 : ev.deltaY;
      self.zoomBy(Math.pow(0.999, delta), ev.clientX - r.left, ev.clientY - r.top);
    }, { passive: false });

    // Pellizco en el móvil: se sigue la distancia entre los dos dedos.
    var puntos = {};
    var dist0 = 0, escala0 = 1;
    this.stage.addEventListener('pointerdown', function (ev) { puntos[ev.pointerId] = ev; });
    this.stage.addEventListener('pointermove', function (ev) {
      if (!puntos[ev.pointerId]) { return; }
      puntos[ev.pointerId] = ev;
      var ids = Object.keys(puntos);
      if (ids.length !== 2) { return; }
      var a = puntos[ids[0]], b = puntos[ids[1]];
      var d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
      if (!dist0) { dist0 = d; escala0 = self.scale; return; }
      var r = self.stage.getBoundingClientRect();
      var cx = (a.clientX + b.clientX) / 2 - r.left;
      var cy = (a.clientY + b.clientY) / 2 - r.top;
      self.zoomBy((escala0 * (d / dist0)) / self.scale, cx, cy);
    });
    var fin = function (ev) { delete puntos[ev.pointerId]; dist0 = 0; };
    this.stage.addEventListener('pointerup', fin);
    this.stage.addEventListener('pointercancel', fin);
  };

  /** Arrastrar un pin (solo en el editor). */
  MapViewer.prototype._bindDrag = function (btn, point) {
    var self = this;
    var moviendo = false, movido = false, iniPx = null;

    btn.addEventListener('pointerdown', function (ev) {
      ev.stopPropagation();
      moviendo = true; movido = false;
      btn.__mvArrastrado = false;
      iniPx = { x: ev.clientX, y: ev.clientY };
      btn.setPointerCapture(ev.pointerId);
    });
    btn.addEventListener('pointermove', function (ev) {
      if (!moviendo) { return; }
      // El mismo umbral que las formas y que el plano. Antes bastaba un píxel
      // de temblor para dar el punto por movido y escribir en la base.
      if (!movido && Math.hypot(ev.clientX - iniPx.x, ev.clientY - iniPx.y) < UMBRAL_ARRASTRE) { return; }
      if (!movido) { movido = true; btn.classList.add('is-dragging'); }
      var r = self.stage.getBoundingClientRect();
      var p = self.toPercent(ev.clientX - r.left, ev.clientY - r.top);
      point.x = p.x; point.y = p.y;
      self._placePins();
    });
    btn.addEventListener('pointerup', function (ev) {
      if (!moviendo) { return; }
      moviendo = false;
      btn.classList.remove('is-dragging');
      try { btn.releasePointerCapture(ev.pointerId); } catch (e) {}
      // Solo se guarda si de verdad se movió: un clic para abrir la ficha no
      // tiene por qué escribir en la base de datos.
      if (!movido) { return; }
      btn.__mvArrastrado = true;
      if (self.opts.onMove) { self.opts.onMove(point); }
    });
  };

  global.MapViewer = MapViewer;
  global.MapViewer.crear = crear;
  // El editor también dibuja en la capa de formas (el borrador del trazado),
  // así que necesita el constructor de nodos SVG.
  global.MapViewer.crearNS = crearNS;
})(window);
