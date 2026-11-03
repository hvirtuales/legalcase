<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d); ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-telephone"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Copropiedad\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.contacts') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group md:col-span-2">
                        <label for="name" class="form-label"><?= lang('Cop.contactName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="100" value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="relationship" class="form-label"><?= lang('Cop.contactRel') ?></label>
                        <input type="text" id="relationship" name="relationship" class="form-input" maxlength="40" value="<?= esc($v('relationship'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label"><?= lang('Cop.contactPhone') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone" class="form-input" required maxlength="30" value="<?= esc($v('phone'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="alt_phone" class="form-label"><?= lang('Cop.contactAlt') ?></label>
                        <input type="text" id="alt_phone" name="alt_phone" class="form-input" maxlength="30" value="<?= esc($v('alt_phone'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label"><?= lang('Cop.email') ?></label>
                        <input type="email" id="email" name="email" class="form-input" maxlength="120" value="<?= esc($v('email'), 'attr') ?>">
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input type="checkbox" id="is_primary" name="is_primary" value="1" class="form-checkbox" <?= $v('is_primary') ? 'checked' : '' ?>>
                    <label for="is_primary"><?= lang('Cop.contactPrimary') ?></label>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.contactFor') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.contactForHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="unit_id" class="form-label"><?= lang('Cop.units') ?></label>
                        <select id="unit_id" name="unit_id" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($units as $uid => $ulabel): ?>
                                <option value="<?= (int) $uid ?>" <?= (int) $v('unit_id') === (int) $uid ? 'selected' : '' ?>><?= esc($ulabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="person_id" class="form-label"><?= lang('Cop.relPickPerson') ?></label>
                        <select id="person_id" name="person_id" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($people as $pid => $plabel): ?>
                                <option value="<?= (int) $pid ?>" <?= (int) $v('person_id') === (int) $pid ? 'selected' : '' ?>><?= esc($plabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group mb-2">
                    <label for="notes" class="form-label"><?= lang('Cop.notes') ?></label>
                    <textarea id="notes" name="notes" class="form-textarea" rows="2"><?= esc($v('notes')) ?></textarea>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Cop.save') ?></button>
                <a href="<?= base_url('copropiedad/contactos') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Cop.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
