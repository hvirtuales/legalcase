<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\FormEngine;
use Components\LeadFlow\Libraries\Meta;

/*
 * Constructor de formularios. Todo va en UN formulario y UN botón de guardar: los
 * ajustes y el árbol de campos viajan juntos (el árbol, serializado en el campo
 * oculto `schema`), así que no hay dos guardados que se puedan desincronizar ni un
 * token CSRF que rote entre ellos.
 *
 * El JavaScript arma los campos con `textContent`/`value`, nunca con innerHTML: lo
 * que escribe el usuario no puede convertirse en marcado. Y lo que manda el
 * navegador no se cree: `FormEngine::sanitize()` lo reconstruye en el servidor.
 */
$v = static fn (string $k, $d = '') => old($k, $form[$k] ?? $d);

$i18n = [
    'types'     => array_map(static fn ($k) => lang('Lfl.' . $k), FormEngine::TYPE_LABELS),
    'operators' => array_map(static fn ($k) => lang('Lfl.' . $k), FormEngine::OPERATOR_LABELS),
    'label'     => lang('Lfl.fbLabel'),
    'help'      => lang('Lfl.fbHelp'),
    'required'  => lang('Lfl.fbRequired'),
    'options'   => lang('Lfl.fbOptions'),
    'optionsPh' => lang('Lfl.fbOptionsPh'),
    'rule'      => lang('Lfl.fbRule'),
    'ruleNone'  => lang('Lfl.fbRuleNone'),
    'ruleValue' => lang('Lfl.fbRuleValue'),
    'up'        => lang('Lfl.fbUp'),
    'down'      => lang('Lfl.fbDown'),
    'remove'    => lang('Lfl.fbRemove'),
    'confirm'   => lang('Lfl.fbRemoveConfirm'),
    'untitled'  => lang('Lfl.fbUntitled'),
    'empty'     => lang('Lfl.fbEmpty'),
    'yesNoHint' => lang('Lfl.fbYesNoHint'),
];
$live = ($form['status'] ?? '') === 'published' && (int) ($form['is_public'] ?? 0) === 1;
?>
<form action="<?= base_url('leadflow/formularios/editar/' . (int) $form['id']) ?>" method="post" data-builder-form>
    <?= csrf_field() ?>
    <input type="hidden" name="schema" value="" data-schema>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h4 class="text-xl font-semibold"><?= esc($form['name']) ?></h4>
            <p class="text-sm text-muted"><?= lang('Lfl.fbSubtitle') ?></p>
        </div>
        <div class="flex gap-2">
            <a href="<?= base_url('leadflow/formularios') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Lfl.back') ?></a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
        </div>
    </div>

    <?= view('Components\LeadFlow\Views\partials\_errors') ?>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4">
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.formIdentity') ?></legend>
                <div class="form-group">
                    <label for="name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" maxlength="150" class="form-input" required value="<?= esc($v('name'), 'attr') ?>">
                </div>
                <div class="form-group">
                    <label for="description" class="form-label"><?= lang('Lfl.description') ?></label>
                    <textarea id="description" name="description" rows="2" class="form-textarea"><?= esc($v('description')) ?></textarea>
                    <p class="form-hint"><?= lang('Lfl.formDescHint') ?></p>
                </div>
                <?php foreach ([['service_id', 'service', $services], ['campaign_id', 'campaign', $campaigns]] as [$field, $label, $opts]): ?>
                    <div class="form-group">
                        <label for="<?= esc($field, 'attr') ?>" class="form-label"><?= lang('Lfl.' . $label) ?></label>
                        <select id="<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-select">
                            <option value=""><?= lang('Lfl.none') ?></option>
                            <?php foreach ($opts as $id => $name): ?>
                                <option value="<?= (int) $id ?>" <?= (int) $v($field) === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <p class="form-hint"><?= lang('Lfl.formAssocHint') ?></p>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.formPublishing') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.formPublishingHint') ?></p>
                <div class="form-group">
                    <label for="status" class="form-label"><?= lang('Lfl.status') ?></label>
                    <select id="status" name="status" class="form-select">
                        <?php foreach (Meta::options(Meta::FORM_STATUSES) as $val => $lbl): ?>
                            <option value="<?= esc($val, 'attr') ?>" <?= $v('status') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="form-check mb-1">
                    <input type="checkbox" name="is_public" value="1" class="form-check-input" <?= (int) $v('is_public') === 1 ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Lfl.formIsPublic') ?></span>
                </label>
                <p class="form-hint mb-2"><?= lang('Lfl.formIsPublicHint') ?></p>
                <label class="form-check mb-1">
                    <input type="checkbox" name="ask_consultation" value="1" class="form-check-input" <?= (int) $v('ask_consultation', 1) === 1 ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Lfl.formAskConsult') ?></span>
                </label>
                <p class="form-hint mb-2"><?= lang('Lfl.formAskConsultHint') ?></p>
                <div class="form-group">
                    <label for="success_message" class="form-label"><?= lang('Lfl.formSuccessMsg') ?></label>
                    <textarea id="success_message" name="success_message" rows="2" class="form-textarea" placeholder="<?= esc(lang('Lfl.pubDoneDefault'), 'attr') ?>"><?= esc($v('success_message')) ?></textarea>
                </div>
            </fieldset>

            <div class="card">
                <div class="card-header"><i class="icon icon-share"></i><h5 class="font-semibold"><?= lang('Lfl.formShare') ?></h5></div>
                <div class="card-body">
                    <?php if (! $live): ?>
                        <p class="mb-2 text-xs text-warning"><?= lang('Lfl.formNotLive') ?></p>
                    <?php endif; ?>
                    <div class="input-group mb-2">
                        <input type="text" readonly class="form-input text-xs" value="<?= esc($publicUrl, 'attr') ?>" data-share-url>
                        <button type="button" class="btn btn-secondary btn-sm" data-copy title="<?= esc(lang('Lfl.copy'), 'attr') ?>"><i class="icon icon-clipboard"></i></button>
                    </div>
                    <p class="form-hint mb-2"><?= lang('Lfl.formUtmHint') ?></p>
                    <?php if ($qr): ?>
                        <img src="<?= esc($qr, 'attr') ?>" alt="QR" class="mx-auto h-40 w-40">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header justify-between">
                    <div class="flex items-center gap-2"><i class="icon icon-list"></i><h5 class="font-semibold"><?= lang('Lfl.fields') ?></h5></div>
                    <div class="flex items-center gap-2">
                        <select class="form-select" data-new-type aria-label="<?= esc(lang('Lfl.type'), 'attr') ?>">
                            <?php foreach (FormEngine::TYPE_LABELS as $type => $key): ?>
                                <option value="<?= esc($type, 'attr') ?>"><?= lang('Lfl.' . $key) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm whitespace-nowrap" data-add><i class="icon icon-plus-circle"></i> <?= lang('Lfl.fbAdd') ?></button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-3 text-xs text-muted"><?= lang('Lfl.fbIntro') ?></p>
                    <div class="space-y-3" data-fields></div>
                </div>
            </div>
        </div>
    </div>
