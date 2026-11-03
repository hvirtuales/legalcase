/*
 * Formulario público: comportamiento en el navegador.
 *
 * Sin dependencias ni CDN, y con una regla de fondo: TODO lo que hace aquí lo
 * vuelve a hacer el servidor. Los pasos, la lógica condicional y las
 * comprobaciones son comodidad para quien responde; la decisión la toma
 * `Validator` con el esquema publicado. Si este archivo no carga, el formulario
 * se ve entero de una vez y se envía igual.
 *
 * Los operadores de la lógica son los mismos de `LogicEngine`: si divergen,
 * manda el servidor y quien responda vería aparecer un error en un campo que
 * creía oculto. Cualquier cambio va en los dos sitios.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-ff-form]');
  if (!form) { return; }

  var pages = Array.prototype.slice.call(form.querySelectorAll('[data-ff-page]'));
  var wizard = form.getAttribute('data-ff-wizard') === '1' && pages.length > 1;
  var step = 0;
  var started = Date.now();

  /* ---------------------------------------------------------------- valores */

  /** Lo respondido hasta ahora, con la misma forma que espera el servidor. */
  function values() {
    var out = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      var m = /^f\[([^\]]+)\](\[\])?$/.exec(el.name || '');
      if (!m) { return; }
      var id = m[1];
      if (el.type === 'checkbox' && m[2]) {
        if (!out[id]) { out[id] = []; }
        if (el.checked) { out[id].push(el.value); }
      } else if (el.type === 'checkbox') {
        if (el.checked) { out[id] = el.value; }
        else if (out[id] === undefined) { out[id] = '0'; }
      } else if (el.type === 'radio') {
        if (el.checked) { out[id] = el.value; }
      } else {
        out[id] = el.value;
      }
    });
    return out;
  }

  function truthy(v) {
    if (Array.isArray(v)) { return v.length > 0; }
    return ['1', 'true', 'on', 'si', 'sí', 'yes'].indexOf(String(v == null ? '' : v).trim().toLowerCase()) !== -1;
  }

  /** Los nueve operadores, en el mismo orden que en LogicEngine.php. */
  function evaluate(rule, vals) {
    var target = vals[rule.fieldId];
    var expected = String(rule.value == null ? '' : rule.value).trim().toLowerCase();

    if (Array.isArray(target)) {
      var has = target.map(String).indexOf(String(rule.value)) !== -1;
      switch (rule.operator) {
        case 'equals': case 'contains': case 'is_checked': return has;
        case 'not_equals': case 'is_not_checked': return !has;
        case 'is_empty': return target.length === 0;
        case 'is_not_empty': return target.length > 0;
        default: return true;
      }
    }

    var t = String(target == null ? '' : target).trim().toLowerCase();
    switch (rule.operator) {
      case 'equals': return t === expected;
      case 'not_equals': return t !== expected;
      case 'contains': return expected !== '' && t.indexOf(expected) !== -1;
      case 'is_checked': return truthy(target);
      case 'is_not_checked': return !truthy(target);
      case 'greater_than': return t !== '' && !isNaN(+t) && !isNaN(+expected) && +t > +expected;
      case 'less_than': return t !== '' && !isNaN(+t) && !isNaN(+expected) && +t < +expected;
      case 'is_empty': return t === '';
      case 'is_not_empty': return t !== '';
      default: return true;
    }
  }

  /** Aplica la visibilidad de arriba abajo, como la resuelve el servidor. */
  function applyLogic() {
    var vals = values();
    form.querySelectorAll('[data-ff-rule]').forEach(function (item) {
      var rule;
      try { rule = JSON.parse(item.getAttribute('data-ff-rule')); } catch (e) { return; }
      if (!rule || !rule.enabled) { return; }
      item.hidden = !evaluate(rule, vals);
    });
  }

  form.addEventListener('input', applyLogic);
  form.addEventListener('change', applyLogic);

  /* ------------------------------------------------------------------ pasos */

  function showStep(n) {
    step = Math.max(0, Math.min(pages.length - 1, n));
    pages.forEach(function (p, i) { p.hidden = i !== step; });

    var prev = form.querySelector('[data-ff-prev]');
    var next = form.querySelector('[data-ff-next]');
    var send = form.querySelector('[data-ff-submit]');
    var last = step === pages.length - 1;

    if (prev) { prev.hidden = step === 0; }
    if (next) { next.hidden = last; }
    if (send) { send.hidden = !last; }

    var bar = form.querySelector('[data-ff-bar]');
    if (bar) { bar.style.width = Math.round(((step + 1) / pages.length) * 100) + '%'; }

    form.querySelectorAll('[data-ff-pill]').forEach(function (pill, i) {
      pill.classList.toggle('is-now', i === step);
      pill.classList.toggle('is-past', i < step);
    });

    // Al cambiar de paso se sube: en pantalla completa el contenido se
    // desplaza dentro de su columna, no en la ventana.
    var body = form.querySelector('.ff-body');
    if (body) { body.scrollTop = 0; }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /**
   * Comprobación de cortesía antes de pasar de paso.
   *
   * Se mira `data-ff-required` y no el atributo `required` nativo: con las
   * páginas ocultas por `hidden`, el navegador se niega a enviar quejándose de
   * un campo que no puede enfocar, y el mensaje no lo entiende nadie.
   */
  function stepIsValid() {
    var page = pages[step];
    var bad = null;

    page.querySelectorAll('[data-ff-item]').forEach(function (item) {
      if (item.hidden || bad) { return; }
      var ctrl = item.querySelector('[data-ff-required]');
      if (!ctrl) { return; }

      var name = ctrl.name;
      var group = page.querySelectorAll('[name="' + CSS.escape(name) + '"]');
      var filled = false;

      group.forEach(function (el) {
        if (el.type === 'checkbox' || el.type === 'radio') {
          if (el.checked && el.value !== '0') { filled = true; }
        } else if (String(el.value).trim() !== '') {
          filled = true;
        }
      });

      if (!filled) { bad = item; }
    });

    if (bad) {
      bad.setAttribute('data-ff-invalid', '1');
      bad.scrollIntoView({ block: 'center', behavior: 'smooth' });
      var focusable = bad.querySelector('input,select,textarea,button');
      if (focusable) { focusable.focus({ preventScroll: true }); }
      return false;
    }
    return true;
  }

  if (wizard) {
    var nextBtn = form.querySelector('[data-ff-next]');
    var prevBtn = form.querySelector('[data-ff-prev]');
    if (nextBtn) { nextBtn.addEventListener('click', function () { if (stepIsValid()) { showStep(step + 1); } }); }
    if (prevBtn) { prevBtn.addEventListener('click', function () { showStep(step - 1); }); }
    showStep(0);
  }

  /* ------------------------------------------------- controles compuestos */

  // Calificación: los botones escriben en el campo oculto, que es lo que viaja.
  form.querySelectorAll('[data-ff-rating]').forEach(function (box) {
    var input = box.querySelector('input[type=hidden]');
    var text = box.querySelector('.ff-rating-text');
    var stars = Array.prototype.slice.call(box.querySelectorAll('.ff-star'));

    stars.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var v = +btn.getAttribute('data-value');
        input.value = v;
        stars.forEach(function (s, i) { s.classList.toggle('is-on', i < v); });
        if (text) { text.textContent = v + ' / ' + stars.length; }
        applyLogic();
      });
    });
  });

  form.querySelectorAll('[data-ff-nps]').forEach(function (box) {
    var input = box.querySelector('input[type=hidden]');
    box.querySelectorAll('.ff-nps-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        input.value = btn.getAttribute('data-value');
        box.querySelectorAll('.ff-nps-btn').forEach(function (b) { b.classList.remove('is-on'); });
        btn.classList.add('is-on');
        applyLogic();
      });
    });
  });

  form.querySelectorAll('[data-ff-slider]').forEach(function (box) {
    var range = box.querySelector('input[type=range]');
    var out = box.querySelector('.ff-slider-out');
    if (range && out) {
      range.addEventListener('input', function () { out.textContent = range.value; });
    }
  });

  // Marcar visualmente la opción elegida (el CSS pinta `.is-on`).
  form.querySelectorAll('.ff-choice input').forEach(function (input) {
    input.addEventListener('change', function () {
      if (input.type === 'radio') {
        form.querySelectorAll('[name="' + CSS.escape(input.name) + '"]').forEach(function (el) {
          el.closest('.ff-choice').classList.toggle('is-on', el.checked);
        });
      } else {
        input.closest('.ff-choice').classList.toggle('is-on', input.checked);
      }
    });
  });

  // Archivo: enseñar el nombre elegido, que si no parece que no pasó nada.
  form.querySelectorAll('.ff-file input[type=file]').forEach(function (input) {
    input.addEventListener('change', function () {
      var box = input.closest('.ff-file');
      var text = box.querySelector('.ff-file-text');
      if (input.files && input.files[0]) {
        box.classList.add('is-picked');
        text.textContent = input.files[0].name;
      }
    });
  });

  /* ------------------------------------------------------------------ firma */

  form.querySelectorAll('[data-ff-sign]').forEach(function (box) {
    var canvas = box.querySelector('canvas');
    var hidden = box.querySelector('input[type=hidden]');
    var clear = box.querySelector('.ff-sign-clear');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var dirty = false;

    // El lienzo se dibuja a la resolución de la pantalla: sin esto la firma se
    // ve pixelada en un móvil, que es justo donde se firma.
    function resize() {
      var rect = canvas.getBoundingClientRect();
      var ratio = window.devicePixelRatio || 1;
      var data = dirty ? canvas.toDataURL('image/png') : null;

      canvas.width = Math.round(rect.width * ratio);
      canvas.height = Math.round(rect.height * ratio);
      ctx.scale(ratio, ratio);
      ctx.strokeStyle = '#111827';
      ctx.lineWidth = 2.2;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';

      if (data) {
        var img = new Image();
        img.onload = function () { ctx.drawImage(img, 0, 0, rect.width, rect.height); };
        img.src = data;
      }
    }
    resize();
    window.addEventListener('resize', resize);

    function pos(e) {
      var rect = canvas.getBoundingClientRect();
      var p = e.touches ? e.touches[0] : e;
      return { x: p.clientX - rect.left, y: p.clientY - rect.top };
    }

    function start(e) {
      e.preventDefault();
      drawing = true;
      var p = pos(e);
      ctx.beginPath();
      ctx.moveTo(p.x, p.y);
    }

    function move(e) {
      if (!drawing) { return; }
      e.preventDefault();
      var p = pos(e);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      dirty = true;
    }

    function stop() {
      if (!drawing) { return; }
      drawing = false;
      // El PNG viaja en el campo oculto; el servidor comprueba que de verdad
      // sea un PNG antes de escribir nada.
      hidden.value = dirty ? canvas.toDataURL('image/png') : '';
      applyLogic();
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', stop);
    canvas.addEventListener('mouseleave', stop);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', stop);

    if (clear) {
      clear.addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        dirty = false;
        hidden.value = '';
      });
    }
  });

  /* ------------------------------------------------- pantalla completa real */

  // La presentación «pantalla completa» ya ocupa la ventana; este botón pide
  // además la pantalla completa DEL NAVEGADOR, que es lo que quita la barra de
  // direcciones en una tableta de recepción o un tótem.
  var fsBtn = document.querySelector('[data-ff-fullscreen]');
  if (fsBtn && document.documentElement.requestFullscreen) {
    fsBtn.addEventListener('click', function () {
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else {
        document.documentElement.requestFullscreen().catch(function () { /* el navegador puede negarse */ });
      }
    });
    document.addEventListener('fullscreenchange', function () {
      fsBtn.classList.toggle('is-on', !!document.fullscreenElement);
    });
  } else if (fsBtn) {
    // Sin la API (iOS en iPhone no la tiene), el botón sobra: esconderlo es más
    // honesto que dejar uno que no hace nada.
    fsBtn.hidden = true;
  }

  /* ------------------------------------------------------------------ envío */

  form.addEventListener('submit', function (e) {
    // Cuánto tardó en llenarlo: alimenta el tiempo medio del informe.
    var elapsed = form.querySelector('[data-ff-elapsed]');
    if (elapsed) { elapsed.value = Math.round((Date.now() - started) / 1000); }

    if (wizard && step !== pages.length - 1) {
      e.preventDefault();
      if (stepIsValid()) { showStep(step + 1); }
      return;
    }

    // En el último paso se revisan TODAS las páginas: un campo obligatorio de
    // una anterior podría haberse vaciado, o haberse vuelto visible por la
    // lógica después de pasar por ella.
    for (var i = 0; i < pages.length; i++) {
      var keep = step;
      step = i;
      var ok = stepIsValid();
      step = keep;
      if (!ok) {
        e.preventDefault();
        if (wizard) { showStep(i); stepIsValid(); }
        return;
      }
    }

    var send = form.querySelector('[data-ff-submit]');
    if (send) { send.disabled = true; }
  });

  applyLogic();
})();
