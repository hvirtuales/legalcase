<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header"><i class="icon icon-clipboard"></i><h5 class="font-semibold"><?= lang('Lfl.formNew') ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>
        <form action="<?= base_url('leadflow/formularios/nuevo') ?>" method="post">
            <?= csrf_field() ?>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.formIdentity') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.formIdentityHint') ?></p>
                <div class="form-group">
                    <label for="name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" maxlength="150" class="form-input" required value="<?= esc(old('name'), 'attr') ?>"
                        placeholder="<?= esc(lang('Lfl.formNamePh'), 'attr') ?>">
                </div>
                <div class="form-group">
                    <label for="description" class="form-label"><?= lang('Lfl.description') ?></label>
                    <textarea id="description" name="description" rows="2" class="form-textarea"><?= esc(old('description')) ?></textarea>
                </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <?php foreach ([['service_id', 'service', $services], ['campaign_id', 'campaign', $campaigns]] as [$field, $label, $opts]): ?>
                        <div class="form-group">
                            <label for="<?= esc($field, 'attr') ?>" class="form-label"><?= lang('Lfl.' . $label) ?></label>
                            <select id="<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-select">
                                <option value=""><?= lang('Lfl.none') ?></option>
                                <?php foreach ($opts as $id => $name): ?>
                                    <option value="<?= (int) $id ?>"><?= esc($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.formCreateAndBuild') ?></button>
                <a href="<?= base_url('leadflow/formularios') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Lfl.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
