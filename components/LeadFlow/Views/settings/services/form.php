<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="card">
    <div class="card-header"><i class="icon icon-briefcase"></i><h5 class="font-semibold"><?= esc($title) ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>
        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.service') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.serviceHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group md:col-span-2">
                        <label for="name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="120" class="form-input" required value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="sort" class="form-label"><?= lang('Lfl.order') ?></label>
                        <input type="number" id="sort" name="sort" class="form-input" value="<?= (int) $v('sort', 100) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="description" class="form-label"><?= lang('Lfl.description') ?></label>
                    <textarea id="description" name="description" rows="2" class="form-textarea"><?= esc($v('description')) ?></textarea>
                </div>
                <label class="form-check mb-1">
                    <input type="checkbox" name="is_priority" value="1" class="form-check-input" <?= $v('is_priority') ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Lfl.priorityService') ?></span>
                </label>
                <p class="form-hint mb-2"><?= lang('Lfl.priorityServiceHint') ?></p>
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" <?= $row === [] || $v('is_active', 1) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Lfl.active') ?></span>
                </label>
                <label class="form-check mt-2">
                    <input type="checkbox" name="is_public" value="1" class="form-check-input" <?= $v('is_public') ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Lfl.publicService') ?></span>
                </label>
                <p class="form-hint"><?= lang('Lfl.publicServiceHint') ?></p>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.serviceDefaults') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.serviceDefaultsHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="owner_user_id" class="form-label"><?= lang('Lfl.defaultOwner') ?></label>
                        <select id="owner_user_id" name="owner_user_id" class="form-select">
                            <option value=""><?= lang('Lfl.nobody') ?></option>
                            <?php foreach ($team as $uid => $uname): ?>
                                <option value="<?= (int) $uid ?>" <?= (int) $v('owner_user_id') === (int) $uid ? 'selected' : '' ?>><?= esc($uname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="form_id" class="form-label"><?= lang('Lfl.form') ?></label>
                        <select id="form_id" name="form_id" class="form-select">
                            <option value=""><?= lang('Lfl.none') ?></option>
                            <?php foreach ($forms as $fid => $fname): ?>
                                <option value="<?= (int) $fid ?>" <?= (int) $v('form_id') === (int) $fid ? 'selected' : '' ?>><?= esc($fname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.dirArea') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.dirAreaHint') ?></p>
                <div class="form-group">
                    <label for="area_id" class="form-label"><?= lang('Lfl.dirArea') ?></label>
                    <select id="area_id" name="area_id" class="form-select">
                        <option value=""><?= lang('Lfl.none') ?></option>
                        <?php foreach ($areas as $aid => $aname): ?>
                            <option value="<?= (int) $aid ?>" <?= (int) $v('area_id') === (int) $aid ? 'selected' : '' ?>><?= esc($aname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.matterType') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.matterTypeHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <?php if ($matterTypes): ?>
                        <div class="form-group">
                            <label for="matter_type_id" class="form-label"><?= lang('Lfl.matterTypeFromDw') ?></label>
                            <select id="matter_type_id" name="matter_type_id" class="form-select">
                                <option value=""><?= lang('Lfl.none') ?></option>
                                <?php foreach ($matterTypes as $mid => $mname): ?>
                                    <option value="<?= esc((string) $mid, 'attr') ?>" <?= (string) $v('matter_type_id') === (string) $mid ? 'selected' : '' ?>><?= esc($mname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="matter_type_id" class="form-label"><?= lang('Lfl.matterTypeId') ?></label>
                            <input type="text" id="matter_type_id" name="matter_type_id" maxlength="40" class="form-input" value="<?= esc((string) $v('matter_type_id'), 'attr') ?>">
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="matter_type_name" class="form-label"><?= lang('Lfl.matterTypeName') ?></label>
                        <input type="text" id="matter_type_name" name="matter_type_name" maxlength="120" class="form-input" value="<?= esc((string) $v('matter_type_name'), 'attr') ?>"
                            placeholder="Family-Based Petition (I-130)">
                        <p class="form-hint"><?= lang('Lfl.matterTypeNameHint') ?></p>
                    </div>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                <a href="<?= base_url('leadflow/configuracion/servicios') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Lfl.cancel') ?></a>
            </div>
        </form>

        <?php
        /*
         * La plantilla documental va DESPUES del cierre del formulario: un <form>
         * dentro de otro no es HTML valido —el navegador cierra el de dentro— y su
         * boton acabaria enviando el del servicio.
         */
        ?>
        <?= view('Components\LeadFlow\Views\settings\services\_requirements', [
            'row'          => $row ?? null,
            'requirements' => $requirements ?? [],
            'docTypes'     => $docTypes ?? [],
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
