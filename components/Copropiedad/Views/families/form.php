<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d); ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-person-badge"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Copropiedad\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Cop.families') ?></legend>
                <p class="form-section-hint"><?= lang('Cop.familyNameHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="name" class="form-label"><?= lang('Cop.familyName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="120"
                            value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="unit_id" class="form-label"><?= lang('Cop.familyUnit') ?></label>
                        <select id="unit_id" name="unit_id" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($units as $uid => $ulabel): ?>
                                <option value="<?= (int) $uid ?>" <?= (int) $v('unit_id') === (int) $uid ? 'selected' : '' ?>><?= esc($ulabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php // El jefe de hogar solo se ofrece al editar: al crear todavía no
                      // hay miembros entre los que elegir. ?>
                <?php if ($people): ?>
                    <div class="form-group mb-2 md:w-1/2">
                        <label for="head_person_id" class="form-label"><?= lang('Cop.familyHead') ?></label>
                        <select id="head_person_id" name="head_person_id" class="form-select">
                            <option value=""><?= lang('Cop.none') ?></option>
                            <?php foreach ($people as $pid => $plabel): ?>
                                <option value="<?= (int) $pid ?>" <?= (int) $v('head_person_id') === (int) $pid ? 'selected' : '' ?>><?= esc($plabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group mb-2">
                    <label for="notes" class="form-label"><?= lang('Cop.notes') ?></label>
                    <textarea id="notes" name="notes" class="form-textarea" rows="2"><?= esc($v('notes')) ?></textarea>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Cop.save') ?></button>
                <a href="<?= base_url('copropiedad/familias') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Cop.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
