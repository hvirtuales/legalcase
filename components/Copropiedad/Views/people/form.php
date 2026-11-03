<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

/*
 * Orden por decisión: quién es → cómo se le contacta → a qué hogar pertenece.
 * El PAPEL (propietario, residente…) no está aquí: se asigna desde la ficha del
 * inmueble, porque va con fechas y con un inmueble concreto.
 */
$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-person"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Copropiedad\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.people') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group">
                        <label for="doc_type" class="form-label"><?= lang('Cop.docType') ?></label>
                        <select id="doc_type" name="doc_type" class="form-select">
                            <?php foreach (PersonModel::DOC_TYPES as $t): ?>
                                <option value="<?= esc($t, 'attr') ?>" <?= $v('doc_type', 'CC') === $t ? 'selected' : '' ?>><?= esc($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="doc_number" class="form-label"><?= lang('Cop.docNumber') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="doc_number" name="doc_number" class="form-input" required maxlength="30"
                            value="<?= esc($v('doc_number'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="first_name" class="form-label"><?= lang('Cop.firstName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-input" required maxlength="60"
                            value="<?= esc($v('first_name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name" class="form-label"><?= lang('Cop.lastName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-input" required maxlength="60"
                            value="<?= esc($v('last_name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="birthdate" class="form-label"><?= lang('Cop.birthdate') ?></label>
                        <input type="date" id="birthdate" name="birthdate" class="form-input" value="<?= esc($v('birthdate'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="occupation" class="form-label"><?= lang('Cop.occupation') ?></label>
                        <input type="text" id="occupation" name="occupation" class="form-input" maxlength="80" value="<?= esc($v('occupation'), 'attr') ?>">
                    </div>
                    <div class="form-group md:col-span-2">
                        <label for="photo" class="form-label"><?= lang('Cop.photo') ?></label>
                        <input type="file" id="photo" name="photo" class="form-input" accept="image/*" data-preview>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.phone') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="phone" class="form-label"><?= lang('Cop.phone') ?></label>
                        <input type="text" id="phone" name="phone" class="form-input" maxlength="30" value="<?= esc($v('phone'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="whatsapp" class="form-label"><?= lang('Cop.whatsapp') ?></label>
                        <input type="text" id="whatsapp" name="whatsapp" class="form-input" maxlength="30" value="<?= esc($v('whatsapp'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label"><?= lang('Cop.email') ?></label>
                        <input type="email" id="email" name="email" class="form-input" maxlength="120" value="<?= esc($v('email'), 'attr') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.family') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.familiesIntro') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="family_id" class="form-label"><?= lang('Cop.family') ?></label>
                        <select id="family_id" name="family_id" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($families as $fid => $fname): ?>
                                <option value="<?= (int) $fid ?>" <?= (int) $v('family_id') === (int) $fid ? 'selected' : '' ?>><?= esc($fname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="family_role" class="form-label"><?= lang('Cop.familyRole') ?></label>
                        <select id="family_role" name="family_role" class="form-select">
                            <?php foreach (Meta::options(Meta::FAMILY_ROLES) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('family_role', 'otro') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
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
                <a href="<?= base_url('copropiedad/personas') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Cop.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
