<?php
/**
 * Editor de texto enriquecido mínimo (negrita, cursiva, subrayado, listas y
 * enlace) sobre `contenteditable`, como el de la app original pero sin CDN: el
 * JS va inline al final de la vista que lo usa (ver `_editor_js.php`).
 *
 * El contenido viaja en un <input hidden> que se sincroniza al enviar el
 * formulario; el HTML se sanea SIEMPRE en el servidor.
 *
 * @var string $name        nombre del campo
 * @var string $value       HTML actual
 * @var string $placeholder texto guía
 */
$name        = $name ?? 'html';
$value       = $value ?? '';
$placeholder = $placeholder ?? '';
$id          = 'ed_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
?>
<div class="quo-editor rounded-md border border-line bg-surface" data-editor>
    <div class="flex flex-wrap items-center gap-1 border-b border-line px-2 py-1">
        <button type="button" class="btn btn-ghost btn-sm" data-cmd="bold" title="<?= esc(lang('Quo.edBold'), 'attr') ?>"><strong>B</strong></button>
        <button type="button" class="btn btn-ghost btn-sm" data-cmd="italic" title="<?= esc(lang('Quo.edItalic'), 'attr') ?>"><em>I</em></button>
        <button type="button" class="btn btn-ghost btn-sm" data-cmd="underline" title="<?= esc(lang('Quo.edUnderline'), 'attr') ?>"><u>U</u></button>
        <span class="mx-1 h-4 w-px bg-line"></span>
        <button type="button" class="btn btn-ghost btn-sm" data-cmd="insertUnorderedList" title="<?= esc(lang('Quo.edBullets'), 'attr') ?>"><i class="icon icon-list"></i></button>
        <button type="button" class="btn btn-ghost btn-sm" data-cmd="insertOrderedList" title="<?= esc(lang('Quo.edNumbers'), 'attr') ?>">1.</button>
        <span class="mx-1 h-4 w-px bg-line"></span>
        <button type="button" class="btn btn-ghost btn-sm" data-cmd="createLink" title="<?= esc(lang('Quo.edLink'), 'attr') ?>"><i class="icon icon-link"></i></button>
    </div>
    <div id="<?= $id ?>" contenteditable="true" data-target="<?= esc($name, 'attr') ?>"
        class="min-h-24 px-3 py-2 text-sm text-ink focus:outline-none"
        data-placeholder="<?= esc($placeholder, 'attr') ?>"><?= $value ?></div>
    <input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
</div>