</form>

<script type="application/json" id="lflFields"><?= json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="lflI18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
(function () {
    var T = JSON.parse(document.getElementById('lflI18n').textContent);
    var fields = JSON.parse(document.getElementById('lflFields').textContent) || [];
    var box = document.querySelector('[data-fields]');
    var CHOICE = ['select', 'multiselect'];

    var uid = function () { return 'f_' + Math.random().toString(36).slice(2, 10); };
    var el = function (tag, attrs, text) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
        if (text !== undefined) { n.textContent = text; }
        return n;
    };
    var group = function (labelText, control) {
        var g = el('div', { 'class': 'form-group mb-2' });
        g.appendChild(el('label', { 'class': 'form-label text-xs' }, labelText));
        g.appendChild(control);
        return g;
    };

    var render = function () {
        box.textContent = '';
        if (!fields.length) { box.appendChild(el('p', { 'class': 'text-sm text-muted' }, T.empty)); return; }

        fields.forEach(function (f, i) {
            var card = el('div', { 'class': 'rounded-lg border border-line p-3' });
            var head = el('div', { 'class': 'mb-2 flex items-center justify-between gap-2' });
            head.appendChild(el('span', { 'class': 'badge badge-light' }, (i + 1) + '. ' + (T.types[f.type] || f.type)));
            var tools = el('div', { 'class': 'flex gap-1' });
            [['up', 'icon-caret-up-fill', -1], ['down', 'icon-caret-down-fill', 1]].forEach(function (b) {
                var btn = el('button', { type: 'button', 'class': 'btn btn-ghost btn-sm', title: T[b[0]] });
                btn.appendChild(el('i', { 'class': 'icon ' + b[1] }));
                btn.addEventListener('click', function () {
                    var j = i + b[2];
                    if (j < 0 || j >= fields.length) { return; }
                    var tmp = fields[i]; fields[i] = fields[j]; fields[j] = tmp;
                    // Una regla solo puede mirar un campo anterior: al reordenar, las
                    // que quedan apuntando hacia delante se retiran (el servidor haría lo mismo).
                    fields.forEach(function (x, k) {
                        if (x.rule && fields.slice(0, k).map(function (y) { return y.id; }).indexOf(x.rule.field) === -1) { delete x.rule; }
                    });
                    render();
                });
                tools.appendChild(btn);
            });
            var del = el('button', { type: 'button', 'class': 'btn btn-danger btn-sm', title: T.remove });
            del.appendChild(el('i', { 'class': 'icon icon-trash' }));
            del.addEventListener('click', function () {
                if (!window.confirm(T.confirm)) { return; }
                var id = fields[i].id;
                fields.splice(i, 1);
                fields.forEach(function (x) { if (x.rule && x.rule.field === id) { delete x.rule; } });
                render();
            });
            tools.appendChild(del);
            head.appendChild(tools);
            card.appendChild(head);

            var grid = el('div', { 'class': 'grid grid-cols-1 gap-x-3 md:grid-cols-2' });
            var label = el('input', { type: 'text', 'class': 'form-input', maxlength: '200' });
            label.value = f.label || '';
            label.addEventListener('input', function () { f.label = label.value; });
            grid.appendChild(group(T.label, label));
            var help = el('input', { type: 'text', 'class': 'form-input', maxlength: '300' });
            help.value = f.help || '';
            help.addEventListener('input', function () { f.help = help.value; });
            grid.appendChild(group(T.help, help));
            card.appendChild(grid);

            var req = el('label', { 'class': 'form-check mb-2' });
            var reqBox = el('input', { type: 'checkbox', 'class': 'form-check-input' });
            reqBox.checked = !!f.required;
            reqBox.addEventListener('change', function () { f.required = reqBox.checked; });
            req.appendChild(reqBox);
            req.appendChild(el('span', { 'class': 'form-check-label' }, T.required));
            card.appendChild(req);

            if (CHOICE.indexOf(f.type) !== -1) {
                var opts = el('textarea', { 'class': 'form-textarea', rows: '3', placeholder: T.optionsPh });
                opts.value = (f.options || []).join('\n');
                opts.addEventListener('input', function () { f.options = opts.value.split('\n'); });
                card.appendChild(group(T.options, opts));
            }

            // Lógica condicional: «mostrar este campo si…» sobre un campo ANTERIOR.
            var prev = fields.slice(0, i);
            if (prev.length) {
                var rule = el('div', { 'class': 'grid grid-cols-1 gap-x-3 md:grid-cols-3' });
                var target = el('select', { 'class': 'form-select' });
                target.appendChild(el('option', { value: '' }, T.ruleNone));
                prev.forEach(function (p) {
                    var o = el('option', { value: p.id }, p.label || T.untitled);
                    if (f.rule && f.rule.field === p.id) { o.selected = true; }
                    target.appendChild(o);
                });
                var op = el('select', { 'class': 'form-select' });
                Object.keys(T.operators).forEach(function (k) {
                    var o = el('option', { value: k }, T.operators[k]);
                    if (f.rule && f.rule.op === k) { o.selected = true; }
                    op.appendChild(o);
                });
                var val = el('input', { type: 'text', 'class': 'form-input', maxlength: '120', placeholder: T.yesNoHint });
                val.value = f.rule ? (f.rule.value || '') : '';
                var sync = function () {
                    if (!target.value) { delete f.rule; op.disabled = val.disabled = true; return; }
                    op.disabled = val.disabled = false;
                    f.rule = { field: target.value, op: op.value, value: val.value };
                };
                [target, op].forEach(function (x) { x.addEventListener('change', sync); });
                val.addEventListener('input', sync);
                rule.appendChild(group(T.rule, target));
                rule.appendChild(group(' ', op));
                rule.appendChild(group(T.ruleValue, val));
                card.appendChild(rule);
                op.disabled = val.disabled = !f.rule;
            }

            box.appendChild(card);
        });
    };

    document.querySelector('[data-add]').addEventListener('click', function () {
        var type = document.querySelector('[data-new-type]').value;
        var f = { id: uid(), type: type, label: T.types[type] || '', help: '', required: false };
        if (CHOICE.indexOf(type) !== -1) { f.options = []; }
        fields.push(f);
        render();
    });

    document.querySelector('[data-builder-form]').addEventListener('submit', function () {
        document.querySelector('[data-schema]').value = JSON.stringify(fields);
    });

    var copy = document.querySelector('[data-copy]');
    if (copy) {
        copy.addEventListener('click', function () {
            var input = document.querySelector('[data-share-url]');
            input.select();
            // navigator.clipboard no existe fuera de un origen seguro (la app se usa por IP en la red local).
            if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(input.value); } else { document.execCommand('copy'); }
        });
    }

    render();
})();
</script>
<?= $this->endSection() ?>
