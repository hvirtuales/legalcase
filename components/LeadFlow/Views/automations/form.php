<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\AutomationRegistry;

/*
 * Editor de una automatización: DISPARADOR → CONDICIONES → ACCIONES.
 *
 * Todo va en UN formulario con UN botón de guardar: las condiciones y las acciones
 * viajan serializadas en dos campos ocultos, igual que el `schema` del constructor
 * de formularios, así no hay dos envíos que se puedan desincronizar.
 *
 * El JavaScript arma los controles con `textContent`/`value`, nunca con innerHTML.
 * Y lo que manda el navegador no se cree: `AutomationRegistry` lo reconstruye en el
 * servidor, así que una etapa de otra firma o una acción inventada no se guardan.
 */
$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);

$i18n = [
    'fields'       => $registry['fields'],
    'fieldTypes'   => $registry['fieldTypes'],
    'operators'    => $registry['operators'],
    'actions'      => $registry['actions'],
    'params'       => $registry['params'],
    'options'      => $registry['options'],
    'paramLabels'  => $registry['paramLabels'],
    'noConditions' => lang('Lfl.autNoConditions'),
    'noActions'    => lang('Lfl.autNoActions'),
    'remove'       => lang('Lfl.autRemove'),
];
?>
<form action="<?= esc($action, 'attr') ?>" method="post" data-aut-form>
    <?= csrf_field() ?>
    <input type="hidden" name="conditions" value="" data-aut-conditions>
    <input type="hidden" name="actions" value="" data-aut-actions>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h4 class="text-xl font-semibold"><?= esc($title) ?></h4>
        <div class="flex gap-2">
            <a href="<?= base_url('leadflow/automatizaciones') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Lfl.back') ?></a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
        </div>
    </div>

    <?= view('Components\LeadFlow\Views\partials\_errors') ?>

    <fieldset class="form-section">
        <legend class="form-section-title"><?= lang('Lfl.autWhen') ?></legend>
        <p class="form-section-hint"><?= lang('Lfl.autTriggerHint') ?></p>
        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
            <div class="form-group">
                <label for="name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" maxlength="120" class="form-input" required value="<?= esc($v('name'), 'attr') ?>">
            </div>
            <div class="form-group">
                <label for="trigger_event" class="form-label"><?= lang('Lfl.autTrigger') ?></label>
                <select id="trigger_event" name="trigger_event" class="form-select">
                    <?php foreach (AutomationRegistry::TRIGGERS as $key => $langKey): ?>
                        <option value="<?= esc($key, 'attr') ?>" <?= $v('trigger_event', 'lead_created') === $key ? 'selected' : '' ?>><?= esc(lang('Lfl.' . $langKey)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sort" class="form-label"><?= lang('Lfl.order') ?></label>
                <input type="number" id="sort" name="sort" min="0" max="999" class="form-input" value="<?= (int) $v('sort', 10) ?>">
                <p class="form-hint"><?= lang('Lfl.autSortHint') ?></p>
            </div>
        </div>
        <label class="form-check mb-1">
            <input type="checkbox" name="once_per_lead" value="1" class="form-check-input" <?= $row === [] || $v('once_per_lead', 1) ? 'checked' : '' ?>>
            <span class="form-check-label"><?= lang('Lfl.autOncePerLead') ?></span>
        </label>
        <p class="form-hint mb-2"><?= lang('Lfl.autOncePerLeadHint') ?></p>
        <label class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" <?= $row === [] || $v('is_active', 1) ? 'checked' : '' ?>>
            <span class="form-check-label"><?= lang('Lfl.active') ?></span>
        </label>
    </fieldset>

    <fieldset class="form-section">
        <legend class="form-section-title"><?= lang('Lfl.autIf') ?></legend>
        <p class="form-section-hint"><?= lang('Lfl.autConditionsHint') ?></p>
        <div class="form-group">
            <label for="match_mode" class="form-label"><?= lang('Lfl.autMatchMode') ?></label>
            <select id="match_mode" name="match_mode" class="form-select md:w-1/2">
                <option value="all" <?= $v('match_mode', 'all') === 'all' ? 'selected' : '' ?>><?= lang('Lfl.autMatchAll') ?></option>
                <option value="any" <?= $v('match_mode', 'all') === 'any' ? 'selected' : '' ?>><?= lang('Lfl.autMatchAny') ?></option>
            </select>
        </div>
        <div class="space-y-2" data-aut-conds-box></div>
        <button type="button" class="btn btn-secondary btn-sm mt-2" data-aut-add-cond><i class="icon icon-plus-circle"></i> <?= lang('Lfl.autAddCondition') ?></button>
    </fieldset>

    <fieldset class="form-section">
        <legend class="form-section-title"><?= lang('Lfl.autThen') ?></legend>
        <p class="form-section-hint"><?= lang('Lfl.autActionsHint') ?></p>
        <div class="space-y-2" data-aut-acts-box></div>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <select class="form-select w-auto" data-aut-new-action aria-label="<?= esc(lang('Lfl.autActions'), 'attr') ?>">
                <?php foreach (AutomationRegistry::ACTIONS as $type => $def): ?>
                    <option value="<?= esc($type, 'attr') ?>"><?= esc(lang('Lfl.' . $def[0])) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" data-aut-add-act><i class="icon icon-plus-circle"></i> <?= lang('Lfl.autAddAction') ?></button>
        </div>
        <p class="form-hint mt-3">
            <strong><?= lang('Lfl.autPlaceholders') ?>:</strong>
            <?= esc(lang('Lfl.autPlaceholdersHint', ['{' . implode('}, {', AutomationRegistry::PLACEHOLDERS) . '}'])) ?>
        </p>
    </fieldset>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
        <a href="<?= base_url('leadflow/automatizaciones') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Lfl.cancel') ?></a>
    </div>
</form>

<script type="application/json" id="lflAutI18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="lflAutConds"><?= json_encode($conditions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="lflAutActs"><?= json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
(function () {
    var T     = JSON.parse(document.getElementById('lflAutI18n').textContent);
    var conds = JSON.parse(document.getElementById('lflAutConds').textContent) || [];
    var acts  = JSON.parse(document.getElementById('lflAutActs').textContent) || [];
    var condBox = document.querySelector('[data-aut-conds-box]');
    var actBox  = document.querySelector('[data-aut-acts-box]');
    // Estos dos operadores no comparan contra nada: su campo de valor se apaga.
    var NOVALUE = ['is_empty', 'is_not_empty'];

    var el = function (tag, attrs, text) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
        if (text !== undefined) { n.textContent = text; }
        return n;
    };
    var group = function (labelText, control) {
        var g = el('div', { 'class': 'form-group mb-0' });
        g.appendChild(el('label', { 'class': 'form-label text-xs' }, labelText));
        g.appendChild(control);
        return g;
    };
    var select = function (map, value, onChange) {
        var s = el('select', { 'class': 'form-select' });
        Object.keys(map || {}).forEach(function (k) {
            var o = el('option', { value: k }, map[k]);
            if (String(value) === String(k)) { o.selected = true; }
            s.appendChild(o);
        });
        s.addEventListener('change', function () { onChange(s.value); });
        return s;
    };
    var removeBtn = function (list, i, render) {
        var b = el('button', { type: 'button', 'class': 'btn btn-danger btn-sm', title: T.remove });
        b.appendChild(el('i', { 'class': 'icon icon-trash' }));
        b.addEventListener('click', function () { list.splice(i, 1); render(); });
        return b;
    };

    // Un control de valor acorde al tipo del campo: lista cerrada, número o texto.
    var valueControl = function (kind, value, onChange, placeholder) {
        if (T.options[kind]) {
            return select(T.options[kind], value, onChange);
        }
        var input = el('input', {
            type: kind === 'number' ? 'number' : 'text',
            'class': 'form-input',
            maxlength: '120'
        });
        if (placeholder) { input.setAttribute('placeholder', placeholder); }
        input.value = value === undefined || value === null ? '' : value;
        input.addEventListener('input', function () { onChange(input.value); });
        return input;
    };

    var renderConds = function () {
        condBox.textContent = '';
        if (!conds.length) {
            condBox.appendChild(el('p', { 'class': 'text-sm text-muted' }, T.noConditions));
            return;
        }
        conds.forEach(function (c, i) {
            var rowEl = el('div', { 'class': 'grid grid-cols-1 items-end gap-2 rounded-lg border border-line p-2 md:grid-cols-[1fr_1fr_1fr_auto]' });

            var field = select(T.fields, c.field, function (val) { c.field = val; c.value = ''; renderConds(); });
            rowEl.appendChild(group(T.paramLabels.field || '', field));

            var op = select(T.operators, c.op, function (val) { c.op = val; renderConds(); });
            rowEl.appendChild(group(' ', op));

            if (NOVALUE.indexOf(c.op) === -1) {
                var kind = T.fieldTypes[c.field] || 'text';
                rowEl.appendChild(group(' ', valueControl(kind, c.value, function (val) { c.value = val; })));
            } else {
                rowEl.appendChild(el('div'));
            }

            rowEl.appendChild(removeBtn(conds, i, renderConds));
            condBox.appendChild(rowEl);
        });
    };

    var renderActs = function () {
        actBox.textContent = '';
        if (!acts.length) {
            actBox.appendChild(el('p', { 'class': 'text-sm text-muted' }, T.noActions));
            return;
        }
        acts.forEach(function (a, i) {
            var card = el('div', { 'class': 'rounded-lg border border-line p-3' });
            var head = el('div', { 'class': 'mb-2 flex items-center justify-between gap-2' });
            head.appendChild(el('span', { 'class': 'badge badge-light' }, (i + 1) + '. ' + (T.actions[a.type] || a.type)));
            head.appendChild(removeBtn(acts, i, renderActs));
            card.appendChild(head);

            var params = T.params[a.type] || {};
            var keys   = Object.keys(params);
            if (keys.length) {
                var grid = el('div', { 'class': 'grid grid-cols-1 gap-x-3 md:grid-cols-2' });
                keys.forEach(function (p) {
                    var kind = params[p];
                    var ctrl;
                    if (kind === 'textarea') {
                        ctrl = el('textarea', { 'class': 'form-textarea', rows: '3' });
                        ctrl.value = a[p] === undefined || a[p] === null ? '' : a[p];
                        ctrl.addEventListener('input', function () { a[p] = ctrl.value; });
                    } else {
                        ctrl = valueControl(kind, a[p], function (val) { a[p] = val; });
                    }
                    // Un `select` no dispara `change` al pintarse: se fija su valor inicial.
                    if (ctrl.tagName === 'SELECT' && (a[p] === undefined || a[p] === null || a[p] === '')) {
                        a[p] = ctrl.value;
                    }
                    grid.appendChild(group(T.paramLabels[p] || p, ctrl));
                });
                card.appendChild(grid);
            }
            actBox.appendChild(card);
        });
    };

    document.querySelector('[data-aut-add-cond]').addEventListener('click', function () {
        var first = Object.keys(T.fields)[0];
        conds.push({ field: first, op: 'equals', value: '' });
        renderConds();
    });

    document.querySelector('[data-aut-add-act]').addEventListener('click', function () {
        acts.push({ type: document.querySelector('[data-aut-new-action]').value });
        renderActs();
    });

    document.querySelector('[data-aut-form]').addEventListener('submit', function () {
        document.querySelector('[data-aut-conditions]').value = JSON.stringify(conds);
        document.querySelector('[data-aut-actions]').value = JSON.stringify(acts);
    });

    renderConds();
    renderActs();
})();
</script>
<?= $this->endSection() ?>
