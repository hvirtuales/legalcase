<?php
/**
 * La lógica condicional en el navegador: la misma que `FormEngine::isVisible()`.
 * Un campo oculto se deshabilita (no se exige ni se envía); el servidor, de todos
 * modos, descarta lo que llegue para él. Sin dependencias: lo usan tanto la vista
 * interna como la pública (que no carga nada del núcleo).
 */
?>
<script>
(function () {
    var root = document.querySelector('[data-dyn]');
    if (!root) { return; }
    var YES = ['yes', 'si', 'sí', 'true', '1', 'y', 's'], NO = ['no', 'false', '0', 'n'];

    var valueOf = function (id) {
        var wrap = root.querySelector('[data-field="' + id + '"]');
        if (!wrap || wrap.hidden) { return ''; }
        var boxes = wrap.querySelectorAll('input[type=checkbox]');
        if (boxes.length) { return Array.prototype.filter.call(boxes, function (b) { return b.checked; }).map(function (b) { return b.value; }); }
        var radio = wrap.querySelector('input[type=radio]:checked');
        if (wrap.querySelector('input[type=radio]')) { return radio ? radio.value : ''; }
        var input = wrap.querySelector('input, select, textarea');
        return input ? input.value : '';
    };

    var visible = function (rule) {
        var t = valueOf(rule.field), e = String(rule.value || '').trim().toLowerCase();
        if (Array.isArray(t)) {
            var has = t.map(function (x) { return String(x).toLowerCase(); }).indexOf(e) !== -1;
            return { equals: has, contains: has, not_equals: !has, is_empty: !t.length, is_not_empty: t.length > 0 }[rule.op];
        }
        t = String(t).trim().toLowerCase();
        if (t === 'yes' || t === 'no') { e = YES.indexOf(e) !== -1 ? 'yes' : (NO.indexOf(e) !== -1 ? 'no' : e); }
        return { equals: t === e, not_equals: t !== e, contains: e !== '' && t.indexOf(e) !== -1, is_empty: t === '', is_not_empty: t !== '' }[rule.op];
    };

    var apply = function () {
        // En orden: una regla solo mira campos anteriores, ya resueltos.
        root.querySelectorAll('[data-field]').forEach(function (wrap) {
            var raw = wrap.getAttribute('data-rule');
            if (!raw) { return; }
            var show = visible(JSON.parse(raw)) !== false;
            wrap.hidden = !show;
            wrap.querySelectorAll('input, select, textarea').forEach(function (i) { i.disabled = !show; });
        });
    };

    root.addEventListener('change', apply);
    root.addEventListener('input', apply);
    apply();
})();
</script>
