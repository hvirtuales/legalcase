<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;

$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-star"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Copropiedad\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.pets') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group">
                        <label for="name" class="form-label"><?= lang('Cop.petName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="60" value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="species" class="form-label"><?= lang('Cop.petSpecies') ?></label>
                        <select id="species" name="species" class="form-select">
                            <?php foreach (Meta::options(Meta::SPECIES) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('species', 'perro') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="breed" class="form-label"><?= lang('Cop.petBreed') ?></label>
                        <input type="text" id="breed" name="breed" class="form-input" maxlength="60" value="<?= esc($v('breed'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="color" class="form-label"><?= lang('Cop.petColor') ?></label>
                        <input type="text" id="color" name="color" class="form-input" maxlength="40" value="<?= esc($v('color'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="size" class="form-label"><?= lang('Cop.petSize') ?></label>
                        <select id="size" name="size" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach (Meta::options(Meta::SIZES) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('size') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="birthdate" class="form-label"><?= lang('Cop.birthdate') ?></label>
                        <input type="date" id="birthdate" name="birthdate" class="form-input" value="<?= esc($v('birthdate'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="chip" class="form-label"><?= lang('Cop.petChip') ?></label>
                        <input type="text" id="chip" name="chip" class="form-input" maxlength="40" value="<?= esc($v('chip'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="photo" class="form-label"><?= lang('Cop.photo') ?></label>
                        <input type="file" id="photo" name="photo" class="form-input" accept="image/*" data-preview>
                    </div>
                </div>

                <div class="form-check mb-1">
                    <input type="checkbox" id="is_dangerous" name="is_dangerous" value="1" class="form-checkbox" <?= $v('is_dangerous') ? 'checked' : '' ?>>
                    <label for="is_dangerous"><?= lang('Cop.petDangerous') ?></label>
                </div>
                <small class="form-hint"><?= lang('Cop.petDangerHint') ?></small>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.petVaccine') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="vaccine_date" class="form-label"><?= lang('Cop.petVaccine') ?></label>
                        <input type="date" id="vaccine_date" name="vaccine_date" class="form-input" value="<?= esc($v('vaccine_date'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="vaccine_expires" class="form-label"><?= lang('Cop.petVaccineExp') ?></label>
                        <input type="date" id="vaccine_expires" name="vaccine_expires" class="form-input" value="<?= esc($v('vaccine_expires'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Cop.docExpiresHint') ?></small>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.units') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
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
                        <label for="person_id" class="form-label"><?= lang('Cop.petOwner') ?></label>
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
                <a href="<?= base_url('copropiedad/mascotas') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Cop.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
