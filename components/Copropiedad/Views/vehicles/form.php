<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;

$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-key"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Copropiedad\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.vehicles') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group">
                        <label for="kind" class="form-label"><?= lang('Cop.vehKind') ?></label>
                        <select id="kind" name="kind" class="form-select">
                            <?php foreach (Meta::options(Meta::VEHICLE_KINDS) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('kind', 'carro') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="plate" class="form-label"><?= lang('Cop.vehPlate') ?></label>
                        <input type="text" id="plate" name="plate" class="form-input" maxlength="15" value="<?= esc($v('plate'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Cop.vehPlateHint') ?></small>
                    </div>
                    <div class="form-group">
                        <label for="brand" class="form-label"><?= lang('Cop.vehBrand') ?></label>
                        <input type="text" id="brand" name="brand" class="form-input" maxlength="40" value="<?= esc($v('brand'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="model" class="form-label"><?= lang('Cop.vehModel') ?></label>
                        <input type="text" id="model" name="model" class="form-input" maxlength="40" value="<?= esc($v('model'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="color" class="form-label"><?= lang('Cop.vehColor') ?></label>
                        <input type="text" id="color" name="color" class="form-input" maxlength="30" value="<?= esc($v('color'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="year" class="form-label"><?= lang('Cop.vehYear') ?></label>
                        <input type="number" min="1900" max="2100" id="year" name="year" class="form-input" value="<?= esc($v('year'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="sticker" class="form-label"><?= lang('Cop.vehSticker') ?></label>
                        <input type="text" id="sticker" name="sticker" class="form-input" maxlength="30" value="<?= esc($v('sticker'), 'attr') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.units') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.vehUnitHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="unit_id" class="form-label"><?= lang('Cop.units') ?> <span class="text-danger">*</span></label>
                        <select id="unit_id" name="unit_id" class="form-select" required>
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($units as $uid => $ulabel): ?>
                                <option value="<?= (int) $uid ?>" <?= (int) $v('unit_id') === (int) $uid ? 'selected' : '' ?>><?= esc($ulabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="parking_unit_id" class="form-label"><?= lang('Cop.vehParking') ?></label>
                        <select id="parking_unit_id" name="parking_unit_id" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($parkings as $pid => $plabel): ?>
                                <option value="<?= (int) $pid ?>" <?= (int) $v('parking_unit_id') === (int) $pid ? 'selected' : '' ?>><?= esc($plabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="person_id" class="form-label"><?= lang('Cop.vehOwner') ?></label>
                        <select id="person_id" name="person_id" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($people as $pid => $plabel): ?>
                                <option value="<?= (int) $pid ?>" <?= (int) $v('person_id') === (int) $pid ? 'selected' : '' ?>><?= esc($plabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input type="checkbox" id="is_active" name="is_active" value="1" class="form-checkbox"
                        <?= $row === [] || $v('is_active', 1) ? 'checked' : '' ?>>
                    <label for="is_active"><?= lang('Cop.active') ?></label>
                </div>

                <div class="form-group mb-2">
                    <label for="notes" class="form-label"><?= lang('Cop.notes') ?></label>
                    <textarea id="notes" name="notes" class="form-textarea" rows="2"><?= esc($v('notes')) ?></textarea>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Cop.save') ?></button>
                <a href="<?= base_url('copropiedad/vehiculos') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Cop.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
