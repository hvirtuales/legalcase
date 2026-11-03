<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\StageModel;

/*
 * Los botones de borrar NO pueden ser un `<form>` dentro del formulario de la
 * tabla (anidar formularios es inválido y el navegador descarta el interior): son
 * botones con el atributo `form="…"` que apuntan a formularios declarados después.
 */
?>
<div class="card">
    <div class="card-header"><i class="icon icon-gear"></i><h5 class="font-semibold"><?= lang('Lfl.navSettings') ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_settings_tabs', ['active' => 'stages']) ?>
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.stagesIntro') ?></p>

        <form action="<?= base_url('leadflow/configuracion/etapas') ?>" method="post">
            <?= csrf_field() ?>
            <div class="table-card mb-4 overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.order') ?></th>
                            <th><?= lang('Lfl.name') ?></th>
                            <th><?= lang('Lfl.kind') ?></th>
                            <th><?= lang('Lfl.probability') ?></th>
                            <th><?= lang('Lfl.color') ?></th>
                            <th><?= lang('Lfl.active') ?></th>
                            <th class="text-right"><?= lang('Lfl.navLeads') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stages as $s): ?>
                            <?php
                            $id       = (int) $s['id'];
                            $isLocked = in_array($s['code'], $locked, true);
                            $n        = $counts[$id] ?? 0;
                            ?>
                            <tr>
                                <td><input type="number" name="sort[<?= (int) $id ?>]" value="<?= (int) $s['sort'] ?>" class="form-input w-20" aria-label="<?= esc(lang('Lfl.order'), 'attr') ?>"></td>
                                <td><input type="text" name="name[<?= (int) $id ?>]" value="<?= esc($s['name'], 'attr') ?>" maxlength="80" class="form-input" aria-label="<?= esc(lang('Lfl.name'), 'attr') ?>"></td>
                                <td class="text-xs"><?= esc(Meta::label(Meta::STAGE_KINDS, $s['kind'])) ?><?= $s['code'] ? '<div class="text-faint">' . esc(lang('Lfl.systemStage')) . '</div>' : '' ?></td>
                                <td><input type="number" min="0" max="100" name="probability[<?= (int) $id ?>]" value="<?= (int) $s['probability'] ?>" class="form-input w-20" aria-label="<?= esc(lang('Lfl.probability'), 'attr') ?>"></td>
                                <td>
                                    <select name="color[<?= (int) $id ?>]" class="form-select" aria-label="<?= esc(lang('Lfl.color'), 'attr') ?>">
                                        <?php foreach (StageModel::COLORS as $c): ?>
                                            <option value="<?= esc($c, 'attr') ?>" <?= $s['color'] === $c ? 'selected' : '' ?>><?= esc(lang('Lfl.color_' . $c)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="checkbox" name="active[<?= (int) $id ?>]" value="1" class="form-check-input" <?= $s['is_active'] ? 'checked' : '' ?> <?= $isLocked ? 'disabled checked' : '' ?>
                                        aria-label="<?= esc(lang('Lfl.active'), 'attr') ?>">
                                </td>
                                <td class="text-right"><?= (int) $n ?></td>
                                <td class="text-right">
                                    <?php if (! $s['code'] && $n === 0): ?>
                                        <button type="submit" form="del-stage-<?= (int) $id ?>" class="btn btn-danger btn-sm" title="<?= esc(lang('Lfl.delete'), 'attr') ?>"
                                            onclick="return confirm('<?= esc(lang('Lfl.confirmDelete'), 'js') ?>')"><i class="icon icon-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
        </form>

        <?php foreach ($stages as $s): ?>
            <?php if (! $s['code']): ?>
                <form id="del-stage-<?= (int) $s['id'] ?>" action="<?= base_url('leadflow/configuracion/etapas/eliminar/' . (int) $s['id']) ?>" method="post" hidden>
                    <?= csrf_field() ?>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>

        <form action="<?= base_url('leadflow/configuracion/etapas/nueva') ?>" method="post" class="mt-6">
            <?= csrf_field() ?>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.stageNew') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.stageNewHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group">
                        <label for="s-name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="s-name" name="name" maxlength="80" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="s-kind" class="form-label"><?= lang('Lfl.kind') ?></label>
                        <select id="s-kind" name="kind" class="form-select">
                            <option value="open"><?= lang('Lfl.kindOpen') ?></option>
                            <option value="lost"><?= lang('Lfl.kindLost') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="s-prob" class="form-label"><?= lang('Lfl.probability') ?></label>
                        <input type="number" id="s-prob" name="probability" min="0" max="100" value="50" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="s-color" class="form-label"><?= lang('Lfl.color') ?></label>
                        <select id="s-color" name="color" class="form-select">
                            <?php foreach (StageModel::COLORS as $c): ?>
                                <option value="<?= esc($c, 'attr') ?>"><?= esc(lang('Lfl.color_' . $c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Lfl.add') ?></button>
            </fieldset>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
