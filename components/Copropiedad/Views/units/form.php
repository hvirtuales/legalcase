<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\UnitModel;

/*
 * Orden por DECISIÓN, no por tabla (contrato §7.1.2):
 *   1. qué es y dónde está        → identificación
 *   2. cómo es                    → medidas
 *   3. en qué estado está         → ocupación
 *   4. lo demás                   → observaciones
 */
$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-buildings"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Copropiedad\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.unitCode') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.unitCodeHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group">
                        <label for="code" class="form-label"><?= lang('Cop.unitCode') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="code" name="code" class="form-input" required maxlength="30"
                            placeholder="501" value="<?= esc($v('code'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="kind" class="form-label"><?= lang('Cop.unitKind') ?> <span class="text-danger">*</span></label>
                        <select id="kind" name="kind" class="form-select">
                            <?php foreach (Meta::options(Meta::UNIT_KINDS) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('kind', 'apartamento') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="block_id" class="form-label"><?= lang('Cop.unitBlock') ?></label>
                        <select id="block_id" name="block_id" class="form-select">
                            <option value=""><?= lang('Cop.unitNoBlock') ?></option>
                            <?php foreach ($blocks as $bid => $bname): ?>
                                <option value="<?= (int) $bid ?>" <?= (int) $v('block_id') === (int) $bid ? 'selected' : '' ?>><?= esc($bname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="floor" class="form-label"><?= lang('Cop.unitFloor') ?></label>
                        <input type="text" id="floor" name="floor" class="form-input" maxlength="10" value="<?= esc($v('floor'), 'attr') ?>">
                    </div>
                </div>

                <?php // Solo tiene sentido para parqueaderos y depósitos, pero se deja
                      // visible siempre: esconderlo con JS obligaría a cargar un guion
                      // por un campo, y quien no lo necesita simplemente lo deja vacío. ?>
                <div class="form-group mb-2 md:w-1/2">
                    <label for="parent_unit_id" class="form-label"><?= lang('Cop.unitParent') ?></label>
                    <select id="parent_unit_id" name="parent_unit_id" class="form-select">
                        <option value=""><?= lang('Cop.none') ?></option>
                        <?php foreach ($parents as $pid => $plabel): ?>
                            <option value="<?= (int) $pid ?>" <?= (int) $v('parent_unit_id') === (int) $pid ? 'selected' : '' ?>><?= esc($plabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint"><?= lang('Cop.unitParentHint') ?></small>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.unitArea') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-5">
                    <div class="form-group">
                        <label for="area" class="form-label"><?= lang('Cop.unitArea') ?></label>
                        <input type="number" step="0.01" min="0" id="area" name="area" class="form-input" value="<?= esc($v('area'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="coefficient" class="form-label"><?= lang('Cop.unitCoeff') ?></label>
                        <input type="number" step="0.000001" min="0" max="100" id="coefficient" name="coefficient"
                            class="form-input" value="<?= esc($v('coefficient'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Cop.unitCoeffHint') ?></small>
                    </div>
                    <div class="form-group">
                        <label for="bedrooms" class="form-label"><?= lang('Cop.unitBedrooms') ?></label>
                        <input type="number" min="0" id="bedrooms" name="bedrooms" class="form-input" value="<?= esc($v('bedrooms'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="bathrooms" class="form-label"><?= lang('Cop.unitBathrooms') ?></label>
                        <input type="number" min="0" id="bathrooms" name="bathrooms" class="form-input" value="<?= esc($v('bathrooms'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="matricula" class="form-label"><?= lang('Cop.unitMatricula') ?></label>
                        <input type="text" id="matricula" name="matricula" class="form-input" maxlength="40" value="<?= esc($v('matricula'), 'attr') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.unitOccupancy') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.unitOccupHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="occupancy" class="form-label"><?= lang('Cop.unitOccupancy') ?></label>
                        <select id="occupancy" name="occupancy" class="form-select">
                            <?php foreach (Meta::options(Meta::OCCUPANCIES) as $val => $lbl): ?>
                                <?php // Los derivables se recalculan al guardar, así que
                                      // se marcan: elegir «desocupado» en un apartamento
                                      // con tres residentes no lo deja desocupado. ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('occupancy', 'desocupado') === $val ? 'selected' : '' ?>>
                                    <?= esc($lbl) ?><?= in_array($val, UnitModel::MANUAL_OCCUPANCIES, true) ? '' : ' *' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= lang('Cop.active') ?></label>
                        <div class="form-check">
                            <input type="checkbox" id="is_active" name="is_active" value="1" class="form-checkbox"
                                <?= $row === [] || $v('is_active', 1) ? 'checked' : '' ?>>
                            <label for="is_active"><?= lang('Cop.active') ?></label>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-2">
                    <label for="notes" class="form-label"><?= lang('Cop.notes') ?></label>
                    <textarea id="notes" name="notes" class="form-textarea" rows="2"><?= esc($v('notes')) ?></textarea>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Cop.save') ?></button>
                <a href="<?= base_url('copropiedad/inmuebles') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Cop.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
