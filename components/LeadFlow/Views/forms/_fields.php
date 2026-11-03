<?php

use Components\LeadFlow\Libraries\FormEngine;
use Components\LeadFlow\Libraries\Meta;

/**
 * Los campos dinámicos de un formulario con el sistema de diseño del núcleo (uso
 * interno: llenar la precalificación desde la ficha). La lógica condicional se
 * aplica en el navegador para que el campo aparezca y desaparezca al contestar,
 * pero el servidor la vuelve a evaluar y descarta lo que llegue para un campo oculto.
 *
 * @var array $fields
 */
$old = (array) (old('q') ?? []);
?>
<div class="grid grid-cols-1 gap-x-4 md:grid-cols-2" data-dyn>
    <?php foreach ($fields as $f): ?>
        <?php
        $id   = $f['id'];
        $name = 'q[' . $id . ']';
        $val  = $old[$id] ?? '';
        $wide = in_array($f['type'], ['textarea', 'address', 'multiselect', 'file'], true);
        ?>
        <div class="form-group <?= $wide ? 'md:col-span-2' : '' ?>" data-field="<?= esc($id, 'attr') ?>"
            <?= isset($f['rule']) ? 'data-rule="' . esc(json_encode($f['rule'], JSON_UNESCAPED_UNICODE), 'attr') . '"' : '' ?>>
            <label class="form-label" for="<?= esc($id, 'attr') ?>"><?= esc($f['label']) ?><?= $f['required'] ? ' <span class="text-danger">*</span>' : '' ?></label>

            <?php if ($f['type'] === 'textarea' || $f['type'] === 'address'): ?>
                <textarea id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" rows="3" class="form-textarea" <?= $f['required'] ? 'required' : '' ?>><?= esc(is_string($val) ? $val : '') ?></textarea>
            <?php elseif ($f['type'] === 'select' || $f['type'] === 'country'): ?>
                <select id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" class="form-select" <?= $f['required'] ? 'required' : '' ?>>
                    <option value=""><?= lang('Lfl.choose') ?></option>
                    <?php foreach ($f['type'] === 'country' ? Meta::countries() : array_combine($f['options'], $f['options']) as $ov => $ol): ?>
                        <option value="<?= esc((string) $ov, 'attr') ?>" <?= (string) $val === (string) $ov ? 'selected' : '' ?>><?= esc($ol) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($f['type'] === 'multiselect'): ?>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    <?php foreach ($f['options'] as $opt): ?>
                        <label class="form-check">
                            <input type="checkbox" name="<?= esc($name, 'attr') ?>[]" value="<?= esc($opt, 'attr') ?>" class="form-check-input" <?= in_array($opt, (array) $val, true) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= esc($opt) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($f['type'] === 'yesno'): ?>
                <div class="flex gap-4">
                    <?php foreach (['yes' => 'Lfl.yes', 'no' => 'Lfl.no'] as $ov => $ol): ?>
                        <label class="form-check">
                            <input type="radio" name="<?= esc($name, 'attr') ?>" value="<?= esc($ov, 'attr') ?>" class="form-check-input" <?= $val === $ov ? 'checked' : '' ?> <?= $f['required'] ? 'required' : '' ?>>
                            <span class="form-check-label"><?= lang($ol) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($f['type'] === 'file'): ?>
                <input type="file" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" class="form-input" accept="application/pdf,image/*" <?= $f['required'] ? 'required' : '' ?>>
            <?php else: ?>
                <?php $type = ['email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date'][$f['type']] ?? 'text'; ?>
                <input type="<?= esc($type, 'attr') ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" class="form-input"
                    value="<?= esc(is_string($val) ? $val : '', 'attr') ?>" <?= $type === 'number' ? 'step="any"' : '' ?> <?= $f['required'] ? 'required' : '' ?>>
            <?php endif; ?>

            <?php if ($f['help'] !== ''): ?><p class="form-hint"><?= esc($f['help']) ?></p><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?= view('Components\LeadFlow\Views\forms\_logic_js') ?>
