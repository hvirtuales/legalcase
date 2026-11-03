<?php
/**
 * JS del cotizador: editor enriquecido, autocompletado de productos, filas de
 * ítems y resumen en vivo. Inline y sin dependencias externas (política del
 * núcleo: nada de CDN).
 *
 * Ojo: el resumen que se pinta aquí es solo orientativo — el servidor recalcula
 * todo al guardar (`QuoteCalculator`).
 *
 * @var array $items líneas existentes al editar
 */
?>
<script>
(function () {
    'use strict';

    // ---------------------------------------------------------------- editores
    document.querySelectorAll('[data-editor]').forEach(function (box) {
        var area   = box.querySelector('[contenteditable]');
        var hidden = box.querySelector('input[type=hidden]');
        if (!area || !hidden) { return; }

        box.querySelectorAll('[data-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cmd = btn.dataset.cmd;
                area.focus();
                if (cmd === 'createLink') {
                    var url = window.prompt(<?= json_encode(lang('Quo.edLinkAsk')) ?>, 'https://');
                    if (!url) { return; }
                    document.execCommand('createLink', false, url);
                } else {
                    document.execCommand(cmd, false, null);
                }
                hidden.value = area.innerHTML;
            });
        });

        area.addEventListener('input', function () { hidden.value = area.innerHTML; });
        // Pegar siempre como texto plano: evita traerse estilos y etiquetas raras.
        area.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });
    });

    // ---------------------------------------------------------------- ítems
    var body     = document.getElementById('itemsBody');
    var noItems  = document.getElementById('noItems');
    var currency = document.getElementById('currency');
    var retentionInput = document.getElementById('retention_percent');
    var index    = 0;

    function fmt(value) {
        var dec = currency && currency.value === 'COP' ? 0 : 2;
        return '$ ' + Number(value || 0).toLocaleString('es-CO', {
            minimumFractionDigits: dec, maximumFractionDigits: dec
        });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function addRow(p) {
        p = p || {};
        var i = index++;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="hidden" name="items[' + i + '][product_id]" value="' + esc(p.id || '') + '">' +
            '<input type="text" class="form-input" name="items[' + i + '][code]" value="' + esc(p.code || '') + '"></td>' +
            '<td><input type="text" class="form-input" name="items[' + i + '][name]" value="' + esc(p.name || '') + '" required>' +
            '<input type="text" class="form-input mt-1 text-xs" name="items[' + i + '][description]" value="' + esc(p.description || '') + '" placeholder="' + <?= json_encode(lang('Quo.colDescription')) ?> + '">' +
            '<input type="hidden" name="items[' + i + '][details_url]" value="' + esc(p.details_url || '') + '"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-input text-right" data-calc name="items[' + i + '][unit_price]" value="' + (p.unit_price || 0) + '"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-input text-right" data-calc name="items[' + i + '][quantity]" value="' + (p.quantity || 1) + '"></td>' +
            '<td><div class="flex gap-1">' +
            '<select class="form-select" data-calc name="items[' + i + '][discount_type]" style="max-width:4rem">' +
            '<option value="%"' + (p.discount_type === '$' ? '' : ' selected') + '>%</option>' +
            '<option value="$"' + (p.discount_type === '$' ? ' selected' : '') + '>$</option></select>' +
            '<input type="number" step="0.01" min="0" class="form-input text-right" data-calc name="items[' + i + '][discount_value]" value="' + (p.discount_value || 0) + '"></div></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-input text-right" data-calc name="items[' + i + '][tax_percent]" value="' + (p.tax_percent || 0) + '"></td>' +
            '<td class="text-right font-semibold" data-total>0</td>' +
            '<td><button type="button" class="btn btn-ghost btn-sm text-danger" data-remove title="' + <?= json_encode(lang('Quo.remove')) ?> + '"><i class="icon icon-trash"></i></button></td>';
        body.appendChild(tr);
        recalc();
    }

    // Misma fórmula que QuoteCalculator (el servidor manda, esto es la vista previa).
    function recalc() {
        var subtotal = 0, discount = 0, tax = 0;

        Array.prototype.forEach.call(body.rows, function (tr) {
            var q = function (n) { return tr.querySelector('[name$="[' + n + ']"]'); };
            var price = parseFloat((q('unit_price') || {}).value) || 0;
            var qty   = parseFloat((q('quantity') || {}).value) || 0;
            var dType = (q('discount_type') || {}).value || '%';
            var dVal  = parseFloat((q('discount_value') || {}).value) || 0;
            var taxP  = parseFloat((q('tax_percent') || {}).value) || 0;

            var lineSub = price * qty;
            var lineDis = dType === '$' ? Math.min(Math.max(dVal, 0), lineSub) : lineSub * (Math.max(dVal, 0) / 100);
            var lineBase = lineSub - lineDis;
            var lineTax  = lineBase * (taxP / 100);

            var cell = tr.querySelector('[data-total]');
            if (cell) { cell.textContent = fmt(lineBase + lineTax); }

            subtotal += lineSub; discount += lineDis; tax += lineTax;
        });

        var base      = subtotal - discount;
        var retention = base * ((parseFloat(retentionInput && retentionInput.value) || 0) / 100);

        document.getElementById('sumSubtotal').textContent  = fmt(subtotal);
        document.getElementById('sumDiscount').textContent  = fmt(discount);
        document.getElementById('sumBase').textContent      = fmt(base);
        document.getElementById('sumTax').textContent       = fmt(tax);
        document.getElementById('sumRetention').textContent = fmt(retention);
        document.getElementById('sumTotal').textContent     = fmt(base + tax - retention);

        noItems.style.display = body.rows.length ? 'none' : '';
    }

    body.addEventListener('input', function (e) { if (e.target.matches('[data-calc]')) { recalc(); } });
    body.addEventListener('change', function (e) { if (e.target.matches('[data-calc]')) { recalc(); } });
    body.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove]');
        if (btn) { btn.closest('tr').remove(); recalc(); }
    });
    if (currency) { currency.addEventListener('change', recalc); }
    if (retentionInput) { retentionInput.addEventListener('input', recalc); }
    document.getElementById('addLine').addEventListener('click', function () { addRow(); });

    // ------------------------------------------------- valor del cliente
    // Las condiciones que se cotizan salen del valor (1..3) que tenga asignado el
    // cliente; si el producto no define ese valor, se usan las suyas base.
    var clientSel = document.getElementById('client_id');
    var priceHint = document.getElementById('priceListHint');

    function activeTier() {
        var opt = clientSel && clientSel.selectedOptions[0];

        return opt ? parseInt(opt.dataset.tier || '0', 10) : 0;
    }

    // Del escalón se toma TODO: precio, cantidad, descuento e impuesto. Si el
    // producto no tiene ese valor, quedan sus condiciones base.
    function withTier(p) {
        var row = {};
        Object.keys(p).forEach(function (k) { row[k] = p[k]; });

        var tier = activeTier();
        var t    = tier && p.tiers ? p.tiers[tier] : null;
        if (t) {
            row.unit_price     = parseFloat(t.unit_price);
            row.quantity       = parseFloat(t.quantity) || row.quantity;
            row.discount_type  = t.discount_type;
            row.discount_value = parseFloat(t.discount_value) || 0;
            row.tax_percent    = parseFloat(t.tax_percent) || 0;
        }

        return row;
    }

    function showPriceHint() {
        if (!priceHint || !clientSel) { return; }
        var tier = activeTier();
        priceHint.textContent = tier ? <?= json_encode(lang('Quo.tierApplied')) ?> + ' ' + tier : '';
    }

    if (clientSel) { clientSel.addEventListener('change', showPriceHint); showPriceHint(); }

    // ---------------------------------------------------------------- buscador
    var search  = document.getElementById('productSearch');
    var results = document.getElementById('productResults');
    var timer;

    search.addEventListener('input', function () {
        clearTimeout(timer);
        var q = search.value.trim();
        if (q.length < 2) { results.innerHTML = ''; return; }

        timer = setTimeout(function () {
            fetch('<?= base_url('servicios/buscar/productos') ?>?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    if (!rows.length) { results.innerHTML = ''; return; }
                    var box = document.createElement('div');
                    box.className = 'absolute z-20 mt-1 w-full rounded-md border border-line bg-surface shadow-lg';
                    rows.forEach(function (p) {
                        var item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-body';
                        item.innerHTML = '<strong>' + esc(p.name) + '</strong> <span class="text-muted">' + esc(p.code) + '</span>';
                        item.addEventListener('click', function () {
                            addRow(withTier(p));
                            results.innerHTML = '';
                            search.value = '';
                        });
                        box.appendChild(item);
                    });
                    results.innerHTML = '';
                    results.appendChild(box);
                })
                .catch(function () { results.innerHTML = ''; });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!results.contains(e.target) && e.target !== search) { results.innerHTML = ''; }
    });

    // ---------------------------------------------------------------- líneas existentes
    var existing = <?= json_encode(array_map(static fn ($i) => [
        'id'             => $i['product_id'],
        'code'           => $i['code'],
        'name'           => $i['name'],
        'description'    => $i['description'],
        'details_url'    => $i['details_url'],
        'unit_price'     => (float) $i['unit_price'],
        'quantity'       => (float) $i['quantity'],
        'discount_type'  => $i['discount_type'],
        'discount_value' => (float) $i['discount_value'],
        'tax_percent'    => (float) $i['tax_percent'],
    ], $items ?? []), JSON_UNESCAPED_UNICODE) ?>;
    existing.forEach(addRow);
    recalc();

    // Sincroniza los editores justo antes de enviar.
    document.getElementById('quoteForm').addEventListener('submit', function () {
        document.querySelectorAll('[data-editor]').forEach(function (box) {
            var area = box.querySelector('[contenteditable]');
            var hidden = box.querySelector('input[type=hidden]');
            if (area && hidden) { hidden.value = area.innerHTML; }
        });
    });
})();
</script>
