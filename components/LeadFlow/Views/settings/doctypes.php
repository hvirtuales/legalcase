<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header"><i class="icon icon-gear"></i><h5 class="font-semibold"><?= lang('Lfl.navSettings') ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_settings_tabs', ['active' => 'doctypes']) ?>
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.docTypesIntro') ?></p>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div>
                <?php if (! $types): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($types as $t): ?>
                            <li class="flex items-center justify-between py-2 text-sm">
                                <span><?= esc($t['name']) ?></span>
                                <?= view('partials/delete_button', ['action' => base_url('leadflow/configuracion/documentos/eliminar/' . (int) $t['id']),
                                    'confirm' => lang('Lfl.confirmDelete'), 'label' => lang('Lfl.delete')]) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <form action="<?= base_url('leadflow/configuracion/documentos') ?>" method="post">
                <?= csrf_field() ?>
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lfl.docTypeNew') ?></legend>
                    <div class="form-group">
                        <label for="dt-name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="dt-name" name="name" maxlength="80" class="form-input" required placeholder="<?= esc(lang('Lfl.docTypePh'), 'attr') ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Lfl.add') ?></button>
                </fieldset>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
