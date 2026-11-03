<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;

$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-shield-check"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Copropiedad\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.auths') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.authsIntro') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="kind" class="form-label"><?= lang('Cop.authKind') ?></label>
                        <select id="kind" name="kind" class="form-select">
                            <?php foreach (Meta::options(Meta::AUTH_KINDS) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('kind', 'ingreso') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                        <label for="granted_by_id" class="form-label"><?= lang('Cop.authGrantedBy') ?></label>
                        <select id="granted_by_id" name="granted_by_id" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($people as $pid => $plabel): ?>
                                <option value="<?= (int) $pid ?>" <?= (int) $v('granted_by_id') === (int) $pid ? 'selected' : '' ?>><?= esc($plabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <?php /* Dos vías para el autorizado: del censo, o de fuera. Obligar a
                    censar a la niñera para poder autorizarla habría llenado el
                    registro de residentes que no lo son. */ ?>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.authWho') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.authWhoHint') ?></p>

                <div class="form-group md:w-1/2">
                    <label for="person_id" class="form-label"><?= lang('Cop.relPickPerson') ?></label>
                    <select id="person_id" name="person_id" class="form-select">
                        <option value=""><?= lang('Cop.none') ?></option>
                        <?php foreach ($people as $pid => $plabel): ?>
                            <option value="<?= (int) $pid ?>" <?= (int) $v('person_id') === (int) $pid ? 'selected' : '' ?>><?= esc($plabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="authorized_name" class="form-label"><?= lang('Cop.authName') ?></label>
                        <input type="text" id="authorized_name" name="authorized_name" class="form-input" maxlength="100"
                            value="<?= esc($v('authorized_name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="authorized_doc" class="form-label"><?= lang('Cop.authDoc') ?></label>
                        <input type="text" id="authorized_doc" name="authorized_doc" class="form-input" maxlength="30"
                            value="<?= esc($v('authorized_doc'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="authorized_phone" class="form-label"><?= lang('Cop.authPhone') ?></label>
                        <input type="text" id="authorized_phone" name="authorized_phone" class="form-input" maxlength="30"
                            value="<?= esc($v('authorized_phone'), 'attr') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.authStatus') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="start_date" class="form-label"><?= lang('Cop.relStart') ?></label>
                        <input type="date" id="start_date" name="start_date" class="form-input" value="<?= esc($v('start_date'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date" class="form-label"><?= lang('Cop.relEnd') ?></label>
                        <input type="date" id="end_date" name="end_date" class="form-input" value="<?= esc($v('end_date'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="status" class="form-label"><?= lang('Cop.authStatus') ?></label>
                        <select id="status" name="status" class="form-select">
                            <?php foreach (Meta::options(Meta::AUTH_STATUSES) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= $v('status', 'vigente') === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group mb-2">
                    <label for="details" class="form-label"><?= lang('Cop.authDetails') ?></label>
                    <textarea id="details" name="details" class="form-textarea" rows="3"><?= esc($v('details')) ?></textarea>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Cop.save') ?></button>
                <a href="<?= base_url('copropiedad/autorizaciones') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Cop.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
